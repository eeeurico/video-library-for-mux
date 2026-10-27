/**
 * The "Video details" dialog: preview, title, thumbnail, IDs and delete.
 */
import {
	Button,
	CheckboxControl,
	Flex,
	Modal,
	Notice,
	Spinner,
	TextControl,
	TextareaControl,
} from '@wordpress/components';
import { useDispatch } from '@wordpress/data';
import { useEffect, useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { store as noticesStore } from '@wordpress/notices';

import { deleteVideo, errorMessage, refreshVideo } from '../api';
import {
	forgetVideo,
	saveVideo,
	setVideo,
	useUploadForVideo,
	useVideo,
} from '../store';
import {
	formatDate,
	formatDuration,
	formatTimestamp,
	uploadStatusText,
} from '../utils';
import CopyButton from './copy-button';
import MuxPlayer from './mux-player';
import ProgressBar from './progress-bar';

function Preview( { video, playerRef, onCheckStatus, isChecking } ) {
	const upload = useUploadForVideo( video.id );

	if ( video.status === 'ready' && video.playable ) {
		return (
			<div className="vlfm-details__player">
				<MuxPlayer ref={ playerRef } video={ video } />
			</div>
		);
	}

	let content;
	if ( upload && upload.phase !== 'processing' ) {
		content = (
			<>
				<p>{ uploadStatusText( upload ) }</p>
				<ProgressBar value={ upload.progress } />
			</>
		);
	} else if ( video.status === 'preparing' || upload ) {
		content = (
			<>
				<Spinner />
				<p>
					{ __(
						'Mux is processing this video. It will play here when it is ready.',
						'video-library-for-mux'
					) }
				</p>
			</>
		);
	} else if ( video.status === 'uploading' ) {
		content = (
			<>
				<p>
					{ __(
						'The file has not finished uploading. If the upload was stopped, delete this video and upload it again.',
						'video-library-for-mux'
					) }
				</p>
				<Button
					variant="secondary"
					onClick={ onCheckStatus }
					isBusy={ isChecking }
				>
					{ __( 'Check status', 'video-library-for-mux' ) }
				</Button>
			</>
		);
	} else if ( video.status === 'errored' ) {
		content = (
			<>
				<p>
					<strong>
						{ __(
							'Mux could not process this video.',
							'video-library-for-mux'
						) }
					</strong>
				</p>
				{ video.error && <p>{ video.error }</p> }
				<Button
					variant="secondary"
					onClick={ onCheckStatus }
					isBusy={ isChecking }
				>
					{ __( 'Check again', 'video-library-for-mux' ) }
				</Button>
			</>
		);
	} else {
		content = (
			<p>
				{ __(
					'This video uses signed playback. Add a signing key in Mux Videos → Settings to preview it.',
					'video-library-for-mux'
				) }
			</p>
		);
	}

	return <div className="vlfm-details__state">{ content }</div>;
}

function DetailsList( { video } ) {
	const rows = [
		[
			__( 'Added', 'video-library-for-mux' ),
			video.author
				? sprintf(
						/* translators: 1: date, 2: user name. */
						__( '%1$s by %2$s', 'video-library-for-mux' ),
						formatDate( video.date ),
						video.author
					)
				: formatDate( video.date ),
		],
		[ __( 'File name', 'video-library-for-mux' ), video.filename ],
		[
			__( 'Duration', 'video-library-for-mux' ),
			formatDuration( video.duration ),
		],
		[
			__( 'Resolution', 'video-library-for-mux' ),
			video.width && video.height
				? `${ video.width } × ${ video.height }`
				: video.resolution,
		],
		[ __( 'Aspect ratio', 'video-library-for-mux' ), video.aspect_ratio ],
		[ __( 'Quality', 'video-library-for-mux' ), video.video_quality ],
		[
			__( 'Playback', 'video-library-for-mux' ),
			video.playback_policy === 'signed'
				? __( 'Signed', 'video-library-for-mux' )
				: video.playback_policy &&
					__( 'Public', 'video-library-for-mux' ),
		],
		[
			__( 'Captions', 'video-library-for-mux' ),
			video.captions
				.map( ( track ) =>
					track.status === 'ready'
						? track.name || track.language
						: sprintf(
								/* translators: %s: captions track name. */
								__( '%s (preparing)', 'video-library-for-mux' ),
								track.name || track.language
							)
				)
				.join( ', ' ),
		],
	].filter( ( [ , value ] ) => value );

	return (
		<dl className="vlfm-meta">
			{ rows.map( ( [ label, value ] ) => (
				<div className="vlfm-meta__row" key={ label }>
					<dt>{ label }</dt>
					<dd>{ value }</dd>
				</div>
			) ) }
			{ video.downloads.length > 0 && (
				<div className="vlfm-meta__row">
					<dt>{ __( 'Download', 'video-library-for-mux' ) }</dt>
					<dd>
						{ video.downloads.map( ( file ) => (
							<a
								key={ file.name }
								href={ file.url }
								target="_blank"
								rel="noopener noreferrer"
							>
								{ file.name }
							</a>
						) ) }
					</dd>
				</div>
			) }
			{ [
				[ __( 'Asset ID', 'video-library-for-mux' ), video.asset_id ],
				[
					__( 'Playback ID', 'video-library-for-mux' ),
					video.playback_id,
				],
			]
				.filter( ( [ , value ] ) => value )
				.map( ( [ label, value ] ) => (
					<div className="vlfm-meta__row is-id" key={ label }>
						<dt>{ label }</dt>
						<dd>
							<code>{ value }</code>
							<CopyButton
								text={ value }
								label={ __( 'Copy', 'video-library-for-mux' ) }
							/>
						</dd>
					</div>
				) ) }
		</dl>
	);
}

function ThumbnailControl( { video, value, onChange, playerRef } ) {
	const canPick = video.status === 'ready' && video.playable;
	const isUnsaved = value !== video.thumbnail_time;

	let image = video.thumbnail;
	if ( isUnsaved ) {
		image =
			! video.tokens && video.playback_id && value !== null
				? `https://image.mux.com/${ video.playback_id }/thumbnail.webp?width=320&time=${ value }`
				: null;
	}

	return (
		<div className="vlfm-thumbnail">
			<p className="vlfm-field-label">
				{ __( 'Thumbnail', 'video-library-for-mux' ) }
			</p>
			<div className="vlfm-thumbnail__body">
				{ image ? (
					<img src={ image } alt="" />
				) : (
					<span className="vlfm-thumbnail__empty" />
				) }
				<div>
					<p className="vlfm-thumbnail__time">
						{ value === null
							? __( 'Chosen by Mux', 'video-library-for-mux' )
							: sprintf(
									/* translators: %s: time in the video, such as 0:12.4. */
									__(
										'Frame at %s',
										'video-library-for-mux'
									),
									formatTimestamp( value )
								) }
					</p>
					<Flex justify="flex-start" wrap>
						<Button
							variant="secondary"
							size="small"
							disabled={ ! canPick }
							onClick={ () => {
								const time =
									playerRef.current?.currentTime || 0;
								onChange( Math.round( time * 10 ) / 10 );
							} }
						>
							{ __(
								'Use current frame',
								'video-library-for-mux'
							) }
						</Button>
						{ value !== null && (
							<Button
								variant="tertiary"
								size="small"
								onClick={ () => onChange( null ) }
							>
								{ __( 'Reset', 'video-library-for-mux' ) }
							</Button>
						) }
					</Flex>
				</div>
			</div>
			{ canPick && (
				<p className="description">
					{ __(
						'Pause the video on the frame you want, then use it as the thumbnail.',
						'video-library-for-mux'
					) }
				</p>
			) }
		</div>
	);
}

/**
 * @param {Object}   props
 * @param {number}   props.id      Video ID.
 * @param {Function} props.onClose Closes the dialog.
 */
export default function VideoDetails( { id, onClose } ) {
	const { video, error } = useVideo( id );
	const playerRef = useRef();
	const [ title, setTitle ] = useState( '' );
	const [ description, setDescription ] = useState( '' );
	const [ thumbnailTime, setThumbnailTime ] = useState( null );
	const [ isSaving, setIsSaving ] = useState( false );
	const [ isChecking, setIsChecking ] = useState( false );
	const [ isConfirmingDelete, setIsConfirmingDelete ] = useState( false );
	const [ deleteFromMux, setDeleteFromMux ] = useState( true );
	const [ isDeleting, setIsDeleting ] = useState( false );
	const { createSuccessNotice, createErrorNotice } =
		useDispatch( noticesStore );

	const loadedId = video?.id;
	useEffect( () => {
		if ( video ) {
			setTitle( video.title );
			setDescription( video.description );
			setThumbnailTime( video.thumbnail_time );
		}
		// Only reset the form when another video is opened.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ loadedId ] );

	const isDirty =
		video &&
		( title !== video.title ||
			description !== video.description ||
			thumbnailTime !== video.thumbnail_time );

	const requestClose = () => {
		if (
			isDirty &&
			// eslint-disable-next-line no-alert
			! window.confirm(
				__(
					'You have unsaved changes. Close without saving?',
					'video-library-for-mux'
				)
			)
		) {
			return;
		}
		onClose();
	};

	const save = async () => {
		if ( ! title.trim() ) {
			createErrorNotice(
				__( 'Give the video a title.', 'video-library-for-mux' ),
				{
					type: 'snackbar',
				}
			);
			return;
		}
		setIsSaving( true );
		try {
			const saved = await saveVideo( id, {
				title: title.trim(),
				description,
				thumbnail_time: thumbnailTime,
			} );
			setTitle( saved.title );
			createSuccessNotice(
				__( 'Video saved.', 'video-library-for-mux' ),
				{
					type: 'snackbar',
				}
			);
		} catch ( saveError ) {
			createErrorNotice( errorMessage( saveError ), {
				type: 'snackbar',
			} );
		} finally {
			setIsSaving( false );
		}
	};

	const checkStatus = async () => {
		setIsChecking( true );
		try {
			setVideo( await refreshVideo( id ) );
		} catch ( checkError ) {
			createErrorNotice( errorMessage( checkError ), {
				type: 'snackbar',
			} );
		} finally {
			setIsChecking( false );
		}
	};

	const remove = async () => {
		setIsDeleting( true );
		try {
			await deleteVideo( id, deleteFromMux );
			forgetVideo( id );
			createSuccessNotice(
				deleteFromMux
					? __(
							'Video deleted from the library and from Mux.',
							'video-library-for-mux'
						)
					: __(
							'Video removed from the library. It is still in Mux.',
							'video-library-for-mux'
						),
				{ type: 'snackbar' }
			);
			onClose();
		} catch ( deleteError ) {
			createErrorNotice( errorMessage( deleteError ), {
				type: 'snackbar',
			} );
			setIsDeleting( false );
		}
	};

	return (
		<Modal
			title={ __( 'Video details', 'video-library-for-mux' ) }
			onRequestClose={ requestClose }
			className="vlfm-details-modal"
			size="large"
		>
			{ ! video && ! error && <Spinner /> }
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }
			{ video && (
				<>
					<div className="vlfm-details">
						<div className="vlfm-details__preview">
							<Preview
								video={ video }
								playerRef={ playerRef }
								onCheckStatus={ checkStatus }
								isChecking={ isChecking }
							/>
						</div>
						<div className="vlfm-details__fields">
							<TextControl
								label={ __( 'Title', 'video-library-for-mux' ) }
								help={ __(
									'Shown in the library, the player and your Mux dashboard. Search uses it too.',
									'video-library-for-mux'
								) }
								value={ title }
								onChange={ setTitle }
								disabled={ ! video.can_edit }
								__nextHasNoMarginBottom
								__next40pxDefaultSize
							/>
							<TextareaControl
								label={ __(
									'Description',
									'video-library-for-mux'
								) }
								help={ __(
									'Notes for your team. It is searchable but not shown to visitors.',
									'video-library-for-mux'
								) }
								value={ description }
								onChange={ setDescription }
								disabled={ ! video.can_edit }
								rows={ 3 }
								__nextHasNoMarginBottom
							/>
							{ video.can_edit && (
								<ThumbnailControl
									video={ video }
									value={ thumbnailTime }
									onChange={ setThumbnailTime }
									playerRef={ playerRef }
								/>
							) }
							<DetailsList video={ video } />
							<div className="vlfm-shortcode">
								<p className="vlfm-field-label">
									{ __(
										'Shortcode',
										'video-library-for-mux'
									) }
								</p>
								<div className="vlfm-shortcode__row">
									<code>{ video.shortcode }</code>
									<CopyButton
										text={ video.shortcode }
										label={ __(
											'Copy',
											'video-library-for-mux'
										) }
									/>
								</div>
							</div>
						</div>
					</div>

					<div className="vlfm-details__footer">
						{ isConfirmingDelete ? (
							<div className="vlfm-confirm-delete">
								<p>
									<strong>
										{ sprintf(
											/* translators: %s: video title. */
											__(
												'Delete “%s”?',
												'video-library-for-mux'
											),
											video.title
										) }
									</strong>
								</p>
								<CheckboxControl
									label={ __(
										'Also delete it from Mux. This cannot be undone, and pages using it will stop playing it.',
										'video-library-for-mux'
									) }
									checked={ deleteFromMux }
									onChange={ setDeleteFromMux }
									__nextHasNoMarginBottom
								/>
								<Flex justify="flex-start">
									<Button
										variant="primary"
										isDestructive
										onClick={ remove }
										isBusy={ isDeleting }
										disabled={ isDeleting }
									>
										{ __(
											'Delete',
											'video-library-for-mux'
										) }
									</Button>
									<Button
										variant="tertiary"
										onClick={ () =>
											setIsConfirmingDelete( false )
										}
									>
										{ __(
											'Cancel',
											'video-library-for-mux'
										) }
									</Button>
								</Flex>
							</div>
						) : (
							<>
								{ video.can_delete ? (
									<Button
										variant="link"
										isDestructive
										onClick={ () =>
											setIsConfirmingDelete( true )
										}
									>
										{ __(
											'Delete…',
											'video-library-for-mux'
										) }
									</Button>
								) : (
									<span />
								) }
								{ video.can_edit && (
									<Button
										variant="primary"
										onClick={ save }
										isBusy={ isSaving }
										disabled={ ! isDirty || isSaving }
										__next40pxDefaultSize
									>
										{ __(
											'Save',
											'video-library-for-mux'
										) }
									</Button>
								) }
							</>
						) }
					</div>
				</>
			) }
		</Modal>
	);
}
