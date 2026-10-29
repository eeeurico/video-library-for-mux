<?php
/**
 * Thin client for the Mux Video API.
 *
 * @package VideoLibraryForMux
 */

namespace VideoLibraryForMux;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Calls https://api.mux.com with the site's access token.
 */
final class Mux_Client {

	const BASE_URL = 'https://api.mux.com';

	/**
	 * Access token ID.
	 *
	 * @var string
	 */
	private $token_id;

	/**
	 * Access token secret.
	 *
	 * @var string
	 */
	private $token_secret;

	/**
	 * Constructor.
	 *
	 * @param string $token_id     Access token ID.
	 * @param string $token_secret Access token secret.
	 */
	public function __construct( $token_id, $token_secret ) {
		$this->token_id     = $token_id;
		$this->token_secret = $token_secret;
	}

	/**
	 * A client using the saved credentials, or null when none are saved.
	 *
	 * @return self|null
	 */
	public static function from_settings() {
		if ( ! Settings::is_configured() ) {
			return null;
		}
		return new self( Settings::get( 'token_id' ), Settings::get( 'token_secret' ) );
	}

	/**
	 * Creates a direct upload URL.
	 *
	 * @param array $body Request body.
	 * @return array|WP_Error The upload object.
	 */
	public function create_upload( array $body ) {
		return $this->data( $this->request( 'POST', '/video/v1/uploads', $body ) );
	}

	/**
	 * Fetches a direct upload.
	 *
	 * @param string $upload_id Upload ID.
	 * @return array|WP_Error
	 */
	public function get_upload( $upload_id ) {
		return $this->data( $this->request( 'GET', '/video/v1/uploads/' . rawurlencode( $upload_id ) ) );
	}

	/**
	 * Cancels a direct upload that has not finished.
	 *
	 * @param string $upload_id Upload ID.
	 * @return array|WP_Error
	 */
	public function cancel_upload( $upload_id ) {
		return $this->data( $this->request( 'PUT', '/video/v1/uploads/' . rawurlencode( $upload_id ) . '/cancel' ) );
	}

	/**
	 * Creates an asset from a URL Mux can fetch.
	 *
	 * @param array $body Request body.
	 * @return array|WP_Error The asset object.
	 */
	public function create_asset( array $body ) {
		return $this->data( $this->request( 'POST', '/video/v1/assets', $body ) );
	}

	/**
	 * Fetches an asset.
	 *
	 * @param string $asset_id Asset ID.
	 * @return array|WP_Error
	 */
	public function get_asset( $asset_id ) {
		return $this->data( $this->request( 'GET', '/video/v1/assets/' . rawurlencode( $asset_id ) ) );
	}

	/**
	 * Updates an asset's passthrough or meta.
	 *
	 * @param string $asset_id Asset ID.
	 * @param array  $body     Fields to change.
	 * @return array|WP_Error
	 */
	public function update_asset( $asset_id, array $body ) {
		return $this->data( $this->request( 'PATCH', '/video/v1/assets/' . rawurlencode( $asset_id ), $body ) );
	}

	/**
	 * Deletes an asset and its playback IDs.
	 *
	 * @param string $asset_id Asset ID.
	 * @return true|WP_Error
	 */
	public function delete_asset( $asset_id ) {
		$result = $this->request( 'DELETE', '/video/v1/assets/' . rawurlencode( $asset_id ) );
		return is_wp_error( $result ) ? $result : true;
	}

	/**
	 * Lists assets, newest first.
	 *
	 * @param array $query Query arguments (limit, cursor).
	 * @return array|WP_Error Array with `data` and `next_cursor`.
	 */
	public function list_assets( array $query = array() ) {
		return $this->request( 'GET', '/video/v1/assets', null, $query );
	}

	/**
	 * Creates a URL signing key.
	 *
	 * @return array|WP_Error Array with `id` and base64 `private_key`.
	 */
	public function create_signing_key() {
		return $this->data( $this->request( 'POST', '/system/v1/signing-keys' ) );
	}

	/**
	 * Unwraps the `data` member of a response.
	 *
	 * @param array|WP_Error $response Decoded response.
	 * @return array|WP_Error
	 */
	private function data( $response ) {
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		return isset( $response['data'] ) && is_array( $response['data'] ) ? $response['data'] : array();
	}

	/**
	 * Sends a request to Mux.
	 *
	 * @param string     $method HTTP method.
	 * @param string     $path   API path.
	 * @param array|null $body   JSON body.
	 * @param array      $query  Query arguments.
	 * @return array|WP_Error Decoded JSON body.
	 */
	private function request( $method, $path, $body = null, array $query = array() ) {
		$url = self::BASE_URL . $path;
		if ( $query ) {
			$url = add_query_arg( array_map( 'rawurlencode', $query ), $url );
		}

		$args = array(
			'method'  => $method,
			'timeout' => 20,
			'headers' => array(
				// Mux uses HTTP Basic auth with the token ID and secret.
				'Authorization' => 'Basic ' . base64_encode( $this->token_id . ':' . $this->token_secret ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
				'Accept'        => 'application/json',
			),
		);

		if ( null !== $body ) {
			$args['headers']['Content-Type'] = 'application/json';
			$args['body']                    = wp_json_encode( (object) $body );
		}

		$response = wp_remote_request( $url, $args );
		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'vlfm_mux_unreachable',
				/* translators: %s: error message. */
				sprintf( __( 'Could not reach Mux: %s', 'videoteca-for-mux' ), $response->get_error_message() ),
				array( 'status' => 502 )
			);
		}

		$code    = (int) wp_remote_retrieve_response_code( $response );
		$decoded = json_decode( wp_remote_retrieve_body( $response ), true );
		$decoded = is_array( $decoded ) ? $decoded : array();

		if ( $code >= 200 && $code < 300 ) {
			return $decoded;
		}

		return new WP_Error( 'vlfm_mux_error', $this->error_message( $code, $decoded ), array( 'status' => $code ) );
	}

	/**
	 * A readable message for a failed request.
	 *
	 * @param int   $code    HTTP status.
	 * @param array $decoded Decoded body.
	 * @return string
	 */
	private function error_message( $code, array $decoded ) {
		if ( ! empty( $decoded['error']['messages'] ) && is_array( $decoded['error']['messages'] ) ) {
			return implode( ' ', array_map( 'strval', $decoded['error']['messages'] ) );
		}

		switch ( $code ) {
			case 401:
				return __( 'The access token ID or secret key is wrong.', 'videoteca-for-mux' );
			case 403:
				return __( 'The access token does not have permission for this.', 'videoteca-for-mux' );
			case 404:
				return __( 'Mux could not find this item. It may have been deleted.', 'videoteca-for-mux' );
			case 429:
				return __( 'Mux is rate limiting requests. Wait a moment and try again.', 'videoteca-for-mux' );
		}

		/* translators: %d: HTTP status code. */
		return sprintf( __( 'Mux returned an unexpected response (HTTP %d).', 'videoteca-for-mux' ), $code );
	}
}
