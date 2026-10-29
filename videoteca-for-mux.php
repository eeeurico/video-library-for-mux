<?php
/**
 * Plugin Name:       Videoteca – Video Library for Mux
 * Plugin URI:        https://github.com/eeeurico/videoteca-for-mux
 * Description:       Upload videos to Mux from the WordPress admin, keep their titles, search your library and embed them with a block, shortcode or ACF field.
 * Version:           1.0.0
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Author:            Eurico Sá Fernandes
 * Author URI:        https://github.com/eeeurico
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       videoteca-for-mux
 *
 * @package VideoLibraryForMux
 */

defined( 'ABSPATH' ) || exit;

define( 'VLFM_VERSION', '1.0.0' );
define( 'VLFM_FILE', __FILE__ );
define( 'VLFM_DIR', plugin_dir_path( __FILE__ ) );
define( 'VLFM_URL', plugin_dir_url( __FILE__ ) );

require_once VLFM_DIR . 'includes/class-settings.php';
require_once VLFM_DIR . 'includes/class-mux-client.php';
require_once VLFM_DIR . 'includes/class-videos.php';
require_once VLFM_DIR . 'includes/class-playback.php';
require_once VLFM_DIR . 'includes/class-webhooks.php';
require_once VLFM_DIR . 'includes/class-rest-controller.php';
require_once VLFM_DIR . 'includes/class-admin.php';
require_once VLFM_DIR . 'includes/class-blocks.php';
require_once VLFM_DIR . 'includes/class-acf.php';
require_once VLFM_DIR . 'includes/class-graphql.php';
require_once VLFM_DIR . 'includes/class-graphql-video.php';
require_once VLFM_DIR . 'includes/class-plugin.php';

VideoLibraryForMux\Plugin::boot();
