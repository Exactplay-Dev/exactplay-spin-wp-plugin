<?php
/**
 * Admin screens: Game Library and Settings.
 *
 * @package ExactplaySpin
 */

namespace Exactplay\Spin;

defined( 'ABSPATH' ) || exit;

/**
 * Adds the "Exactplay Spin" admin menu, settings and plugin list links.
 */
class Admin {

	const LIBRARY_PAGE = 'exactplay-spin';

	const SETTINGS_PAGE = 'exactplay-spin-settings';

	const PER_PAGE = 24;

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
	 * Admin page hook suffixes, used to load assets only on these pages.
	 *
	 * @var string[]
	 */
	private $hooks = array();

	/**
	 * Constructor.
	 *
	 * @param Settings   $settings Settings store.
	 * @param Api_Client $api      API client.
	 */
	public function __construct( Settings $settings, Api_Client $api ) {
		$this->settings = $settings;
		$this->api      = $api;
	}

	/**
	 * Hooks into WordPress.
	 */
	public function register_hooks() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_init', array( $this, 'add_privacy_policy_content' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_exactplay_spin_clear_cache', array( $this, 'handle_clear_cache' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( EXACTPLAY_SPIN_FILE ), array( $this, 'action_links' ) );
		add_filter( 'plugin_row_meta', array( $this, 'row_meta' ), 10, 2 );
	}

	/**
	 * Adds the admin menu.
	 */
	public function add_menu() {
		$this->hooks[] = add_menu_page(
			__( 'Game Library', 'exactplay-spin' ),
			__( 'Exactplay Spin', 'exactplay-spin' ),
			'edit_posts',
			self::LIBRARY_PAGE,
			array( $this, 'render_library_page' ),
			'dashicons-games',
			58
		);
		$this->hooks[] = add_submenu_page(
			self::LIBRARY_PAGE,
			__( 'Game Library', 'exactplay-spin' ),
			__( 'Game Library', 'exactplay-spin' ),
			'edit_posts',
			self::LIBRARY_PAGE,
			array( $this, 'render_library_page' )
		);
		$this->hooks[] = add_submenu_page(
			self::LIBRARY_PAGE,
			__( 'Exactplay Spin Settings', 'exactplay-spin' ),
			__( 'Settings', 'exactplay-spin' ),
			'manage_options',
			self::SETTINGS_PAGE,
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Loads admin styles and scripts on the plugin's pages.
	 *
	 * @param string $hook_suffix Current admin page.
	 */
	public function enqueue_assets( $hook_suffix ) {
		if ( ! in_array( $hook_suffix, $this->hooks, true ) ) {
			return;
		}
		wp_enqueue_style( 'exactplay-spin-admin', EXACTPLAY_SPIN_URL . 'assets/css/admin.css', array(), EXACTPLAY_SPIN_VERSION );
		wp_enqueue_script( 'exactplay-spin-admin', EXACTPLAY_SPIN_URL . 'assets/js/admin.js', array(), EXACTPLAY_SPIN_VERSION, true );
		wp_localize_script(
			'exactplay-spin-admin',
			'exactplaySpinAdmin',
			array(
				'copied' => __( 'Copied!', 'exactplay-spin' ),
			)
		);
	}

	/**
	 * Registers the settings and their fields.
	 */
	public function register_settings() {
		register_setting(
			'exactplay_spin',
			Settings::OPTION,
			array(
				'type'              => 'object',
				'sanitize_callback' => array( $this->settings, 'sanitize' ),
				'default'           => $this->settings->defaults(),
			)
		);

		add_settings_section( 'player', __( 'Game player', 'exactplay-spin' ), '__return_false', self::SETTINGS_PAGE );
		add_settings_section( 'grid', __( 'Game grids', 'exactplay-spin' ), '__return_false', self::SETTINGS_PAGE );
		add_settings_section( 'seo', __( 'SEO and credits', 'exactplay-spin' ), '__return_false', self::SETTINGS_PAGE );
		add_settings_section( 'performance', __( 'Performance', 'exactplay-spin' ), '__return_false', self::SETTINGS_PAGE );

		$languages = array( '' => sprintf( /* translators: %s: language code. */ __( 'Site language (%s)', 'exactplay-spin' ), strtolower( substr( determine_locale(), 0, 2 ) ) ) ) + Settings::language_options();
		$currency  = array( '' => __( "Game's default", 'exactplay-spin' ) ) + Settings::currency_options();

		$fields = array(
			array( 'display', __( 'Single games', 'exactplay-spin' ), 'player', 'select', Settings::display_options(), __( 'Click to play keeps pages fast and loads nothing from the game studio until a visitor wants to play.', 'exactplay-spin' ) ),
			array( 'aspect_ratio', __( 'Aspect ratio', 'exactplay-spin' ), 'player', 'select', Settings::aspect_ratio_options(), '' ),
			array( 'channel', __( 'Game layout', 'exactplay-spin' ), 'player', 'select', Settings::channel_options(), '' ),
			array( 'lang', __( 'Game language', 'exactplay-spin' ), 'player', 'select', $languages, '' ),
			array( 'currency', __( 'Demo currency', 'exactplay-spin' ), 'player', 'select', $currency, '' ),
			array( 'grid_click', __( 'When a game is clicked', 'exactplay-spin' ), 'grid', 'select', Settings::grid_click_options(), '' ),
			array( 'structured_data', __( 'Structured data', 'exactplay-spin' ), 'seo', 'checkbox', __( 'Add Schema.org VideoGame markup for embedded games', 'exactplay-spin' ), '' ),
			array(
				'credit',
				__( 'Credit link', 'exactplay-spin' ),
				'seo',
				'checkbox',
				__( 'Show a small "Powered by Exactplay Spin" line with a link to Plentyspins below games', 'exactplay-spin' ),
				__( 'Off by default. Nothing links out from your pages unless you turn this on.', 'exactplay-spin' ),
			),
			array( 'cache_hours', __( 'Cache catalog data for', 'exactplay-spin' ), 'performance', 'hours', null, __( 'Game lists and artwork URLs are stored on your site so pages load fast. Random picks refresh every 15 minutes.', 'exactplay-spin' ) ),
		);

		foreach ( $fields as $field ) {
			add_settings_field(
				'exactplay_spin_' . $field[0],
				$field[1],
				array( $this, 'render_field' ),
				self::SETTINGS_PAGE,
				$field[2],
				array(
					'key'         => $field[0],
					'type'        => $field[3],
					'options'     => $field[4],
					'description' => $field[5],
					'label_for'   => 'checkbox' === $field[3] ? null : 'exactplay-spin-' . $field[0],
				)
			);
		}
	}

	/**
	 * Renders one settings field.
	 *
	 * @param array $args Field arguments.
	 */
	public function render_field( $args ) {
		$key   = $args['key'];
		$name  = Settings::OPTION . '[' . $key . ']';
		$id    = 'exactplay-spin-' . $key;
		$value = $this->settings->get( $key );

		switch ( $args['type'] ) {
			case 'select':
				printf( '<select id="%1$s" name="%2$s">', esc_attr( $id ), esc_attr( $name ) );
				foreach ( $args['options'] as $option => $label ) {
					printf( '<option value="%1$s"%2$s>%3$s</option>', esc_attr( $option ), selected( (string) $value, (string) $option, false ), esc_html( $label ) );
				}
				echo '</select>';
				break;

			case 'checkbox':
				printf(
					'<label for="%1$s"><input type="checkbox" id="%1$s" name="%2$s" value="1"%3$s /> %4$s</label>',
					esc_attr( $id ),
					esc_attr( $name ),
					checked( (bool) $value, true, false ),
					esc_html( $args['options'] )
				);
				break;

			case 'hours':
				printf(
					'<input type="number" id="%1$s" name="%2$s" value="%3$d" min="1" max="168" step="1" class="small-text" /> %4$s',
					esc_attr( $id ),
					esc_attr( $name ),
					(int) $value,
					esc_html__( 'hours', 'exactplay-spin' )
				);
				break;
		}

		if ( ! empty( $args['description'] ) ) {
			echo '<p class="description">' . esc_html( $args['description'] ) . '</p>';
		}
	}

	/**
	 * Renders the Settings page.
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap exactplay-spin-admin">
			<h1><?php esc_html_e( 'Exactplay Spin Settings', 'exactplay-spin' ); ?></h1>

			<?php settings_errors(); ?>

			<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only flag set by our own redirect. ?>
			<?php if ( isset( $_GET['exactplay-spin-cleared'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'The game catalog cache was cleared.', 'exactplay-spin' ); ?></p></div>
			<?php endif; ?>

			<div class="exactplay-spin-admin__layout">
				<div class="exactplay-spin-admin__main">
					<p><?php esc_html_e( 'These defaults apply to every game you embed. Individual blocks and shortcodes can override them.', 'exactplay-spin' ); ?></p>
					<form action="options.php" method="post">
						<?php
						settings_fields( 'exactplay_spin' );
						do_settings_sections( self::SETTINGS_PAGE );
						submit_button();
						?>
					</form>

					<h2><?php esc_html_e( 'Cache', 'exactplay-spin' ); ?></h2>
					<p><?php esc_html_e( 'New games appear automatically when the cache expires. Clear it to see catalog changes right away.', 'exactplay-spin' ); ?></p>
					<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
						<input type="hidden" name="action" value="exactplay_spin_clear_cache" />
						<?php wp_nonce_field( 'exactplay_spin_clear_cache' ); ?>
						<?php submit_button( __( 'Clear cache', 'exactplay-spin' ), 'secondary', 'submit', false ); ?>
					</form>
				</div>
				<?php $this->render_sidebar(); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Clears cached API responses.
	 */
	public function handle_clear_cache() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'exactplay-spin' ), 403 );
		}
		check_admin_referer( 'exactplay_spin_clear_cache' );

		$this->api->clear_cache();

		wp_safe_redirect( add_query_arg( 'exactplay-spin-cleared', '1', admin_url( 'admin.php?page=' . self::SETTINGS_PAGE ) ) );
		exit;
	}

	/**
	 * Renders the Game Library page.
	 */
	public function render_library_page() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only search form.
		$search   = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$provider = isset( $_GET['provider'] ) ? sanitize_key( wp_unslash( $_GET['provider'] ) ) : '';
		$paged    = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		// phpcs:enable

		$providers = $this->api->get_providers();
		$result    = $this->api->get_games(
			array(
				'search'   => $search,
				'provider' => $provider,
				'page'     => $paged,
				'per_page' => self::PER_PAGE,
			)
		);
		?>
		<div class="wrap exactplay-spin-admin">
			<h1><?php esc_html_e( 'Game Library', 'exactplay-spin' ); ?></h1>

			<div class="exactplay-spin-hero">
				<div>
					<p class="exactplay-spin-hero__lead">
						<?php
						if ( ! is_wp_error( $providers ) ) {
							// Rounded down: a few games belong to two studios, so the per-studio counts overlap.
							$game_count = (int) floor( array_sum( wp_list_pluck( $providers, 'count' ) ) / 100 ) * 100;
							printf(
								/* translators: 1: number of games, e.g. "4,400+", 2: number of studios. */
								esc_html__( '%1$s free-play demo slots from %2$s studios, ready to drop into any post or page.', 'exactplay-spin' ),
								'<strong>' . esc_html( number_format_i18n( $game_count ) ) . '+</strong>',
								'<strong>' . esc_html( number_format_i18n( count( $providers ) ) ) . '</strong>'
							);
						} else {
							esc_html_e( 'Free-play demo slots from the leading studios, ready to drop into any post or page.', 'exactplay-spin' );
						}
						?>
					</p>
					<p>
						<?php
						printf(
							/* translators: 1: "Demo Game" block name, 2: "Demo Game Grid" block name. */
							esc_html__( 'In the block editor, add the %1$s or %2$s block and search for a game. Using the classic editor or a page builder? Copy a shortcode below.', 'exactplay-spin' ),
							'<strong>' . esc_html__( 'Demo Game', 'exactplay-spin' ) . '</strong>',
							'<strong>' . esc_html__( 'Demo Game Grid', 'exactplay-spin' ) . '</strong>'
						);
						?>
					</p>
				</div>
				<div class="exactplay-spin-hero__links">
					<a class="button button-primary" href="<?php echo esc_url( Plugin::PRODUCT_URL ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'About the Exactplay Spin API', 'exactplay-spin' ); ?></a>
					<a class="button" href="<?php echo esc_url( Plugin::DEMO_URL ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'See it live on Plentyspins', 'exactplay-spin' ); ?></a>
				</div>
			</div>

			<form class="exactplay-spin-filters" method="get" role="search">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::LIBRARY_PAGE ); ?>" />
				<label class="screen-reader-text" for="exactplay-spin-search"><?php esc_html_e( 'Search games', 'exactplay-spin' ); ?></label>
				<input type="search" id="exactplay-spin-search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search games…', 'exactplay-spin' ); ?>" />
				<label class="screen-reader-text" for="exactplay-spin-provider"><?php esc_html_e( 'Studio', 'exactplay-spin' ); ?></label>
				<select id="exactplay-spin-provider" name="provider">
					<option value=""><?php esc_html_e( 'All studios', 'exactplay-spin' ); ?></option>
					<?php if ( ! is_wp_error( $providers ) ) : ?>
						<?php foreach ( $providers as $item ) : ?>
							<option value="<?php echo esc_attr( $item['slug'] ); ?>" <?php selected( $provider, $item['slug'] ); ?>>
								<?php echo esc_html( sprintf( '%s (%s)', $item['name'], number_format_i18n( $item['count'] ) ) ); ?>
							</option>
						<?php endforeach; ?>
					<?php endif; ?>
				</select>
				<?php submit_button( __( 'Search', 'exactplay-spin' ), 'secondary', '', false ); ?>
			</form>

			<?php if ( is_wp_error( $result ) ) : ?>
				<div class="notice notice-error inline"><p>
					<?php
					/* translators: %s: error message. */
					echo esc_html( sprintf( __( 'The game catalog could not be loaded: %s', 'exactplay-spin' ), $result->get_error_message() ) );
					?>
				</p></div>
			<?php elseif ( ! $result['games'] ) : ?>
				<p><?php esc_html_e( 'No games match your search.', 'exactplay-spin' ); ?></p>
			<?php else : ?>
				<p class="exactplay-spin-count">
					<?php
					/* translators: %s: number of games. */
					echo esc_html( sprintf( _n( '%s game', '%s games', $result['total'], 'exactplay-spin' ), number_format_i18n( $result['total'] ) ) );
					?>
				</p>
				<ul class="exactplay-spin-library">
					<?php foreach ( $result['games'] as $game ) : ?>
						<?php $this->render_library_card( $game ); ?>
					<?php endforeach; ?>
				</ul>
				<?php
				$links = paginate_links(
					array(
						'base'    => add_query_arg( 'paged', '%#%' ),
						'format'  => '',
						'current' => $paged,
						'total'   => max( 1, $result['total_pages'] ),
						'type'    => 'list',
					)
				);
				if ( $links ) {
					echo '<nav class="exactplay-spin-pagination" aria-label="' . esc_attr__( 'Game library pages', 'exactplay-spin' ) . '">' . wp_kses_post( $links ) . '</nav>';
				}
				?>
			<?php endif; ?>

			<?php $this->render_shortcode_help(); ?>
		</div>
		<?php
	}

	/**
	 * Renders one game in the library.
	 *
	 * @param array $game Game.
	 */
	private function render_library_card( array $game ) {
		$shortcode = sprintf( '[%s game="%s/%s"]', Shortcodes::GAME, $game['provider'], $game['slug'] );
		?>
		<li class="exactplay-spin-card">
			<div class="exactplay-spin-card__media">
				<?php if ( ! empty( $game['images']['landscape'] ) ) : ?>
					<img src="<?php echo esc_url( $game['images']['landscape'] ); ?>" alt="" loading="lazy" width="660" height="370" />
				<?php endif; ?>
			</div>
			<div class="exactplay-spin-card__body">
				<h2 class="exactplay-spin-card__title"><?php echo esc_html( $game['name'] ); ?></h2>
				<p class="exactplay-spin-card__provider"><?php echo esc_html( $game['provider_name'] ); ?></p>
				<code class="exactplay-spin-card__code"><?php echo esc_html( $shortcode ); ?></code>
				<div class="exactplay-spin-card__actions">
					<button type="button" class="button button-small" data-exactplay-spin-copy="<?php echo esc_attr( $shortcode ); ?>"><?php esc_html_e( 'Copy shortcode', 'exactplay-spin' ); ?></button>
					<a class="button button-small" href="<?php echo esc_url( $this->api->launch_url( $game['provider'], $game['slug'], array( 'lang' => $this->settings->resolve_lang() ) ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Play', 'exactplay-spin' ); ?></a>
					<a class="exactplay-spin-card__demo" href="<?php echo esc_url( Plugin::demo_url( $game['provider'], $game['slug'] ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View on Plentyspins', 'exactplay-spin' ); ?></a>
				</div>
			</div>
		</li>
		<?php
	}

	/**
	 * Renders the shortcode reference.
	 */
	private function render_shortcode_help() {
		$examples = array(
			'[exactplay_game game="netent/twin-spin"]'                          => __( 'Embed one game with your default settings.', 'exactplay-spin' ),
			'[exactplay_game game="pragmatic-play/sweet-bonanza" display="iframe" ratio="4:3" lang="de" currency="EUR"]' => __( 'Load the game right away, in German with euros, at 4:3.', 'exactplay-spin' ),
			'[exactplay_games]'                                                  => __( 'A grid of 12 random games from every studio.', 'exactplay-spin' ),
			'[exactplay_games provider="hacksaw" count="8" columns="4"]'         => __( 'Eight random games from one studio.', 'exactplay-spin' ),
			'[exactplay_games games="netent/twin-spin, relax-gaming/money-train-2" image="landscape"]' => __( 'A grid of games you pick, in your order.', 'exactplay-spin' ),
		);
		?>
		<details class="exactplay-spin-help">
			<summary><?php esc_html_e( 'Shortcode reference', 'exactplay-spin' ); ?></summary>
			<table class="widefat striped">
				<tbody>
					<?php foreach ( $examples as $code => $description ) : ?>
						<tr>
							<td><code><?php echo esc_html( $code ); ?></code></td>
							<td><?php echo esc_html( $description ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<p>
				<?php
				echo wp_kses(
					__( '<code>[exactplay_game]</code> options: <code>display</code> (click, iframe), <code>ratio</code> (16:9, 3:2, 4:3, 21:9, 1:1), <code>channel</code> (auto, web, mobile), <code>lang</code>, <code>currency</code>, <code>caption</code> (yes, no).', 'exactplay-spin' ),
					array( 'code' => array() )
				);
				?>
				<br />
				<?php
				echo wp_kses(
					__( '<code>[exactplay_games]</code> options: <code>games</code>, <code>provider</code>, <code>order</code> (random, catalog), <code>exclude</code>, <code>count</code>, <code>columns</code> (1–8), <code>image</code> (portrait, square, landscape), <code>click</code> (modal, tab), <code>show_provider</code> (yes, no), <code>channel</code>, <code>lang</code>, <code>currency</code>.', 'exactplay-spin' ),
					array( 'code' => array() )
				);
				?>
			</p>
		</details>
		<?php
	}

	/**
	 * Renders the settings page sidebar.
	 */
	private function render_sidebar() {
		?>
		<aside class="exactplay-spin-admin__sidebar">
			<div class="exactplay-spin-box">
				<h2><?php esc_html_e( 'About Exactplay Spin', 'exactplay-spin' ); ?></h2>
				<p><?php esc_html_e( 'Exactplay Spin is one API for real demo slots from every studio in its catalog, with artwork in every aspect ratio and a single launcher for desktop and mobile.', 'exactplay-spin' ); ?></p>
				<p><a class="button button-primary" href="<?php echo esc_url( Plugin::PRODUCT_URL ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Learn about the API', 'exactplay-spin' ); ?></a></p>
			</div>
			<div class="exactplay-spin-box">
				<h2><?php esc_html_e( 'See it in action', 'exactplay-spin' ); ?></h2>
				<p><?php esc_html_e( 'Plentyspins is a swipeable slot demo site built entirely on Exactplay Spin.', 'exactplay-spin' ); ?></p>
				<p><a class="button" href="<?php echo esc_url( Plugin::DEMO_URL ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Visit Plentyspins', 'exactplay-spin' ); ?></a></p>
			</div>
		</aside>
		<?php
	}

	/**
	 * Adds links under the plugin name on the Plugins screen.
	 *
	 * @param array $links Existing links.
	 * @return array
	 */
	public function action_links( $links ) {
		$plugin_links = array(
			'<a href="' . esc_url( admin_url( 'admin.php?page=' . self::LIBRARY_PAGE ) ) . '">' . esc_html__( 'Game Library', 'exactplay-spin' ) . '</a>',
		);
		if ( current_user_can( 'manage_options' ) ) {
			$plugin_links[] = '<a href="' . esc_url( admin_url( 'admin.php?page=' . self::SETTINGS_PAGE ) ) . '">' . esc_html__( 'Settings', 'exactplay-spin' ) . '</a>';
		}
		return array_merge( $plugin_links, $links );
	}

	/**
	 * Adds links to the plugin's description row on the Plugins screen.
	 *
	 * @param array  $links Existing links.
	 * @param string $file  Plugin file.
	 * @return array
	 */
	public function row_meta( $links, $file ) {
		if ( plugin_basename( EXACTPLAY_SPIN_FILE ) !== $file ) {
			return $links;
		}
		$links[] = '<a href="' . esc_url( Plugin::PRODUCT_URL ) . '" target="_blank" rel="noopener">' . esc_html__( 'Exactplay Spin API', 'exactplay-spin' ) . '</a>';
		$links[] = '<a href="' . esc_url( Plugin::DEMO_URL ) . '" target="_blank" rel="noopener">' . esc_html__( 'Live demo: Plentyspins', 'exactplay-spin' ) . '</a>';
		return $links;
	}

	/**
	 * Suggests privacy policy text describing the third-party game embeds.
	 */
	public function add_privacy_policy_content() {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}

		$content  = '<p class="privacy-policy-tutorial">' . esc_html__( 'This sample text describes the demo games embedded with Exactplay Spin. Adjust it to match your settings.', 'exactplay-spin' ) . '</p>';
		$content .= '<p>' . esc_html__( 'Some pages include free-play demo games provided by Exactplay (gameserver.exactplay.com) and the game studios that made them. Game artwork is loaded from Exactplay\'s image servers (s3.exactplay.com). When you start a game, your browser connects to Exactplay and the game studio, which receive your IP address and browser details and may set cookies to run the game. Demo games use play money only.', 'exactplay-spin' ) . '</p>';
		$content .= '<p>' . sprintf(
			/* translators: %s: Exactplay privacy policy URL. */
			esc_html__( 'Exactplay privacy policy: %s', 'exactplay-spin' ),
			'<a href="https://exactplay.com/privacy-policy">https://exactplay.com/privacy-policy</a>'
		) . '</p>';

		wp_add_privacy_policy_content( __( 'Exactplay Spin', 'exactplay-spin' ), wp_kses_post( $content ) );
	}
}
