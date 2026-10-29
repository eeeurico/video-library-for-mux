/**
 * Formatting helpers.
 */
import { dateI18n, getSettings } from '@wordpress/date';
import { __, sprintf } from '@wordpress/i18n';

/**
 * A title for a file: its name without the extension.
 *
 * @param {string} filename File name.
 * @return {string} Title.
 */
export function titleFromFilename( filename ) {
	const title = filename.replace( /\.[a-z0-9]{1,5}$/i, '' ).trim();
	return title || __( 'Untitled video', 'videoteca-for-mux' );
}

/**
 * 75 → "1:15", 3725 → "1:02:05".
 *
 * @param {number|null} seconds Duration.
 * @return {string} Clock time, or an empty string.
 */
export function formatDuration( seconds ) {
	if ( seconds === null || seconds === undefined || isNaN( seconds ) ) {
		return '';
	}
	const total = Math.max( 0, Math.round( seconds ) );
	const hours = Math.floor( total / 3600 );
	const minutes = Math.floor( ( total % 3600 ) / 60 );
	const secs = String( total % 60 ).padStart( 2, '0' );
	return hours
		? `${ hours }:${ String( minutes ).padStart( 2, '0' ) }:${ secs }`
		: `${ minutes }:${ secs }`;
}

/**
 * A timestamp with a tenth of a second, for thumbnail times: 12.4 → "0:12.4".
 *
 * @param {number} seconds Time.
 * @return {string} Clock time.
 */
export function formatTimestamp( seconds ) {
	const minutes = Math.floor( seconds / 60 );
	const rest = ( seconds % 60 ).toFixed( 1 ).padStart( 4, '0' );
	return `${ minutes }:${ rest }`;
}

/**
 * 1536000 → "1.5 MB".
 *
 * @param {number} bytes Size.
 * @return {string} Size with unit.
 */
export function formatBytes( bytes ) {
	const units = [ 'B', 'KB', 'MB', 'GB', 'TB' ];
	let value = bytes;
	let unit = 0;
	while ( value >= 1024 && unit < units.length - 1 ) {
		value /= 1024;
		unit++;
	}
	return `${ value.toFixed( unit < 2 ? 0 : 1 ) } ${ units[ unit ] }`;
}

/**
 * A date in the site's date format.
 *
 * @param {string} iso ISO 8601 date.
 * @return {string} Formatted date.
 */
export function formatDate( iso ) {
	return iso ? dateI18n( getSettings().formats.date, iso ) : '';
}

/**
 * A one-line summary of a video: "October 3, 2026 · 1080p".
 *
 * @param {Object} video Video.
 * @return {string} Summary.
 */
export function videoSummary( video ) {
	return [ formatDate( video.date ), video.resolution ]
		.filter( Boolean )
		.join( ' · ' );
}

/**
 * Text for a video that is not ready.
 *
 * @param {Object} video Video.
 * @return {string} Status text.
 */
export function statusText( video ) {
	switch ( video.status ) {
		case 'uploading':
			return __( 'Waiting for the upload', 'videoteca-for-mux' );
		case 'preparing':
			return __( 'Processing in Mux…', 'videoteca-for-mux' );
		case 'errored':
			return __( 'Failed', 'videoteca-for-mux' );
		default:
			return '';
	}
}

/**
 * The upload line under a progress bar.
 *
 * @param {Object} upload Upload from the store.
 * @return {string} Status text.
 */
export function uploadStatusText( upload ) {
	switch ( upload.phase ) {
		case 'creating':
			return __( 'Preparing upload…', 'videoteca-for-mux' );
		case 'uploading':
			return sprintf(
				/* translators: %d: percentage uploaded. */
				__( 'Uploading… %d%%', 'videoteca-for-mux' ),
				upload.progress
			);
		case 'processing':
			return __( 'Uploaded. Mux is processing it…', 'videoteca-for-mux' );
		case 'error':
			return upload.error;
		default:
			return '';
	}
}
