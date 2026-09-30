<?php
/**
 * Plugin Name:       Exactplay Spin – Demo Slot Games
 * Plugin URI:        https://exactplay.com/spin
 * Description:       Embed free-play demo slots from 25+ studios (Pragmatic Play, NetEnt, Play'n GO, Hacksaw and more) in any post or page with a block or shortcode. Powered by the Exactplay Spin API.
 * Version:           1.0.0
 * Requires at least: 6.3
 * Requires PHP:      7.4
 * Author:            Exactplay
 * Author URI:        https://exactplay.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       exactplay-spin
 *
 * @package ExactplaySpin
 */

defined( 'ABSPATH' ) || exit;

define( 'EXACTPLAY_SPIN_VERSION', '1.0.0' );
define( 'EXACTPLAY_SPIN_FILE', __FILE__ );
define( 'EXACTPLAY_SPIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'EXACTPLAY_SPIN_URL', plugin_dir_url( __FILE__ ) );

require_once EXACTPLAY_SPIN_DIR . 'includes/class-plugin.php';
require_once EXACTPLAY_SPIN_DIR . 'includes/class-settings.php';
require_once EXACTPLAY_SPIN_DIR . 'includes/class-api-client.php';
require_once EXACTPLAY_SPIN_DIR . 'includes/class-image-cache.php';
require_once EXACTPLAY_SPIN_DIR . 'includes/class-renderer.php';
require_once EXACTPLAY_SPIN_DIR . 'includes/class-blocks.php';
require_once EXACTPLAY_SPIN_DIR . 'includes/class-shortcodes.php';
require_once EXACTPLAY_SPIN_DIR . 'includes/class-rest-controller.php';
require_once EXACTPLAY_SPIN_DIR . 'includes/class-admin.php';

add_action( 'plugins_loaded', array( 'Exactplay\\Spin\\Plugin', 'instance' ) );
