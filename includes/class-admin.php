<?php
/**
 * Mux Videos: the admin menu and its library screen.
 *
 * @package VideoLibraryForMux
 */

namespace VideoLibraryForMux;

defined( 'ABSPATH' ) || exit;

/**
 * Adds the Mux Videos menu and library screen, and loads its script.
 */
final class Admin {

	const PAGE = 'vlfm-library';

	/**
	 * Hooks the screen into the admin.
	 */
	public static function register() {
		add_action( 'admin_menu', array( __CLASS__, 'add_page' ) );
	}

	/**
	 * URL of the library screen.
	 *
	 * @return string
	 */
	public static function page_url() {
		return admin_url( 'admin.php?page=' . self::PAGE );
	}

	/**
	 * Adds the Mux Videos menu, just below Media, with the library as its first screen.
	 */
	public static function add_page() {
		$hook = add_menu_page(
			__( 'Mux Videos', 'video-library-for-mux' ),
			__( 'Mux Videos', 'video-library-for-mux' ),
			'upload_files',
			self::PAGE,
			array( __CLASS__, 'render_page' ),
			'dashicons-video-alt3',
			11
		);

		// Names the first item "Library", as WordPress does under Media.
		add_submenu_page(
			self::PAGE,
			__( 'Mux Videos', 'video-library-for-mux' ),
			__( 'Library', 'video-library-for-mux' ),
			'upload_files',
			self::PAGE
		);

		add_action(
			'load-' . $hook,
			static function () {
				add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
			}
		);
	}

	/**
	 * Loads the library app.
	 */
	public static function enqueue_assets() {
		$asset_file = VLFM_DIR . 'build/admin/index.asset.php';
		if ( ! file_exists( $asset_file ) ) {
			return;
		}
		$asset = require $asset_file;

		wp_enqueue_script(
			'vlfm-admin',
			VLFM_URL . 'build/admin/index.js',
			array_merge( $asset['dependencies'], array( Playback::PLAYER_HANDLE ) ),
			$asset['version'],
			true
		);
		wp_set_script_translations( 'vlfm-admin', 'video-library-for-mux' );
		wp_add_inline_script(
			'vlfm-admin',
			'window.vlfmLibrary = ' . wp_json_encode(
				array(
					'isConfigured' => Settings::is_configured(),
					'canUpload'    => current_user_can( 'upload_files' ),
					'canSync'      => current_user_can( 'manage_options' ),
					'settingsUrl'  => current_user_can( 'manage_options' ) ? Settings::page_url() : '',
				)
			) . ';',
			'before'
		);

		self::enqueue_library_styles();
		wp_enqueue_style( 'vlfm-admin', VLFM_URL . 'build/admin/index.css', array( 'vlfm-library' ), $asset['version'] );
		wp_style_add_data( 'vlfm-admin', 'rtl', 'replace' );
	}

	/**
	 * The grid and dialog styles, plus the component styles they build on.
	 */
	public static function enqueue_library_styles() {
		$asset_file = VLFM_DIR . 'build/library/index.asset.php';
		$asset      = file_exists( $asset_file ) ? require $asset_file : array( 'version' => VLFM_VERSION );
		$version    = $asset['version'];

		wp_enqueue_style( 'vlfm-library', VLFM_URL . 'build/library/index.css', array( 'wp-components' ), $version );
		wp_style_add_data( 'vlfm-library', 'rtl', 'replace' );
	}

	/**
	 * Renders the screen shell; the script fills it in.
	 */
	public static function render_page() {
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Mux Videos', 'video-library-for-mux' ); ?></h1>
			<span id="vlfm-library-actions"></span>
			<hr class="wp-header-end" />

			<?php if ( ! Settings::is_configured() ) : ?>
				<div class="notice notice-warning inline">
					<p>
						<?php
						if ( current_user_can( 'manage_options' ) ) {
							printf(
								/* translators: %s: URL of the settings screen. */
								wp_kses( __( 'Connect your Mux account in <a href="%s">Mux Videos → Settings</a> to upload videos.', 'video-library-for-mux' ), array( 'a' => array( 'href' => array() ) ) ),
								esc_url( Settings::page_url() )
							);
						} else {
							esc_html_e( 'An administrator needs to connect a Mux account before videos can be uploaded.', 'video-library-for-mux' );
						}
						?>
					</p>
				</div>
			<?php endif; ?>

			<?php if ( Settings::uses_signed_playback() && ! Playback::can_sign() ) : ?>
				<div class="notice notice-warning inline">
					<p><?php esc_html_e( 'Signed playback is on, but there is no signing key, so new videos get public playback IDs and signed videos cannot play. Add a signing key in Mux Videos → Settings.', 'video-library-for-mux' ); ?></p>
				</div>
			<?php endif; ?>

			<div id="vlfm-library">
				<noscript>
					<div class="notice notice-error inline"><p><?php esc_html_e( 'The video library needs JavaScript.', 'video-library-for-mux' ); ?></p></div>
				</noscript>
			</div>
		</div>
		<?php
	}
}
