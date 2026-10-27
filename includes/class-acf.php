<?php
/**
 * Advanced Custom Fields support: a Mux Video field type.
 *
 * @package VideoLibraryForMux
 */

namespace VideoLibraryForMux;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the field type when ACF is active.
 */
final class ACF {

	const FIELD_TYPE = 'vlfm_video';

	/**
	 * Hooks into ACF. Does nothing when ACF is not installed.
	 */
	public static function register() {
		add_action( 'acf/include_field_types', array( __CLASS__, 'include_field_type' ) );
	}

	/**
	 * Loads the field class, which extends one of ACF's, and registers it.
	 */
	public static function include_field_type() {
		if ( ! function_exists( 'acf_register_field_type' ) || ! class_exists( 'acf_field' ) ) {
			return;
		}

		require_once VLFM_DIR . 'includes/class-acf-field.php';
		acf_register_field_type( ACF_Field::class );
	}

	/**
	 * The library video ID in a field value: an ID, a video array or a post.
	 *
	 * @param mixed $value Field value.
	 * @return int Post ID, or 0.
	 */
	public static function video_id( $value ) {
		if ( is_array( $value ) && isset( $value['id'] ) ) {
			$value = $value['id'];
		} elseif ( $value instanceof \WP_Post ) {
			$value = $value->ID;
		}
		return is_numeric( $value ) && (int) $value > 0 ? (int) $value : 0;
	}
}
