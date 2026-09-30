<?php
/**
 * Shortcodes for the classic editor, widgets and page builders.
 *
 * @package ExactplaySpin
 */

namespace Exactplay\Spin;

defined( 'ABSPATH' ) || exit;

/**
 * Registers [exactplay_game] and [exactplay_games].
 *
 * Examples:
 *   [exactplay_game game="netent/twin-spin"]
 *   [exactplay_game game="pragmatic-play/sweet-bonanza" display="iframe" ratio="4:3" lang="de" currency="EUR"]
 *   [exactplay_games provider="hacksaw" count="8" columns="4"]
 *   [exactplay_games games="netent/twin-spin, relax-gaming/money-train-2" image="landscape"]
 */
class Shortcodes {

	const GAME = 'exactplay_game';

	const GRID = 'exactplay_games';

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
		add_shortcode( self::GAME, array( $this, 'game' ) );
		add_shortcode( self::GRID, array( $this, 'games' ) );
	}

	/**
	 * Renders [exactplay_game].
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string
	 */
	public function game( $atts ) {
		$atts = shortcode_atts(
			array(
				'game'     => '',
				'provider' => '',
				'name'     => '',
				'display'  => '',
				'ratio'    => '',
				'channel'  => '',
				'lang'     => '',
				'currency' => '',
				'caption'  => 'yes',
			),
			$atts,
			self::GAME
		);

		// Accept game="provider/slug" as well as separate provider and game attributes.
		$provider = $atts['provider'];
		$game     = $atts['game'];
		$parsed   = Api_Client::parse_key( $game );
		if ( $parsed ) {
			list( $provider, $game ) = $parsed;
		}

		return $this->renderer->render_game(
			array(
				'provider'     => $provider,
				'game'         => $game,
				'name'         => $atts['name'],
				'display'      => $atts['display'],
				'aspect_ratio' => str_replace( ':', '/', $atts['ratio'] ),
				'channel'      => $atts['channel'],
				'lang'         => $atts['lang'],
				'currency'     => $atts['currency'],
				'caption'      => self::to_bool( $atts['caption'] ),
			)
		);
	}

	/**
	 * Renders [exactplay_games].
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string
	 */
	public function games( $atts ) {
		$atts = shortcode_atts(
			array(
				'games'         => '',
				'provider'      => '',
				'order'         => 'random',
				'exclude'       => '', // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude -- Exactplay API parameter, not a WP_Query argument.
				'count'         => 12,
				'columns'       => 4,
				'image'         => 'portrait',
				'click'         => '',
				'show_provider' => 'yes',
				'channel'       => '',
				'lang'          => '',
				'currency'      => '',
			),
			$atts,
			self::GRID
		);

		if ( '' !== trim( $atts['games'] ) ) {
			$source = 'selected';
		} else {
			$source = 'catalog' === $atts['order'] ? 'catalog' : 'random';
		}

		return $this->renderer->render_grid(
			array(
				'source'        => $source,
				'provider'      => $atts['provider'],
				'games'         => array_map( 'trim', explode( ',', $atts['games'] ) ),
				'exclude'       => $atts['exclude'], // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude -- Exactplay API parameter, not a WP_Query argument.
				'count'         => $atts['count'],
				'columns'       => $atts['columns'],
				'image'         => $atts['image'],
				'click'         => $atts['click'],
				'show_provider' => self::to_bool( $atts['show_provider'] ),
				'channel'       => $atts['channel'],
				'lang'          => $atts['lang'],
				'currency'      => $atts['currency'],
			)
		);
	}

	/**
	 * Reads yes/no style shortcode values.
	 *
	 * @param mixed $value Raw value.
	 * @return bool
	 */
	private static function to_bool( $value ) {
		return ! in_array( strtolower( trim( (string) $value ) ), array( '', '0', 'false', 'no', 'off' ), true );
	}
}
