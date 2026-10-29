<?php
/**
 * The Mux Video field type. Loaded by ACF::include_field_type() once ACF is ready.
 *
 * @package VideoLibraryForMux
 */

namespace VideoLibraryForMux;

defined( 'ABSPATH' ) || exit;

/**
 * Stores a library video's ID and returns the video in the chosen format.
 */
final class ACF_Field extends \acf_field {

	/**
	 * Describes the field type.
	 */
	public function initialize() {
		$this->name        = ACF::FIELD_TYPE;
		$this->label       = __( 'Mux Video', 'videoteca-for-mux' );
		$this->category    = 'content';
		$this->description = __( 'A video from the Mux video library. Editors choose one, upload a new one or import one from a URL, without leaving the screen.', 'videoteca-for-mux' );
		$this->defaults    = array( 'return_format' => 'array' );

		// The player format returns markup that is escaped as it is built, and
		// that wp_kses_post() would strip, as it does not know <mux-player>.
		$this->supports['escaping_html'] = true;
	}

	/**
	 * Settings shown when the field is added to a field group.
	 *
	 * @param array $field Field.
	 */
	public function render_field_settings( $field ) {
		acf_render_field_setting(
			$field,
			array(
				'label'        => __( 'Return Format', 'videoteca-for-mux' ),
				'instructions' => __( 'Video Array has the playback ID, thumbnail, captions and the tokens signed videos need. Player HTML is a Mux Player, ready to print.', 'videoteca-for-mux' ),
				'type'         => 'radio',
				'name'         => 'return_format',
				'layout'       => 'horizontal',
				'choices'      => array(
					'array'  => __( 'Video Array', 'videoteca-for-mux' ),
					'id'     => __( 'Video ID', 'videoteca-for-mux' ),
					'player' => __( 'Player HTML', 'videoteca-for-mux' ),
				),
			)
		);
	}

	/**
	 * The field on an edit screen: a hidden input, and the picker the script draws.
	 *
	 * @param array $field Field.
	 */
	public function render_field( $field ) {
		$video = Videos::get_public( ACF::video_id( $field['value'] ) );
		?>
		<div class="vlfm-acf-video" data-video="<?php echo esc_attr( wp_json_encode( $video ) ); ?>">
			<input type="hidden" name="<?php echo esc_attr( $field['name'] ); ?>" value="<?php echo esc_attr( $video ? $video['id'] : '' ); ?>" />
			<div class="vlfm-acf-video__app"></div>
		</div>
		<?php
	}

	/**
	 * The picker's script and styles. ACF loads every field type's assets up
	 * front, so fields that arrive later, in repeater rows or blocks, work too.
	 */
	public function input_admin_enqueue_scripts() {
		$asset_file = VLFM_DIR . 'build/acf/index.asset.php';
		if ( ! file_exists( $asset_file ) ) {
			return;
		}
		$asset = require $asset_file;

		wp_enqueue_script(
			'vlfm-acf',
			VLFM_URL . 'build/acf/index.js',
			array_merge( $asset['dependencies'], array( 'acf-input' ) ),
			$asset['version'],
			true
		);
		wp_set_script_translations( 'vlfm-acf', 'videoteca-for-mux' );
		wp_add_inline_script(
			'vlfm-acf',
			'window.vlfmAcf = ' . wp_json_encode(
				array(
					'isConfigured' => Settings::is_configured(),
					'canBrowse'    => Rest_Controller::can_browse(),
					'canUpload'    => Rest_Controller::can_upload(),
					'settingsUrl'  => current_user_can( 'manage_options' ) ? Settings::page_url() : '',
				)
			) . ';',
			'before'
		);

		Admin::enqueue_library_styles();
		wp_enqueue_style( 'vlfm-acf', VLFM_URL . 'build/acf/index.css', array( 'vlfm-library' ), $asset['version'] );
		wp_style_add_data( 'vlfm-acf', 'rtl', 'replace' );
	}

	/**
	 * Only library videos can be saved.
	 *
	 * @param bool|string $valid Whether the value is valid, or an error message.
	 * @param mixed       $value Submitted value.
	 * @param array       $field Field.
	 * @param string      $input Input name.
	 * @return bool|string
	 */
	public function validate_value( $valid, $value, $field, $input ) {
		if ( true !== $valid || empty( $value ) ) {
			// ACF has already checked required fields.
			return $valid;
		}
		if ( ! Videos::is_video( ACF::video_id( $value ) ) ) {
			return __( 'Choose a video from the video library.', 'videoteca-for-mux' );
		}
		return $valid;
	}

	/**
	 * Saves the video ID, or nothing when the value is not a library video.
	 *
	 * @param mixed      $value   Submitted value.
	 * @param int|string $post_id Where the value is saved.
	 * @param array      $field   Field.
	 * @return int|string
	 */
	public function update_value( $value, $post_id, $field ) {
		$id = ACF::video_id( $value );
		return Videos::is_video( $id ) ? $id : '';
	}

	/**
	 * What get_field() returns.
	 *
	 * @param mixed      $value       Saved video ID.
	 * @param int|string $post_id     Where the value is saved.
	 * @param array      $field       Field.
	 * @param bool       $escape_html Whether the caller needs HTML-safe output.
	 * @return array|int|string|false False when there is no video.
	 */
	public function format_value( $value, $post_id, $field, $escape_html = false ) {
		$id = ACF::video_id( $value );
		if ( ! $id ) {
			return false;
		}

		switch ( isset( $field['return_format'] ) ? $field['return_format'] : 'array' ) {
			case 'id':
				return Videos::is_video( $id ) ? $id : false;

			case 'player':
				if ( function_exists( 'is_graphql_request' ) && is_graphql_request() ) {
					// GraphQL resolves the MuxVideo type from the ID, and has no page to print a player on.
					return $id;
				}
				$player = Playback::embed( $id );
				return '' !== $player ? $player : false;

			default:
				$video = Videos::get_public( $id );
				return $video ? $video : false;
		}
	}

	/**
	 * The field in ACF's REST API: the video ID.
	 *
	 * @param array $field Field.
	 * @return array
	 */
	public function get_rest_schema( array $field ) {
		return array(
			'type'     => array( 'integer', 'null' ),
			'required' => ! empty( $field['required'] ),
		);
	}

	/**
	 * The value in ACF's REST API responses.
	 *
	 * @param mixed      $value   Saved video ID.
	 * @param int|string $post_id Where the value is saved.
	 * @param array      $field   Field.
	 * @return int|null
	 */
	public function format_value_for_rest( $value, $post_id, array $field ) {
		$id = ACF::video_id( $value );
		return Videos::is_video( $id ) ? $id : null;
	}
}
