/**
 * Calls to the plugin's REST routes (/wp-json/vlfm/v1).
 */
import apiFetch from '@wordpress/api-fetch';
import { addQueryArgs } from '@wordpress/url';
import { __ } from '@wordpress/i18n';

const NAMESPACE = '/vlfm/v1';

/**
 * A page of library videos.
 *
 * @param {Object} query search, status, page, per_page, orderby, order.
 * @return {Promise<{videos: Object[], total: number, totalPages: number}>} Page of results.
 */
export async function fetchVideos( query ) {
	let response;
	try {
		response = await apiFetch( {
			path: addQueryArgs( `${ NAMESPACE }/videos`, query ),
			parse: false,
		} );
	} catch ( error ) {
		// With parse: false, apiFetch rejects with the raw Response.
		if ( typeof error?.json === 'function' ) {
			throw await error.json().catch( () => ( {} ) );
		}
		throw error;
	}
	return {
		videos: await response.json(),
		total: Number( response.headers.get( 'X-WP-Total' ) ) || 0,
		totalPages: Number( response.headers.get( 'X-WP-TotalPages' ) ) || 0,
	};
}

export const fetchVideo = ( id ) =>
	apiFetch( { path: `${ NAMESPACE }/videos/${ id }` } );

export const updateVideo = ( id, data ) =>
	apiFetch( { path: `${ NAMESPACE }/videos/${ id }`, method: 'POST', data } );

export const deleteVideo = ( id, deleteFromMux ) =>
	apiFetch( {
		path: addQueryArgs( `${ NAMESPACE }/videos/${ id }`, {
			delete_from_mux: deleteFromMux ? 1 : 0,
		} ),
		method: 'DELETE',
	} );

export const refreshVideo = ( id ) =>
	apiFetch( {
		path: `${ NAMESPACE }/videos/${ id }/refresh`,
		method: 'POST',
	} );

export const createUpload = ( data ) =>
	apiFetch( { path: `${ NAMESPACE }/uploads`, method: 'POST', data } );

export const importFromUrl = ( data ) =>
	apiFetch( { path: `${ NAMESPACE }/imports`, method: 'POST', data } );

export const syncFromMux = ( next ) =>
	apiFetch( { path: `${ NAMESPACE }/sync`, method: 'POST', data: { next } } );

/**
 * A readable message from an apiFetch rejection.
 *
 * @param {*} error Rejection value.
 * @return {string} Message.
 */
export function errorMessage( error ) {
	return (
		error?.message ||
		__( 'Something went wrong. Try again.', 'videoteca-for-mux' )
	);
}
