<?php
/**
 * The Mux Video block and the [vlfm_video] shortcode.
 *
 * @package VideoLibraryForMux
 */

namespace VideoLibraryForMux;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the block and shortcode and renders them.
 */
final class Blocks {

	/**
	 * Registers the block, the shortcode and the editor assets.
	 */
	public static function register() {
		if ( file_exists( VLFM_DIR . 'build/block/block.json' ) ) {
			register_block_type(
				VLFM_DIR . 'build/block',
				array( 'render_callback' => array( __CLASS__, 'render_block' ) )
			);
		}

		add_shortcode( 'vlfm_video', array( __CLASS__, 'render_shortcode' ) );
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'enqueue_editor_assets' ) );
		add_action( 'enqueue_block_assets', array( __CLASS__, 'enqueue_editor_canvas_assets' ) );
	}

	/**
	 * Settings for the block's script, and styles for the dialog it opens.
	 */
	public static function enqueue_editor_assets() {
		$handle = generate_block_asset_handle( 'vlfm/video', 'editorScript' );

		wp_add_inline_script(
			$handle,
			'window.vlfmEditor = ' . wp_json_encode(
				array(
					'isConfigured' => Settings::is_configured(),
					'canUpload'    => current_user_can( 'upload_files' ),
					'libraryUrl'   => current_user_can( 'upload_files' ) ? Admin::page_url() : '',
					'settingsUrl'  => current_user_can( 'manage_options' ) ? Settings::page_url() : '',
					'accentColor'  => (string) Settings::get( 'accent_color' ),
				)
			) . ';',
			'before'
		);

		Admin::enqueue_library_styles();
	}

	/**
	 * The player inside the editor canvas, so the block can preview videos.
	 * enqueue_block_assets also runs on the front end, where the block loads it itself.
	 */
	public static function enqueue_editor_canvas_assets() {
		if ( is_admin() ) {
			wp_enqueue_script( Playback::PLAYER_HANDLE );
		}
	}

	/**
	 * Renders the block.
	 *
	 * @param array $attributes Block attributes.
	 * @return string
	 */
	public static function render_block( $attributes ) {
		$player = Playback::render_player(
			isset( $attributes['videoId'] ) ? (int) $attributes['videoId'] : 0,
			array(
				'background'     => ! empty( $attributes['background'] ),
				'autoplay'       => ! empty( $attributes['autoplay'] ),
				'muted'          => ! empty( $attributes['muted'] ),
				'loop'           => ! empty( $attributes['loop'] ),
				'controls'       => ! isset( $attributes['controls'] ) || ! empty( $attributes['controls'] ),
				'show_title'     => ! empty( $attributes['showTitle'] ),
				'hide_captions'  => ! empty( $attributes['hideCaptions'] ),
				'playback_rates' => isset( $attributes['playbackRates'] ) ? (string) $attributes['playbackRates'] : '',
				'preload'        => isset( $attributes['preload'] ) ? (string) $attributes['preload'] : '',
				'max_resolution' => isset( $attributes['maxResolution'] ) ? (string) $attributes['maxResolution'] : '',
				'poster'         => isset( $attributes['posterId'] ) ? (int) $attributes['posterId'] : 0,
				'start_time'     => isset( $attributes['startTime'] ) ? (float) $attributes['startTime'] : 0,
				'accent_color'   => isset( $attributes['accentColor'] ) ? (string) $attributes['accentColor'] : '',
			)
		);

		if ( '' === $player ) {
			return '';
		}

		return sprintf( '<figure %s>%s</figure>', get_block_wrapper_attributes(), $player );
	}

	/**
	 * Renders the shortcode, for example
	 * [vlfm_video id="123" autoplay="1" controls="0" captions="off" speeds="0.5 1 2" poster="456"].
	 *
	 * Switches take 1, true, on or yes. See readme.txt for every attribute.
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string
	 */
	public static function render_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'id'             => 0,
				'background'     => '',
				'autoplay'       => '',
				'muted'          => '',
				'loop'           => '',
				'controls'       => 'true',
				'title'          => '',
				'captions'       => '',
				'speeds'         => '',
				'preload'        => '',
				'max_resolution' => '',
				'poster'         => '',
				'start'          => 0,
				'accent_color'   => '',
			),
			$atts,
			'vlfm_video'
		);

		return Playback::embed(
			absint( $atts['id'] ),
			array(
				'background'     => self::is_on( $atts['background'] ),
				'autoplay'       => self::is_on( $atts['autoplay'] ),
				'muted'          => self::is_on( $atts['muted'] ),
				'loop'           => self::is_on( $atts['loop'] ),
				'controls'       => self::is_on( $atts['controls'] ),
				'show_title'     => self::is_on( $atts['title'] ),
				'hide_captions'  => 'off' === strtolower( trim( (string) $atts['captions'] ) ),
				'playback_rates' => (string) $atts['speeds'],
				'preload'        => strtolower( trim( (string) $atts['preload'] ) ),
				'max_resolution' => strtolower( trim( (string) $atts['max_resolution'] ) ),
				'poster'         => (string) $atts['poster'],
				'start_time'     => max( 0, (float) $atts['start'] ),
				'accent_color'   => (string) $atts['accent_color'],
			)
		);
	}

	/**
	 * Reads a shortcode switch. Written by hand, so "on" and "yes" count as well as "1" and "true".
	 *
	 * @param string $value Attribute value.
	 * @return bool
	 */
	private static function is_on( $value ) {
		return in_array( strtolower( trim( (string) $value ) ), array( '1', 'true', 'on', 'yes' ), true );
	}
}
