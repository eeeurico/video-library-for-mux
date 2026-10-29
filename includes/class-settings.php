<?php
/**
 * Settings screen (Mux Videos → Settings) and option accessors.
 *
 * @package VideoLibraryForMux
 */

namespace VideoLibraryForMux;

defined( 'ABSPATH' ) || exit;

/**
 * Stores every setting in a single option and renders the screen with the Settings API.
 */
final class Settings {

	const OPTION            = 'vlfm_settings';
	const STATUS_OPTION     = 'vlfm_connection_status';
	const PAGE              = 'vlfm-settings';
	const GROUP             = 'vlfm_settings_group';
	const CREATE_KEY_ACTION = 'vlfm_create_signing_key';

	/**
	 * Fields that hold secrets. They are never printed back into the page,
	 * and an empty submission keeps the stored value.
	 *
	 * @var string[]
	 */
	const SECRET_FIELDS = array( 'token_secret', 'signing_key_private', 'webhook_secret' );

	/**
	 * Default values for every setting.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'token_id'                 => '',
			'token_secret'             => '',
			'video_quality'            => 'plus',
			'max_resolution_tier'      => '1080p',
			'captions_language'        => '',
			'static_renditions'        => '',
			'playback_policy'          => 'public',
			'signing_key_id'           => '',
			'signing_key_private'      => '',
			'token_ttl_hours'          => 24,
			'accent_color'             => '',
			'analytics'                => false,
			'analytics_cookies'        => false,
			'webhook_secret'           => '',
			'delete_data_on_uninstall' => false,
		);
	}

	/**
	 * All settings merged over their defaults.
	 *
	 * @return array
	 */
	public static function all() {
		return wp_parse_args( (array) get_option( self::OPTION, array() ), self::defaults() );
	}

	/**
	 * One setting.
	 *
	 * @param string $key Setting name.
	 * @return mixed
	 */
	public static function get( $key ) {
		$settings = self::all();
		return isset( $settings[ $key ] ) ? $settings[ $key ] : null;
	}

	/**
	 * Whether API credentials have been saved.
	 *
	 * @return bool
	 */
	public static function is_configured() {
		return '' !== self::get( 'token_id' ) && '' !== self::get( 'token_secret' );
	}

	/**
	 * Whether new uploads should get a signed playback ID.
	 *
	 * @return bool
	 */
	public static function uses_signed_playback() {
		return 'signed' === self::get( 'playback_policy' );
	}

	/**
	 * Whether a signing key is available for signed playback tokens.
	 *
	 * @return bool
	 */
	public static function can_sign() {
		return '' !== self::get( 'signing_key_id' ) && '' !== self::get( 'signing_key_private' );
	}

	/**
	 * Languages Mux can auto-generate captions for.
	 *
	 * @return array Language code => label.
	 */
	public static function caption_languages() {
		return array(
			'auto' => __( 'Detect automatically', 'videoteca-for-mux' ),
			'en'   => __( 'English', 'videoteca-for-mux' ),
			'es'   => __( 'Spanish', 'videoteca-for-mux' ),
			'pt'   => __( 'Portuguese', 'videoteca-for-mux' ),
			'fr'   => __( 'French', 'videoteca-for-mux' ),
			'de'   => __( 'German', 'videoteca-for-mux' ),
			'it'   => __( 'Italian', 'videoteca-for-mux' ),
			'nl'   => __( 'Dutch (beta)', 'videoteca-for-mux' ),
			'pl'   => __( 'Polish (beta)', 'videoteca-for-mux' ),
			'ru'   => __( 'Russian (beta)', 'videoteca-for-mux' ),
			'uk'   => __( 'Ukrainian (beta)', 'videoteca-for-mux' ),
			'ca'   => __( 'Catalan (beta)', 'videoteca-for-mux' ),
			'tr'   => __( 'Turkish (beta)', 'videoteca-for-mux' ),
			'sv'   => __( 'Swedish (beta)', 'videoteca-for-mux' ),
			'no'   => __( 'Norwegian (beta)', 'videoteca-for-mux' ),
			'da'   => __( 'Danish (beta)', 'videoteca-for-mux' ),
			'fi'   => __( 'Finnish (beta)', 'videoteca-for-mux' ),
			'cs'   => __( 'Czech (beta)', 'videoteca-for-mux' ),
			'sk'   => __( 'Slovak (beta)', 'videoteca-for-mux' ),
			'hr'   => __( 'Croatian (beta)', 'videoteca-for-mux' ),
			'ro'   => __( 'Romanian (beta)', 'videoteca-for-mux' ),
			'bg'   => __( 'Bulgarian (beta)', 'videoteca-for-mux' ),
			'el'   => __( 'Greek (beta)', 'videoteca-for-mux' ),
		);
	}

	/**
	 * Hooks the settings screen into the admin.
	 */
	public static function register() {
		// After the Mux Videos menu it sits under, so the library stays the menu's first item.
		add_action( 'admin_menu', array( __CLASS__, 'add_page' ), 20 );
		add_action( 'admin_init', array( __CLASS__, 'register_fields' ) );
		add_action( 'admin_post_' . self::CREATE_KEY_ACTION, array( __CLASS__, 'handle_create_signing_key' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( VLFM_FILE ), array( __CLASS__, 'action_links' ) );
	}

	/**
	 * URL of the settings screen.
	 *
	 * @return string
	 */
	public static function page_url() {
		return admin_url( 'admin.php?page=' . self::PAGE );
	}

	/**
	 * Adds "Settings" and "Library" links to the plugin row.
	 *
	 * @param array $links Existing links.
	 * @return array
	 */
	public static function action_links( $links ) {
		array_unshift(
			$links,
			sprintf( '<a href="%s">%s</a>', esc_url( self::page_url() ), esc_html__( 'Settings', 'videoteca-for-mux' ) ),
			sprintf( '<a href="%s">%s</a>', esc_url( Admin::page_url() ), esc_html__( 'Library', 'videoteca-for-mux' ) )
		);
		return $links;
	}

	/**
	 * Adds Mux Videos → Settings.
	 */
	public static function add_page() {
		$hook = add_submenu_page(
			Admin::PAGE,
			__( 'Mux Video settings', 'videoteca-for-mux' ),
			__( 'Settings', 'videoteca-for-mux' ),
			'manage_options',
			self::PAGE,
			array( __CLASS__, 'render_page' )
		);

		add_action(
			'load-' . $hook,
			static function () {
				add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
			}
		);
	}

	/**
	 * Loads the core color picker for the accent color field.
	 */
	public static function enqueue_assets() {
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wp-color-picker' );
		wp_add_inline_script( 'wp-color-picker', 'jQuery( function ( $ ) { $( ".vlfm-color-field" ).wpColorPicker(); } );' );
		wp_add_inline_style( 'wp-color-picker', '.vlfm-connection{display:flex;gap:4px;align-items:center;font-weight:600}.vlfm-connection.is-connected{color:#00a32a}.vlfm-connection.is-error{color:#d63638}' );
	}

	/**
	 * Registers the option, sections and fields.
	 */
	public static function register_fields() {
		register_setting(
			self::GROUP,
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => self::defaults(),
			)
		);

		add_settings_section(
			'vlfm_connection',
			__( 'Mux connection', 'videoteca-for-mux' ),
			array( __CLASS__, 'render_connection_section' ),
			self::PAGE
		);
		self::add_field( 'token_id', __( 'Access token ID', 'videoteca-for-mux' ), 'vlfm_connection', array( 'type' => 'text' ) );
		self::add_field( 'token_secret', __( 'Secret key', 'videoteca-for-mux' ), 'vlfm_connection', array( 'type' => 'secret' ) );

		add_settings_section(
			'vlfm_uploads',
			__( 'New uploads', 'videoteca-for-mux' ),
			array( __CLASS__, 'render_uploads_section' ),
			self::PAGE
		);
		self::add_field(
			'video_quality',
			__( 'Video quality', 'videoteca-for-mux' ),
			'vlfm_uploads',
			array(
				'type'        => 'select',
				'options'     => array(
					'basic'   => __( 'Basic', 'videoteca-for-mux' ),
					'plus'    => __( 'Plus', 'videoteca-for-mux' ),
					'premium' => __( 'Premium', 'videoteca-for-mux' ),
				),
				'description' => __( 'Mux charges differently for each quality level. Plus suits most sites.', 'videoteca-for-mux' ),
			)
		);
		self::add_field(
			'max_resolution_tier',
			__( 'Maximum resolution', 'videoteca-for-mux' ),
			'vlfm_uploads',
			array(
				'type'    => 'select',
				'options' => array(
					'1080p' => '1080p',
					'1440p' => '1440p (2K)',
					'2160p' => '2160p (4K)',
				),
			)
		);
		self::add_field(
			'captions_language',
			__( 'Auto-generated captions', 'videoteca-for-mux' ),
			'vlfm_uploads',
			array(
				'type'        => 'select',
				'options'     => array( '' => __( 'Off', 'videoteca-for-mux' ) ) + self::caption_languages(),
				'description' => __( 'Mux transcribes the spoken language and adds a captions track to each new video.', 'videoteca-for-mux' ),
			)
		);
		self::add_field(
			'static_renditions',
			__( 'Downloadable file', 'videoteca-for-mux' ),
			'vlfm_uploads',
			array(
				'type'        => 'select',
				'options'     => array(
					''           => __( 'None', 'videoteca-for-mux' ),
					'highest'    => __( 'MP4 at the highest resolution', 'videoteca-for-mux' ),
					'audio-only' => __( 'Audio only (M4A)', 'videoteca-for-mux' ),
				),
				'description' => __( 'Creates a static file next to the stream, for downloads or places that cannot play streams.', 'videoteca-for-mux' ),
			)
		);

		add_settings_section(
			'vlfm_playback',
			__( 'Playback', 'videoteca-for-mux' ),
			'__return_false',
			self::PAGE
		);
		self::add_field(
			'playback_policy',
			__( 'Playback access', 'videoteca-for-mux' ),
			'vlfm_playback',
			array(
				'type'    => 'radio',
				'options' => array(
					'public' => __( 'Public: anyone with the playback ID can watch.', 'videoteca-for-mux' ),
					'signed' => __( 'Signed: the site issues short-lived tokens, so videos only play where you embed them.', 'videoteca-for-mux' ),
				),
			)
		);
		self::add_field( 'signing_key_id', __( 'Signing key ID', 'videoteca-for-mux' ), 'vlfm_playback', array( 'type' => 'signing_key' ) );
		self::add_field(
			'signing_key_private',
			__( 'Signing private key', 'videoteca-for-mux' ),
			'vlfm_playback',
			array(
				'type'        => 'secret',
				'description' => __( 'The base64-encoded private key Mux shows once, when the key is created.', 'videoteca-for-mux' ),
			)
		);
		self::add_field(
			'token_ttl_hours',
			__( 'Token lifetime', 'videoteca-for-mux' ),
			'vlfm_playback',
			array(
				'type'        => 'number',
				'min'         => 1,
				'max'         => 720,
				'suffix'      => __( 'hours', 'videoteca-for-mux' ),
				'description' => __( 'How long a signed page stays playable. Keep it longer than your page cache lifetime.', 'videoteca-for-mux' ),
			)
		);
		self::add_field(
			'accent_color',
			__( 'Player accent color', 'videoteca-for-mux' ),
			'vlfm_playback',
			array( 'type' => 'color' )
		);
		self::add_field(
			'analytics',
			__( 'Viewer analytics', 'videoteca-for-mux' ),
			'vlfm_playback',
			array(
				'type'        => 'checkbox',
				'label'       => __( 'Send playback quality data to Mux Data', 'videoteca-for-mux' ),
				'description' => __( 'Off by default. When on, Mux Player reports start-up time, buffering and errors, with the viewer\'s browser, device and approximate location. Turn it on only if your privacy policy covers it.', 'videoteca-for-mux' ),
			)
		);
		self::add_field(
			'analytics_cookies',
			__( 'Analytics cookies', 'videoteca-for-mux' ),
			'vlfm_playback',
			array(
				'type'        => 'checkbox',
				'label'       => __( 'Let Mux Data set a cookie to recognize returning viewers', 'videoteca-for-mux' ),
				'description' => __( 'Leave this off unless your cookie notice covers it.', 'videoteca-for-mux' ),
			)
		);

		add_settings_section(
			'vlfm_webhooks',
			__( 'Webhooks', 'videoteca-for-mux' ),
			array( __CLASS__, 'render_webhooks_section' ),
			self::PAGE
		);
		self::add_field( 'webhook_url', __( 'Webhook URL', 'videoteca-for-mux' ), 'vlfm_webhooks', array( 'type' => 'webhook_url' ) );
		self::add_field( 'webhook_secret', __( 'Signing secret', 'videoteca-for-mux' ), 'vlfm_webhooks', array( 'type' => 'secret' ) );

		add_settings_section(
			'vlfm_data',
			__( 'Data', 'videoteca-for-mux' ),
			'__return_false',
			self::PAGE
		);
		self::add_field(
			'delete_data_on_uninstall',
			__( 'Uninstall', 'videoteca-for-mux' ),
			'vlfm_data',
			array(
				'type'        => 'checkbox',
				'label'       => __( 'Remove the video library and these settings when the plugin is deleted', 'videoteca-for-mux' ),
				'description' => __( 'Your videos stay in your Mux account either way.', 'videoteca-for-mux' ),
			)
		);
	}

	/**
	 * Registers one field with the shared renderer.
	 *
	 * @param string $key     Setting name.
	 * @param string $label   Field label.
	 * @param string $section Section ID.
	 * @param array  $args    Renderer arguments.
	 */
	private static function add_field( $key, $label, $section, $args ) {
		$args['key'] = $key;
		if ( ! in_array( $args['type'], array( 'radio', 'checkbox', 'webhook_url' ), true ) ) {
			$args['label_for'] = 'vlfm-' . $key;
		}
		add_settings_field( 'vlfm-' . $key, $label, array( __CLASS__, 'render_field' ), self::PAGE, $section, $args );
	}

	/**
	 * Renders the settings screen.
	 */
	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Mux Video settings', 'videoteca-for-mux' ); ?></h1>
			<?php
			// WordPress only shows these by itself on screens under Settings.
			settings_errors();
			?>
			<form action="options.php" method="post">
				<?php
				settings_fields( self::GROUP );
				do_settings_sections( self::PAGE );
				submit_button();
				?>
			</form>
			<form id="vlfm-create-key-form" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::CREATE_KEY_ACTION ); ?>" />
				<?php wp_nonce_field( self::CREATE_KEY_ACTION ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Intro and status for the connection section.
	 */
	public static function render_connection_section() {
		printf(
			'<p>%s</p>',
			wp_kses(
				sprintf(
					/* translators: %s: URL of the Mux access tokens page. */
					__( 'Create an access token in your <a href="%s" target="_blank" rel="noopener noreferrer">Mux dashboard</a> with <strong>Mux Video: Read and Write</strong> permission. Add <strong>System: Read and Write</strong> too if you want this page to create signing keys for you.', 'videoteca-for-mux' ),
					'https://dashboard.mux.com/settings/access-tokens'
				),
				array(
					'a'      => array(
						'href'   => array(),
						'target' => array(),
						'rel'    => array(),
					),
					'strong' => array(),
				)
			)
		);

		if ( ! self::is_configured() ) {
			return;
		}

		$status = get_option( self::STATUS_OPTION );
		if ( ! is_array( $status ) ) {
			return;
		}

		if ( ! empty( $status['ok'] ) ) {
			printf(
				'<p class="vlfm-connection is-connected"><span class="dashicons dashicons-yes-alt" aria-hidden="true"></span> %s</p>',
				esc_html__( 'Connected to Mux.', 'videoteca-for-mux' )
			);
		} else {
			printf(
				'<p class="vlfm-connection is-error"><span class="dashicons dashicons-warning" aria-hidden="true"></span> %s</p>',
				esc_html( $status['message'] )
			);
		}
	}

	/**
	 * Intro for the uploads section.
	 */
	public static function render_uploads_section() {
		printf(
			'<p>%s</p>',
			esc_html__( 'These apply to videos uploaded or imported from now on. Existing videos keep the settings they were created with.', 'videoteca-for-mux' )
		);
	}

	/**
	 * Intro for the webhooks section.
	 */
	public static function render_webhooks_section() {
		printf(
			'<p>%s</p>',
			wp_kses(
				sprintf(
					/* translators: %s: URL of the Mux webhooks settings page. */
					__( 'Optional. The library checks on new uploads while you have it open. A webhook lets Mux tell the site when a video is ready even after you close the page. Add the URL below in the <a href="%s" target="_blank" rel="noopener noreferrer">Mux webhook settings</a>, then paste the signing secret Mux gives you. The site turns webhooks away until the secret is saved.', 'videoteca-for-mux' ),
					'https://dashboard.mux.com/settings/webhooks'
				),
				array(
					'a' => array(
						'href'   => array(),
						'target' => array(),
						'rel'    => array(),
					),
				)
			)
		);
	}

	/**
	 * Shared field renderer.
	 *
	 * @param array $args Field arguments.
	 */
	public static function render_field( $args ) {
		$key      = $args['key'];
		$id       = 'vlfm-' . $key;
		$name     = self::OPTION . '[' . $key . ']';
		$settings = self::all();
		$value    = isset( $settings[ $key ] ) ? $settings[ $key ] : '';

		switch ( $args['type'] ) {
			case 'secret':
				$is_set = '' !== $value;
				printf(
					'<input type="password" id="%1$s" name="%2$s" value="" class="regular-text" autocomplete="new-password" spellcheck="false" placeholder="%3$s" />',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( $is_set ? __( 'Saved. Leave blank to keep it.', 'videoteca-for-mux' ) : '' )
				);
				break;

			case 'select':
				printf( '<select id="%1$s" name="%2$s">', esc_attr( $id ), esc_attr( $name ) );
				foreach ( $args['options'] as $option => $label ) {
					printf( '<option value="%1$s"%2$s>%3$s</option>', esc_attr( $option ), selected( $value, $option, false ), esc_html( $label ) );
				}
				echo '</select>';
				break;

			case 'radio':
				echo '<fieldset>';
				foreach ( $args['options'] as $option => $label ) {
					printf(
						'<label><input type="radio" name="%1$s" value="%2$s"%3$s /> %4$s</label><br />',
						esc_attr( $name ),
						esc_attr( $option ),
						checked( $value, $option, false ),
						esc_html( $label )
					);
				}
				echo '</fieldset>';
				break;

			case 'checkbox':
				printf(
					'<label><input type="checkbox" name="%1$s" value="1"%2$s /> %3$s</label>',
					esc_attr( $name ),
					checked( (bool) $value, true, false ),
					esc_html( $args['label'] )
				);
				break;

			case 'number':
				printf(
					'<input type="number" id="%1$s" name="%2$s" value="%3$s" min="%4$d" max="%5$d" class="small-text" /> %6$s',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( $value ),
					(int) $args['min'],
					(int) $args['max'],
					esc_html( $args['suffix'] )
				);
				break;

			case 'color':
				printf(
					'<input type="text" id="%1$s" name="%2$s" value="%3$s" class="vlfm-color-field" />',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( $value )
				);
				break;

			case 'signing_key':
				printf(
					'<input type="text" id="%1$s" name="%2$s" value="%3$s" class="regular-text code" spellcheck="false" />',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( $value )
				);
				if ( self::is_configured() ) {
					printf(
						' <button type="submit" form="vlfm-create-key-form" class="button">%s</button>',
						esc_html__( 'Create a signing key', 'videoteca-for-mux' )
					);
				}
				printf(
					'<p class="description">%s</p>',
					esc_html__( 'Needed for signed playback. Creating a key here fills in both fields and saves them.', 'videoteca-for-mux' )
				);
				break;

			case 'webhook_url':
				printf(
					'<code>%s</code><p class="description">%s</p>',
					esc_html( rest_url( Rest_Controller::ROUTE_NAMESPACE . '/webhook' ) ),
					esc_html__( 'Mux needs to reach this address, so webhooks will not arrive on a local or password-protected site.', 'videoteca-for-mux' )
				);
				break;

			default:
				printf(
					'<input type="text" id="%1$s" name="%2$s" value="%3$s" class="regular-text code" spellcheck="false" />',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( $value )
				);
		}

		if ( ! empty( $args['description'] ) && 'signing_key' !== $args['type'] ) {
			printf( '<p class="description">%s</p>', esc_html( $args['description'] ) );
		}
	}

	/**
	 * Sanitizes the submitted settings and checks the connection when the credentials change.
	 *
	 * @param mixed $input Submitted values.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$input    = is_array( $input ) ? $input : array(); // options.php has already unslashed it.
		$previous = self::all();
		$defaults = self::defaults();
		$clean    = array();

		$clean['token_id'] = isset( $input['token_id'] ) ? sanitize_text_field( $input['token_id'] ) : $previous['token_id'];

		foreach ( self::SECRET_FIELDS as $secret ) {
			$submitted        = isset( $input[ $secret ] ) ? trim( (string) $input[ $secret ] ) : '';
			$clean[ $secret ] = '' === $submitted ? $previous[ $secret ] : self::sanitize_secret( $submitted );
		}

		$clean['video_quality']       = self::pick( $input, 'video_quality', array( 'basic', 'plus', 'premium' ), $defaults );
		$clean['max_resolution_tier'] = self::pick( $input, 'max_resolution_tier', array( '1080p', '1440p', '2160p' ), $defaults );
		$clean['captions_language']   = self::pick( $input, 'captions_language', array_merge( array( '' ), array_keys( self::caption_languages() ) ), $defaults );
		$clean['static_renditions']   = self::pick( $input, 'static_renditions', array( '', 'highest', 'audio-only' ), $defaults );
		$clean['playback_policy']     = self::pick( $input, 'playback_policy', array( 'public', 'signed' ), $defaults );

		$clean['signing_key_id']  = isset( $input['signing_key_id'] ) ? sanitize_text_field( $input['signing_key_id'] ) : $previous['signing_key_id'];
		$clean['token_ttl_hours'] = isset( $input['token_ttl_hours'] ) ? min( 720, max( 1, absint( $input['token_ttl_hours'] ) ) ) : $defaults['token_ttl_hours'];
		$clean['accent_color']    = isset( $input['accent_color'] ) ? (string) sanitize_hex_color( $input['accent_color'] ) : '';

		$clean['analytics']                = ! empty( $input['analytics'] );
		$clean['analytics_cookies']        = ! empty( $input['analytics_cookies'] );
		$clean['delete_data_on_uninstall'] = ! empty( $input['delete_data_on_uninstall'] );

		if ( '' !== $clean['token_id'] && '' !== $clean['token_secret'] ) {
			self::check_connection( $clean['token_id'], $clean['token_secret'] );
		} else {
			delete_option( self::STATUS_OPTION );
		}

		if ( 'signed' === $clean['playback_policy'] && ( '' === $clean['signing_key_id'] || '' === $clean['signing_key_private'] ) ) {
			add_settings_error(
				self::OPTION,
				'vlfm_missing_signing_key',
				__( 'Signed playback needs a signing key. Until you add one, new uploads get public playback IDs.', 'videoteca-for-mux' ),
				'warning'
			);
		}

		return $clean;
	}

	/**
	 * Keeps a secret as typed, minus whitespace and control characters.
	 *
	 * @param string $value Submitted secret.
	 * @return string
	 */
	private static function sanitize_secret( $value ) {
		return (string) preg_replace( '/[^\x21-\x7E]/', '', $value );
	}

	/**
	 * Picks an allowed value from the submission, or the default.
	 *
	 * @param array  $input    Submitted values.
	 * @param string $key      Setting name.
	 * @param array  $allowed  Allowed values.
	 * @param array  $defaults Defaults.
	 * @return string
	 */
	private static function pick( $input, $key, $allowed, $defaults ) {
		$value = isset( $input[ $key ] ) ? (string) $input[ $key ] : $defaults[ $key ];
		return in_array( $value, $allowed, true ) ? $value : $defaults[ $key ];
	}

	/**
	 * Calls Mux with the given credentials and records the outcome.
	 *
	 * @param string $token_id     Access token ID.
	 * @param string $token_secret Secret key.
	 */
	private static function check_connection( $token_id, $token_secret ) {
		static $checked = array();

		// The sanitize callback can run twice on the first save; only call Mux once.
		$fingerprint = md5( $token_id . ':' . $token_secret );
		if ( isset( $checked[ $fingerprint ] ) ) {
			return;
		}
		$checked[ $fingerprint ] = true;

		$client = new Mux_Client( $token_id, $token_secret );
		$result = $client->list_assets( array( 'limit' => 1 ) );

		if ( is_wp_error( $result ) ) {
			$status = array(
				'ok'      => false,
				'message' => sprintf(
					/* translators: %s: error message from Mux. */
					__( 'Mux did not accept these credentials: %s', 'videoteca-for-mux' ),
					$result->get_error_message()
				),
			);
			add_settings_error( self::OPTION, 'vlfm_connection_failed', $status['message'], 'error' );
		} else {
			$status = array(
				'ok'      => true,
				'message' => '',
			);
		}

		update_option( self::STATUS_OPTION, $status + array( 'checked' => time() ), false );
	}

	/**
	 * Creates a Mux signing key and saves it (admin-post handler).
	 */
	public static function handle_create_signing_key() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to change these settings.', 'videoteca-for-mux' ), 403 );
		}
		check_admin_referer( self::CREATE_KEY_ACTION );

		$client = Mux_Client::from_settings();
		$key    = $client ? $client->create_signing_key() : new \WP_Error( 'vlfm_not_configured', __( 'Save your Mux access token first.', 'videoteca-for-mux' ) );

		if ( is_wp_error( $key ) ) {
			add_settings_error(
				self::OPTION,
				'vlfm_signing_key_failed',
				sprintf(
					/* translators: %s: error message from Mux. */
					__( 'Mux could not create a signing key: %s Check that the access token has System read and write permission.', 'videoteca-for-mux' ),
					$key->get_error_message()
				),
				'error'
			);
		} elseif ( empty( $key['id'] ) || empty( $key['private_key'] ) ) {
			add_settings_error( self::OPTION, 'vlfm_signing_key_failed', __( 'Mux returned an incomplete signing key. Try again.', 'videoteca-for-mux' ), 'error' );
		} else {
			$settings                        = self::all();
			$settings['signing_key_id']      = sanitize_text_field( $key['id'] );
			$settings['signing_key_private'] = self::sanitize_secret( $key['private_key'] );

			// Store directly: running the sanitize callback again would re-check the connection.
			remove_filter( 'sanitize_option_' . self::OPTION, array( __CLASS__, 'sanitize' ) );
			update_option( self::OPTION, $settings );

			add_settings_error( self::OPTION, 'vlfm_signing_key_created', __( 'Signing key created and saved.', 'videoteca-for-mux' ), 'success' );
		}

		set_transient( 'settings_errors', get_settings_errors(), 30 );
		wp_safe_redirect( add_query_arg( 'settings-updated', 'true', self::page_url() ) );
		exit;
	}
}
