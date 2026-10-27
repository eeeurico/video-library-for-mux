<?php
/**
 * Playback: Mux Player markup, image URLs and signed playback tokens.
 *
 * @package VideoLibraryForMux
 */

namespace VideoLibraryForMux;

defined( 'ABSPATH' ) || exit;

/**
 * Builds everything a page needs to play a library video.
 */
final class Playback {

	const PLAYER_HANDLE = 'vlfm-player';
	const IMAGE_HOST    = 'https://image.mux.com/';
	const STREAM_HOST   = 'https://stream.mux.com/';

	/**
	 * Limits Mux accepts for max_resolution.
	 */
	const MAX_RESOLUTIONS = array( '720p', '1080p', '1440p', '2160p' );

	/**
	 * Values the player accepts for preload.
	 */
	const PRELOAD_VALUES = array( 'none', 'metadata', 'auto' );

	/**
	 * Registers the bundled Mux Player web component.
	 */
	public static function register_player_script() {
		$asset_file = VLFM_DIR . 'build/player/index.asset.php';
		if ( ! file_exists( $asset_file ) ) {
			return;
		}
		$asset = require $asset_file;

		wp_register_script(
			self::PLAYER_HANDLE,
			VLFM_URL . 'build/player/index.js',
			$asset['dependencies'],
			$asset['version'],
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
	}

	/**
	 * The playback policy for new assets. Signed only once a key exists to sign with.
	 *
	 * @return string
	 */
	public static function policy_for_new_assets() {
		return Settings::uses_signed_playback() && self::can_sign() ? 'signed' : 'public';
	}

	/**
	 * Whether signed playback tokens can be issued.
	 *
	 * @return bool
	 */
	public static function can_sign() {
		return Settings::can_sign() && function_exists( 'openssl_sign' );
	}

	/**
	 * Chooses the playback ID to use from an asset's list.
	 *
	 * @param array $playback_ids Mux playback IDs.
	 * @return array|null Array with `id` and `policy`.
	 */
	public static function pick_playback_id( array $playback_ids ) {
		$preferred = self::can_sign() && Settings::uses_signed_playback() ? array( 'signed', 'public' ) : array( 'public', 'signed' );

		foreach ( $preferred as $policy ) {
			foreach ( $playback_ids as $playback_id ) {
				if ( isset( $playback_id['policy'], $playback_id['id'] ) && $policy === $playback_id['policy'] ) {
					return array(
						'id'     => (string) $playback_id['id'],
						'policy' => $policy,
					);
				}
			}
		}

		return null;
	}

	/**
	 * Media values for a video that cannot be shown yet.
	 *
	 * @return array
	 */
	public static function empty_media() {
		return array(
			'playable'  => false,
			'thumbnail' => null,
			'preview'   => null,
			'tokens'    => null,
		);
	}

	/**
	 * Thumbnail, hover preview and player tokens for a playback ID.
	 *
	 * @param string     $playback_id    Playback ID.
	 * @param string     $policy         public or signed.
	 * @param float|null $thumbnail_time Seconds into the video, or null for Mux's default.
	 * @param float|null $duration       Video duration in seconds.
	 * @return array
	 */
	public static function media( $playback_id, $policy, $thumbnail_time, $duration ) {
		$signed = 'signed' === $policy;
		if ( $signed && ! self::can_sign() ) {
			return self::empty_media();
		}

		$thumbnail = array( 'width' => 640 );
		if ( null !== $thumbnail_time ) {
			$thumbnail['time'] = $thumbnail_time;
		}

		// A short looping clip for hovering in the library, from the thumbnail onwards.
		$start = null !== $thumbnail_time ? $thumbnail_time : 0;
		if ( $duration && $start + 4 > $duration ) {
			$start = max( 0, $duration - 4 );
		}
		$preview = array(
			'width' => 320,
			'start' => round( $start, 2 ),
			'end'   => round( $start + 4, 2 ),
		);

		$thumbnail_base = self::IMAGE_HOST . rawurlencode( $playback_id ) . '/thumbnail.webp';
		$preview_base   = self::IMAGE_HOST . rawurlencode( $playback_id ) . '/animated.webp';

		if ( ! $signed ) {
			return array(
				'playable'  => true,
				'thumbnail' => add_query_arg( $thumbnail, $thumbnail_base ),
				'preview'   => add_query_arg( $preview, $preview_base ),
				'tokens'    => null,
			);
		}

		// Signed URLs carry their options inside the token, not in the query string.
		return array(
			'playable'  => true,
			'thumbnail' => add_query_arg( 'token', self::sign( $playback_id, 't', $thumbnail ), $thumbnail_base ),
			'preview'   => add_query_arg( 'token', self::sign( $playback_id, 'g', $preview ), $preview_base ),
			'tokens'    => self::player_tokens( $playback_id, $thumbnail_time ),
		);
	}

	/**
	 * The three tokens Mux Player needs for a signed playback ID.
	 *
	 * @param string     $playback_id     Playback ID.
	 * @param float|null $thumbnail_time  Poster time.
	 * @param array      $playback_claims Stream options, such as max_resolution, which signed
	 *                                    streams only accept inside the token.
	 * @return array
	 */
	public static function player_tokens( $playback_id, $thumbnail_time, array $playback_claims = array() ) {
		return array(
			'playback'   => self::sign( $playback_id, 'v', $playback_claims ),
			'thumbnail'  => self::sign( $playback_id, 't', null !== $thumbnail_time ? array( 'time' => $thumbnail_time ) : array() ),
			'storyboard' => self::sign( $playback_id, 's' ),
		);
	}

	/**
	 * URL of the HLS stream, for players other than Mux Player.
	 *
	 * @param string $playback_id Playback ID.
	 * @param string $policy      public or signed.
	 * @return string|null
	 */
	public static function stream_url( $playback_id, $policy ) {
		$url = self::STREAM_HOST . rawurlencode( $playback_id ) . '.m3u8';
		if ( 'signed' !== $policy ) {
			return $url;
		}
		return self::can_sign() ? add_query_arg( 'token', self::sign( $playback_id, 'v' ), $url ) : null;
	}

	/**
	 * URL of a static rendition (MP4 or M4A).
	 *
	 * @param string $playback_id Playback ID.
	 * @param string $policy      public or signed.
	 * @param string $name        Rendition file name, such as highest.mp4.
	 * @return string|null
	 */
	public static function download_url( $playback_id, $policy, $name ) {
		$url = self::STREAM_HOST . rawurlencode( $playback_id ) . '/' . rawurlencode( $name );
		if ( 'signed' !== $policy ) {
			return $url;
		}
		return self::can_sign() ? add_query_arg( 'token', self::sign( $playback_id, 'v' ), $url ) : null;
	}

	/**
	 * Signs a playback token (RS256 JWT) with the site's signing key.
	 *
	 * @param string $playback_id Playback ID.
	 * @param string $audience    v (video), t (thumbnail), g (animated) or s (storyboard).
	 * @param array  $claims      Extra claims, such as thumbnail options.
	 * @return string Token, or an empty string when signing is not possible.
	 */
	public static function sign( $playback_id, $audience, array $claims = array() ) {
		static $cache = array();

		if ( ! self::can_sign() ) {
			return '';
		}

		$cache_key = md5( $playback_id . '|' . $audience . '|' . wp_json_encode( $claims ) );
		if ( isset( $cache[ $cache_key ] ) ) {
			return $cache[ $cache_key ];
		}

		$key_id = Settings::get( 'signing_key_id' );
		$ttl    = max( 1, (int) Settings::get( 'token_ttl_hours' ) ) * HOUR_IN_SECONDS;

		// Round the expiry up to the hour so cached pages share tokens.
		$expires = (int) ( ceil( ( time() + $ttl ) / HOUR_IN_SECONDS ) * HOUR_IN_SECONDS );

		$header  = array(
			'alg' => 'RS256',
			'typ' => 'JWT',
			'kid' => $key_id,
		);
		$payload = array_merge(
			$claims,
			array(
				'sub' => $playback_id,
				'aud' => $audience,
				'exp' => $expires,
				'kid' => $key_id,
			)
		);

		$signing_input = self::base64url( wp_json_encode( $header ) ) . '.' . self::base64url( wp_json_encode( $payload ) );

		$key = openssl_pkey_get_private( self::private_key_pem() );
		if ( false === $key ) {
			return '';
		}

		$signature = '';
		if ( ! openssl_sign( $signing_input, $signature, $key, OPENSSL_ALGO_SHA256 ) ) {
			return '';
		}

		$cache[ $cache_key ] = $signing_input . '.' . self::base64url( $signature );
		return $cache[ $cache_key ];
	}

	/**
	 * The signing key as PEM. Mux hands it out base64-encoded.
	 *
	 * @return string
	 */
	private static function private_key_pem() {
		$key = (string) Settings::get( 'signing_key_private' );
		if ( false !== strpos( $key, '-----BEGIN' ) ) {
			return $key;
		}
		$decoded = base64_decode( $key, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Mux delivers the PEM key base64-encoded.
		return false === $decoded ? '' : $decoded;
	}

	/**
	 * URL-safe base64 without padding, as JWTs use.
	 *
	 * @param string $data Raw bytes.
	 * @return string
	 */
	private static function base64url( $data ) {
		return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- JWT encoding.
	}

	/**
	 * Mux Player markup for a library video.
	 *
	 * @param int   $post_id Library video ID.
	 * @param array $options {
	 *     Player options.
	 *
	 *     @type bool       $background     Play muted on a loop with no controls, like a moving image.
	 *     @type bool       $autoplay       Start playing, muted, when the page loads.
	 *     @type bool       $muted          Start muted.
	 *     @type bool       $loop           Play again from the start when the video ends.
	 *     @type bool       $controls       Show the control bars. Without them a click plays and pauses.
	 *     @type bool       $show_title     Show the title in the player.
	 *     @type bool       $hide_captions  Start with captions off.
	 *     @type string     $playback_rates Speed menu: empty for the player's speeds, "off", or a list such as "0.5 1 2".
	 *     @type string     $preload        none, metadata, auto, or empty for the browser's choice.
	 *     @type string     $max_resolution 720p, 1080p, 1440p, 2160p, or empty for no limit.
	 *     @type int|string $poster         Poster image: an attachment ID or an image URL.
	 *     @type float      $start_time     Seconds into the video to start at.
	 *     @type string     $accent_color   Hex color for the controls.
	 * }
	 * @return string HTML, or an empty string when the video cannot play.
	 */
	public static function render_player( $post_id, array $options = array() ) {
		$post = get_post( $post_id );
		if ( ! Videos::is_video( $post ) || 'publish' !== $post->post_status ) {
			return '';
		}

		$playback_id = (string) get_post_meta( $post->ID, Videos::META_PLAYBACK_ID, true );
		$policy      = (string) get_post_meta( $post->ID, Videos::META_POLICY, true );
		$status      = (string) get_post_meta( $post->ID, Videos::META_STATUS, true );
		if ( 'ready' !== $status || '' === $playback_id || ( 'signed' === $policy && ! self::can_sign() ) ) {
			return '';
		}

		$options = wp_parse_args(
			$options,
			array(
				'background'     => false,
				'autoplay'       => false,
				'muted'          => false,
				'loop'           => false,
				'controls'       => true,
				'show_title'     => false,
				'hide_captions'  => false,
				'playback_rates' => '',
				'preload'        => '',
				'max_resolution' => '',
				'poster'         => '',
				'start_time'     => 0,
				'accent_color'   => '',
			)
		);

		if ( $options['background'] ) {
			$options['autoplay']      = true;
			$options['muted']         = true;
			$options['loop']          = true;
			$options['controls']      = false;
			$options['show_title']    = false;
			$options['hide_captions'] = true;
		}

		$asset          = get_post_meta( $post->ID, Videos::META_ASSET, true );
		$thumb_time     = get_post_meta( $post->ID, Videos::META_THUMBNAIL_TIME, true );
		$thumb_time     = '' === $thumb_time ? null : (float) $thumb_time;
		$accent         = sanitize_hex_color( $options['accent_color'] ? $options['accent_color'] : Settings::get( 'accent_color' ) );
		$poster         = self::poster_url( $options['poster'] );
		$playback_rates = self::sanitize_playback_rates( $options['playback_rates'] );
		$max_resolution = in_array( $options['max_resolution'], self::MAX_RESOLUTIONS, true ) ? $options['max_resolution'] : '';
		$preload        = in_array( $options['preload'], self::PRELOAD_VALUES, true ) ? $options['preload'] : '';

		$attributes = array();
		if ( 'signed' === $policy ) {
			// Tokens go first so the player never requests the stream without one.
			$tokens                         = self::player_tokens( $playback_id, $thumb_time, $max_resolution ? array( 'max_resolution' => $max_resolution ) : array() );
			$attributes['playback-token']   = $tokens['playback'];
			$attributes['thumbnail-token']  = $tokens['thumbnail'];
			$attributes['storyboard-token'] = $tokens['storyboard'];
		} else {
			if ( null !== $thumb_time && '' === $poster ) {
				$attributes['thumbnail-time'] = $thumb_time;
			}
			if ( $max_resolution ) {
				$attributes['max-resolution'] = $max_resolution;
			}
		}

		$attributes['playback-id']          = $playback_id;
		$attributes['stream-type']          = 'on-demand';
		$attributes['metadata-video-title'] = $post->post_title;
		$attributes['metadata-video-id']    = (string) get_post_meta( $post->ID, Videos::META_ASSET_ID, true );

		if ( $poster ) {
			$attributes['poster'] = $poster;
		}
		if ( $accent ) {
			$attributes['accent-color'] = $accent;
		}
		if ( $options['show_title'] && $options['controls'] ) {
			$attributes['title'] = $post->post_title;
		}
		if ( $options['start_time'] > 0 ) {
			$attributes['start-time'] = (float) $options['start_time'];
		}
		if ( $options['autoplay'] ) {
			// Browsers only autoplay muted video, so ask the player to mute.
			$attributes['autoplay'] = 'muted';
		} elseif ( $preload ) {
			$attributes['preload'] = $preload;
		}
		if ( $options['muted'] ) {
			$attributes['muted'] = true;
		}
		if ( $options['loop'] ) {
			$attributes['loop'] = true;
		}
		if ( $options['hide_captions'] ) {
			$attributes['default-hidden-captions'] = true;
		}
		if ( $playback_rates && 'off' !== $playback_rates ) {
			$attributes['playbackrates'] = $playback_rates;
		}
		if ( $options['autoplay'] && ! $options['controls'] ) {
			// The player script stops these autoplaying for visitors who ask for reduced motion.
			$attributes['data-vlfm-reduced-motion'] = 'still';
		}
		if ( ! Settings::get( 'analytics' ) ) {
			$attributes['disable-tracking'] = true;
		}
		if ( ! Settings::get( 'analytics_cookies' ) ) {
			$attributes['disable-cookies'] = true;
		}

		$aspect_ratio = is_array( $asset ) && ! empty( $asset['aspect_ratio'] ) ? str_replace( ':', ' / ', (string) $asset['aspect_ratio'] ) : '16 / 9';
		if ( ! preg_match( '#^\d+(\.\d+)? / \d+(\.\d+)?$#', $aspect_ratio ) ) {
			$aspect_ratio = '16 / 9';
		}
		$style = array( 'aspect-ratio: ' . $aspect_ratio );
		if ( ! $options['controls'] ) {
			// Hiding the three control bars, rather than all controls, keeps click to play and pause.
			$style[] = '--top-controls: none';
			$style[] = '--center-controls: none';
			$style[] = '--bottom-controls: none';
		}
		if ( $options['background'] ) {
			$style[] = '--loading-indicator: none';
		}
		if ( 'off' === $playback_rates ) {
			$style[] = '--playback-rate-button: none';
			$style[] = '--playback-rate-menu-button: none';
		}
		$attributes['style'] = implode( '; ', $style ) . ';';

		$html = '';
		foreach ( $attributes as $name => $value ) {
			$html .= true === $value ? ' ' . $name : sprintf( ' %s="%s"', $name, esc_attr( (string) $value ) );
		}

		return '<mux-player' . $html . '></mux-player>';
	}

	/**
	 * A player ready to print: the markup in a figure, with its script and styles queued.
	 *
	 * @param int   $post_id Library video ID.
	 * @param array $options Player options, as for render_player().
	 * @return string HTML, or an empty string when the video cannot play.
	 */
	public static function embed( $post_id, array $options = array() ) {
		$player = self::render_player( $post_id, $options );
		if ( '' === $player ) {
			return '';
		}

		wp_enqueue_script( self::PLAYER_HANDLE );
		// The block's stylesheet, registered with the block.
		wp_enqueue_style( 'vlfm-video-style' );

		return '<figure class="vlfm-video">' . $player . '</figure>';
	}

	/**
	 * URL of a poster image given as an attachment ID or an image URL.
	 *
	 * @param int|string $poster Attachment ID or URL.
	 * @return string URL, or an empty string.
	 */
	public static function poster_url( $poster ) {
		if ( is_numeric( $poster ) ) {
			$url = (int) $poster > 0 ? wp_get_attachment_image_url( (int) $poster, 'full' ) : false;
			return $url ? $url : '';
		}
		return is_string( $poster ) ? esc_url_raw( trim( $poster ), array( 'http', 'https' ) ) : '';
	}

	/**
	 * Cleans up a speed menu setting: empty, "off", or speeds such as "0.5 1 1.5 2".
	 *
	 * @param string $value Setting.
	 * @return string
	 */
	public static function sanitize_playback_rates( $value ) {
		$value = strtolower( trim( (string) $value ) );
		if ( '' === $value || 'off' === $value ) {
			return $value;
		}

		$rates = array();
		foreach ( preg_split( '/[\s,]+/', $value ) as $rate ) {
			if ( is_numeric( $rate ) && $rate >= 0.25 && $rate <= 4 ) {
				$rates[] = (float) $rate;
			}
		}
		$rates = array_unique( $rates );
		sort( $rates );

		return implode( ' ', array_slice( $rates, 0, 10 ) );
	}
}
