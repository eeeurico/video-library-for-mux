<?php
/**
 * REST API used by the library screen and the block editor.
 *
 * @package VideoLibraryForMux
 */

namespace VideoLibraryForMux;

use WP_Error;
use WP_REST_Request;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Routes under /wp-json/vlfm/v1.
 */
final class Rest_Controller {

	const ROUTE_NAMESPACE = 'vlfm/v1';

	/**
	 * Registers the routes.
	 */
	public static function register_routes() {
		$id_arg = array(
			'id' => array(
				'type'     => 'integer',
				'required' => true,
			),
		);

		register_rest_route(
			self::ROUTE_NAMESPACE,
			'/videos',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'list_videos' ),
				'permission_callback' => array( __CLASS__, 'can_browse' ),
				'args'                => array(
					'search'   => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'status'   => array(
						'type'    => 'string',
						'enum'    => array( '', 'ready', 'processing', 'errored' ),
						'default' => '',
					),
					'page'     => array(
						'type'    => 'integer',
						'minimum' => 1,
						'default' => 1,
					),
					'per_page' => array(
						'type'    => 'integer',
						'minimum' => 1,
						'maximum' => 100,
						'default' => 40,
					),
					'orderby'  => array(
						'type'    => 'string',
						'enum'    => array( 'date', 'title' ),
						'default' => 'date',
					),
					'order'    => array(
						'type'    => 'string',
						'enum'    => array( 'asc', 'desc' ),
						'default' => 'desc',
					),
				),
			)
		);

		register_rest_route(
			self::ROUTE_NAMESPACE,
			'/videos/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'get_video' ),
					'permission_callback' => array( __CLASS__, 'can_browse' ),
					'args'                => $id_arg,
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( __CLASS__, 'update_video' ),
					'permission_callback' => array( __CLASS__, 'can_edit_video' ),
					'args'                => $id_arg + array(
						'title'          => array(
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'description'    => array(
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_textarea_field',
						),
						'thumbnail_time' => array(
							'type'    => array( 'number', 'null' ),
							'minimum' => 0,
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( __CLASS__, 'delete_video' ),
					'permission_callback' => array( __CLASS__, 'can_delete_video' ),
					'args'                => $id_arg + array(
						'delete_from_mux' => array(
							'type'    => 'boolean',
							'default' => false,
						),
					),
				),
			)
		);

		register_rest_route(
			self::ROUTE_NAMESPACE,
			'/videos/(?P<id>\d+)/refresh',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'refresh_video' ),
				'permission_callback' => array( __CLASS__, 'can_browse' ),
				'args'                => $id_arg,
			)
		);

		register_rest_route(
			self::ROUTE_NAMESPACE,
			'/uploads',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'create_upload' ),
				'permission_callback' => array( __CLASS__, 'can_upload' ),
				'args'                => array(
					'filename' => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'title'    => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		register_rest_route(
			self::ROUTE_NAMESPACE,
			'/imports',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'import_url' ),
				'permission_callback' => array( __CLASS__, 'can_upload' ),
				'args'                => array(
					'url'   => array(
						'type'     => 'string',
						'format'   => 'uri',
						'required' => true,
					),
					'title' => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		register_rest_route(
			self::ROUTE_NAMESPACE,
			'/sync',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'sync' ),
				'permission_callback' => array( __CLASS__, 'can_sync' ),
				'args'                => array(
					'next' => array(
						'type'              => array( 'string', 'null' ),
						'default'           => '',
						'sanitize_callback' => static function ( $value ) {
							return sanitize_text_field( (string) $value );
						},
					),
				),
			)
		);

		register_rest_route(
			self::ROUTE_NAMESPACE,
			'/webhook',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( Webhooks::class, 'handle' ),
				// Mux calls this without a WordPress login. Webhooks::handle() checks
				// the signature and only acts on state it reads back from Mux.
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Browsing and inserting videos: anyone who can write posts.
	 *
	 * @return bool
	 */
	public static function can_browse() {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Uploading or importing: same as the media library.
	 *
	 * @return bool
	 */
	public static function can_upload() {
		return current_user_can( 'upload_files' );
	}

	/**
	 * Importing the whole Mux account: administrators.
	 *
	 * @return bool
	 */
	public static function can_sync() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Editing one video.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool
	 */
	public static function can_edit_video( WP_REST_Request $request ) {
		return current_user_can( 'edit_post', (int) $request['id'] );
	}

	/**
	 * Deleting one video.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool
	 */
	public static function can_delete_video( WP_REST_Request $request ) {
		return current_user_can( 'delete_post', (int) $request['id'] );
	}

	/**
	 * The error returned while no access token is saved.
	 *
	 * @return WP_Error
	 */
	public static function not_configured_error() {
		return new WP_Error(
			'vlfm_not_configured',
			__( 'Connect a Mux account in Mux Videos → Settings first.', 'video-library-for-mux' ),
			array( 'status' => 409 )
		);
	}

	/**
	 * GET /videos
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public static function list_videos( WP_REST_Request $request ) {
		$query = Videos::query(
			array(
				'search'   => trim( $request['search'] ),
				'status'   => $request['status'],
				'page'     => $request['page'],
				'per_page' => $request['per_page'],
				'orderby'  => $request['orderby'],
				'order'    => strtoupper( $request['order'] ),
			)
		);

		$response = rest_ensure_response( array_map( array( Videos::class, 'to_array' ), $query->posts ) );
		$response->header( 'X-WP-Total', (string) (int) $query->found_posts );
		$response->header( 'X-WP-TotalPages', (string) (int) $query->max_num_pages );
		return $response;
	}

	/**
	 * GET /videos/{id}
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public static function get_video( WP_REST_Request $request ) {
		$post = self::find_video( $request['id'] );
		return is_wp_error( $post ) ? $post : rest_ensure_response( Videos::to_array( $post ) );
	}

	/**
	 * POST /videos/{id}
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public static function update_video( WP_REST_Request $request ) {
		$post = self::find_video( $request['id'] );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$fields = array();
		if ( null !== $request['title'] ) {
			if ( '' === trim( $request['title'] ) ) {
				return new WP_Error( 'vlfm_empty_title', __( 'Give the video a title.', 'video-library-for-mux' ), array( 'status' => 400 ) );
			}
			$fields['title'] = $request['title'];
		}
		if ( null !== $request['description'] ) {
			$fields['description'] = $request['description'];
		}
		if ( $request->has_param( 'thumbnail_time' ) ) {
			$fields['thumbnail_time'] = null === $request['thumbnail_time'] ? null : (float) $request['thumbnail_time'];
		}

		$result = Videos::update( $post->ID, $fields );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( Videos::to_array( get_post( $post->ID ) ) );
	}

	/**
	 * DELETE /videos/{id}
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public static function delete_video( WP_REST_Request $request ) {
		$post = self::find_video( $request['id'] );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$result = Videos::delete( $post->ID, (bool) $request['delete_from_mux'] );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response(
			array(
				'deleted' => true,
				'id'      => $post->ID,
			)
		);
	}

	/**
	 * POST /videos/{id}/refresh
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public static function refresh_video( WP_REST_Request $request ) {
		$post = self::find_video( $request['id'] );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$result = Videos::refresh( $post->ID );
		if ( is_wp_error( $result ) && ! Videos::is_not_found( $result ) ) {
			return $result;
		}

		return rest_ensure_response( Videos::to_array( get_post( $post->ID ) ) );
	}

	/**
	 * POST /uploads: creates the library entry and a Mux direct upload URL.
	 *
	 * The browser then sends the file straight to Mux, so PHP upload limits do not apply.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public static function create_upload( WP_REST_Request $request ) {
		$client = Mux_Client::from_settings();
		if ( ! $client ) {
			return self::not_configured_error();
		}

		$filename = $request['filename'];
		$title    = '' !== trim( $request['title'] ) ? $request['title'] : Videos::title_from_filename( $filename );

		$post_id = Videos::create(
			array(
				'title'    => $title,
				'filename' => $filename,
				'source'   => 'upload',
				'status'   => 'uploading',
			)
		);
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		$upload = $client->create_upload(
			array(
				'cors_origin'        => self::cors_origin(),
				'timeout'            => DAY_IN_SECONDS,
				'new_asset_settings' => Videos::new_asset_settings( $post_id, $title ),
			)
		);

		if ( is_wp_error( $upload ) || empty( $upload['id'] ) || empty( $upload['url'] ) ) {
			wp_delete_post( $post_id, true );
			return is_wp_error( $upload ) ? $upload : new WP_Error( 'vlfm_upload_failed', __( 'Mux did not return an upload address.', 'video-library-for-mux' ), array( 'status' => 502 ) );
		}

		Videos::set_meta( $post_id, Videos::META_UPLOAD_ID, (string) $upload['id'] );

		return rest_ensure_response(
			array(
				'video'      => Videos::to_array( get_post( $post_id ) ),
				'upload_url' => esc_url_raw( $upload['url'] ),
			)
		);
	}

	/**
	 * POST /imports: asks Mux to fetch a video from a URL.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public static function import_url( WP_REST_Request $request ) {
		$client = Mux_Client::from_settings();
		if ( ! $client ) {
			return self::not_configured_error();
		}

		$url    = esc_url_raw( trim( $request['url'] ), array( 'http', 'https' ) );
		$scheme = wp_parse_url( $url, PHP_URL_SCHEME );
		if ( '' === $url || ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
			return new WP_Error( 'vlfm_invalid_url', __( 'Enter a web address that starts with http:// or https://.', 'video-library-for-mux' ), array( 'status' => 400 ) );
		}

		$path     = (string) wp_parse_url( $url, PHP_URL_PATH );
		$filename = sanitize_text_field( rawurldecode( wp_basename( $path ) ) );
		$title    = '' !== trim( $request['title'] ) ? $request['title'] : Videos::title_from_filename( $filename );

		$post_id = Videos::create(
			array(
				'title'    => $title,
				'filename' => $filename,
				'source'   => 'url',
				'status'   => 'preparing',
			)
		);
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		$settings              = Videos::new_asset_settings( $post_id, $title );
		$first_input           = isset( $settings['inputs'][0] ) ? $settings['inputs'][0] : array();
		$settings['inputs'][0] = array( 'url' => $url ) + $first_input;

		$asset = $client->create_asset( $settings );
		if ( is_wp_error( $asset ) || empty( $asset['id'] ) ) {
			wp_delete_post( $post_id, true );
			return is_wp_error( $asset ) ? $asset : new WP_Error( 'vlfm_import_failed', __( 'Mux did not create the video.', 'video-library-for-mux' ), array( 'status' => 502 ) );
		}

		Videos::apply_asset( $post_id, $asset );

		return rest_ensure_response( Videos::to_array( get_post( $post_id ) ) );
	}

	/**
	 * POST /sync: imports one page of assets from the Mux account.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public static function sync( WP_REST_Request $request ) {
		$result = Videos::import_page( (string) $request['next'] );
		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}

	/**
	 * A library video by ID.
	 *
	 * @param int $id Post ID.
	 * @return \WP_Post|WP_Error
	 */
	private static function find_video( $id ) {
		$post = get_post( (int) $id );
		if ( ! Videos::is_video( $post ) ) {
			return new WP_Error( 'vlfm_not_found', __( 'This video is not in the library.', 'video-library-for-mux' ), array( 'status' => 404 ) );
		}
		return $post;
	}

	/**
	 * The origin the browser uploads from, for the upload URL's CORS headers.
	 *
	 * @return string
	 */
	private static function cors_origin() {
		$origin = get_http_origin();
		if ( $origin && preg_match( '#^https?://[^/\s]+$#i', $origin ) ) {
			return $origin;
		}

		$admin = wp_parse_url( admin_url() );
		$port  = isset( $admin['port'] ) ? ':' . $admin['port'] : '';
		return $admin['scheme'] . '://' . $admin['host'] . $port;
	}
}
