<?php
/**
 * REST endpoints used by the block editor.
 *
 * @package ExactplaySpin
 */

namespace Exactplay\Spin;

use WP_Error;
use WP_REST_Request;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Proxies catalog lookups through the site, since the Exactplay Spin API
 * doesn't allow cross-origin browser requests and responses are worth caching.
 */
class Rest_Controller {

	const ROUTE_NAMESPACE = 'exactplay-spin/v1';

	/**
	 * API client.
	 *
	 * @var Api_Client
	 */
	private $api;

	/**
	 * Constructor.
	 *
	 * @param Api_Client $api API client.
	 */
	public function __construct( Api_Client $api ) {
		$this->api = $api;
	}

	/**
	 * Hooks into WordPress.
	 */
	public function register_hooks() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Registers routes.
	 */
	public function register_routes() {
		register_rest_route(
			self::ROUTE_NAMESPACE,
			'/providers',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_providers' ),
				'permission_callback' => array( $this, 'can_edit' ),
			)
		);

		register_rest_route(
			self::ROUTE_NAMESPACE,
			'/games',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_games' ),
				'permission_callback' => array( $this, 'can_edit' ),
				'args'                => array(
					'search'   => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'provider' => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => array( Api_Client::class, 'sanitize_slug' ),
					),
					'page'     => array(
						'type'    => 'integer',
						'default' => 1,
						'minimum' => 1,
					),
					'per_page' => array(
						'type'    => 'integer',
						'default' => 24,
						'minimum' => 1,
						'maximum' => Api_Client::MAX_PAGE_SIZE,
					),
				),
			)
		);
	}

	/**
	 * Only people who can write posts may browse the catalog through the site.
	 *
	 * @return bool
	 */
	public function can_edit() {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * GET /providers
	 *
	 * @return array|WP_Error
	 */
	public function get_providers() {
		return self::with_status( $this->api->get_providers() );
	}

	/**
	 * GET /games
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|WP_Error
	 */
	public function get_games( WP_REST_Request $request ) {
		return self::with_status(
			$this->api->get_games(
				array(
					'search'   => $request['search'],
					'provider' => $request['provider'],
					'page'     => $request['page'],
					'per_page' => $request['per_page'],
				)
			)
		);
	}

	/**
	 * Reports upstream API failures as 502 Bad Gateway.
	 *
	 * @param array|WP_Error $result Result.
	 * @return array|WP_Error
	 */
	private static function with_status( $result ) {
		if ( is_wp_error( $result ) ) {
			return new WP_Error( $result->get_error_code(), $result->get_error_message(), array( 'status' => 502 ) );
		}
		return $result;
	}
}
