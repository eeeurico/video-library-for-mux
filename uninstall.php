<?php
/**
 * Removes the library and settings when the plugin is deleted, if the site
 * owner asked for that in Mux Videos → Settings. Videos in Mux are never touched.
 *
 * @package VideoLibraryForMux
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$vlfm_settings = get_option( 'vlfm_settings' );

if ( is_array( $vlfm_settings ) && ! empty( $vlfm_settings['delete_data_on_uninstall'] ) ) {
	do {
		$vlfm_ids = get_posts(
			array(
				'post_type'      => 'vlfm_video',
				'post_status'    => 'any',
				'fields'         => 'ids',
				'posts_per_page' => 100,
				'no_found_rows'  => true,
			)
		);
		foreach ( $vlfm_ids as $vlfm_id ) {
			wp_delete_post( $vlfm_id, true );
		}
	} while ( $vlfm_ids );

	delete_option( 'vlfm_settings' );
	delete_option( 'vlfm_connection_status' );
}
