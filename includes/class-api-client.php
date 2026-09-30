<?php
/**
 * Exactplay Spin API client.
 *
 * @package ExactplaySpin
 */

namespace Exactplay\Spin;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Talks to the Exactplay Spin gameserver and caches its responses.
 *
 * API reference: https://gameserver.exactplay.com/llms-full.txt
 */
class Api_Client {

	const BASE_URL = 'https://gameserver.exactplay.com';

	const CACHE_VERSION_OPTION = 'exactplay_spin_cache_version';

	const MAX_PAGE_SIZE = 100;

	const TIMEOUT = 10;

	const CONCURRENCY = 16;

	const RANDOM_TTL = 15 * MINUTE_IN_SECONDS;

	const ERROR_TTL = 5 * MINUTE_IN_SECONDS;

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
	 * Lists active providers, sorted by name.
	 *
	 * @return array|WP_Error List of [ slug, name, logo, count ].
	 */
	public function get_providers() {
		$data = $this->request( '/api/v1/providers', array(), $this->ttl() );
		if ( is_wp_error( $data ) ) {
			return $data;
		}

		$providers = array();
		foreach ( $data as $raw ) {
			if ( ! is_array( $raw ) || ! empty( $raw['isDisabled'] ) || empty( $raw['providerSlug'] ) ) {
				continue;
			}
			$count = isset( $raw['gameCount'] ) ? absint( $raw['gameCount'] ) : 0;
			if ( 0 === $count ) {
				continue;
			}
			$providers[] = array(
				'slug'  => self::sanitize_slug( $raw['providerSlug'] ),
				'name'  => sanitize_text_field( isset( $raw['providerName'] ) ? $raw['providerName'] : $raw['providerSlug'] ),
				'logo'  => isset( $raw['providerLogo'] ) ? esc_url_raw( $raw['providerLogo'] ) : '',
				'count' => $count,
			);
		}

		usort(
			$providers,
			function ( $a, $b ) {
				return strcasecmp( $a['name'], $b['name'] );
			}
		);

		return $providers;
	}

	/**
	 * Lists games, optionally filtered by provider and search term.
	 *
	 * @param array $args {
	 *     @type string $provider Provider slug.
	 *     @type string $search   Search term.
	 *     @type int    $page     1-indexed page.
	 *     @type int    $per_page Results per page (max 100).
	 * }
	 * @return array|WP_Error [ games, total, page, per_page, total_pages ].
	 */
	public function get_games( array $args = array() ) {
		$provider = isset( $args['provider'] ) ? self::sanitize_slug( $args['provider'] ) : '';
		$search   = isset( $args['search'] ) ? trim( sanitize_text_field( $args['search'] ) ) : '';
		$page     = isset( $args['page'] ) ? max( 1, absint( $args['page'] ) ) : 1;
		$per_page = isset( $args['per_page'] ) ? min( self::MAX_PAGE_SIZE, max( 1, absint( $args['per_page'] ) ) ) : 20;

		$query = array_filter(
			array(
				'provider' => $provider,
				'search'   => $search,
				'page'     => $page,
				'pageSize' => $per_page,
			)
		);

		// Some API deployments ignore the search parameter. When the results clearly
		// don't match the term, search a locally cached catalog index instead, and
		// remember that for the cache lifetime so later searches skip the API call.
		$searching     = '' !== self::normalize_term( $search );
		$fallback_flag = $this->cache_key( 'search-ignored' );
		if ( $searching && get_transient( $fallback_flag ) ) {
			return $this->search_index( $search, $provider, $page, $per_page );
		}

		$data = $this->request( '/api/v1/games', $query, $this->ttl() );
		if ( is_wp_error( $data ) ) {
			return $data;
		}

		$result = $this->normalize_page( $data, $page, $per_page );

		if ( $searching && ! self::games_match( $result['games'], $search ) ) {
			set_transient( $fallback_flag, 1, $this->ttl() );
			return $this->search_index( $search, $provider, $page, $per_page );
		}

		return $result;
	}

	/**
	 * Returns random games, e.g. for lobbies and "more games" sections.
	 *
	 * @param int    $count    Number of games.
	 * @param string $provider Optional provider slug.
	 * @param string $exclude  Optional game slug to leave out.
	 * @return array|WP_Error List of games.
	 */
	public function get_random_games( $count = 12, $provider = '', $exclude = '' ) {
		$query = array_filter(
			array(
				'pageSize' => min( self::MAX_PAGE_SIZE, max( 1, absint( $count ) ) ),
				'provider' => self::sanitize_slug( $provider ),
				'exclude'  => self::sanitize_slug( $exclude ), // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude -- Exactplay API parameter, not a WP_Query argument.
			)
		);

		$data = $this->request( '/api/v1/games/random', $query, min( self::RANDOM_TTL, $this->ttl() ) );
		if ( is_wp_error( $data ) ) {
			return $data;
		}

		$list = isset( $data['data'] ) && is_array( $data['data'] ) ? $data['data'] : $data;
		return $this->normalize_games( $list );
	}

	/**
	 * Returns one game's details.
	 *
	 * @param string $provider Provider slug.
	 * @param string $slug     Game slug.
	 * @return array|WP_Error Game.
	 */
	public function get_game( $provider, $slug ) {
		$games = $this->get_games_by_keys( array( $provider . '/' . $slug ), true );
		if ( is_wp_error( $games ) ) {
			return $games;
		}
		return isset( $games[0] ) ? $games[0] : new WP_Error( 'exactplay_spin_not_found', __( 'Game not found.', 'exactplay-spin' ) );
	}

	/**
	 * Returns details for several games, fetching uncached ones in parallel.
	 *
	 * @param string[] $keys         Game keys in "provider/game-slug" form.
	 * @param bool     $return_error Return the first error instead of skipping failed games.
	 * @return array|WP_Error Games in the order requested; unknown games are skipped.
	 */
	public function get_games_by_keys( array $keys, $return_error = false ) {
		$requests = array();
		foreach ( $keys as $key ) {
			$parsed = self::parse_key( $key );
			if ( $parsed ) {
				$requests[ $parsed[0] . '/' . $parsed[1] ] = array( '/api/v1/game/' . $parsed[0] . '/' . $parsed[1], array() );
			}
		}

		$games = array();
		foreach ( $this->request_many( $requests, $this->ttl() ) as $data ) {
			if ( is_wp_error( $data ) ) {
				if ( $return_error ) {
					return $data;
				}
				continue;
			}
			$game = $this->normalize_game( $data );
			if ( $game ) {
				$games[] = $game;
			}
		}
		return $games;
	}

	/**
	 * Builds the URL that launches a game in demo mode.
	 *
	 * @param string $provider Provider slug.
	 * @param string $slug     Game slug.
	 * @param array  $args     Optional channel, lang and cur query parameters.
	 * @return string
	 */
	public function launch_url( $provider, $slug, array $args = array() ) {
		$url   = self::BASE_URL . '/game/' . rawurlencode( self::sanitize_slug( $provider ) ) . '/' . rawurlencode( self::sanitize_slug( $slug ) );
		$query = array_filter(
			array(
				'channel' => isset( $args['channel'] ) && in_array( $args['channel'], array( 'web', 'mobile' ), true ) ? $args['channel'] : '',
				'lang'    => isset( $args['lang'] ) ? Settings::sanitize_lang( $args['lang'] ) : '',
				'cur'     => isset( $args['cur'] ) ? Settings::sanitize_currency( $args['cur'] ) : '',
			)
		);
		return $query ? add_query_arg( $query, $url ) : $url;
	}

	/**
	 * Invalidates every cached API response.
	 */
	public function clear_cache() {
		update_option( self::CACHE_VERSION_OPTION, (int) get_option( self::CACHE_VERSION_OPTION, 1 ) + 1, false );
	}

	/**
	 * Splits "provider/game-slug" into its parts.
	 *
	 * @param string $key Game key.
	 * @return array|null [ provider, slug ] or null when invalid.
	 */
	public static function parse_key( $key ) {
		$parts = explode( '/', trim( (string) $key, " \t\n\r/" ) );
		if ( 2 !== count( $parts ) ) {
			return null;
		}
		$provider = self::sanitize_slug( $parts[0] );
		$slug     = self::sanitize_slug( $parts[1] );
		return ( '' === $provider || '' === $slug ) ? null : array( $provider, $slug );
	}

	/**
	 * Keeps only characters that appear in provider and game slugs.
	 *
	 * @param mixed $value Raw slug.
	 * @return string
	 */
	public static function sanitize_slug( $value ) {
		return strtolower( preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $value ) );
	}

	/**
	 * Cache lifetime for catalog data, in seconds.
	 *
	 * @return int
	 */
	private function ttl() {
		return (int) $this->settings->get( 'cache_hours' ) * HOUR_IN_SECONDS;
	}

	/**
	 * Performs one cached GET request.
	 *
	 * @param string $path  API path.
	 * @param array  $query Query parameters.
	 * @param int    $ttl   Cache lifetime in seconds; 0 disables caching.
	 * @return array|WP_Error Decoded JSON.
	 */
	private function request( $path, array $query, $ttl ) {
		$results = $this->request_many( array( 'request' => array( $path, $query ) ), $ttl );
		return $results['request'];
	}

	/**
	 * Performs several cached GET requests, fetching the uncached ones in parallel.
	 *
	 * @param array $requests Map of key => [ path, query ].
	 * @param int   $ttl      Cache lifetime in seconds; 0 disables caching.
	 * @return array Map of key => decoded JSON or WP_Error, in input order.
	 */
	private function request_many( array $requests, $ttl ) {
		$results = array();
		$missing = array();

		foreach ( $requests as $key => $request ) {
			$url = add_query_arg( array_map( 'rawurlencode', $request[1] ), self::BASE_URL . $request[0] );

			$cached = $ttl ? get_transient( $this->cache_key( $url ) ) : false;
			if ( is_array( $cached ) && isset( $cached['error'] ) ) {
				$results[ $key ] = new WP_Error( 'exactplay_spin_http', $cached['error'], isset( $cached['status'] ) ? array( 'status' => $cached['status'] ) : null );
			} elseif ( is_array( $cached ) && isset( $cached['data'] ) ) {
				$results[ $key ] = $cached['data'];
			} else {
				$results[ $key ] = null;
				$missing[ $key ] = $url;
			}
		}

		foreach ( $this->fetch( $missing ) as $key => $data ) {
			$results[ $key ] = $data;
			if ( ! $ttl ) {
				continue;
			}
			if ( is_wp_error( $data ) ) {
				$status = $data->get_error_data();
				$cache  = array(
					'error'  => $data->get_error_message(),
					'status' => is_array( $status ) && isset( $status['status'] ) ? (int) $status['status'] : 0,
				);
				set_transient( $this->cache_key( $missing[ $key ] ), $cache, min( $ttl, self::ERROR_TTL ) );
			} else {
				set_transient( $this->cache_key( $missing[ $key ] ), array( 'data' => $data ), $ttl );
			}
		}

		return $results;
	}

	/**
	 * Fetches URLs and decodes their JSON bodies.
	 *
	 * @param array $urls Map of key => URL.
	 * @return array Map of key => decoded JSON or WP_Error.
	 */
	private function fetch( array $urls ) {
		$results = array();
		$headers = array( 'Accept' => 'application/json' );

		if ( count( $urls ) > 1 && class_exists( '\WpOrg\Requests\Requests' ) ) {
			foreach ( array_chunk( $urls, self::CONCURRENCY, true ) as $chunk ) {
				$batch = array();
				foreach ( $chunk as $key => $url ) {
					$batch[ $key ] = array(
						'url'     => $url,
						'headers' => $headers,
					);
				}

				try {
					$responses = \WpOrg\Requests\Requests::request_multiple( $batch, $this->parallel_options() );
				} catch ( \Exception $e ) {
					$responses = array_fill_keys( array_keys( $batch ), $e );
				}

				foreach ( $responses as $key => $response ) {
					if ( $response instanceof \WpOrg\Requests\Response ) {
						$results[ $key ] = $this->decode( (int) $response->status_code, $response->body );
					} else {
						$message         = $response instanceof \Exception ? $response->getMessage() : __( 'Request failed.', 'exactplay-spin' );
						$results[ $key ] = new WP_Error( 'exactplay_spin_http', $message );
					}
				}
			}
			return $results;
		}

		foreach ( $urls as $key => $url ) {
			$response = wp_safe_remote_get(
				$url,
				array(
					'timeout'    => self::TIMEOUT,
					'user-agent' => $this->user_agent(),
					'headers'    => $headers,
				)
			);

			$results[ $key ] = is_wp_error( $response )
				? $response
				: $this->decode( (int) wp_remote_retrieve_response_code( $response ), wp_remote_retrieve_body( $response ) );
		}
		return $results;
	}

	/**
	 * Options for parallel requests. WordPress's HTTP API has no parallel call, so
	 * these apply the same certificate bundle and proxy settings WP_Http uses.
	 *
	 * @return array
	 */
	private function parallel_options() {
		$options = array(
			'timeout'   => self::TIMEOUT,
			'useragent' => $this->user_agent(),
			'verify'    => ABSPATH . WPINC . '/certificates/ca-bundle.crt',
		);

		$proxy = new \WP_HTTP_Proxy();
		if ( $proxy->is_enabled() && $proxy->send_through_proxy( self::BASE_URL ) ) {
			$requests_proxy = new \WpOrg\Requests\Proxy\Http( $proxy->host() . ':' . $proxy->port() );
			if ( $proxy->use_authentication() ) {
				$requests_proxy->use_authentication = true;
				$requests_proxy->user               = $proxy->username();
				$requests_proxy->pass               = $proxy->password();
			}
			$options['proxy'] = $requests_proxy;

			// Requests only applies the proxy to single cURL requests, so apply it to each parallel handle too.
			$options['hooks'] = new \WpOrg\Requests\Hooks();
			$options['hooks']->register( 'curl.before_multi_add', array( $requests_proxy, 'curl_before_send' ) );
		}

		return $options;
	}

	/**
	 * Decodes an API response body.
	 *
	 * @param int    $status HTTP status code.
	 * @param string $body   Response body.
	 * @return array|WP_Error
	 */
	private function decode( $status, $body ) {
		$data = json_decode( (string) $body, true );

		if ( 200 !== $status ) {
			$message = is_array( $data ) && ! empty( $data['error'] ) && is_string( $data['error'] )
				? sanitize_text_field( $data['error'] )
				/* translators: %d: HTTP status code. */
				: sprintf( __( 'The Exactplay Spin API returned HTTP %d.', 'exactplay-spin' ), $status );
			return new WP_Error( 'exactplay_spin_http', $message, array( 'status' => $status ) );
		}

		if ( ! is_array( $data ) ) {
			return new WP_Error( 'exactplay_spin_invalid_json', __( 'The Exactplay Spin API returned an unexpected response.', 'exactplay-spin' ) );
		}

		return $data;
	}

	/**
	 * Transient name for a URL.
	 *
	 * @param string $url Request URL.
	 * @return string
	 */
	private function cache_key( $url ) {
		return 'exactplay_spin_' . (int) get_option( self::CACHE_VERSION_OPTION, 1 ) . '_' . md5( $url );
	}

	/**
	 * User agent sent with API requests.
	 *
	 * @return string
	 */
	private function user_agent() {
		return 'ExactplaySpin-WordPress/' . EXACTPLAY_SPIN_VERSION;
	}

	/**
	 * Normalizes a paginated list response.
	 *
	 * @param array $data     Decoded response.
	 * @param int   $page     Requested page.
	 * @param int   $per_page Requested page size.
	 * @return array
	 */
	private function normalize_page( array $data, $page, $per_page ) {
		$games = $this->normalize_games( isset( $data['data'] ) && is_array( $data['data'] ) ? $data['data'] : array() );
		$meta  = isset( $data['meta'] ) && is_array( $data['meta'] ) ? $data['meta'] : array();
		$total = isset( $meta['total'] ) ? absint( $meta['total'] ) : count( $games );

		return array(
			'games'       => $games,
			'total'       => $total,
			'page'        => isset( $meta['page'] ) ? max( 1, absint( $meta['page'] ) ) : $page,
			'per_page'    => $per_page,
			'total_pages' => isset( $meta['totalPages'] ) ? absint( $meta['totalPages'] ) : (int) ceil( $total / $per_page ),
		);
	}

	/**
	 * Normalizes a list of raw games, dropping invalid entries.
	 *
	 * @param array $list Raw games.
	 * @return array
	 */
	private function normalize_games( array $list ) {
		return array_values( array_filter( array_map( array( $this, 'normalize_game' ), $list ) ) );
	}

	/**
	 * Converts a raw API game into the plugin's game shape.
	 *
	 * The list endpoints nest the provider under "providers" while the detail
	 * endpoint has it at the top level, so both are handled here.
	 *
	 * @param mixed $raw Raw game.
	 * @return array|null [ slug, name, provider, provider_name, provider_logo, images ].
	 */
	private function normalize_game( $raw ) {
		if ( ! is_array( $raw ) || empty( $raw['gameSlug'] ) ) {
			return null;
		}

		$provider      = isset( $raw['providerSlug'] ) ? $raw['providerSlug'] : '';
		$provider_name = isset( $raw['providerName'] ) ? $raw['providerName'] : '';
		if ( '' === $provider && ! empty( $raw['providers'][0]['providerSlug'] ) ) {
			$provider      = $raw['providers'][0]['providerSlug'];
			$provider_name = isset( $raw['providers'][0]['providerName'] ) ? $raw['providers'][0]['providerName'] : '';
		}
		if ( '' === $provider && ! empty( $raw['gameLauncher'] ) && preg_match( '#/game/([A-Za-z0-9_-]+)/#', (string) $raw['gameLauncher'], $matches ) ) {
			$provider = $matches[1];
		}

		$provider = self::sanitize_slug( $provider );
		$slug     = self::sanitize_slug( $raw['gameSlug'] );
		if ( '' === $provider || '' === $slug ) {
			return null;
		}

		$types  = array(
			'THUMBNAIL_LANDSCAPE' => 'landscape',
			'THUMBNAIL_SQUARE'    => 'square',
			'THUMBNAIL_PORTRAIT'  => 'portrait',
			'BACKGROUND'          => 'background',
			'THUMBNAIL'           => 'thumbnail',
		);
		$images = array();
		if ( ! empty( $raw['images'] ) && is_array( $raw['images'] ) ) {
			foreach ( $raw['images'] as $image ) {
				if ( isset( $image['type'], $image['url'], $types[ $image['type'] ] ) ) {
					$images[ $types[ $image['type'] ] ] = esc_url_raw( $image['url'], array( 'https' ) );
				}
			}
		}

		return array(
			'slug'          => $slug,
			'name'          => sanitize_text_field( isset( $raw['gameName'] ) ? $raw['gameName'] : $slug ),
			'provider'      => $provider,
			'provider_name' => sanitize_text_field( '' !== $provider_name ? $provider_name : $provider ),
			'provider_logo' => isset( $raw['providerLogo'] ) ? esc_url_raw( $raw['providerLogo'], array( 'https' ) ) : '',
			'images'        => array_filter( $images ),
		);
	}

	/**
	 * Searches the cached catalog index.
	 *
	 * @param string $search   Search term.
	 * @param string $provider Optional provider slug.
	 * @param int    $page     1-indexed page.
	 * @param int    $per_page Page size.
	 * @return array|WP_Error Same shape as get_games().
	 */
	private function search_index( $search, $provider, $page, $per_page ) {
		$index = $this->get_index( $provider );
		if ( is_wp_error( $index ) ) {
			return $index;
		}

		$needle  = self::normalize_term( $search );
		$prefix  = array();
		$partial = array();
		foreach ( $index as $game ) {
			$name = self::normalize_term( $game['name'] );
			if ( 0 === strpos( $name, $needle ) ) {
				$prefix[] = $game;
			} elseif ( false !== strpos( $name, $needle ) || false !== strpos( self::normalize_term( $game['slug'] ), $needle ) ) {
				$partial[] = $game;
			}
		}
		$matches = array_merge( $prefix, $partial );
		$total   = count( $matches );

		return array(
			'games'       => array_slice( $matches, ( $page - 1 ) * $per_page, $per_page ),
			'total'       => $total,
			'page'        => $page,
			'per_page'    => $per_page,
			'total_pages' => (int) ceil( $total / $per_page ),
		);
	}

	/**
	 * Returns a slim list of every game, for one provider or all of them.
	 *
	 * Each provider's list is cached separately so no single transient grows too large.
	 *
	 * @param string $provider Optional provider slug.
	 * @return array|WP_Error
	 */
	private function get_index( $provider ) {
		$providers = $this->get_providers();
		if ( is_wp_error( $providers ) ) {
			return $providers;
		}
		if ( '' !== $provider ) {
			$providers = wp_list_filter( $providers, array( 'slug' => $provider ) );
		}

		$index    = array();
		$queued   = array();
		$requests = array();
		foreach ( $providers as $item ) {
			$cached = get_transient( $this->cache_key( 'index/' . $item['slug'] ) );
			if ( is_array( $cached ) ) {
				$index[ $item['slug'] ] = $cached;
				continue;
			}
			$index[ $item['slug'] ]  = array();
			$queued[ $item['slug'] ] = true;
			$pages                   = (int) ceil( $item['count'] / self::MAX_PAGE_SIZE );
			for ( $page = 1; $page <= $pages; $page++ ) {
				$requests[ $item['slug'] . '|' . $page ] = array(
					'/api/v1/games',
					array(
						'provider' => $item['slug'],
						'page'     => $page,
						'pageSize' => self::MAX_PAGE_SIZE,
					),
				);
			}
		}

		$failed = array();
		foreach ( $this->request_many( $requests, 0 ) as $key => $data ) {
			$slug = strtok( $key, '|' );
			if ( is_wp_error( $data ) ) {
				$failed[ $slug ] = $data;
				continue;
			}
			foreach ( $this->normalize_page( $data, 1, self::MAX_PAGE_SIZE )['games'] as $game ) {
				// Only the landscape thumbnail is needed for search results.
				$game['images']   = array_intersect_key( $game['images'], array( 'landscape' => true ) );
				$index[ $slug ][] = $game;
			}
		}

		foreach ( array_keys( array_diff_key( $queued, $failed ) ) as $slug ) {
			set_transient( $this->cache_key( 'index/' . $slug ), $index[ $slug ], $this->ttl() );
		}

		if ( $failed && count( $failed ) === count( $index ) ) {
			return reset( $failed );
		}

		return array_merge( ...array_values( $index ) );
	}

	/**
	 * Whether every game in a result set matches the search term.
	 *
	 * @param array  $games  Games.
	 * @param string $search Search term.
	 * @return bool
	 */
	private static function games_match( array $games, $search ) {
		$needle = self::normalize_term( $search );
		foreach ( $games as $game ) {
			if ( false === strpos( self::normalize_term( $game['name'] ), $needle ) && false === strpos( self::normalize_term( $game['slug'] ), $needle ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Lowercases a term and strips everything but letters and digits.
	 *
	 * @param string $term Term.
	 * @return string
	 */
	private static function normalize_term( $term ) {
		return preg_replace( '/[^a-z0-9]+/', '', strtolower( remove_accents( (string) $term ) ) );
	}
}
