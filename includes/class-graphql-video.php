<?php
/**
 * A library video as WPGraphQL resolves it.
 *
 * @package VideoLibraryForMux
 */

namespace VideoLibraryForMux;

defined( 'ABSPATH' ) || exit;

/**
 * The MuxVideo fields, keyed by their GraphQL names so WPGraphQL reads them
 * directly. Its own class, so node(id:) can tell it apart from other nodes.
 */
final class GraphQL_Video extends \ArrayObject {
}
