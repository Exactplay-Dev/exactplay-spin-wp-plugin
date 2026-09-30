<?php
/**
 * Local WebP copies of game artwork.
 *
 * @package ExactplaySpin
 */

namespace Exactplay\Spin;

defined( 'ABSPATH' ) || exit;

/**
 * Serves game artwork from the site's uploads folder instead of Exactplay's image server.
 *
 * The first time an image is shown, its URL points to a signed address on this site.
 * That request downloads the original, converts it to WebP, stores it and redirects to
 * the stored file. Later page views link to the stored file directly, so visitors'
 * browsers never contact the image server and pages never wait for a download.
 */
class Image_Cache {

	const HOST = 's3.exactplay.com';

	const QUERY_VAR = 'exactplay-spin-image';

	const DIR = 'exactplay-spin';

	const QUALITY = 80;

	const FAILURE_TTL = HOUR_IN_SECONDS;

	/**
	 * Settings store.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param Settings $settings Settings store.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Hooks into WordPress.
	 */
	public function register_hooks() {
		add_action( 'init', array( $this, 'maybe_serve' ), 0 );
	}

	/**
	 * URL to show an image to visitors: the stored copy when it exists, otherwise an
	 * address on this site that creates it. Other URLs are returned unchanged.
	 *
	 * @param string $remote    Image URL from the API.
	 * @param int    $max_width Width to scale larger images down to.
	 * @return string
	 */
	public function url( $remote, $max_width ) {
		$path = self::remote_path( $remote );
		if ( null === $path || ! $this->settings->get( 'local_images' ) ) {
			return $remote;
		}

		$max_width = absint( $max_width );
		$stored    = self::find( $path, $max_width );
		if ( $stored ) {
			return $stored;
		}

		return add_query_arg(
			array(
				self::QUERY_VAR => rawurlencode( $path ),
				'w'             => $max_width,
				'sig'           => self::sign( $path, $max_width ),
			),
			home_url( '/' )
		);
	}

	/**
	 * Handles requests for images that haven't been stored yet.
	 */
	public function maybe_serve() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Public image URL; requests are verified by the HMAC signature below.
		if ( ! isset( $_GET[ self::QUERY_VAR ], $_GET['w'], $_GET['sig'] ) ) {
			return;
		}
		$path  = self::validate_path( sanitize_text_field( wp_unslash( $_GET[ self::QUERY_VAR ] ) ) );
		$width = absint( $_GET['w'] );
		$sig   = sanitize_text_field( wp_unslash( $_GET['sig'] ) );
		// phpcs:enable

		if ( null === $path ) {
			status_header( 404 );
			exit;
		}

		// Only addresses this site generated may create files; anything else goes to the original.
		$local = null;
		if ( hash_equals( self::sign( $path, $width ), $sig ) ) {
			$local = self::find( $path, $width );
			if ( ! $local ) {
				$local = self::create( $path, $width );
			}
		}

		if ( $local ) {
			header( 'Cache-Control: public, max-age=604800' );
			wp_safe_redirect( $local, 302, 'Exactplay Spin' );
			exit;
		}

		add_filter(
			'allowed_redirect_hosts',
			function ( $hosts ) {
				$hosts[] = self::HOST;
				return $hosts;
			}
		);
		wp_safe_redirect( 'https://' . self::HOST . '/' . $path, 302, 'Exactplay Spin' );
		exit;
	}

	/**
	 * Deletes every stored image.
	 */
	public static function delete_all() {
		$storage = self::storage();
		if ( ! $storage || ! is_dir( $storage['path'] ) ) {
			return;
		}
		foreach ( (array) glob( $storage['path'] . '*' ) as $file ) {
			wp_delete_file( $file );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		if ( WP_Filesystem() ) {
			global $wp_filesystem;
			$wp_filesystem->rmdir( $storage['path'] );
		}
	}

	/**
	 * Downloads, converts and stores an image.
	 *
	 * @param string $path  Path on the image server.
	 * @param int    $width Width to scale larger images down to.
	 * @return string Stored image URL, or '' on failure.
	 */
	private static function create( $path, $width ) {
		$failed = 'exactplay_spin_image_failed_' . md5( $path . '|' . $width );
		if ( get_transient( $failed ) ) {
			return '';
		}

		$storage = self::storage( true );
		if ( ! $storage ) {
			set_transient( $failed, 1, self::FAILURE_TTL );
			return '';
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		$download = download_url( 'https://' . self::HOST . '/' . $path, 15 );
		if ( is_wp_error( $download ) ) {
			set_transient( $failed, 1, self::FAILURE_TTL );
			return '';
		}

		$saved  = null;
		$editor = wp_get_image_editor( $download );
		if ( ! is_wp_error( $editor ) ) {
			$size = $editor->get_size();
			if ( $width && $size['width'] > $width ) {
				$editor->resize( $width, null );
			}
			$editor->set_quality( self::QUALITY );

			// Without WebP support the image is still stored locally, in its original format.
			$webp  = $editor::supports_mime_type( 'image/webp' );
			$saved = $editor->save( $storage['path'] . self::file_name( $path, $width, $webp ), $webp ? 'image/webp' : null );
		}
		wp_delete_file( $download );

		if ( ! is_array( $saved ) || empty( $saved['file'] ) ) {
			set_transient( $failed, 1, self::FAILURE_TTL );
			return '';
		}
		return $storage['url'] . $saved['file'];
	}

	/**
	 * URL of the stored copy of an image, if there is one.
	 *
	 * @param string $path  Path on the image server.
	 * @param int    $width Width the image was scaled to.
	 * @return string
	 */
	private static function find( $path, $width ) {
		$storage = self::storage();
		if ( ! $storage ) {
			return '';
		}
		foreach ( array( true, false ) as $webp ) {
			$file = self::file_name( $path, $width, $webp );
			if ( file_exists( $storage['path'] . $file ) ) {
				return $storage['url'] . $file;
			}
		}
		return '';
	}

	/**
	 * Folder and URL where images are stored.
	 *
	 * @param bool $create Create the folder if it doesn't exist.
	 * @return array|null [ path, url ] with trailing slashes, or null when unavailable.
	 */
	private static function storage( $create = false ) {
		$uploads = wp_upload_dir( null, false );
		if ( ! empty( $uploads['error'] ) ) {
			return null;
		}

		$path = trailingslashit( $uploads['basedir'] ) . self::DIR . '/';
		// wp_mkdir_p() fails when a parallel request creates the folder first, so check again.
		if ( $create && ! wp_mkdir_p( $path ) && ! is_dir( $path ) ) {
			return null;
		}

		return array(
			'path' => $path,
			'url'  => trailingslashit( $uploads['baseurl'] ) . self::DIR . '/',
		);
	}

	/**
	 * Stored file name for an image.
	 *
	 * @param string $path  Path on the image server.
	 * @param int    $width Width the image is scaled to.
	 * @param bool   $webp  Whether the file is WebP.
	 * @return string
	 */
	private static function file_name( $path, $width, $webp ) {
		$extension = $webp ? 'webp' : strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
		return substr( md5( $path ), 0, 16 ) . '-' . $width . '.' . $extension;
	}

	/**
	 * Path of an image on Exactplay's image server, or null for any other URL.
	 *
	 * @param string $url Image URL.
	 * @return string|null
	 */
	private static function remote_path( $url ) {
		$parts = wp_parse_url( (string) $url );
		if ( empty( $parts['host'] ) || self::HOST !== strtolower( $parts['host'] ) || empty( $parts['scheme'] ) || 'https' !== $parts['scheme'] || empty( $parts['path'] ) ) {
			return null;
		}
		return self::validate_path( ltrim( $parts['path'], '/' ) );
	}

	/**
	 * Accepts only plain image paths such as "static/pragmatic/sweet-bonanza/thumbnail_portrait.png".
	 *
	 * @param string $path Path.
	 * @return string|null
	 */
	private static function validate_path( $path ) {
		if ( false !== strpos( $path, '..' ) || ! preg_match( '#^[A-Za-z0-9._/-]+\.(png|jpe?g|webp|gif)$#i', $path ) ) {
			return null;
		}
		return $path;
	}

	/**
	 * Signature that proves an image address was generated by this site.
	 *
	 * @param string $path  Path on the image server.
	 * @param int    $width Width.
	 * @return string
	 */
	private static function sign( $path, $width ) {
		return substr( wp_hash( $path . '|' . $width, 'nonce' ), 0, 16 );
	}
}
