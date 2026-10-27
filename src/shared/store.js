/**
 * A small shared store for videos and uploads in progress.
 *
 * Library cards, the details dialog and the block all read from it, so a title
 * edited in one place or a status that changes in Mux shows up everywhere.
 */
import * as UpChunk from '@mux/upchunk';
import { useEffect, useState, useSyncExternalStore } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import {
	createUpload,
	deleteVideo,
	errorMessage,
	fetchVideo,
	refreshVideo,
	updateVideo,
} from './api';
import { titleFromFilename } from './utils';

const POLL_INTERVAL = 4000;

const videos = new Map();
const uploads = new Map();
const chunkUploads = new Map();
const watchers = new Map();
const listeners = new Set();

let uploadsSnapshot = [];
let nextUploadKey = 1;

function emit() {
	uploadsSnapshot = Array.from( uploads.values() );
	listeners.forEach( ( listener ) => listener() );
}

function subscribe( listener ) {
	listeners.add( listener );
	return () => listeners.delete( listener );
}

/* Videos ------------------------------------------------------------------ */

export function setVideo( video ) {
	storeVideo( video );
	emit();
	return video;
}

export function setVideos( list ) {
	list.forEach( storeVideo );
	emit();
}

function storeVideo( video ) {
	videos.set( video.id, video );

	// An upload is finished once Mux has finished with its video.
	if ( video.status !== 'ready' && video.status !== 'errored' ) {
		return;
	}
	for ( const [ key, upload ] of uploads ) {
		if ( upload.videoId === video.id && upload.phase === 'processing' ) {
			if ( video.status === 'ready' ) {
				uploads.delete( key );
			} else {
				uploads.set( key, {
					...upload,
					phase: 'error',
					error: video.error,
				} );
			}
		}
	}
}

export function getVideo( id ) {
	return videos.get( id );
}

export function forgetVideo( id ) {
	videos.delete( id );
	emit();
}

export function useStoredVideo( id ) {
	return useSyncExternalStore( subscribe, () =>
		id ? videos.get( id ) : undefined
	);
}

/**
 * Polls Mux for a video until it is ready or failed. Calls share one loop per video.
 *
 * @param {number} id Video ID.
 * @return {Function} Stops watching.
 */
export function watchVideo( id ) {
	const watcher = watchers.get( id );
	if ( watcher ) {
		watcher.count++;
	} else {
		watchers.set( id, { count: 1, timer: null } );
		schedulePoll( id );
	}

	return () => {
		const current = watchers.get( id );
		if ( ! current ) {
			return;
		}
		current.count--;
		if ( current.count <= 0 ) {
			clearTimeout( current.timer );
			watchers.delete( id );
		}
	};
}

function schedulePoll( id ) {
	const watcher = watchers.get( id );
	if ( ! watcher ) {
		return;
	}
	watcher.timer = setTimeout( async () => {
		try {
			const video = setVideo( await refreshVideo( id ) );
			if ( video.status === 'ready' || video.status === 'errored' ) {
				watchers.delete( id );
				return;
			}
		} catch ( error ) {
			// Stop when the site or Mux says no: the request is invalid, the
			// video is gone, the user lost access or the Mux account was
			// disconnected. Otherwise keep trying: Mux or the site may be
			// briefly unreachable.
			if ( [ 400, 401, 403, 404, 409 ].includes( error?.data?.status ) ) {
				watchers.delete( id );
				return;
			}
		}
		schedulePoll( id );
	}, POLL_INTERVAL );
}

/**
 * A library video, fetched when needed and kept fresh while Mux processes it.
 *
 * @param {number|undefined} id Video ID.
 * @return {{video: Object|undefined, error: string|null}} Video and load error.
 */
export function useVideo( id ) {
	const video = useStoredVideo( id );
	const [ error, setError ] = useState( null );

	useEffect( () => {
		setError( null );
		if ( ! id || getVideo( id ) ) {
			return;
		}
		let cancelled = false;
		fetchVideo( id )
			.then( ( fetched ) => ! cancelled && setVideo( fetched ) )
			.catch(
				( fetchError ) =>
					! cancelled && setError( errorMessage( fetchError ) )
			);
		return () => {
			cancelled = true;
		};
	}, [ id ] );

	// Uploads still sending their file are watched by the upload itself, once
	// the file has reached Mux. An abandoned upload is checked on request.
	const status = video?.status;
	useEffect( () => {
		if ( id && status === 'preparing' ) {
			return watchVideo( id );
		}
	}, [ id, status ] );

	return { video, error };
}

/**
 * Saves changes to a video and updates every view of it.
 *
 * @param {number} id      Video ID.
 * @param {Object} changes title, description or thumbnail_time.
 * @return {Promise<Object>} Updated video.
 */
export async function saveVideo( id, changes ) {
	return setVideo( await updateVideo( id, changes ) );
}

/* Uploads ----------------------------------------------------------------- */

function patchUpload( key, changes ) {
	const upload = uploads.get( key );
	if ( upload ) {
		uploads.set( key, { ...upload, ...changes } );
		emit();
	}
}

function findUploadForVideo( id ) {
	return uploadsSnapshot.find( ( upload ) => upload.videoId === id );
}

/**
 * Whether a file looks like something Mux can ingest.
 *
 * @param {File} file File.
 * @return {boolean} Whether to accept it.
 */
export function isMediaFile( file ) {
	if ( file.type ) {
		return (
			file.type.startsWith( 'video/' ) || file.type.startsWith( 'audio/' )
		);
	}
	return /\.(mp4|m4v|mov|mkv|webm|avi|wmv|flv|mpe?g|mts|m2ts|ts|3gp|mxf|mp3|m4a|wav|aac|flac|ogg)$/i.test(
		file.name
	);
}

/**
 * Starts uploading a file straight from the browser to Mux.
 *
 * The library entry is created first, with the title, so the title never
 * depends on what Mux does with the file.
 *
 * @param {File}     file                File to upload.
 * @param {Object}   options
 * @param {string}   [options.title]     Title. Defaults to the file name.
 * @param {Function} [options.onCreated] Called with the video once it is in the library.
 * @return {string} Upload key.
 */
export function startUpload( file, { title, onCreated } = {} ) {
	const key = `upload-${ nextUploadKey++ }`;
	uploads.set( key, {
		key,
		filename: file.name,
		size: file.size,
		title: title || titleFromFilename( file.name ),
		phase: 'creating',
		progress: 0,
		videoId: null,
		error: null,
	} );
	emit();

	runUpload( key, file, onCreated );
	return key;
}

async function runUpload( key, file, onCreated ) {
	let response;
	try {
		response = await createUpload( {
			filename: file.name,
			title: uploads.get( key )?.title,
		} );
	} catch ( error ) {
		patchUpload( key, { phase: 'error', error: errorMessage( error ) } );
		return;
	}

	const video = setVideo( response.video );

	// Cancelled while the upload address was being created.
	if ( ! uploads.has( key ) ) {
		deleteVideo( video.id, true ).catch( () => {} );
		forgetVideo( video.id );
		return;
	}

	patchUpload( key, { phase: 'uploading', videoId: video.id } );
	onCreated?.( video );

	// The title may have been edited while the entry was being created.
	const latestTitle = uploads.get( key )?.title;
	if ( latestTitle && latestTitle !== video.title ) {
		saveVideo( video.id, { title: latestTitle } ).catch( () => {} );
	}

	const chunkUpload = UpChunk.createUpload( {
		endpoint: response.upload_url,
		file,
		chunkSize: 30720, // KB; Mux recommends large chunks.
	} );
	chunkUploads.set( key, chunkUpload );

	chunkUpload.on( 'progress', ( event ) =>
		patchUpload( key, { progress: Math.round( event.detail ) } )
	);
	chunkUpload.on( 'error', ( event ) => {
		chunkUploads.delete( key );
		patchUpload( key, {
			phase: 'error',
			error:
				event.detail?.message ||
				__( 'The upload failed.', 'video-library-for-mux' ),
		} );
	} );
	chunkUpload.on( 'success', () => {
		chunkUploads.delete( key );
		patchUpload( key, { phase: 'processing', progress: 100 } );
		watchVideo( video.id );
	} );
}

/**
 * Renames an upload, and its library entry once there is one.
 *
 * @param {string} key   Upload key.
 * @param {string} title New title.
 * @return {Promise} Resolves once saved.
 */
export function renameUpload( key, title ) {
	const upload = uploads.get( key );
	if ( ! upload ) {
		return Promise.resolve();
	}
	patchUpload( key, { title } );
	if ( upload.videoId && title.trim() ) {
		return saveVideo( upload.videoId, { title } );
	}
	return Promise.resolve();
}

/**
 * Stops an upload, or clears a failed one, and removes its unfinished library entry.
 *
 * @param {string} key Upload key.
 */
export function cancelUpload( key ) {
	const upload = uploads.get( key );
	chunkUploads.get( key )?.abort();
	chunkUploads.delete( key );
	uploads.delete( key );
	emit();

	// A video whose file reached Mux is kept, even if processing failed.
	const fileReachedMux =
		upload?.phase === 'processing' ||
		getVideo( upload?.videoId )?.status === 'errored';
	if ( upload?.videoId && ! fileReachedMux ) {
		deleteVideo( upload.videoId, true ).catch( () => {} );
		forgetVideo( upload.videoId );
	}
}

export function useUploads() {
	return useSyncExternalStore( subscribe, () => uploadsSnapshot );
}

export function useUploadForVideo( id ) {
	return useSyncExternalStore( subscribe, () =>
		id ? findUploadForVideo( id ) : undefined
	);
}

export function useUpload( key ) {
	return useSyncExternalStore( subscribe, () =>
		key ? uploads.get( key ) : undefined
	);
}

// Leaving the page would stop uploads that have not reached Mux yet.
window.addEventListener( 'beforeunload', ( event ) => {
	if (
		uploadsSnapshot.some(
			( upload ) =>
				upload.phase === 'creating' || upload.phase === 'uploading'
		)
	) {
		event.preventDefault();
		event.returnValue = '';
	}
} );
