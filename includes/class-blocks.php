<?php
/**
 * Block and asset registration.
 *
 * @package ExactplaySpin
 */

namespace Exactplay\Spin;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the "Demo Game" and "Demo Game Grid" blocks and shared assets.
 */
class Blocks {

	/**
	 * HTML renderer.
	 *
	 * @var Renderer
	 */
	private $renderer;

	/**
	 * Constructor.
	 *
	 * @param Renderer $renderer HTML renderer.
	 */
	public function __construct( Renderer $renderer ) {
		$this->renderer = $renderer;
	}

	/**
	 * Hooks into WordPress.
	 */
	public function register_hooks() {
		add_action( 'init', array( $this, 'register' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_for_shortcodes' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'add_editor_config' ) );
	}

	/**
	 * Registers scripts, styles and blocks.
	 */
	public function register() {
		wp_register_style( 'exactplay-spin', EXACTPLAY_SPIN_URL . 'assets/css/frontend.css', array(), EXACTPLAY_SPIN_VERSION );

		wp_register_script(
			'exactplay-spin-view',
			EXACTPLAY_SPIN_URL . 'assets/js/frontend.js',
			array(),
			EXACTPLAY_SPIN_VERSION,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
		wp_localize_script(
			'exactplay-spin-view',
			'exactplaySpinView',
			array(
				'close'  => __( 'Close', 'exactplay-spin' ),
				'newTab' => __( 'Open in new tab', 'exactplay-spin' ),
			)
		);

		wp_register_script(
			'exactplay-spin-editor',
			EXACTPLAY_SPIN_URL . 'assets/js/editor.js',
			array( 'wp-api-fetch', 'wp-block-editor', 'wp-blocks', 'wp-components', 'wp-element', 'wp-i18n', 'wp-server-side-render', 'wp-url' ),
			EXACTPLAY_SPIN_VERSION,
			true
		);
		wp_set_script_translations( 'exactplay-spin-editor', 'exactplay-spin' );

		wp_register_style( 'exactplay-spin-editor', EXACTPLAY_SPIN_URL . 'assets/css/editor.css', array(), EXACTPLAY_SPIN_VERSION );

		register_block_type(
			EXACTPLAY_SPIN_DIR . 'blocks/game',
			array( 'render_callback' => array( $this, 'render_game_block' ) )
		);
		register_block_type(
			EXACTPLAY_SPIN_DIR . 'blocks/games',
			array( 'render_callback' => array( $this, 'render_games_block' ) )
		);
	}

	/**
	 * Passes option labels, site defaults and links to the block editor script.
	 */
	public function add_editor_config() {
		wp_add_inline_script( 'exactplay-spin-editor', 'window.exactplaySpinEditor = ' . wp_json_encode( $this->editor_config() ) . ';', 'before' );
	}

	/**
	 * Loads the stylesheet in the head when the current post uses a shortcode,
	 * avoiding a flash of unstyled content in classic themes.
	 */
	public function enqueue_for_shortcodes() {
		$post = get_post();
		if ( is_singular() && $post && ( has_shortcode( $post->post_content, Shortcodes::GAME ) || has_shortcode( $post->post_content, Shortcodes::GRID ) ) ) {
			wp_enqueue_style( 'exactplay-spin' );
		}
	}

	/**
	 * Renders the "Demo Game" block.
	 *
	 * @param array $attributes Block attributes.
	 * @return string
	 */
	public function render_game_block( $attributes ) {
		$attributes = wp_parse_args(
			$attributes,
			array(
				'provider'    => '',
				'game'        => '',
				'gameName'    => '',
				'display'     => '',
				'aspectRatio' => '',
				'channel'     => '',
				'lang'        => '',
				'currency'    => '',
				'showCaption' => true,
			)
		);

		return $this->renderer->render_game(
			array(
				'provider'     => $attributes['provider'],
				'game'         => $attributes['game'],
				'name'         => $attributes['gameName'],
				'display'      => $attributes['display'],
				'aspect_ratio' => $attributes['aspectRatio'],
				'channel'      => $attributes['channel'],
				'lang'         => $attributes['lang'],
				'currency'     => $attributes['currency'],
				'caption'      => (bool) $attributes['showCaption'],
			),
			get_block_wrapper_attributes()
		);
	}

	/**
	 * Renders the "Demo Game Grid" block.
	 *
	 * @param array $attributes Block attributes.
	 * @return string
	 */
	public function render_games_block( $attributes ) {
		$attributes = wp_parse_args(
			$attributes,
			array(
				'source'       => 'random',
				'provider'     => '',
				'games'        => array(),
				'count'        => 12,
				'columns'      => 4,
				'imageShape'   => 'portrait',
				'clickAction'  => '',
				'showProvider' => true,
				'channel'      => '',
				'lang'         => '',
				'currency'     => '',
			)
		);

		return $this->renderer->render_grid(
			array(
				'source'        => $attributes['source'],
				'provider'      => $attributes['provider'],
				'games'         => is_array( $attributes['games'] ) ? $attributes['games'] : array(),
				'count'         => $attributes['count'],
				'columns'       => $attributes['columns'],
				'image'         => $attributes['imageShape'],
				'click'         => $attributes['clickAction'],
				'show_provider' => (bool) $attributes['showProvider'],
				'channel'       => $attributes['channel'],
				'lang'          => $attributes['lang'],
				'currency'      => $attributes['currency'],
			),
			get_block_wrapper_attributes()
		);
	}

	/**
	 * Data the block editor script needs: option labels and links.
	 *
	 * @return array
	 */
	private function editor_config() {
		return array(
			'productUrl'  => Plugin::PRODUCT_URL,
			'demoUrl'     => Plugin::DEMO_URL,
			'settingsUrl' => admin_url( 'admin.php?page=exactplay-spin-settings' ),
			'libraryUrl'  => admin_url( 'admin.php?page=exactplay-spin' ),
			'options'     => array(
				'display'     => Settings::display_options(),
				'aspectRatio' => Settings::aspect_ratio_options(),
				'channel'     => Settings::channel_options(),
				'clickAction' => Settings::grid_click_options(),
				'lang'        => Settings::language_options(),
				'currency'    => Settings::currency_options(),
			),
		);
	}
}
