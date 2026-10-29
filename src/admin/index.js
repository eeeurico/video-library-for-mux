/**
 * Mux Videos → Library: the library screen.
 */
import { Button, DropZone, Modal, Notice } from '@wordpress/components';
import { useDispatch } from '@wordpress/data';
import domReady from '@wordpress/dom-ready';
import { createPortal, createRoot, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { store as noticesStore } from '@wordpress/notices';

import { errorMessage, syncFromMux } from '../shared/api';
import ImportUrlForm from '../shared/components/import-url-form';
import Snackbars from '../shared/components/snackbars';
import {
	UploadArea,
	UploadList,
	uploadFiles,
} from '../shared/components/uploader';
import VideoBrowser from '../shared/components/video-browser';
import VideoDetails from '../shared/components/video-details';
import './admin.scss';

const config = window.vlfmLibrary || {};
const canUpload = config.isConfigured && config.canUpload;
const canSync = config.isConfigured && config.canSync;

function useSync( onDone ) {
	const [ progress, setProgress ] = useState( null );
	const { createSuccessNotice, createErrorNotice } =
		useDispatch( noticesStore );

	const run = async () => {
		let next = '';
		let checked = 0;
		let created = 0;
		setProgress( { checked, created } );
		try {
			do {
				const result = await syncFromMux( next );
				checked += result.checked;
				created += result.created;
				setProgress( { checked, created } );
				next = result.next;
			} while ( next );

			createSuccessNotice(
				created
					? sprintf(
							/* translators: %d: number of videos added. */
							_n(
								'Sync finished. %d video was added to the library.',
								'Sync finished. %d videos were added to the library.',
								created,
								'videoteca-for-mux'
							),
							created
						)
					: __(
							'Sync finished. The library already had every video in your Mux account.',
							'videoteca-for-mux'
						),
				{ type: 'snackbar' }
			);
			onDone();
		} catch ( error ) {
			createErrorNotice( errorMessage( error ), { type: 'snackbar' } );
		} finally {
			setProgress( null );
		}
	};

	return [ progress, run ];
}

function LibraryApp() {
	const [ isUploaderOpen, setIsUploaderOpen ] = useState( false );
	const [ isImportOpen, setIsImportOpen ] = useState( false );
	const [ openVideoId, setOpenVideoId ] = useState( null );
	const [ addedIds, setAddedIds ] = useState( [] );
	const [ reloadToken, setReloadToken ] = useState( 0 );
	const { createErrorNotice, createSuccessNotice } =
		useDispatch( noticesStore );
	const [ syncProgress, runSync ] = useSync( () =>
		setReloadToken( ( token ) => token + 1 )
	);

	const onFiles = ( files ) => uploadFiles( files, createErrorNotice );

	const headerActions = (
		<>
			{ canUpload && (
				<button
					type="button"
					className="page-title-action"
					aria-expanded={ isUploaderOpen }
					onClick={ () => setIsUploaderOpen( ! isUploaderOpen ) }
				>
					{ __( 'Upload video', 'videoteca-for-mux' ) }
				</button>
			) }
			{ canUpload && (
				<button
					type="button"
					className="page-title-action"
					onClick={ () => setIsImportOpen( true ) }
				>
					{ __( 'Import from URL', 'videoteca-for-mux' ) }
				</button>
			) }
			{ canSync && (
				<button
					type="button"
					className="page-title-action"
					onClick={ runSync }
					disabled={ !! syncProgress }
				>
					{ __( 'Sync from Mux', 'videoteca-for-mux' ) }
				</button>
			) }
		</>
	);

	const emptyActions = canUpload ? (
		<div className="vlfm-browser__empty-actions">
			<Button
				variant="primary"
				onClick={ () => setIsUploaderOpen( true ) }
				__next40pxDefaultSize
			>
				{ __( 'Upload a video', 'videoteca-for-mux' ) }
			</Button>
			{ canSync && (
				<Button
					variant="secondary"
					onClick={ runSync }
					__next40pxDefaultSize
				>
					{ __(
						'Add the videos already in your Mux account',
						'videoteca-for-mux'
					) }
				</Button>
			) }
		</div>
	) : null;

	const actionsRoot = document.getElementById( 'vlfm-library-actions' );

	return (
		<div className="vlfm-library">
			{ actionsRoot && createPortal( headerActions, actionsRoot ) }

			{ canUpload && <DropZone onFilesDrop={ onFiles } /> }

			{ syncProgress && (
				<Notice status="info" isDismissible={ false }>
					{ sprintf(
						/* translators: 1: videos checked so far, 2: videos added so far. */
						__(
							'Checking your Mux account… %1$d checked, %2$d added.',
							'videoteca-for-mux'
						),
						syncProgress.checked,
						syncProgress.created
					) }
				</Notice>
			) }

			{ isUploaderOpen && canUpload && (
				<UploadArea
					onFiles={ onFiles }
					onClose={ () => setIsUploaderOpen( false ) }
					hasDropZone={ false }
				/>
			) }

			<UploadList />

			<VideoBrowser
				onSelect={ ( video ) => setOpenVideoId( video.id ) }
				addedIds={ addedIds }
				reloadToken={ reloadToken }
				emptyActions={ emptyActions }
			/>

			{ openVideoId && (
				<VideoDetails
					id={ openVideoId }
					onClose={ () => setOpenVideoId( null ) }
				/>
			) }

			{ isImportOpen && (
				<Modal
					title={ __( 'Import from URL', 'videoteca-for-mux' ) }
					onRequestClose={ () => setIsImportOpen( false ) }
					className="vlfm-import-modal"
				>
					<ImportUrlForm
						onImported={ ( video ) => {
							setAddedIds( ( ids ) => [ video.id, ...ids ] );
							setIsImportOpen( false );
							createSuccessNotice(
								sprintf(
									/* translators: %s: video title. */
									__(
										'Mux is fetching “%s”. It appears in the library while it processes.',
										'videoteca-for-mux'
									),
									video.title
								),
								{ type: 'snackbar' }
							);
						} }
					/>
				</Modal>
			) }

			<Snackbars />
		</div>
	);
}

domReady( () => {
	const container = document.getElementById( 'vlfm-library' );
	if ( container ) {
		createRoot( container ).render( <LibraryApp /> );
	}
} );
