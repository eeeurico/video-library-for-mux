<?php
/**
 * Plugin bootstrap.
 *
 * @package VideoLibraryForMux
 */

namespace VideoLibraryForMux;

defined( 'ABSPATH' ) || exit;

/**
 * Wires every component into WordPress.
 */
final class Plugin {

	/**
	 * Registers all hooks. Runs once, from the main plugin file.
	 */
	public static function boot() {
		add_action( 'init', array( Videos::class, 'register_post_type' ) );
		add_action( 'init', array( Playback::class, 'register_player_script' ) );
		add_action( 'init', array( Blocks::class, 'register' ) );
		add_action( 'rest_api_init', array( Rest_Controller::class, 'register_routes' ) );
		add_filter( 'posts_search', array( Videos::class, 'filter_posts_search' ), 10, 2 );

		// Integrations. Each does nothing unless its plugin is active.
		ACF::register();
		GraphQL::register();

		if ( is_admin() ) {
			Settings::register();
			Admin::register();
		}
	}
}
