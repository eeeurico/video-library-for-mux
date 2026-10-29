/**
 * The dialog the block and the ACF field open to pick, upload or import a video.
 */
import { Button, Modal, TabPanel } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import ImportUrlForm from './import-url-form';
import { UploadArea } from './uploader';
import VideoBrowser from './video-browser';
import { getVideo } from '../store';

/**
 * @param {Object}   props
 * @param {string}   props.initialTab   Tab to open: library, upload or url.
 * @param {number}   [props.selectedId] The current video.
 * @param {boolean}  props.canUpload    Whether to offer uploads and imports.
 * @param {Function} props.onSelect     Called with the chosen video.
 * @param {Function} props.onUpload     Called with the files to upload.
 * @param {Function} props.onClose      Closes the dialog.
 */
export default function LibraryModal( {
	initialTab,
	selectedId,
	canUpload,
	onSelect,
	onUpload,
	onClose,
} ) {
	const [ selected, setSelected ] = useState( () =>
		selectedId ? getVideo( selectedId ) : null
	);

	const tabs = [
		{
			name: 'library',
			title: __( 'Video library', 'videoteca-for-mux' ),
		},
	];
	if ( canUpload ) {
		tabs.push(
			{ name: 'upload', title: __( 'Upload', 'videoteca-for-mux' ) },
			{
				name: 'url',
				title: __( 'Import from URL', 'videoteca-for-mux' ),
			}
		);
	}

	return (
		<Modal
			title={ __( 'Mux Video', 'videoteca-for-mux' ) }
			onRequestClose={ onClose }
			className="vlfm-library-modal"
			size="fill"
		>
			<TabPanel
				className="vlfm-library-modal__tabs"
				tabs={ tabs }
				initialTabName={ initialTab }
			>
				{ ( tab ) => {
					if ( tab.name === 'upload' ) {
						return (
							<div className="vlfm-library-modal__panel">
								<UploadArea
									multiple={ false }
									onFiles={ onUpload }
								/>
							</div>
						);
					}

					if ( tab.name === 'url' ) {
						return (
							<div className="vlfm-library-modal__panel">
								<ImportUrlForm
									onImported={ onSelect }
									submitLabel={ __(
										'Import and insert',
										'videoteca-for-mux'
									) }
								/>
							</div>
						);
					}

					return (
						<>
							<div className="vlfm-library-modal__panel">
								<VideoBrowser
									mode="select"
									selectedId={ selected?.id }
									onSelect={ setSelected }
									onActivate={ onSelect }
								/>
							</div>
							<div className="vlfm-library-modal__footer">
								<span className="vlfm-library-modal__selection">
									{ selected?.title }
								</span>
								<Button
									variant="primary"
									disabled={ ! selected }
									onClick={ () => onSelect( selected ) }
									__next40pxDefaultSize
								>
									{ __(
										'Use this video',
										'videoteca-for-mux'
									) }
								</Button>
							</div>
						</>
					);
				} }
			</TabPanel>
		</Modal>
	);
}
