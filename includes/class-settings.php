<?php
/**
 * Plugin settings.
 *
 * @package ExactplaySpin
 */

namespace Exactplay\Spin;

defined( 'ABSPATH' ) || exit;

/**
 * Reads, validates and describes the plugin's settings.
 */
class Settings {

	const OPTION = 'exactplay_spin_settings';

	/**
	 * Cached merged settings.
	 *
	 * @var array|null
	 */
	private $values = null;

	/**
	 * Default values for every setting.
	 *
	 * @return array
	 */
	public function defaults() {
		return array(
			'display'         => 'click',
			'aspect_ratio'    => '16/9',
			'channel'         => 'auto',
			'lang'            => '',
			'currency'        => '',
			'grid_click'      => 'modal',
			'structured_data' => true,
			'credit'          => false,
			'local_images'    => true,
			'cache_hours'     => 12,
		);
	}

	/**
	 * Returns all settings merged over the defaults.
	 *
	 * @return array
	 */
	public function all() {
		if ( null === $this->values ) {
			$stored       = get_option( self::OPTION, array() );
			$this->values = array_merge( $this->defaults(), is_array( $stored ) ? $stored : array() );
		}
		return $this->values;
	}

	/**
	 * Returns one setting.
	 *
	 * @param string $key Setting name.
	 * @return mixed
	 */
	public function get( $key ) {
		$all = $this->all();
		return isset( $all[ $key ] ) ? $all[ $key ] : null;
	}

	/**
	 * Forgets cached values after the option changes.
	 */
	public function flush() {
		$this->values = null;
	}

	/**
	 * Language code sent to the game launcher, falling back to the site language.
	 *
	 * @param string $override Per-embed language, if any.
	 * @return string
	 */
	public function resolve_lang( $override = '' ) {
		$lang = self::sanitize_lang( $override );
		if ( '' === $lang ) {
			$lang = self::sanitize_lang( (string) $this->get( 'lang' ) );
		}
		if ( '' === $lang ) {
			$lang = strtolower( substr( determine_locale(), 0, 2 ) );
		}
		return $lang;
	}

	/**
	 * Sanitizes the settings form submission.
	 *
	 * @param mixed $input Raw input.
	 * @return array
	 */
	public function sanitize( $input ) {
		$input    = is_array( $input ) ? $input : array();
		$defaults = $this->defaults();
		$clean    = array();

		$clean['display']      = self::pick( $input, 'display', array_keys( self::display_options() ), $defaults['display'] );
		$clean['aspect_ratio'] = self::pick( $input, 'aspect_ratio', array_keys( self::aspect_ratio_options() ), $defaults['aspect_ratio'] );
		$clean['channel']      = self::pick( $input, 'channel', array_keys( self::channel_options() ), $defaults['channel'] );
		$clean['grid_click']   = self::pick( $input, 'grid_click', array_keys( self::grid_click_options() ), $defaults['grid_click'] );
		$clean['lang']         = self::sanitize_lang( isset( $input['lang'] ) ? $input['lang'] : '' );
		$clean['currency']     = self::sanitize_currency( isset( $input['currency'] ) ? $input['currency'] : '' );

		$clean['structured_data'] = ! empty( $input['structured_data'] );
		$clean['credit']          = ! empty( $input['credit'] );
		$clean['local_images']    = ! empty( $input['local_images'] );

		$hours                = isset( $input['cache_hours'] ) ? absint( $input['cache_hours'] ) : $defaults['cache_hours'];
		$clean['cache_hours'] = min( 168, max( 1, $hours ) );

		$this->flush();
		return $clean;
	}

	/**
	 * Returns $input[ $key ] when it is one of $allowed, otherwise $fallback.
	 *
	 * @param array  $input    Raw input.
	 * @param string $key      Key to read.
	 * @param array  $allowed  Allowed values.
	 * @param string $fallback Fallback value.
	 * @return string
	 */
	private static function pick( $input, $key, $allowed, $fallback ) {
		$value = isset( $input[ $key ] ) ? (string) $input[ $key ] : '';
		return in_array( $value, $allowed, true ) ? $value : $fallback;
	}

	/**
	 * Accepts language codes like "en" or "pt-br".
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function sanitize_lang( $value ) {
		$value = strtolower( trim( (string) $value ) );
		return preg_match( '/^[a-z]{2}(-[a-z]{2})?$/', $value ) ? $value : '';
	}

	/**
	 * Accepts 3-letter currency codes like "EUR".
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function sanitize_currency( $value ) {
		$value = strtoupper( trim( (string) $value ) );
		return preg_match( '/^[A-Z]{3}$/', $value ) ? $value : '';
	}

	/**
	 * Display modes for single games.
	 *
	 * @return array
	 */
	public static function display_options() {
		return array(
			'click'  => __( 'Click to play (loads the game when the visitor clicks)', 'exactplay-spin' ),
			'iframe' => __( 'Load the game immediately', 'exactplay-spin' ),
		);
	}

	/**
	 * Aspect ratios for embedded games.
	 *
	 * @return array
	 */
	public static function aspect_ratio_options() {
		return array(
			'16/9' => '16:9',
			'3/2'  => '3:2',
			'4/3'  => '4:3',
			'21/9' => '21:9',
			'1/1'  => '1:1',
		);
	}

	/**
	 * Game client channels.
	 *
	 * @return array
	 */
	public static function channel_options() {
		return array(
			'auto'   => __( 'Automatic (the game detects the device)', 'exactplay-spin' ),
			'web'    => __( 'Desktop', 'exactplay-spin' ),
			'mobile' => __( 'Mobile', 'exactplay-spin' ),
		);
	}

	/**
	 * What happens when a visitor clicks a game in a grid.
	 *
	 * @return array
	 */
	public static function grid_click_options() {
		return array(
			'modal' => __( 'Open the game in a pop-up player', 'exactplay-spin' ),
			'tab'   => __( 'Open the game in a new tab', 'exactplay-spin' ),
		);
	}

	/**
	 * Common game languages. Other codes can be used in shortcodes.
	 *
	 * @return array
	 */
	public static function language_options() {
		return array(
			'en' => 'English',
			'de' => 'Deutsch',
			'es' => 'Español',
			'fr' => 'Français',
			'it' => 'Italiano',
			'pt' => 'Português',
			'nl' => 'Nederlands',
			'sv' => 'Svenska',
			'fi' => 'Suomi',
			'no' => 'Norsk',
			'da' => 'Dansk',
			'pl' => 'Polski',
			'cs' => 'Čeština',
			'hu' => 'Magyar',
			'ro' => 'Română',
			'el' => 'Ελληνικά',
			'tr' => 'Türkçe',
			'ru' => 'Русский',
			'ja' => '日本語',
			'ko' => '한국어',
			'zh' => '中文',
		);
	}

	/**
	 * Common demo currencies. Other codes can be used in shortcodes.
	 *
	 * @return array
	 */
	public static function currency_options() {
		$codes = array( 'EUR', 'USD', 'GBP', 'CAD', 'AUD', 'NZD', 'CHF', 'SEK', 'NOK', 'DKK', 'PLN', 'CZK', 'HUF', 'RON', 'TRY', 'BRL', 'MXN', 'INR', 'JPY', 'ZAR' );
		return array_combine( $codes, $codes );
	}
}
