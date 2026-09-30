<?php
/**
 * Front-end markup for games and game grids.
 *
 * @package ExactplaySpin
 */

namespace Exactplay\Spin;

defined( 'ABSPATH' ) || exit;

/**
 * Renders embeds shared by blocks and shortcodes.
 */
class Renderer {

	/**
	 * Settings store.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * API client.
	 *
	 * @var Api_Client
	 */
	private $api;

	/**
	 * Local copies of game artwork.
	 *
	 * @var Image_Cache
	 */
	private $images;

	/**
	 * Games that already printed structured data on this page.
	 *
	 * @var array
	 */
	private $schema_printed = array();

	/**
	 * Image sizes reported by the API, used to reserve layout space.
	 *
	 * @var array
	 */
	const IMAGE_SIZES = array(
		'landscape' => array( 660, 370 ),
		'square'    => array( 512, 512 ),
		'portrait'  => array( 750, 848 ),
	);

	/**
	 * Constructor.
	 *
	 * @param Settings    $settings Settings store.
	 * @param Api_Client  $api      API client.
	 * @param Image_Cache $images   Local copies of game artwork.
	 */
	public function __construct( Settings $settings, Api_Client $api, Image_Cache $images ) {
		$this->settings = $settings;
		$this->api      = $api;
		$this->images   = $images;
	}

	/**
	 * Default attributes for a single game.
	 *
	 * @return array
	 */
	public static function game_defaults() {
		return array(
			'provider'     => '',
			'game'         => '',
			'name'         => '',
			'display'      => '',
			'aspect_ratio' => '',
			'channel'      => '',
			'lang'         => '',
			'currency'     => '',
			'caption'      => true,
		);
	}

	/**
	 * Default attributes for a game grid.
	 *
	 * @return array
	 */
	public static function grid_defaults() {
		return array(
			'source'        => 'random',
			'provider'      => '',
			'games'         => array(),
			'exclude'       => '', // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude -- Exactplay API parameter, not a WP_Query argument.
			'count'         => 12,
			'columns'       => 4,
			'image'         => 'portrait',
			'click'         => '',
			'show_provider' => true,
			'channel'       => '',
			'lang'          => '',
			'currency'      => '',
		);
	}

	/**
	 * Renders a single embedded game.
	 *
	 * @param array  $atts               See game_defaults().
	 * @param string $wrapper_attributes Extra attributes for the outer element (from block supports).
	 * @return string
	 */
	public function render_game( array $atts, $wrapper_attributes = '' ) {
		$atts   = array_merge( self::game_defaults(), $atts );
		$parsed = Api_Client::parse_key( $atts['provider'] . '/' . $atts['game'] );
		if ( ! $parsed ) {
			return $this->notice( __( 'No game selected. Pick one in the block, or use game="studio/game" in the shortcode. You can copy shortcodes from Exactplay Spin → Game Library.', 'exactplay-spin' ) );
		}
		list( $provider, $slug ) = $parsed;

		$game = $this->api->get_game( $provider, $slug );
		if ( is_wp_error( $game ) ) {
			$status = $game->get_error_data();
			if ( is_array( $status ) && isset( $status['status'] ) && 404 === $status['status'] ) {
				/* translators: %s: game identifier such as "netent/twin-spin". */
				return $this->notice( sprintf( __( 'The game "%s" is no longer available. Replace it with another game from Exactplay Spin → Game Library.', 'exactplay-spin' ), $provider . '/' . $slug ) );
			}
			// The launcher URL doesn't depend on the details call, so the game still works without artwork.
			$game = array(
				'slug'          => $slug,
				'name'          => '' !== $atts['name'] ? sanitize_text_field( $atts['name'] ) : ucwords( str_replace( '-', ' ', $slug ) ),
				'provider'      => $provider,
				'provider_name' => '',
				'images'        => array(),
			);
		}

		$preview = self::is_editor_preview();
		$display = in_array( $atts['display'], array( 'click', 'iframe' ), true ) ? $atts['display'] : $this->settings->get( 'display' );
		if ( $preview ) {
			// Never start games inside the block editor.
			$display = 'click';
		}

		$ratio = array_key_exists( $atts['aspect_ratio'], Settings::aspect_ratio_options() ) ? $atts['aspect_ratio'] : $this->settings->get( 'aspect_ratio' );
		$src   = $this->launch_url( $game, $atts );
		/* translators: %s: game name. */
		$frame_title = sprintf( __( '%s demo', 'exactplay-spin' ), $game['name'] );

		$this->enqueue_assets();

		$classes = 'exactplay-spin-game exactplay-spin-game--' . $display;
		$html    = '<figure ' . $this->merge_wrapper_attributes( $wrapper_attributes, $classes, '--exactplay-spin-ratio:' . $ratio ) . '>';
		$html   .= '<div class="exactplay-spin-game__stage">';

		if ( 'iframe' === $display ) {
			$html .= $this->iframe( $src, $frame_title );
		} else {
			$poster = self::first_image( $game['images'], array( 'background', 'landscape', 'square', 'portrait' ) );
			$html  .= sprintf(
				'<button type="button" class="exactplay-spin-game__poster" data-exactplay-spin-src="%1$s" data-exactplay-spin-title="%2$s">',
				esc_url( $src ),
				esc_attr( $frame_title )
			);
			if ( $poster ) {
				$html .= sprintf( '<img class="exactplay-spin-game__image" src="%s" alt="" loading="lazy" decoding="async" />', esc_url( $this->images->url( $poster, 1280 ) ) );
			}
			$html .= '<span class="exactplay-spin-game__play">' . self::play_icon() . '<span>';
			/* translators: %s: game name. */
			$html .= esc_html( sprintf( __( 'Play %s', 'exactplay-spin' ), $game['name'] ) );
			$html .= '</span></span></button>';
		}
		$html .= '</div>';

		if ( rest_sanitize_boolean( $atts['caption'] ) ) {
			$html .= '<figcaption class="exactplay-spin-game__caption"><span class="exactplay-spin-game__title">';
			$html .= '<strong>' . esc_html( $game['name'] ) . '</strong>';
			if ( '' !== $game['provider_name'] ) {
				/* translators: %s: game studio name. */
				$html .= ' <span class="exactplay-spin-game__provider">' . esc_html( sprintf( __( 'by %s', 'exactplay-spin' ), $game['provider_name'] ) ) . '</span>';
			}
			$html .= '</span>';
			$html .= sprintf(
				'<a class="exactplay-spin-game__fullscreen" href="%1$s" target="_blank" rel="nofollow noopener">%2$s<span>%3$s</span></a>',
				esc_url( $src ),
				self::fullscreen_icon(),
				esc_html__( 'Fullscreen', 'exactplay-spin' )
			);
			$html .= '</figcaption>';
		}

		$html .= $this->credit( $game );
		$html .= '</figure>';

		if ( ! $preview ) {
			$html .= $this->structured_data( $game );
		}

		return $html;
	}

	/**
	 * Renders a grid of games.
	 *
	 * @param array  $atts               See grid_defaults().
	 * @param string $wrapper_attributes Extra attributes for the outer element (from block supports).
	 * @return string
	 */
	public function render_grid( array $atts, $wrapper_attributes = '' ) {
		$atts    = array_merge( self::grid_defaults(), $atts );
		$count   = min( Api_Client::MAX_PAGE_SIZE, max( 1, absint( $atts['count'] ) ) );
		$columns = min( 8, max( 1, absint( $atts['columns'] ) ) );
		$shape   = array_key_exists( $atts['image'], self::IMAGE_SIZES ) ? $atts['image'] : 'portrait';
		$click   = in_array( $atts['click'], array( 'modal', 'tab' ), true ) ? $atts['click'] : $this->settings->get( 'grid_click' );

		switch ( $atts['source'] ) {
			case 'selected':
				$keys = is_array( $atts['games'] ) ? $atts['games'] : explode( ',', (string) $atts['games'] );
				$keys = array_map(
					function ( $key ) {
						return is_array( $key ) && isset( $key['provider'], $key['slug'] ) ? $key['provider'] . '/' . $key['slug'] : (string) $key;
					},
					$keys
				);
				if ( ! array_filter( $keys ) ) {
					return $this->notice( __( 'This grid has no games yet. Pick games in the block, or list them in the shortcode, e.g. games="netent/twin-spin, hacksaw/wanted-dead-or-a-wild".', 'exactplay-spin' ) );
				}
				$games = $this->api->get_games_by_keys( array_slice( $keys, 0, Api_Client::MAX_PAGE_SIZE ) );
				break;

			case 'catalog':
				$result = $this->api->get_games(
					array(
						'provider' => $atts['provider'],
						'per_page' => $count,
					)
				);
				$games  = is_wp_error( $result ) ? $result : $result['games'];
				break;

			default:
				$games = $this->api->get_random_games( $count, $atts['provider'], $atts['exclude'] );
		}

		if ( is_wp_error( $games ) ) {
			/* translators: %s: error message. */
			return $this->notice( sprintf( __( 'Games could not be loaded: %s. Try again in a few minutes. If it keeps happening, check that your server can connect to gameserver.exactplay.com.', 'exactplay-spin' ), rtrim( $games->get_error_message(), '.' ) ) );
		}
		if ( ! $games ) {
			return $this->notice( __( 'No games found. Check the studio and game names; you can copy them from Exactplay Spin → Game Library.', 'exactplay-spin' ) );
		}

		$this->enqueue_assets();

		list( $width, $height ) = self::IMAGE_SIZES[ $shape ];
		$fallbacks              = array_unique( array( $shape, 'landscape', 'square', 'portrait', 'thumbnail' ) );
		$show_provider          = rest_sanitize_boolean( $atts['show_provider'] );

		$classes = 'exactplay-spin-grid exactplay-spin-grid--' . $shape;
		$html    = '<div ' . $this->merge_wrapper_attributes( $wrapper_attributes, $classes, '--exactplay-spin-columns:' . $columns ) . '>';
		$html   .= '<ul class="exactplay-spin-grid__list">';

		foreach ( $games as $game ) {
			$src   = $this->launch_url( $game, $atts );
			$image = self::first_image( $game['images'], $fallbacks );
			/* translators: %s: game name. */
			$frame_title = sprintf( __( '%s demo', 'exactplay-spin' ), $game['name'] );

			$html .= '<li class="exactplay-spin-tile">';
			$html .= sprintf(
				'<a class="exactplay-spin-tile__link" href="%1$s" target="_blank" rel="nofollow noopener"%2$s>',
				esc_url( $src ),
				'modal' === $click ? ' data-exactplay-spin-modal="' . esc_url( $src ) . '" data-exactplay-spin-title="' . esc_attr( $frame_title ) . '"' : ''
			);
			$html .= '<span class="exactplay-spin-tile__media">';
			if ( $image ) {
				$html .= sprintf(
					'<img src="%1$s" alt="" width="%2$d" height="%3$d" loading="lazy" decoding="async" />',
					esc_url( $this->images->url( $image, $width ) ),
					$width,
					$height
				);
			}
			$html .= '<span class="exactplay-spin-tile__play" aria-hidden="true">' . self::play_icon() . '</span></span>';
			$html .= '<span class="exactplay-spin-tile__name">' . esc_html( $game['name'] ) . '</span>';
			if ( $show_provider && '' !== $game['provider_name'] ) {
				$html .= '<span class="exactplay-spin-tile__provider">' . esc_html( $game['provider_name'] ) . '</span>';
			}
			$html .= '</a></li>';
		}

		$html .= '</ul>';
		$html .= $this->credit();
		$html .= '</div>';

		return $html;
	}

	/**
	 * Enqueues the front-end stylesheet and script.
	 */
	public function enqueue_assets() {
		wp_enqueue_style( 'exactplay-spin' );
		if ( ! self::is_editor_preview() ) {
			wp_enqueue_script( 'exactplay-spin-view' );
		}
	}

	/**
	 * Whether the markup is being rendered for a block editor preview.
	 *
	 * @return bool
	 */
	public static function is_editor_preview() {
		return defined( 'REST_REQUEST' ) && REST_REQUEST;
	}

	/**
	 * Builds the launcher URL for a game using per-embed overrides and site defaults.
	 *
	 * @param array $game Game.
	 * @param array $atts Embed attributes with channel, lang and currency.
	 * @return string
	 */
	private function launch_url( array $game, array $atts ) {
		$channel = in_array( $atts['channel'], array( 'auto', 'web', 'mobile' ), true ) ? $atts['channel'] : $this->settings->get( 'channel' );
		$cur     = Settings::sanitize_currency( $atts['currency'] );

		return $this->api->launch_url(
			$game['provider'],
			$game['slug'],
			array(
				// "auto" leaves the choice to the studio, which detects the visitor's device.
				'channel' => 'auto' === $channel ? '' : $channel,
				'lang'    => $this->settings->resolve_lang( $atts['lang'] ),
				'cur'     => '' !== $cur ? $cur : $this->settings->get( 'currency' ),
			)
		);
	}

	/**
	 * Game iframe markup.
	 *
	 * @param string $src   Launcher URL.
	 * @param string $title Accessible frame title.
	 * @return string
	 */
	private function iframe( $src, $title ) {
		return sprintf(
			'<iframe class="exactplay-spin-game__frame" src="%1$s" title="%2$s" loading="lazy" allow="autoplay; fullscreen; screen-wake-lock" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe>',
			esc_url( $src ),
			esc_attr( $title )
		);
	}

	/**
	 * Optional "Powered by" line. Off by default; site owners opt in under Settings.
	 *
	 * @param array|null $game Game to link to on Plentyspins, or null for the home page.
	 * @return string
	 */
	private function credit( $game = null ) {
		if ( ! $this->settings->get( 'credit' ) ) {
			return '';
		}

		$demo_url = $game ? Plugin::demo_url( $game['provider'], $game['slug'] ) : Plugin::demo_url();

		return sprintf(
			'<p class="exactplay-spin-credit">%1$s &middot; %2$s</p>',
			sprintf(
				/* translators: %s: "Exactplay Spin" link. */
				esc_html__( 'Free demo games powered by %s', 'exactplay-spin' ),
				'<a href="' . esc_url( Plugin::PRODUCT_URL ) . '">Exactplay Spin</a>'
			),
			sprintf(
				/* translators: %s: "Plentyspins" link. */
				esc_html__( 'Swipe more demos on %s', 'exactplay-spin' ),
				'<a href="' . esc_url( $demo_url ) . '">Plentyspins</a>'
			)
		);
	}

	/**
	 * Schema.org VideoGame markup for a game, printed once per game per page.
	 *
	 * @param array $game Game.
	 * @return string
	 */
	private function structured_data( array $game ) {
		$key = $game['provider'] . '/' . $game['slug'];
		if ( ! $this->settings->get( 'structured_data' ) || isset( $this->schema_printed[ $key ] ) ) {
			return '';
		}
		$this->schema_printed[ $key ] = true;

		$data = array(
			'@context'            => 'https://schema.org',
			'@type'               => 'VideoGame',
			'name'                => $game['name'],
			'genre'               => array( 'Casino Game', 'Slot Machine' ),
			'gamePlatform'        => array( 'Web Browser', 'Mobile', 'Desktop' ),
			'applicationCategory' => 'Game',
			'offers'              => array(
				'@type'         => 'Offer',
				'price'         => '0',
				'priceCurrency' => 'USD',
				'availability'  => 'https://schema.org/InStock',
			),
		);
		$image = self::first_image( $game['images'], array( 'landscape', 'square', 'portrait' ) );
		if ( $image ) {
			$data['image'] = $image;
		}
		if ( '' !== $game['provider_name'] ) {
			$data['author'] = array(
				'@type' => 'Organization',
				'name'  => $game['provider_name'],
			);
		}

		return wp_get_inline_script_tag(
			wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG ),
			array( 'type' => 'application/ld+json' )
		);
	}

	/**
	 * Explains a problem to people who can fix it; renders nothing for visitors.
	 *
	 * @param string $message Message.
	 * @return string
	 */
	private function notice( $message ) {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return '';
		}
		return '<p class="exactplay-spin-notice">' . esc_html( $message ) . '</p>';
	}

	/**
	 * Combines block-support wrapper attributes with the plugin's class and style.
	 *
	 * @param string $wrapper_attributes Attributes from get_block_wrapper_attributes(), or ''.
	 * @param string $classes            Plugin classes.
	 * @param string $style              Plugin inline style (CSS custom properties).
	 * @return string
	 */
	private function merge_wrapper_attributes( $wrapper_attributes, $classes, $style ) {
		if ( '' === $wrapper_attributes ) {
			return 'class="' . esc_attr( $classes ) . '" style="' . esc_attr( $style ) . '"';
		}

		$wrapper_attributes = preg_replace( '/\bclass="/', 'class="' . esc_attr( $classes ) . ' ', $wrapper_attributes, 1, $class_count );
		if ( ! $class_count ) {
			$wrapper_attributes .= ' class="' . esc_attr( $classes ) . '"';
		}
		$wrapper_attributes = preg_replace( '/\bstyle="/', 'style="' . esc_attr( $style ) . ';', $wrapper_attributes, 1, $style_count );
		if ( ! $style_count ) {
			$wrapper_attributes .= ' style="' . esc_attr( $style ) . '"';
		}
		return $wrapper_attributes;
	}

	/**
	 * Returns the first available image URL in order of preference.
	 *
	 * @param array $images Images keyed by shape.
	 * @param array $order  Shapes in order of preference.
	 * @return string
	 */
	private static function first_image( array $images, array $order ) {
		foreach ( $order as $shape ) {
			if ( ! empty( $images[ $shape ] ) ) {
				return $images[ $shape ];
			}
		}
		return '';
	}

	/**
	 * Inline play icon.
	 *
	 * @return string
	 */
	private static function play_icon() {
		return '<svg class="exactplay-spin-icon" viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M8 5.14v13.72a1 1 0 0 0 1.5.86l11-6.86a1 1 0 0 0 0-1.72l-11-6.86A1 1 0 0 0 8 5.14Z"/></svg>';
	}

	/**
	 * Inline fullscreen icon.
	 *
	 * @return string
	 */
	private static function fullscreen_icon() {
		return '<svg class="exactplay-spin-icon" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"><path fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" d="M4 9V4h5M20 9V4h-5M4 15v5h5M20 15v5h-5"/></svg>';
	}
}
