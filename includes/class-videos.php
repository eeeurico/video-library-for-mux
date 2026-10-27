<?php
/**
 * The video library: a private post type that mirrors Mux assets.
 *
 * Each video is a post, so its title lives in WordPress and is searchable with
 * the usual tools. Mux gets a copy of the title in the asset's `meta.title`.
 *
 * @package VideoLibraryForMux
 */

namespace VideoLibraryForMux;

use WP_Error;
use WP_Post;
use WP_Query;

defined( 'ABSPATH' ) || exit;

/**
 * Stores, finds and updates library videos.
 */
final class Videos {

	const POST_TYPE = 'vlfm_video';

	const META_ASSET_ID       = '_vlfm_asset_id';
	const META_UPLOAD_ID      = '_vlfm_upload_id';
	const META_PLAYBACK_ID    = '_vlfm_playback_id';
	const META_POLICY         = '_vlfm_playback_policy';
	const META_STATUS         = '_vlfm_status';
	const META_ERROR          = '_vlfm_error';
	const META_FILENAME       = '_vlfm_filename';
	const META_SOURCE         = '_vlfm_source';
	const META_THUMBNAIL_TIME = '_vlfm_thumbnail_time';
	const META_ASSET          = '_vlfm_asset';

	/**
	 * Meta values the library search looks through, next to the title and description.
	 *
	 * @var string[]
	 */
	const SEARCHABLE_META = array( self::META_FILENAME, self::META_ASSET_ID, self::META_PLAYBACK_ID );

	/**
	 * Registers the post type. It has no screens of its own: the library page manages it.
	 */
	public static function register_post_type() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'          => __( 'Mux videos', 'video-library-for-mux' ),
					'singular_name' => __( 'Mux video', 'video-library-for-mux' ),
				),
				'public'              => false,
				'show_ui'             => false,
				'show_in_rest'        => false,
				'exclude_from_search' => true,
				'publicly_queryable'  => false,
				'rewrite'             => false,
				'query_var'           => false,
				'can_export'          => true,
				'delete_with_user'    => false,
				'supports'            => array( 'title', 'editor', 'author' ),
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
			)
		);
	}

	/**
	 * A title for a file name: the name without its extension.
	 *
	 * @param string $filename File name.
	 * @return string
	 */
	public static function title_from_filename( $filename ) {
		$title = preg_replace( '/\.[a-z0-9]{1,5}$/i', '', wp_basename( (string) $filename ) );
		$title = sanitize_text_field( $title );
		return '' !== $title ? $title : __( 'Untitled video', 'video-library-for-mux' );
	}

	/**
	 * Creates a library video.
	 *
	 * @param array $args {
	 *     Video details.
	 *
	 *     @type string $title    Title.
	 *     @type string $filename Original file name.
	 *     @type string $source   Where it came from: upload, url or mux.
	 *     @type string $status   Initial status.
	 *     @type int    $date     Unix timestamp to date the post with.
	 * }
	 * @return int|WP_Error Post ID.
	 */
	public static function create( array $args ) {
		$postarr = array(
			'post_type'   => self::POST_TYPE,
			'post_status' => 'publish',
			'post_title'  => $args['title'],
			'post_author' => get_current_user_id(),
			'meta_input'  => array(
				self::META_FILENAME => isset( $args['filename'] ) ? $args['filename'] : '',
				self::META_SOURCE   => $args['source'],
				self::META_STATUS   => isset( $args['status'] ) ? $args['status'] : 'preparing',
			),
		);

		if ( ! empty( $args['date'] ) ) {
			$postarr['post_date_gmt'] = gmdate( 'Y-m-d H:i:s', (int) $args['date'] );
			$postarr['post_date']     = get_date_from_gmt( $postarr['post_date_gmt'] );
		}

		// wp_insert_post() and the meta API both unslash their input.
		return wp_insert_post( wp_slash( $postarr ), true );
	}

	/**
	 * Settings for an asset created from an upload or URL, from the plugin settings.
	 *
	 * @param int    $post_id Library video ID.
	 * @param string $title   Title to give the asset.
	 * @return array
	 */
	public static function new_asset_settings( $post_id, $title ) {
		$settings = array(
			'playback_policies'   => array( Playback::policy_for_new_assets() ),
			'video_quality'       => Settings::get( 'video_quality' ),
			'max_resolution_tier' => Settings::get( 'max_resolution_tier' ),
			'meta'                => array(
				'title'       => self::mux_title( $title ),
				'external_id' => (string) $post_id,
			),
		);

		$language = Settings::get( 'captions_language' );
		if ( '' !== $language ) {
			$settings['inputs'] = array(
				array(
					'generated_subtitles' => array(
						array(
							'language_code' => $language,
							'name'          => self::caption_track_name( $language ),
						),
					),
				),
			);
		}

		$renditions = Settings::get( 'static_renditions' );
		if ( '' !== $renditions ) {
			$settings['static_renditions'] = array( array( 'resolution' => $renditions ) );
		}

		return $settings;
	}

	/**
	 * The label Mux Player shows for an auto-generated captions track.
	 *
	 * @param string $language Language code.
	 * @return string
	 */
	private static function caption_track_name( $language ) {
		if ( 'auto' === $language ) {
			return __( 'Captions (auto-generated)', 'video-library-for-mux' );
		}
		$languages = Settings::caption_languages();
		$label     = isset( $languages[ $language ] ) ? trim( str_replace( __( '(beta)', 'video-library-for-mux' ), '', $languages[ $language ] ) ) : $language;
		/* translators: %s: language name. */
		return sprintf( __( '%s (auto-generated)', 'video-library-for-mux' ), $label );
	}

	/**
	 * A title trimmed to the length Mux accepts.
	 *
	 * @param string $title Title.
	 * @return string
	 */
	private static function mux_title( $title ) {
		return function_exists( 'mb_substr' ) ? mb_substr( $title, 0, 512 ) : substr( $title, 0, 512 );
	}

	/**
	 * Finds the library video for a Mux asset.
	 *
	 * @param string $asset_id Asset ID.
	 * @return int Post ID, or 0.
	 */
	public static function find_by_asset_id( $asset_id ) {
		return self::find_by_meta( self::META_ASSET_ID, $asset_id );
	}

	/**
	 * Finds the library video for a Mux direct upload.
	 *
	 * @param string $upload_id Upload ID.
	 * @return int Post ID, or 0.
	 */
	public static function find_by_upload_id( $upload_id ) {
		return self::find_by_meta( self::META_UPLOAD_ID, $upload_id );
	}

	/**
	 * Finds the library video with a playback ID.
	 *
	 * @param string $playback_id Playback ID.
	 * @return int Post ID, or 0.
	 */
	public static function find_by_playback_id( $playback_id ) {
		return self::find_by_meta( self::META_PLAYBACK_ID, $playback_id );
	}

	/**
	 * Finds a video by an exact meta value.
	 *
	 * @param string $key   Meta key.
	 * @param string $value Meta value.
	 * @return int Post ID, or 0.
	 */
	private static function find_by_meta( $key, $value ) {
		if ( ! is_string( $value ) || '' === $value ) {
			return 0;
		}

		$ids = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'any',
				'fields'         => 'ids',
				'posts_per_page' => 1,
				'no_found_rows'  => true,
				'meta_key'       => $key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => $value, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);

		return $ids ? (int) $ids[0] : 0;
	}

	/**
	 * Whether a post is a library video.
	 *
	 * @param mixed $post Post or ID.
	 * @return bool
	 */
	public static function is_video( $post ) {
		$post = get_post( $post );
		return $post instanceof WP_Post && self::POST_TYPE === $post->post_type;
	}

	/**
	 * Writes a meta value, slashing it first because the meta API unslashes.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $key     Meta key.
	 * @param mixed  $value   Value.
	 */
	public static function set_meta( $post_id, $key, $value ) {
		update_post_meta( $post_id, $key, wp_slash( $value ) );
	}

	/**
	 * Records the state of a direct upload that has no asset yet.
	 *
	 * @param int   $post_id Library video ID.
	 * @param array $upload  Mux upload object.
	 */
	public static function apply_upload( $post_id, array $upload ) {
		if ( ! empty( $upload['asset_id'] ) ) {
			self::set_meta( $post_id, self::META_ASSET_ID, (string) $upload['asset_id'] );
			return;
		}

		$status = isset( $upload['status'] ) ? $upload['status'] : '';
		switch ( $status ) {
			case 'errored':
				$message = ! empty( $upload['error']['message'] ) ? (string) $upload['error']['message'] : __( 'Mux could not process the uploaded file.', 'video-library-for-mux' );
				self::mark_errored( $post_id, $message );
				break;
			case 'cancelled':
				self::mark_errored( $post_id, __( 'The upload was cancelled before it finished.', 'video-library-for-mux' ) );
				break;
			case 'timed_out':
				self::mark_errored( $post_id, __( 'The upload link expired before the file finished uploading.', 'video-library-for-mux' ) );
				break;
			default:
				self::set_meta( $post_id, self::META_STATUS, 'uploading' );
		}
	}

	/**
	 * Marks a video as failed.
	 *
	 * @param int    $post_id Library video ID.
	 * @param string $message What went wrong.
	 */
	private static function mark_errored( $post_id, $message ) {
		self::set_meta( $post_id, self::META_STATUS, 'errored' );
		self::set_meta( $post_id, self::META_ERROR, sanitize_text_field( $message ) );
	}

	/**
	 * Copies the state of a Mux asset onto a library video.
	 *
	 * @param int             $post_id Library video ID.
	 * @param array           $asset   Mux asset object.
	 * @param Mux_Client|null $client  When given, the asset's title in Mux is
	 *                                 brought in line with the library title.
	 */
	public static function apply_asset( $post_id, array $asset, $client = null ) {
		$status = isset( $asset['status'] ) && in_array( $asset['status'], array( 'preparing', 'ready', 'errored' ), true ) ? $asset['status'] : 'preparing';

		self::set_meta( $post_id, self::META_ASSET_ID, (string) $asset['id'] );
		self::set_meta( $post_id, self::META_STATUS, $status );
		self::set_meta( $post_id, self::META_ASSET, self::trim_asset( $asset ) );

		if ( ! empty( $asset['upload_id'] ) ) {
			self::set_meta( $post_id, self::META_UPLOAD_ID, (string) $asset['upload_id'] );
		}

		if ( 'errored' === $status ) {
			$messages = isset( $asset['errors']['messages'] ) ? (array) $asset['errors']['messages'] : array();
			self::set_meta( $post_id, self::META_ERROR, sanitize_text_field( $messages ? implode( ' ', $messages ) : __( 'Mux could not process this video.', 'video-library-for-mux' ) ) );
		} else {
			delete_post_meta( $post_id, self::META_ERROR );
		}

		$playback = Playback::pick_playback_id( isset( $asset['playback_ids'] ) ? (array) $asset['playback_ids'] : array() );
		if ( $playback ) {
			self::set_meta( $post_id, self::META_PLAYBACK_ID, $playback['id'] );
			self::set_meta( $post_id, self::META_POLICY, $playback['policy'] );
		}

		if ( $client ) {
			$title     = get_post_field( 'post_title', $post_id, 'raw' );
			$mux_title = isset( $asset['meta']['title'] ) ? (string) $asset['meta']['title'] : '';
			if ( '' !== $title && self::mux_title( $title ) !== $mux_title ) {
				self::push_title( $post_id, $client );
			}
		}
	}

	/**
	 * Keeps the parts of an asset the library uses.
	 *
	 * @param array $asset Mux asset object.
	 * @return array
	 */
	private static function trim_asset( array $asset ) {
		$keep    = array( 'id', 'status', 'duration', 'aspect_ratio', 'resolution_tier', 'max_resolution_tier', 'max_stored_resolution', 'video_quality', 'created_at', 'meta' );
		$trimmed = array_intersect_key( $asset, array_flip( $keep ) );

		$trimmed['tracks'] = array();
		if ( ! empty( $asset['tracks'] ) && is_array( $asset['tracks'] ) ) {
			foreach ( $asset['tracks'] as $track ) {
				$trimmed['tracks'][] = array_intersect_key(
					(array) $track,
					array_flip( array( 'id', 'type', 'text_type', 'text_source', 'language_code', 'name', 'status', 'max_width', 'max_height' ) )
				);
			}
		}

		$trimmed['static_renditions'] = array();
		if ( ! empty( $asset['static_renditions']['files'] ) && is_array( $asset['static_renditions']['files'] ) ) {
			foreach ( $asset['static_renditions']['files'] as $file ) {
				$trimmed['static_renditions'][] = array_intersect_key(
					(array) $file,
					array_flip( array( 'name', 'status', 'resolution', 'ext' ) )
				);
			}
		}

		return $trimmed;
	}

	/**
	 * Re-reads a video's upload and asset from Mux.
	 *
	 * @param int $post_id Library video ID.
	 * @return true|WP_Error
	 */
	public static function refresh( $post_id ) {
		$client = Mux_Client::from_settings();
		if ( ! $client ) {
			return Rest_Controller::not_configured_error();
		}

		$asset_id = (string) get_post_meta( $post_id, self::META_ASSET_ID, true );

		if ( '' === $asset_id ) {
			$upload_id = (string) get_post_meta( $post_id, self::META_UPLOAD_ID, true );
			if ( '' === $upload_id ) {
				return new WP_Error( 'vlfm_no_source', __( 'This video is not linked to anything in Mux.', 'video-library-for-mux' ), array( 'status' => 409 ) );
			}

			$upload = $client->get_upload( $upload_id );
			if ( is_wp_error( $upload ) ) {
				if ( self::is_not_found( $upload ) ) {
					self::mark_errored( $post_id, __( 'Mux no longer has this upload.', 'video-library-for-mux' ) );
				}
				return $upload;
			}

			self::apply_upload( $post_id, $upload );
			if ( empty( $upload['asset_id'] ) ) {
				return true;
			}
			$asset_id = (string) $upload['asset_id'];
		}

		$asset = $client->get_asset( $asset_id );
		if ( is_wp_error( $asset ) ) {
			if ( self::is_not_found( $asset ) ) {
				self::mark_errored( $post_id, __( 'This video no longer exists in Mux.', 'video-library-for-mux' ) );
			}
			return $asset;
		}

		self::apply_asset( $post_id, $asset, $client );
		return true;
	}

	/**
	 * Whether a client error is a 404.
	 *
	 * @param WP_Error $error Error.
	 * @return bool
	 */
	public static function is_not_found( WP_Error $error ) {
		$data = $error->get_error_data();
		return is_array( $data ) && isset( $data['status'] ) && 404 === (int) $data['status'];
	}

	/**
	 * Updates a video's title, description or thumbnail time.
	 *
	 * @param int   $post_id Library video ID.
	 * @param array $fields  Any of title, description, thumbnail_time (null resets it).
	 * @return true|WP_Error
	 */
	public static function update( $post_id, array $fields ) {
		$postarr = array( 'ID' => $post_id );
		if ( isset( $fields['title'] ) ) {
			$postarr['post_title'] = $fields['title'];
		}
		if ( isset( $fields['description'] ) ) {
			$postarr['post_content'] = $fields['description'];
		}

		if ( count( $postarr ) > 1 ) {
			$result = wp_update_post( wp_slash( $postarr ), true );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		if ( array_key_exists( 'thumbnail_time', $fields ) ) {
			if ( null === $fields['thumbnail_time'] ) {
				delete_post_meta( $post_id, self::META_THUMBNAIL_TIME );
			} else {
				self::set_meta( $post_id, self::META_THUMBNAIL_TIME, round( (float) $fields['thumbnail_time'], 2 ) );
			}
		}

		if ( isset( $fields['title'] ) ) {
			$client = Mux_Client::from_settings();
			if ( $client ) {
				// A failure here is not fatal: the next refresh retries it.
				self::push_title( $post_id, $client );
			}
		}

		return true;
	}

	/**
	 * Copies the library title to the Mux asset's metadata.
	 *
	 * @param int        $post_id Library video ID.
	 * @param Mux_Client $client  Client.
	 * @return true|WP_Error
	 */
	private static function push_title( $post_id, Mux_Client $client ) {
		$asset_id = (string) get_post_meta( $post_id, self::META_ASSET_ID, true );
		if ( '' === $asset_id ) {
			// The upload has not become an asset yet. The title goes over once it has.
			return true;
		}

		$asset = get_post_meta( $post_id, self::META_ASSET, true );
		$meta  = is_array( $asset ) && isset( $asset['meta'] ) && is_array( $asset['meta'] ) ? $asset['meta'] : array();

		$meta['title'] = self::mux_title( get_post_field( 'post_title', $post_id, 'raw' ) );
		if ( empty( $meta['external_id'] ) ) {
			$meta['external_id'] = (string) $post_id;
		}

		$updated = $client->update_asset( $asset_id, array( 'meta' => $meta ) );
		if ( is_wp_error( $updated ) ) {
			return $updated;
		}

		if ( is_array( $asset ) ) {
			$asset['meta'] = $meta;
			self::set_meta( $post_id, self::META_ASSET, $asset );
		}
		return true;
	}

	/**
	 * Deletes a library video, and optionally its Mux asset.
	 *
	 * @param int  $post_id         Library video ID.
	 * @param bool $delete_from_mux Also delete the asset in Mux.
	 * @return true|WP_Error
	 */
	public static function delete( $post_id, $delete_from_mux ) {
		$client    = Mux_Client::from_settings();
		$asset_id  = (string) get_post_meta( $post_id, self::META_ASSET_ID, true );
		$upload_id = (string) get_post_meta( $post_id, self::META_UPLOAD_ID, true );

		if ( $client && '' === $asset_id && '' !== $upload_id ) {
			// An unfinished upload is useless without its library entry.
			$client->cancel_upload( $upload_id );
		}

		if ( $delete_from_mux && '' !== $asset_id ) {
			if ( ! $client ) {
				return Rest_Controller::not_configured_error();
			}
			$deleted = $client->delete_asset( $asset_id );
			if ( is_wp_error( $deleted ) && ! self::is_not_found( $deleted ) ) {
				return $deleted;
			}
		}

		wp_delete_post( $post_id, true );
		return true;
	}

	/**
	 * Imports one page of assets from the Mux account.
	 *
	 * Assets already in the library are refreshed; their library titles are kept.
	 * New assets take their title from `meta.title` when Mux has one.
	 *
	 * @param string $next Opaque position from the previous page, or empty to start.
	 * @return array|WP_Error Counts and the position of the next page.
	 */
	public static function import_page( $next = '' ) {
		$client = Mux_Client::from_settings();
		if ( ! $client ) {
			return Rest_Controller::not_configured_error();
		}

		$limit = 100;
		$query = array( 'limit' => $limit );
		$page  = 1;
		if ( 0 === strpos( $next, 'page:' ) ) {
			$page          = max( 1, (int) substr( $next, 5 ) );
			$query['page'] = $page;
		} elseif ( '' !== $next ) {
			$query['cursor'] = $next;
		}

		$response = $client->list_assets( $query );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$assets  = isset( $response['data'] ) && is_array( $response['data'] ) ? $response['data'] : array();
		$created = 0;
		$updated = 0;

		foreach ( $assets as $asset ) {
			if ( empty( $asset['id'] ) ) {
				continue;
			}

			$post_id = self::find_by_asset_id( $asset['id'] );
			if ( ! $post_id && ! empty( $asset['upload_id'] ) ) {
				$post_id = self::find_by_upload_id( $asset['upload_id'] );
			}

			if ( $post_id ) {
				self::apply_asset( $post_id, $asset );
				++$updated;
				continue;
			}

			$created_at = isset( $asset['created_at'] ) ? (int) $asset['created_at'] : time();
			$title      = isset( $asset['meta']['title'] ) ? sanitize_text_field( $asset['meta']['title'] ) : '';
			if ( '' === $title ) {
				$title = sprintf(
					/* translators: %s: date the video was created in Mux. */
					__( 'Untitled video, %s', 'video-library-for-mux' ),
					wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $created_at )
				);
			}

			$post_id = self::create(
				array(
					'title'  => $title,
					'source' => 'mux',
					'date'   => $created_at,
				)
			);
			if ( is_wp_error( $post_id ) ) {
				return $post_id;
			}

			self::apply_asset( $post_id, $asset );
			++$created;
		}

		$next_position = null;
		if ( ! empty( $response['next_cursor'] ) ) {
			$next_position = (string) $response['next_cursor'];
		} elseif ( empty( $query['cursor'] ) && count( $assets ) === $limit ) {
			$next_position = 'page:' . ( $page + 1 );
		}

		return array(
			'checked' => count( $assets ),
			'created' => $created,
			'updated' => $updated,
			'next'    => $next_position,
		);
	}

	/**
	 * Queries the library.
	 *
	 * @param array $args search, status (ready|processing|uploading|preparing|errored), page, per_page,
	 *                    orderby (date|title), order, and optionally offset, which replaces page.
	 * @return WP_Query
	 */
	public static function query( array $args ) {
		$query_args = array(
			'post_type'              => self::POST_TYPE,
			'post_status'            => 'publish',
			'posts_per_page'         => $args['per_page'],
			'paged'                  => $args['page'],
			'orderby'                => $args['orderby'],
			'order'                  => $args['order'],
			'ignore_sticky_posts'    => true,
			'update_post_term_cache' => false,
		);

		if ( isset( $args['offset'] ) ) {
			$query_args['offset'] = (int) $args['offset'];
		}

		$statuses = array(
			'ready'      => array( 'ready' ),
			'processing' => array( 'uploading', 'preparing' ),
			'uploading'  => array( 'uploading' ),
			'preparing'  => array( 'preparing' ),
			'errored'    => array( 'errored' ),
		);
		if ( isset( $statuses[ $args['status'] ] ) ) {
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Filtering a small private library.
			$query_args['meta_query'] = array(
				array(
					'key'     => self::META_STATUS,
					'value'   => $statuses[ $args['status'] ],
					'compare' => 'IN',
				),
			);
		}

		if ( '' !== $args['search'] ) {
			$query_args['s']           = $args['search'];
			$query_args['vlfm_search'] = true;
		}

		return new WP_Query( $query_args );
	}

	/**
	 * Replaces the search clause of library queries.
	 *
	 * Every word has to appear in the title, the description, the original file
	 * name, the asset ID or the playback ID.
	 *
	 * @param string   $search Search SQL.
	 * @param WP_Query $query  Query.
	 * @return string
	 */
	public static function filter_posts_search( $search, $query ) {
		if ( ! $query->get( 'vlfm_search' ) ) {
			return $search;
		}

		global $wpdb;

		$words = preg_split( '/\s+/', trim( (string) $query->get( 's' ) ), -1, PREG_SPLIT_NO_EMPTY );
		$words = array_slice( array_unique( $words ), 0, 10 );
		if ( ! $words ) {
			return $search;
		}

		$meta_keys = "'" . implode( "','", array_map( 'esc_sql', self::SEARCHABLE_META ) ) . "'";
		$clauses   = array();

		foreach ( $words as $word ) {
			$like = '%' . $wpdb->esc_like( $word ) . '%';
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names and constant meta keys only.
			$clauses[] = $wpdb->prepare(
				"({$wpdb->posts}.post_title LIKE %s OR {$wpdb->posts}.post_content LIKE %s OR EXISTS (
					SELECT 1 FROM {$wpdb->postmeta} AS vlfm_meta
					WHERE vlfm_meta.post_id = {$wpdb->posts}.ID
					AND vlfm_meta.meta_key IN ({$meta_keys})
					AND vlfm_meta.meta_value LIKE %s
				))",
				$like,
				$like,
				$like
			);
			// phpcs:enable
		}

		return ' AND ' . implode( ' AND ', $clauses ) . ' ';
	}

	/**
	 * The shape the REST API and the scripts use.
	 *
	 * @param WP_Post $post Library video.
	 * @return array
	 */
	public static function to_array( WP_Post $post ) {
		$asset       = get_post_meta( $post->ID, self::META_ASSET, true );
		$asset       = is_array( $asset ) ? $asset : array();
		$status      = (string) get_post_meta( $post->ID, self::META_STATUS, true );
		$playback_id = (string) get_post_meta( $post->ID, self::META_PLAYBACK_ID, true );
		$policy      = (string) get_post_meta( $post->ID, self::META_POLICY, true );
		$thumb_time  = get_post_meta( $post->ID, self::META_THUMBNAIL_TIME, true );
		$thumb_time  = '' === $thumb_time ? null : (float) $thumb_time;
		$duration    = isset( $asset['duration'] ) ? (float) $asset['duration'] : null;

		$width    = null;
		$height   = null;
		$captions = array();
		foreach ( isset( $asset['tracks'] ) ? $asset['tracks'] : array() as $track ) {
			if ( 'video' === ( isset( $track['type'] ) ? $track['type'] : '' ) && null === $width ) {
				$width  = isset( $track['max_width'] ) ? (int) $track['max_width'] : null;
				$height = isset( $track['max_height'] ) ? (int) $track['max_height'] : null;
			}
			if ( 'text' === ( isset( $track['type'] ) ? $track['type'] : '' ) ) {
				$captions[] = array(
					'name'      => isset( $track['name'] ) ? $track['name'] : '',
					'language'  => isset( $track['language_code'] ) ? $track['language_code'] : '',
					'status'    => isset( $track['status'] ) ? $track['status'] : '',
					'generated' => isset( $track['text_source'] ) && 0 === strpos( $track['text_source'], 'generated' ),
				);
			}
		}

		$is_ready = 'ready' === $status && '' !== $playback_id;
		$media    = $is_ready ? Playback::media( $playback_id, $policy, $thumb_time, $duration ) : Playback::empty_media();

		$downloads = array();
		if ( $is_ready ) {
			foreach ( isset( $asset['static_renditions'] ) ? $asset['static_renditions'] : array() as $file ) {
				if ( isset( $file['status'], $file['name'] ) && 'ready' === $file['status'] ) {
					$url = Playback::download_url( $playback_id, $policy, $file['name'] );
					if ( $url ) {
						$downloads[] = array(
							'name' => $file['name'],
							'url'  => $url,
						);
					}
				}
			}
		}

		return array(
			'id'              => $post->ID,
			'title'           => $post->post_title,
			'description'     => $post->post_content,
			'filename'        => (string) get_post_meta( $post->ID, self::META_FILENAME, true ),
			'source'          => (string) get_post_meta( $post->ID, self::META_SOURCE, true ),
			'status'          => '' !== $status ? $status : 'preparing',
			'error'           => (string) get_post_meta( $post->ID, self::META_ERROR, true ),
			'date'            => get_post_time( 'c', true, $post ),
			'author'          => get_the_author_meta( 'display_name', $post->post_author ),
			'asset_id'        => (string) get_post_meta( $post->ID, self::META_ASSET_ID, true ),
			'playback_id'     => $playback_id,
			'playback_policy' => $policy,
			'playable'        => $media['playable'],
			'duration'        => $duration,
			'aspect_ratio'    => isset( $asset['aspect_ratio'] ) ? (string) $asset['aspect_ratio'] : null,
			'resolution'      => isset( $asset['resolution_tier'] ) ? (string) $asset['resolution_tier'] : null,
			'width'           => $width,
			'height'          => $height,
			'video_quality'   => isset( $asset['video_quality'] ) ? (string) $asset['video_quality'] : null,
			'thumbnail_time'  => $thumb_time,
			'thumbnail'       => $media['thumbnail'],
			'preview'         => $media['preview'],
			'tokens'          => $media['tokens'],
			'captions'        => $captions,
			'downloads'       => $downloads,
			'shortcode'       => sprintf( '[vlfm_video id="%d"]', $post->ID ),
			'can_edit'        => current_user_can( 'edit_post', $post->ID ),
			'can_delete'      => current_user_can( 'delete_post', $post->ID ),
		);
	}

	/**
	 * The shape templates and GraphQL get: what a page needs to show the video,
	 * without the library's own bookkeeping.
	 *
	 * @param WP_Post $post Library video.
	 * @return array
	 */
	public static function to_public_array( WP_Post $post ) {
		$video = array_diff_key(
			self::to_array( $post ),
			array_flip( array( 'filename', 'source', 'error', 'author', 'shortcode', 'can_edit', 'can_delete' ) )
		);

		$video['stream_url'] = $video['playable'] ? Playback::stream_url( $video['playback_id'], $video['playback_policy'] ) : null;

		return $video;
	}

	/**
	 * A library video in the public shape, or null when there is no such video.
	 *
	 * @param mixed $id Post ID.
	 * @return array|null
	 */
	public static function get_public( $id ) {
		$post = is_numeric( $id ) && (int) $id > 0 ? get_post( (int) $id ) : null;
		if ( ! self::is_video( $post ) || 'publish' !== $post->post_status ) {
			return null;
		}
		return self::to_public_array( $post );
	}
}
