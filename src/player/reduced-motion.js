/**
 * Players that autoplay with no controls, such as background videos, stay on
 * their poster for visitors who ask their device for less motion. A click
 * still plays them.
 *
 * Imported before the player itself, so the autoplay attribute is gone before
 * the player reads it.
 */
if ( window.matchMedia?.( '(prefers-reduced-motion: reduce)' ).matches ) {
	document
		.querySelectorAll( 'mux-player[data-vlfm-reduced-motion]' )
		.forEach( ( player ) => player.removeAttribute( 'autoplay' ) );
}
