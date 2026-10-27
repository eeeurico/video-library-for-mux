/**
 * React wrapper for the <mux-player> web component.
 *
 * Attributes are set by hand rather than as JSX props: React 18 and 19 treat
 * props on custom elements differently, and attributes are what the player
 * reads. Style it through a wrapping element, or pass CSS variables in `style`.
 */
import { forwardRef, useLayoutEffect, useRef } from '@wordpress/element';
import { useMergeRefs } from '@wordpress/compose';

function MuxPlayer( { video, attributes = {}, style = {} }, ref ) {
	const elementRef = useRef();
	const mergedRef = useMergeRefs( [ elementRef, ref ] );

	useLayoutEffect( () => {
		const element = elementRef.current;
		if ( ! element ) {
			return;
		}

		const values = {
			// Tokens first, so the player never asks for a signed stream without one.
			'playback-token': video.tokens?.playback,
			'thumbnail-token': video.tokens?.thumbnail,
			'storyboard-token': video.tokens?.storyboard,
			// Listed before the playback ID so the first request already uses it.
			// Signed streams ignore it: their limit has to be inside the token.
			'max-resolution': undefined,
			'playback-id': video.playback_id,
			'stream-type': 'on-demand',
			'metadata-video-title': video.title,
			'thumbnail-time':
				! video.tokens && video.thumbnail_time !== null
					? video.thumbnail_time
					: undefined,
			// Previews in the admin should not count as views.
			'disable-tracking': true,
			'disable-cookies': true,
			...attributes,
		};

		for ( const [ name, value ] of Object.entries( values ) ) {
			if (
				value === undefined ||
				value === null ||
				value === false ||
				value === ''
			) {
				element.removeAttribute( name );
			} else {
				element.setAttribute(
					name,
					value === true ? '' : String( value )
				);
			}
		}
	} );

	const aspectRatio = video.aspect_ratio
		? video.aspect_ratio.replace( ':', ' / ' )
		: '16 / 9';

	return <mux-player ref={ mergedRef } style={ { aspectRatio, ...style } } />;
}

export default forwardRef( MuxPlayer );
