/**
 * One video in the library grid.
 */
import { Icon, Spinner, TextHighlight } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

import { useUploadForVideo, useVideo } from '../store';
import { formatDuration, statusText, videoSummary } from '../utils';
import ProgressBar from './progress-bar';

function CardState( { video, upload } ) {
	if (
		upload &&
		( upload.phase === 'creating' || upload.phase === 'uploading' )
	) {
		return (
			<span className="vlfm-card__state">
				<span>
					{ sprintf(
						/* translators: %d: percentage uploaded. */
						__( 'Uploading %d%%', 'videoteca-for-mux' ),
						upload.progress
					) }
				</span>
				<ProgressBar value={ upload.progress } />
			</span>
		);
	}

	if ( video.status === 'preparing' || upload?.phase === 'processing' ) {
		return (
			<span className="vlfm-card__state">
				<Spinner />
				<span>{ statusText( { status: 'preparing' } ) }</span>
			</span>
		);
	}

	if ( video.status === 'uploading' ) {
		return (
			<span className="vlfm-card__state">
				<Icon icon="upload" />
				<span>
					{ __( 'Upload not finished', 'videoteca-for-mux' ) }
				</span>
			</span>
		);
	}

	if ( video.status === 'errored' ) {
		return (
			<span className="vlfm-card__state is-error">
				<Icon icon="warning" />
				<span>{ statusText( video ) }</span>
			</span>
		);
	}

	if ( ! video.playable ) {
		return (
			<span className="vlfm-card__state">
				<Icon icon="lock" />
				<span>
					{ __( 'Needs a signing key', 'videoteca-for-mux' ) }
				</span>
			</span>
		);
	}

	return null;
}

/**
 * @param {Object}   props
 * @param {number}   props.id              Video ID.
 * @param {string}   [props.highlight]     Search terms to highlight in the title.
 * @param {boolean}  [props.isSelected]    Selection state; undefined outside select mode.
 * @param {Function} [props.onClick]       Called with the video.
 * @param {Function} [props.onDoubleClick] Called with the video.
 */
export default function VideoCard( {
	id,
	highlight,
	isSelected,
	onClick,
	onDoubleClick,
} ) {
	const { video } = useVideo( id );
	const upload = useUploadForVideo( id );
	const [ isActive, setIsActive ] = useState( false );

	if ( ! video ) {
		return null;
	}

	// Failed videos cannot be inserted.
	const isDisabled = isSelected !== undefined && video.status === 'errored';

	const image = isActive && video.preview ? video.preview : video.thumbnail;
	const duration = formatDuration( video.duration );

	return (
		<li
			className={ [
				'vlfm-card',
				isSelected && 'is-selected',
				isDisabled && 'is-disabled',
			]
				.filter( Boolean )
				.join( ' ' ) }
		>
			<button
				type="button"
				className="vlfm-card__button"
				aria-pressed={
					isSelected === undefined ? undefined : isSelected
				}
				aria-disabled={ isDisabled || undefined }
				onClick={ () => ! isDisabled && onClick?.( video ) }
				onDoubleClick={ () => ! isDisabled && onDoubleClick?.( video ) }
				onMouseEnter={ () => setIsActive( true ) }
				onMouseLeave={ () => setIsActive( false ) }
				onFocus={ () => setIsActive( true ) }
				onBlur={ () => setIsActive( false ) }
			>
				<span className="vlfm-card__media">
					{ image && <img src={ image } alt="" loading="lazy" /> }
					{ ! image && video.status === 'ready' && (
						<span className="vlfm-card__placeholder">
							<Icon icon="video-alt3" size={ 32 } />
						</span>
					) }
					{ duration && video.status === 'ready' && (
						<span className="vlfm-card__duration">
							{ duration }
						</span>
					) }
					<CardState video={ video } upload={ upload } />
					{ isSelected && (
						<span className="vlfm-card__check">
							<Icon icon="yes" />
						</span>
					) }
				</span>
				<span className="vlfm-card__body">
					<span className="vlfm-card__title">
						<TextHighlight
							text={ video.title }
							highlight={ highlight || '' }
						/>
					</span>
					<span className="vlfm-card__meta">
						{ videoSummary( video ) }
					</span>
				</span>
			</button>
		</li>
	);
}
