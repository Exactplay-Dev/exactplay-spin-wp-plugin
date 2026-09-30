<?php
/**
 * Removes the plugin's settings and cached data.
 *
 * @package ExactplaySpin
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'exactplay_spin_settings' );
delete_option( 'exactplay_spin_cache_version' );

// Stored WebP copies of game artwork.
require_once __DIR__ . '/includes/class-image-cache.php';
Exactplay\Spin\Image_Cache::delete_all();

global $wpdb;

// Cached API responses are transients with hashed names, so they're removed by prefix.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
		$wpdb->esc_like( '_transient_exactplay_spin_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_exactplay_spin_' ) . '%'
	)
);
