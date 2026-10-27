<?php
/**
 * WPGraphQL support: the MuxVideo type, library queries for editors, and the
 * ACF Mux Video field resolving to MuxVideo.
 *
 * @package VideoLibraryForMux
 */

namespace VideoLibraryForMux;

use GraphQL\Error\UserError;
use GraphQLRelay\Connection\ArrayConnection;
use GraphQLRelay\Relay;

defined( 'ABSPATH' ) || exit;

/**
 * Adds library videos to the WPGraphQL schema.
 */
final class GraphQL {

	const TYPE = 'MuxVideo';

	/**
	 * Name of the data loader, and the type in MuxVideo global IDs.
	 */
	const LOADER = 'vlfm_video';

	/**
	 * Most videos one muxVideos page returns.
	 */
	const MAX_PAGE = 100;

	/**
	 * Hooks into WPGraphQL. Does nothing when it is not installed.
	 */
	public static function register() {
		add_action( 'graphql_register_types', array( __CLASS__, 'register_types' ) );
		add_filter( 'graphql_data_loader_classes', array( __CLASS__, 'add_loader' ) );
		add_filter( 'graphql_resolve_node_type', array( __CLASS__, 'resolve_node_type' ), 10, 2 );
		add_action( 'wpgraphql/acf/registry_init', array( __CLASS__, 'register_acf_field_type' ) );
	}

	/**
	 * Registers the types and root fields.
	 */
	public static function register_types() {
		register_graphql_enum_type(
			'MuxVideoStatusEnum',
			array(
				'description' => __( 'Where a video is in Mux.', 'video-library-for-mux' ),
				'values'      => array(
					'UPLOADING' => array(
						'value'       => 'uploading',
						'description' => __( 'The file is still on its way to Mux.', 'video-library-for-mux' ),
					),
					'PREPARING' => array(
						'value'       => 'preparing',
						'description' => __( 'Mux is processing the video.', 'video-library-for-mux' ),
					),
					'READY'     => array(
						'value'       => 'ready',
						'description' => __( 'The video can play.', 'video-library-for-mux' ),
					),
					'ERRORED'   => array(
						'value'       => 'errored',
						'description' => __( 'Mux could not process the video.', 'video-library-for-mux' ),
					),
				),
			)
		);

		register_graphql_enum_type(
			'MuxPlaybackPolicyEnum',
			array(
				'description' => __( 'Who can play a video.', 'video-library-for-mux' ),
				'values'      => array(
					'PUBLIC' => array(
						'value'       => 'public',
						'description' => __( 'Anyone with the playback ID.', 'video-library-for-mux' ),
					),
					'SIGNED' => array(
						'value'       => 'signed',
						'description' => __( 'Only players with a token signed by this site.', 'video-library-for-mux' ),
					),
				),
			)
		);

		register_graphql_enum_type(
			'MuxVideoIdTypeEnum',
			array(
				'description' => __( 'What the id argument of muxVideo is.', 'video-library-for-mux' ),
				'values'      => array(
					'ID'          => array(
						'value'       => 'global_id',
						'description' => __( 'The global ID.', 'video-library-for-mux' ),
					),
					'DATABASE_ID' => array(
						'value'       => 'database_id',
						'description' => __( 'The library video ID, as the block and shortcode use.', 'video-library-for-mux' ),
					),
					'ASSET_ID'    => array(
						'value'       => 'asset_id',
						'description' => __( 'The Mux asset ID.', 'video-library-for-mux' ),
					),
					'PLAYBACK_ID' => array(
						'value'       => 'playback_id',
						'description' => __( 'The Mux playback ID.', 'video-library-for-mux' ),
					),
				),
			)
		);

		register_graphql_enum_type(
			'MuxVideoOrderbyEnum',
			array(
				'description' => __( 'How to sort library videos.', 'video-library-for-mux' ),
				'values'      => array(
					'DATE'  => array(
						'value'       => 'date',
						'description' => __( 'By the date the video was added.', 'video-library-for-mux' ),
					),
					'TITLE' => array(
						'value'       => 'title',
						'description' => __( 'By title.', 'video-library-for-mux' ),
					),
				),
			)
		);

		register_graphql_object_type(
			'MuxVideoTokens',
			array(
				'description' => __( 'Tokens Mux Player needs to play a signed video. They expire after the time set in Mux Videos → Settings.', 'video-library-for-mux' ),
				'fields'      => array(
					'playback'   => array(
						'type'        => 'String',
						'description' => __( 'For the playback-token attribute, and for stream and download URLs.', 'video-library-for-mux' ),
					),
					'thumbnail'  => array(
						'type'        => 'String',
						'description' => __( 'For the thumbnail-token attribute.', 'video-library-for-mux' ),
					),
					'storyboard' => array(
						'type'        => 'String',
						'description' => __( 'For the storyboard-token attribute.', 'video-library-for-mux' ),
					),
				),
			)
		);

		register_graphql_object_type(
			'MuxVideoCaption',
			array(
				'description' => __( 'A captions or subtitles track.', 'video-library-for-mux' ),
				'fields'      => array(
					'name'      => array(
						'type'        => 'String',
						'description' => __( 'The name players show.', 'video-library-for-mux' ),
					),
					'language'  => array(
						'type'        => 'String',
						'description' => __( 'Language code.', 'video-library-for-mux' ),
					),
					'status'    => array(
						'type'        => 'String',
						'description' => __( 'The track status in Mux, such as ready.', 'video-library-for-mux' ),
					),
					'generated' => array(
						'type'        => 'Boolean',
						'description' => __( 'Whether Mux generated the track from speech.', 'video-library-for-mux' ),
					),
				),
			)
		);

		register_graphql_object_type(
			'MuxVideoDownload',
			array(
				'description' => __( 'A downloadable MP4 or M4A file of a video.', 'video-library-for-mux' ),
				'fields'      => array(
					'name' => array(
						'type'        => 'String',
						'description' => __( 'File name, such as highest.mp4.', 'video-library-for-mux' ),
					),
					'url'  => array(
						'type'        => 'String',
						'description' => __( 'Download URL. Signed for signed videos.', 'video-library-for-mux' ),
					),
				),
			)
		);

		register_graphql_object_type(
			self::TYPE,
			array(
				'description' => __( 'A video in the Mux video library.', 'video-library-for-mux' ),
				'interfaces'  => array( 'Node', 'DatabaseIdentifier' ),
				'fields'      => array(
					'id'                 => array(
						'type'        => array( 'non_null' => 'ID' ),
						'description' => __( 'The global ID.', 'video-library-for-mux' ),
					),
					'databaseId'         => array(
						'type'        => array( 'non_null' => 'Int' ),
						'description' => __( 'The library video ID, as the block and shortcode use.', 'video-library-for-mux' ),
					),
					'title'              => array(
						'type'        => 'String',
						'description' => __( 'The title from the video library.', 'video-library-for-mux' ),
					),
					'description'        => array(
						'type'        => 'String',
						'description' => __( 'The description from the video library.', 'video-library-for-mux' ),
					),
					'date'               => array(
						'type'        => 'String',
						'description' => __( 'When the video was added, in ISO 8601 format and UTC.', 'video-library-for-mux' ),
					),
					'status'             => array(
						'type'        => 'MuxVideoStatusEnum',
						'description' => __( 'Where the video is in Mux.', 'video-library-for-mux' ),
					),
					'playable'           => array(
						'type'        => 'Boolean',
						'description' => __( 'Whether the video can play: it is ready, and signed videos have tokens.', 'video-library-for-mux' ),
					),
					'playbackId'         => array(
						'type'        => 'String',
						'description' => __( 'The Mux playback ID, for the playback-id attribute of Mux Player.', 'video-library-for-mux' ),
					),
					'playbackPolicy'     => array(
						'type'        => 'MuxPlaybackPolicyEnum',
						'description' => __( 'Whether the playback ID is public or needs signed tokens.', 'video-library-for-mux' ),
					),
					'assetId'            => array(
						'type'        => 'String',
						'description' => __( 'The Mux asset ID.', 'video-library-for-mux' ),
					),
					'duration'           => array(
						'type'        => 'Float',
						'description' => __( 'Length in seconds.', 'video-library-for-mux' ),
					),
					'aspectRatio'        => array(
						'type'        => 'String',
						'description' => __( 'Aspect ratio, such as 16:9.', 'video-library-for-mux' ),
					),
					'resolution'         => array(
						'type'        => 'String',
						'description' => __( 'Resolution tier, such as 1080p.', 'video-library-for-mux' ),
					),
					'width'              => array(
						'type'        => 'Int',
						'description' => __( 'Width of the largest rendition, in pixels.', 'video-library-for-mux' ),
					),
					'height'             => array(
						'type'        => 'Int',
						'description' => __( 'Height of the largest rendition, in pixels.', 'video-library-for-mux' ),
					),
					'videoQuality'       => array(
						'type'        => 'String',
						'description' => __( 'The Mux video quality level, such as basic or plus.', 'video-library-for-mux' ),
					),
					'thumbnailTime'      => array(
						'type'        => 'Float',
						'description' => __( 'The thumbnail frame, in seconds, when one was chosen in the library.', 'video-library-for-mux' ),
					),
					'thumbnailUrl'       => array(
						'type'        => 'String',
						'description' => __( 'A 640 pixel wide WebP thumbnail.', 'video-library-for-mux' ),
					),
					'animatedPreviewUrl' => array(
						'type'        => 'String',
						'description' => __( 'A four-second animated WebP preview, 320 pixels wide.', 'video-library-for-mux' ),
					),
					'streamUrl'          => array(
						'type'        => 'String',
						'description' => __( 'The HLS stream, for players other than Mux Player. Signed for signed videos.', 'video-library-for-mux' ),
					),
					'tokens'             => array(
						'type'        => 'MuxVideoTokens',
						'description' => __( 'Tokens for signed videos. Null for public ones.', 'video-library-for-mux' ),
					),
					'captions'           => array(
						'type'        => array( 'non_null' => array( 'list_of' => array( 'non_null' => 'MuxVideoCaption' ) ) ),
						'description' => __( 'Captions and subtitles tracks.', 'video-library-for-mux' ),
					),
					'downloads'          => array(
						'type'        => array( 'non_null' => array( 'list_of' => array( 'non_null' => 'MuxVideoDownload' ) ) ),
						'description' => __( 'Downloadable files, when the site creates them.', 'video-library-for-mux' ),
					),
				),
			)
		);

		register_graphql_field(
			'RootQuery',
			'muxVideo',
			array(
				'type'        => self::TYPE,
				'description' => __( 'A video from the Mux video library. Only for users who can edit posts; everyone else gets null. Public pages get their videos through ACF fields.', 'video-library-for-mux' ),
				'args'        => array(
					'id'     => array(
						'type'        => array( 'non_null' => 'ID' ),
						'description' => __( 'The video to get.', 'video-library-for-mux' ),
					),
					'idType' => array(
						'type'        => 'MuxVideoIdTypeEnum',
						'description' => __( 'What the id is. Defaults to the global ID.', 'video-library-for-mux' ),
					),
				),
				'resolve'     => static function ( $root, array $args ) {
					return self::find( self::lookup( (string) $args['id'], isset( $args['idType'] ) ? $args['idType'] : 'global_id' ) );
				},
			)
		);

		register_graphql_connection(
			array(
				'fromType'       => 'RootQuery',
				'toType'         => self::TYPE,
				'fromFieldName'  => 'muxVideos',
				'description'    => __( 'Videos in the Mux video library, newest first. Only for users who can edit posts; everyone else gets an empty list. Pages forwards with first and after.', 'video-library-for-mux' ),
				'connectionArgs' => array(
					'search'  => array(
						'type'        => 'String',
						'description' => __( 'Words to find in the title, description, file name, asset ID or playback ID.', 'video-library-for-mux' ),
					),
					'status'  => array(
						'type'        => 'MuxVideoStatusEnum',
						'description' => __( 'Only videos with this status.', 'video-library-for-mux' ),
					),
					'orderby' => array(
						'type'        => 'MuxVideoOrderbyEnum',
						'description' => __( 'What to sort by. Defaults to the date.', 'video-library-for-mux' ),
					),
					'order'   => array(
						'type'        => 'OrderEnum',
						'description' => __( 'Sort direction. Defaults to descending.', 'video-library-for-mux' ),
					),
				),
				'resolve'        => static function ( $root, array $args ) {
					return self::resolve_connection( $args );
				},
			)
		);
	}

	/**
	 * Lets node(id:) load library videos.
	 *
	 * @param array $classes Loader classes by name.
	 * @return array
	 */
	public static function add_loader( $classes ) {
		require_once VLFM_DIR . 'includes/class-graphql-loader.php';
		$classes[ self::LOADER ] = GraphQL_Loader::class;
		return $classes;
	}

	/**
	 * Tells WPGraphQL which type a library video node is.
	 *
	 * @param string|null $type Type found so far.
	 * @param mixed       $node Node.
	 * @return string|null
	 */
	public static function resolve_node_type( $type, $node ) {
		return $node instanceof GraphQL_Video ? self::TYPE : $type;
	}

	/**
	 * Maps the ACF Mux Video field to MuxVideo. Anyone who can see the post the
	 * field is on can see its video, as they can on the page itself.
	 */
	public static function register_acf_field_type() {
		if ( ! function_exists( 'register_graphql_acf_field_type' ) ) {
			return;
		}

		register_graphql_acf_field_type(
			ACF::FIELD_TYPE,
			array(
				'graphql_type'         => self::TYPE,
				'exclude_admin_fields' => array( 'graphql_non_null' ),
				'resolve'              => static function ( $root, $args, $context, $info, $field_type, $field_config ) {
					$video = Videos::get_public( ACF::video_id( $field_config->resolve_field( $root, $args, $context, $info ) ) );
					return $video ? self::model( $video ) : null;
				},
			)
		);
	}

	/**
	 * A video for a library query, when the viewer may browse the library.
	 *
	 * @param mixed $id Post ID.
	 * @return GraphQL_Video|null
	 */
	public static function find( $id ) {
		if ( ! Rest_Controller::can_browse() ) {
			return null;
		}
		$video = Videos::get_public( $id );
		return $video ? self::model( $video ) : null;
	}

	/**
	 * The post ID for a muxVideo id argument.
	 *
	 * @param string $id      Value of the id argument.
	 * @param string $id_type What it is: global_id, database_id, asset_id or playback_id.
	 * @return int Post ID, or 0.
	 */
	private static function lookup( $id, $id_type ) {
		switch ( $id_type ) {
			case 'database_id':
				return absint( $id );
			case 'asset_id':
				return Videos::find_by_asset_id( $id );
			case 'playback_id':
				return Videos::find_by_playback_id( $id );
			default:
				$parts = Relay::fromGlobalId( $id );
				return isset( $parts['type'], $parts['id'] ) && self::LOADER === $parts['type'] ? absint( $parts['id'] ) : 0;
		}
	}

	/**
	 * One page of muxVideos.
	 *
	 * @param array $args Connection arguments.
	 * @return array Edges, nodes and page info.
	 * @throws UserError When asked to page backwards.
	 */
	private static function resolve_connection( array $args ) {
		$connection = array(
			'edges'    => array(),
			'nodes'    => array(),
			'pageInfo' => array(
				'hasNextPage'     => false,
				'hasPreviousPage' => false,
				'startCursor'     => null,
				'endCursor'       => null,
			),
		);

		if ( ! Rest_Controller::can_browse() ) {
			return $connection;
		}

		if ( ! empty( $args['last'] ) || ! empty( $args['before'] ) ) {
			throw new UserError( esc_html__( 'muxVideos only pages forwards. Use first and after.', 'video-library-for-mux' ) );
		}

		$where  = isset( $args['where'] ) ? $args['where'] : array();
		$first  = isset( $args['first'] ) ? min( self::MAX_PAGE, max( 1, (int) $args['first'] ) ) : 10;
		$offset = 0;
		if ( ! empty( $args['after'] ) ) {
			$after  = ArrayConnection::cursorToOffset( $args['after'] );
			$offset = is_numeric( $after ) ? max( 0, (int) $after + 1 ) : 0;
		}

		$query = Videos::query(
			array(
				'search'   => isset( $where['search'] ) ? trim( (string) $where['search'] ) : '',
				'status'   => isset( $where['status'] ) ? $where['status'] : '',
				'orderby'  => isset( $where['orderby'] ) ? $where['orderby'] : 'date',
				'order'    => isset( $where['order'] ) ? $where['order'] : 'DESC',
				'per_page' => $first,
				'page'     => 1,
				'offset'   => $offset,
			)
		);

		foreach ( $query->posts as $index => $post ) {
			$node                  = self::model( Videos::to_public_array( $post ) );
			$connection['nodes'][] = $node;
			$connection['edges'][] = array(
				'cursor' => ArrayConnection::offsetToCursor( $offset + $index ),
				'node'   => $node,
			);
		}

		if ( $connection['edges'] ) {
			$connection['pageInfo'] = array(
				'hasNextPage'     => $offset + count( $query->posts ) < (int) $query->found_posts,
				'hasPreviousPage' => $offset > 0,
				'startCursor'     => $connection['edges'][0]['cursor'],
				'endCursor'       => end( $connection['edges'] )['cursor'],
			);
		}

		return $connection;
	}

	/**
	 * The MuxVideo fields for a video in the public shape.
	 *
	 * @param array $video Video, from Videos::to_public_array().
	 * @return GraphQL_Video
	 */
	private static function model( array $video ) {
		return new GraphQL_Video(
			array(
				'id'                 => Relay::toGlobalId( self::LOADER, (string) $video['id'] ),
				'databaseId'         => $video['id'],
				'title'              => $video['title'],
				'description'        => $video['description'],
				'date'               => $video['date'],
				'status'             => $video['status'],
				'playable'           => $video['playable'],
				'playbackId'         => '' !== $video['playback_id'] ? $video['playback_id'] : null,
				'playbackPolicy'     => '' !== $video['playback_policy'] ? $video['playback_policy'] : null,
				'assetId'            => '' !== $video['asset_id'] ? $video['asset_id'] : null,
				'duration'           => $video['duration'],
				'aspectRatio'        => $video['aspect_ratio'],
				'resolution'         => $video['resolution'],
				'width'              => $video['width'],
				'height'             => $video['height'],
				'videoQuality'       => $video['video_quality'],
				'thumbnailTime'      => $video['thumbnail_time'],
				'thumbnailUrl'       => $video['thumbnail'],
				'animatedPreviewUrl' => $video['preview'],
				'streamUrl'          => $video['stream_url'],
				'tokens'             => $video['tokens'],
				'captions'           => $video['captions'],
				'downloads'          => $video['downloads'],
			)
		);
	}
}
