<?php
/**
 * Receives Mux webhooks.
 *
 * @package VideoLibraryForMux
 */

namespace VideoLibraryForMux;

use WP_Error;
use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

/**
 * Turns Mux events into library refreshes.
 *
 * The payload only says which upload or asset changed. The current state is
 * always read back from the Mux API, so a forged event cannot change the library.
 */
final class Webhooks {

	/**
	 * How old a signed event may be, in seconds.
	 */
	const TOLERANCE = 300;

	/**
	 * Handles POST /vlfm/v1/webhook.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public static function handle( WP_REST_Request $request ) {
		$secret = (string) Settings::get( 'webhook_secret' );
		if ( '' === $secret ) {
			// Without a secret there is no way to tell Mux from anyone else.
			return new WP_Error( 'vlfm_webhooks_off', __( 'Webhooks are off until a signing secret is saved in the settings.', 'videoteca-for-mux' ), array( 'status' => 403 ) );
		}
		if ( ! self::verify( (string) $request->get_header( 'mux_signature' ), $request->get_body(), $secret ) ) {
			return new WP_Error( 'vlfm_invalid_signature', __( 'The webhook signature is not valid.', 'videoteca-for-mux' ), array( 'status' => 401 ) );
		}

		$event = json_decode( $request->get_body(), true );
		if ( ! is_array( $event ) || empty( $event['type'] ) ) {
			return new WP_Error( 'vlfm_invalid_event', __( 'The webhook body is not a Mux event.', 'videoteca-for-mux' ), array( 'status' => 400 ) );
		}

		$type    = (string) $event['type'];
		$data    = isset( $event['data'] ) && is_array( $event['data'] ) ? $event['data'] : array();
		$subject = isset( $event['object'] ) && is_array( $event['object'] ) ? $event['object'] : array();
		$post_id = self::find_video( $type, $data, $subject );

		if ( $post_id ) {
			if ( 'video.asset.deleted' === $type ) {
				self::handle_deleted( $post_id );
			} else {
				Videos::refresh( $post_id );
			}
		}

		return rest_ensure_response( array( 'received' => true ) );
	}

	/**
	 * Finds the library video an event is about.
	 *
	 * @param string $type    Event type.
	 * @param array  $data    Event data.
	 * @param array  $subject The event's `object` member: the type and ID it is about.
	 * @return int Post ID, or 0.
	 */
	private static function find_video( $type, array $data, array $subject ) {
		if ( 0 === strpos( $type, 'video.upload.' ) ) {
			$upload_id = isset( $subject['id'] ) ? $subject['id'] : ( isset( $data['id'] ) ? $data['id'] : '' );
			return Videos::find_by_upload_id( (string) $upload_id );
		}

		if ( 0 !== strpos( $type, 'video.asset.' ) ) {
			return 0;
		}

		// Track and rendition events carry the asset ID in data; asset events in object and data.
		if ( ! empty( $data['asset_id'] ) ) {
			$asset_id = $data['asset_id'];
		} elseif ( isset( $subject['type'], $subject['id'] ) && 'asset' === $subject['type'] ) {
			$asset_id = $subject['id'];
		} else {
			$asset_id = isset( $data['id'] ) ? $data['id'] : '';
		}

		$post_id = Videos::find_by_asset_id( (string) $asset_id );
		if ( ! $post_id && ! empty( $data['upload_id'] ) ) {
			$post_id = Videos::find_by_upload_id( (string) $data['upload_id'] );
		}
		return $post_id;
	}

	/**
	 * Removes a video from the library once Mux confirms its asset is gone.
	 *
	 * @param int $post_id Library video ID.
	 */
	private static function handle_deleted( $post_id ) {
		$client   = Mux_Client::from_settings();
		$asset_id = (string) get_post_meta( $post_id, Videos::META_ASSET_ID, true );
		if ( ! $client || '' === $asset_id ) {
			return;
		}

		$asset = $client->get_asset( $asset_id );
		if ( is_wp_error( $asset ) && Videos::is_not_found( $asset ) ) {
			wp_delete_post( $post_id, true );
		}
	}

	/**
	 * Checks a Mux-Signature header.
	 *
	 * @param string $header Header value, such as "t=1565220904,v1=20c7...".
	 * @param string $body   Raw request body.
	 * @param string $secret Webhook signing secret.
	 * @return bool
	 */
	public static function verify( $header, $body, $secret ) {
		$timestamp  = 0;
		$signatures = array();

		foreach ( explode( ',', $header ) as $part ) {
			$pair = explode( '=', trim( $part ), 2 );
			if ( 2 !== count( $pair ) ) {
				continue;
			}
			if ( 't' === $pair[0] ) {
				$timestamp = (int) $pair[1];
			} elseif ( 'v1' === $pair[0] ) {
				$signatures[] = $pair[1];
			}
		}

		if ( ! $timestamp || ! $signatures || abs( time() - $timestamp ) > self::TOLERANCE ) {
			return false;
		}

		$expected = hash_hmac( 'sha256', $timestamp . '.' . $body, $secret );
		foreach ( $signatures as $signature ) {
			if ( hash_equals( $expected, $signature ) ) {
				return true;
			}
		}
		return false;
	}
}
