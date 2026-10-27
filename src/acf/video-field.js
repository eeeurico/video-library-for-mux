/**
 * The picker inside an ACF Mux Video field, laid out like ACF's own image field.
 */
import { DropZone, Icon, Notice, Spinner } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import LibraryModal from '../shared/components/library-modal';
import ProgressBar from '../shared/components/progress-bar';
import { uploadFiles } from '../shared/components/uploader';
import {
	cancelUpload,
	setVideo,
	useUpload,
	useUploadForVideo,
	useVideo,
} from '../shared/store';
import { formatDuration, statusText, uploadStatusText } from '../shared/utils';

const config = window.vlfmAcf || {};
const canUpload = Boolean( config.isConfigured && config.canUpload );

// Uploads that have not reached Mux yet can still be cancelled.
const isSending = ( upload ) =>
	upload?.phase === 'creating' || upload?.phase === 'uploading';

function Media( { video, error, upload } ) {
	const [ isActive, setIsActive ] = useState( false );

	if ( ! video ) {
		return (
			<div className="vlfm-acf-field__media">
				{ error ? (
					<span>
						{ __(
							'This video is no longer in the video library.',
							'video-library-for-mux'
						) }
					</span>
				) : (
					<Spinner />
				) }
			</div>
		);
	}

	if ( upload && upload.phase !== 'processing' ) {
		return (
			<div
				className={ `vlfm-acf-field__media${
					upload.phase === 'error' ? ' is-error' : ''
				}` }
			>
				{ upload.phase !== 'error' && (
					<ProgressBar value={ upload.progress } />
				) }
				<span>{ uploadStatusText( upload ) }</span>
			</div>
		);
	}

	if ( video.status !== 'ready' || upload ) {
		const status = upload ? 'preparing' : video.status;
		return (
			<div
				className={ `vlfm-acf-field__media${
					status === 'errored' ? ' is-error' : ''
				}` }
			>
				{ status === 'preparing' && <Spinner /> }
				{ status === 'errored' && <Icon icon="warning" /> }
				<span>
					{ status === 'errored'
						? __(
								'Mux could not process this video.',
								'video-library-for-mux'
							)
						: statusText( { status } ) }
				</span>
			</div>
		);
	}

	if ( ! video.playable ) {
		return (
			<div className="vlfm-acf-field__media">
				<Icon icon="lock" />
				<span>
					{ __( 'Needs a signing key', 'video-library-for-mux' ) }
				</span>
			</div>
		);
	}

	const image = isActive && video.preview ? video.preview : video.thumbnail;
	return (
		<div
			className="vlfm-acf-field__media has-image"
			onMouseEnter={ () => setIsActive( true ) }
			onMouseLeave={ () => setIsActive( false ) }
		>
			{ image && <img src={ image } alt="" /> }
			{ video.duration > 0 && (
				<span className="vlfm-acf-field__duration">
					{ formatDuration( video.duration ) }
				</span>
			) }
		</div>
	);
}

/**
 * @param {Object}      props
 * @param {number|null} props.initialId The saved video.
 * @param {Function}    props.onChange  Called with the new video ID, or null.
 */
export default function VideoField( { initialId, onChange } ) {
	const [ videoId, setVideoId ] = useState( initialId );
	const [ modalTab, setModalTab ] = useState( null );
	const [ pendingUploadKey, setPendingUploadKey ] = useState( null );
	const [ notice, setNotice ] = useState( null );
	const pendingUpload = useUpload( pendingUploadKey );
	const videoUpload = useUploadForVideo( videoId );
	const { video, error } = useVideo( videoId );

	const change = ( id ) => {
		setVideoId( id );
		onChange( id );
	};

	const upload = ( files ) => {
		setModalTab( null );
		setNotice( null );
		const [ key ] = uploadFiles(
			Array.from( files ).slice( 0, 1 ),
			setNotice,
			{ onCreated: ( created ) => change( created.id ) }
		);
		if ( key ) {
			setPendingUploadKey( key );
		}
	};

	const select = ( chosen ) => {
		setVideo( chosen );
		setPendingUploadKey( null );
		setModalTab( null );
		setNotice( null );
		change( chosen.id );
	};

	const remove = () => {
		if ( isSending( videoUpload ) ) {
			cancelUpload( videoUpload.key );
		}
		setPendingUploadKey( null );
		change( null );
	};

	const failedUpload =
		! videoId && pendingUpload?.phase === 'error' ? pendingUpload : null;

	return (
		<div className="vlfm-acf-field">
			{ config.canBrowse && canUpload && (
				<DropZone onFilesDrop={ upload } />
			) }

			{ ( notice || failedUpload ) && (
				<Notice
					status="error"
					onRemove={ () => {
						setNotice( null );
						setPendingUploadKey( null );
					} }
				>
					{ notice || failedUpload.error }
				</Notice>
			) }

			{ videoId ? (
				<div className="vlfm-acf-field__video">
					<Media
						video={ video }
						error={ error }
						upload={ videoUpload }
					/>
					<div className="vlfm-acf-field__details">
						<strong className="vlfm-acf-field__title">
							{ video?.title || videoUpload?.title }
						</strong>
						{ video?.status === 'ready' && (
							<span className="vlfm-acf-field__meta">
								{ [
									formatDuration( video.duration ),
									video.resolution,
								]
									.filter( Boolean )
									.join( ' · ' ) }
							</span>
						) }
						{ config.canBrowse && (
							<span className="vlfm-acf-field__actions">
								{ isSending( videoUpload ) ? (
									<button
										type="button"
										className="button"
										onClick={ remove }
									>
										{ __(
											'Cancel upload',
											'video-library-for-mux'
										) }
									</button>
								) : (
									<>
										<button
											type="button"
											className="button"
											onClick={ () =>
												setModalTab( 'library' )
											}
										>
											{ __(
												'Replace',
												'video-library-for-mux'
											) }
										</button>
										<button
											type="button"
											className="button-link button-link-delete"
											onClick={ remove }
										>
											{ __(
												'Remove',
												'video-library-for-mux'
											) }
										</button>
									</>
								) }
							</span>
						) }
					</div>
				</div>
			) : (
				<p className="vlfm-acf-field__empty">
					{ pendingUpload?.phase === 'creating' ? (
						<>
							<Spinner />
							{ uploadStatusText( pendingUpload ) }
						</>
					) : (
						<>
							{ __(
								'No video selected',
								'video-library-for-mux'
							) }
							{ config.canBrowse && (
								<button
									type="button"
									className="button"
									onClick={ () => setModalTab( 'library' ) }
								>
									{ __(
										'Add Video',
										'video-library-for-mux'
									) }
								</button>
							) }
						</>
					) }
				</p>
			) }

			{ config.canBrowse && ! config.isConfigured && (
				<p className="vlfm-acf-field__hint">
					{ __(
						'Connect a Mux account to upload videos.',
						'video-library-for-mux'
					) }{ ' ' }
					{ config.settingsUrl && (
						<a href={ config.settingsUrl }>
							{ __(
								'Open Mux Video settings',
								'video-library-for-mux'
							) }
						</a>
					) }
				</p>
			) }

			{ modalTab && (
				<LibraryModal
					initialTab={ modalTab }
					selectedId={ videoId }
					canUpload={ canUpload }
					onSelect={ select }
					onUpload={ upload }
					onClose={ () => setModalTab( null ) }
				/>
			) }
		</div>
	);
}
