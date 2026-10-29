/**
 * Form that asks Mux to fetch a video from a URL.
 */
import { Button, Notice, TextControl } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import { errorMessage, importFromUrl } from '../api';
import { setVideo, watchVideo } from '../store';
import { titleFromFilename } from '../utils';

function titleFromUrl( url ) {
	try {
		const name = decodeURIComponent(
			new URL( url ).pathname.split( '/' ).pop() || ''
		);
		return name ? titleFromFilename( name ) : '';
	} catch {
		return '';
	}
}

/**
 * @param {Object}   props
 * @param {Function} props.onImported    Called with the new video.
 * @param {string}   [props.submitLabel] Button text.
 */
export default function ImportUrlForm( { onImported, submitLabel } ) {
	const [ url, setUrl ] = useState( '' );
	const [ title, setTitle ] = useState( '' );
	const [ isImporting, setIsImporting ] = useState( false );
	const [ error, setError ] = useState( null );

	const submit = async ( event ) => {
		event.preventDefault();
		setIsImporting( true );
		setError( null );
		try {
			const video = setVideo(
				await importFromUrl( {
					url: url.trim(),
					title: title.trim() || titleFromUrl( url.trim() ),
				} )
			);
			watchVideo( video.id );
			setUrl( '' );
			setTitle( '' );
			onImported( video );
		} catch ( importError ) {
			setError( errorMessage( importError ) );
		} finally {
			setIsImporting( false );
		}
	};

	return (
		<form className="vlfm-import" onSubmit={ submit }>
			<p>
				{ __(
					'Mux downloads the file from this address, so it has to be publicly reachable. Links to pages, such as YouTube, do not work.',
					'videoteca-for-mux'
				) }
			</p>
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }
			<TextControl
				type="url"
				label={ __( 'Video file URL', 'videoteca-for-mux' ) }
				placeholder="https://example.com/video.mp4"
				value={ url }
				onChange={ setUrl }
				required
				__nextHasNoMarginBottom
				__next40pxDefaultSize
			/>
			<TextControl
				label={ __( 'Title', 'videoteca-for-mux' ) }
				placeholder={ titleFromUrl( url ) }
				help={ __(
					'Leave it empty to use the file name.',
					'videoteca-for-mux'
				) }
				value={ title }
				onChange={ setTitle }
				__nextHasNoMarginBottom
				__next40pxDefaultSize
			/>
			<Button
				type="submit"
				variant="primary"
				isBusy={ isImporting }
				disabled={ isImporting || ! url.trim() }
				__next40pxDefaultSize
			>
				{ submitLabel || __( 'Import', 'videoteca-for-mux' ) }
			</Button>
		</form>
	);
}
