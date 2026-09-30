<?php
/**
 * Plugin bootstrap.
 *
 * @package ExactplaySpin
 */

namespace Exactplay\Spin;

defined( 'ABSPATH' ) || exit;

/**
 * Wires the plugin's components together.
 */
final class Plugin {

	/**
	 * Exactplay Spin product page (the API behind this plugin).
	 */
	const PRODUCT_URL = 'https://exactplay.com/spin';

	/**
	 * Live demo site built on the Exactplay Spin API.
	 */
	const DEMO_URL = 'https://plentyspins.com';

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Settings store.
	 *
	 * @var Settings
	 */
	public $settings;

	/**
	 * API client.
	 *
	 * @var Api_Client
	 */
	public $api;

	/**
	 * HTML renderer.
	 *
	 * @var Renderer
	 */
	public $renderer;

	/**
	 * Returns the plugin instance, creating it on first call.
	 *
	 * @return Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Sets up components and hooks.
	 */
	private function __construct() {
		$this->settings = new Settings();
		$this->api      = new Api_Client( $this->settings );
		$this->renderer = new Renderer( $this->settings, $this->api );

		( new Blocks( $this->renderer ) )->register_hooks();
		( new Shortcodes( $this->renderer ) )->register_hooks();
		( new Rest_Controller( $this->api ) )->register_hooks();

		if ( is_admin() ) {
			( new Admin( $this->settings, $this->api ) )->register_hooks();
		}
	}

	/**
	 * Builds a PlentySpins URL for a game, or the PlentySpins home page.
	 *
	 * @param string $provider Provider slug.
	 * @param string $game     Game slug.
	 * @return string
	 */
	public static function demo_url( $provider = '', $game = '' ) {
		if ( '' === $provider || '' === $game ) {
			return self::DEMO_URL;
		}
		return self::DEMO_URL . '/' . rawurlencode( $provider ) . '/' . rawurlencode( $game );
	}
}
