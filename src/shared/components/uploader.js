/**
 * Drop zone for new videos and the list of uploads in progress.
 */
import {
	Button,
	DropZone,
	FormFileUpload,
	TextControl,
} from '@wordpress/components';
import { useDispatch } from '@wordpress/data';
import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { store as noticesStore } from '@wordpress/notices';

import {
	cancelUpload,
	isMediaFile,
	renameUpload,
	startUpload,
	useUploads,
} from '../store';
import { errorMessage } from '../api';
import { formatBytes, uploadStatusText } from '../utils';
import ProgressBar from './progress-bar';

export const ACCEPT = 'video/*,audio/*';

/**
 * Starts uploads for the media files in a list, and reports the others.
 *
 * @param {FileList|File[]} fileList          Files.
 * @param {Function}        createErrorNotice Notice action.
 * @param {Object}          options           Options for startUpload().
 * @return {string[]} Upload keys.
 */
export function uploadFiles( fileList, createErrorNotice, options = {} ) {
	const keys = [];
	Array.from( fileList ).forEach( ( file ) => {
		if ( isMediaFile( file ) ) {
			keys.push( startUpload( file, options ) );
		} else {
			createErrorNotice(
				sprintf(
					/* translators: %s: file name. */
					__(
						'“%s” is not a video or audio file.',
						'video-library-for-mux'
					),
					file.name
				),
				{ type: 'snackbar' }
			);
		}
	} );
	return keys;
}

/**
 * The dashed "Drop video files to upload" area.
 *
 * @param {Object}   props
 * @param {Function} props.onFiles       Called with the chosen files.
 * @param {boolean}  [props.multiple]    Allow several files.
 * @param {Function} [props.onClose]     Shows a close button when given.
 * @param {boolean}  [props.hasDropZone] False when a parent already handles drops.
 */
export function UploadArea( {
	onFiles,
	multiple = true,
	onClose,
	hasDropZone = true,
} ) {
	return (
		<div className="vlfm-uploader">
			{ onClose && (
				<Button
					className="vlfm-uploader__close"
					icon="no-alt"
					label={ __( 'Close uploader', 'video-library-for-mux' ) }
					onClick={ onClose }
				/>
			) }
			{ hasDropZone && (
				<DropZone
					onFilesDrop={ onFiles }
					label={ __(
						'Drop files to upload to Mux',
						'video-library-for-mux'
					) }
				/>
			) }
			<p className="vlfm-uploader__title">
				{ multiple
					? __(
							'Drop video files to upload',
							'video-library-for-mux'
						)
					: __(
							'Drop a video file to upload',
							'video-library-for-mux'
						) }
			</p>
			<p>{ __( 'or', 'video-library-for-mux' ) }</p>
			<FormFileUpload
				accept={ ACCEPT }
				multiple={ multiple }
				onChange={ ( event ) => {
					onFiles( event.currentTarget.files );
					event.currentTarget.value = '';
				} }
				render={ ( { openFileDialog } ) => (
					<Button
						variant="secondary"
						onClick={ openFileDialog }
						__next40pxDefaultSize
					>
						{ multiple
							? __( 'Select files', 'video-library-for-mux' )
							: __( 'Select a file', 'video-library-for-mux' ) }
					</Button>
				) }
			/>
			<p className="vlfm-uploader__note">
				{ __(
					'Files go straight from your browser to Mux, so the WordPress upload size limit does not apply.',
					'video-library-for-mux'
				) }
			</p>
		</div>
	);
}

function UploadRow( { upload } ) {
	const [ title, setTitle ] = useState( upload.title );
	const { createErrorNotice } = useDispatch( noticesStore );
	const isActive =
		upload.phase === 'creating' || upload.phase === 'uploading';

	const commitTitle = () => {
		const trimmed = title.trim();
		if ( ! trimmed ) {
			setTitle( upload.title );
			return;
		}
		if ( trimmed !== upload.title ) {
			renameUpload( upload.key, trimmed ).catch( ( error ) =>
				createErrorNotice( errorMessage( error ), { type: 'snackbar' } )
			);
		}
	};

	return (
		<li className={ `vlfm-upload is-${ upload.phase }` }>
			<div className="vlfm-upload__main">
				<TextControl
					label={ __( 'Title', 'video-library-for-mux' ) }
					value={ title }
					onChange={ setTitle }
					onBlur={ commitTitle }
					onKeyDown={ ( event ) => {
						if ( event.key === 'Enter' ) {
							event.currentTarget.blur();
						}
					} }
					disabled={ upload.phase === 'error' }
					__nextHasNoMarginBottom
					__next40pxDefaultSize
				/>
				<p className="vlfm-upload__file">
					{ upload.filename } · { formatBytes( upload.size ) }
				</p>
				{ upload.phase !== 'error' && (
					<ProgressBar value={ upload.progress } />
				) }
				<p
					className={ `vlfm-upload__status${
						upload.phase === 'error' ? ' is-error' : ''
					}` }
					aria-live="polite"
				>
					{ uploadStatusText( upload ) }
				</p>
			</div>
			<div className="vlfm-upload__actions">
				{ isActive ? (
					<Button
						variant="tertiary"
						isDestructive
						onClick={ () => cancelUpload( upload.key ) }
					>
						{ __( 'Cancel', 'video-library-for-mux' ) }
					</Button>
				) : (
					<Button
						variant="tertiary"
						onClick={ () => cancelUpload( upload.key ) }
					>
						{ __( 'Remove', 'video-library-for-mux' ) }
					</Button>
				) }
			</div>
		</li>
	);
}

/**
 * Uploads that are still sending their file, or that failed.
 */
export function UploadList() {
	const uploads = useUploads().filter(
		( upload ) => upload.phase !== 'processing'
	);

	if ( ! uploads.length ) {
		return null;
	}

	return (
		<ul
			className="vlfm-uploads"
			aria-label={ __( 'Uploads', 'video-library-for-mux' ) }
		>
			{ uploads.map( ( upload ) => (
				<UploadRow key={ upload.key } upload={ upload } />
			) ) }
		</ul>
	);
}
