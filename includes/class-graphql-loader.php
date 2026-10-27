<?php
/**
 * Loads library videos for WPGraphQL's node(id:) field. Loaded by
 * GraphQL::add_loader() once WPGraphQL is running.
 *
 * @package VideoLibraryForMux
 */

namespace VideoLibraryForMux;

defined( 'ABSPATH' ) || exit;

/**
 * Batches video lookups by post ID.
 */
final class GraphQL_Loader extends \WPGraphQL\Data\Loader\AbstractDataLoader {

	/**
	 * Finds the videos for a batch of IDs.
	 *
	 * @param int[]|string[] $keys Post IDs.
	 * @return array Videos, or null for each ID the viewer cannot see, keyed by ID.
	 */
	public function loadKeys( array $keys ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- Name set by WPGraphQL.
		$loaded = array();
		foreach ( $keys as $key ) {
			$loaded[ $key ] = GraphQL::find( $key );
		}
		return $loaded;
	}
}
