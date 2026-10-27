/**
 * Editor view of the Mux Video block.
 */
import {
	BlockControls,
	BlockIcon,
	InspectorControls,
	MediaUpload,
	MediaUploadCheck,
	PanelColorSettings,
	useBlockProps,
} from '@wordpress/block-editor';
import {
	BaseControl,
	Button,
	DropZone,
	ExternalLink,
	FormFileUpload,
	Notice,
	PanelBody,
	Placeholder,
	SelectControl,
	Spinner,
	TextControl,
	ToggleControl,
	ToolbarButton,
} from '@wordpress/components';
import { store as coreStore } from '@wordpress/core-data';
import { useDispatch, useSelect } from '@wordpress/data';
import { useEffect, useState } from '@wordpress/element';
import { __, _x, sprintf } from '@wordpress/i18n';
import { store as noticesStore } from '@wordpress/notices';

import { errorMessage } from '../shared/api';
import MuxPlayer from '../shared/components/mux-player';
import ProgressBar from '../shared/components/progress-bar';
import { ACCEPT, uploadFiles } from '../shared/components/uploader';
import {
	cancelUpload,
	saveVideo,
	setVideo,
	useUpload,
	useUploadForVideo,
	useVideo,
} from '../shared/store';
import { formatDuration, uploadStatusText } from '../shared/utils';
import LibraryModal from '../shared/components/library-modal';

const config = window.vlfmEditor || {};
const canUpload = Boolean( config.isConfigured && config.canUpload );

const SLOWER_SPEEDS = '0.5 0.75 1 1.25 1.5 1.75 2';

const SPEED_OPTIONS = [
	{
		value: '',
		label: __( 'Standard: 1× to 2×', 'video-library-for-mux' ),
	},
	{
		value: SLOWER_SPEEDS,
		label: __( 'With slower speeds: 0.5× to 2×', 'video-library-for-mux' ),
	},
	{ value: 'off', label: __( 'Hidden', 'video-library-for-mux' ) },
];

const PRELOAD_OPTIONS = [
	{ value: '', label: __( 'Browser default', 'video-library-for-mux' ) },
	{ value: 'auto', label: __( 'Auto', 'video-library-for-mux' ) },
	{ value: 'metadata', label: __( 'Metadata', 'video-library-for-mux' ) },
	{
		value: 'none',
		label: _x( 'None', 'preload value', 'video-library-for-mux' ),
	},
];

const RESOLUTION_OPTIONS = [
	{ value: '', label: __( 'No limit', 'video-library-for-mux' ) },
	{ value: '720p', label: '720p' },
	{ value: '1080p', label: '1080p' },
	{ value: '1440p', label: '1440p' },
	{ value: '2160p', label: __( '2160p (4K)', 'video-library-for-mux' ) },
];

/**
 * Speed menu choices, plus the current value when it is a custom list from a shortcode.
 *
 * @param {string} value Current value.
 * @return {Array} Options.
 */
function speedOptions( value ) {
	if ( SPEED_OPTIONS.some( ( option ) => option.value === value ) ) {
		return SPEED_OPTIONS;
	}
	return [
		...SPEED_OPTIONS,
		{
			value,
			label: sprintf(
				/* translators: %s: list of playback speeds, such as "0.5 1 2". */
				__( 'Custom: %s', 'video-library-for-mux' ),
				value
			),
		},
	];
}

function controlsHelp( controls, autoplay ) {
	if ( controls ) {
		return null;
	}
	return autoplay
		? __( 'A click on the video pauses it.', 'video-library-for-mux' )
		: __(
				'A click on the video plays and pauses it.',
				'video-library-for-mux'
			);
}

/**
 * The options the player ends up with: a background video sets most of them.
 * Matches Playback::render_player() in PHP.
 *
 * @param {Object} attributes Block attributes.
 * @return {Object} Attributes with the background video settings applied.
 */
function playerOptions( attributes ) {
	if ( ! attributes.background ) {
		return attributes;
	}
	return {
		...attributes,
		autoplay: true,
		muted: true,
		loop: true,
		controls: false,
		showTitle: false,
		hideCaptions: true,
	};
}

/**
 * CSS variables that hide parts of the player.
 *
 * @param {Object} options Player options.
 * @return {Object} Style object.
 */
function playerStyle( options ) {
	const style = {};
	if ( ! options.controls ) {
		// Hiding the three control bars, rather than all controls, keeps click to play and pause.
		style[ '--top-controls' ] = 'none';
		style[ '--center-controls' ] = 'none';
		style[ '--bottom-controls' ] = 'none';
	}
	if ( options.background ) {
		style[ '--loading-indicator' ] = 'none';
	}
	if ( options.playbackRates === 'off' ) {
		style[ '--playback-rate-button' ] = 'none';
		style[ '--playback-rate-menu-button' ] = 'none';
	}
	return style;
}

function NotConfiguredHint() {
	if ( config.isConfigured ) {
		return null;
	}
	return (
		<p className="vlfm-placeholder__hint">
			{ __(
				'Connect a Mux account to upload videos.',
				'video-library-for-mux'
			) }{ ' ' }
			{ config.settingsUrl && (
				<a href={ config.settingsUrl }>
					{ __( 'Open Mux Video settings', 'video-library-for-mux' ) }
				</a>
			) }
		</p>
	);
}

function StateBox( { video, children } ) {
	return (
		<div
			className="vlfm-block-state"
			style={
				video?.thumbnail
					? { backgroundImage: `url(${ video.thumbnail })` }
					: undefined
			}
		>
			<div className="vlfm-block-state__content">{ children }</div>
		</div>
	);
}

function Preview( {
	video,
	error,
	upload,
	attributes,
	posterUrl,
	isSelected,
	onReplace,
	onCancelUpload,
} ) {
	if ( ! video && error ) {
		return (
			<StateBox>
				<p>
					{ __(
						'This video is no longer in the Mux video library.',
						'video-library-for-mux'
					) }
				</p>
				<Button variant="secondary" onClick={ onReplace }>
					{ __( 'Choose another video', 'video-library-for-mux' ) }
				</Button>
			</StateBox>
		);
	}

	if ( ! video ) {
		return (
			<StateBox>
				<Spinner />
			</StateBox>
		);
	}

	if ( upload && upload.phase !== 'processing' ) {
		return (
			<StateBox>
				<p className="vlfm-block-state__title">{ upload.title }</p>
				{ upload.phase !== 'error' && (
					<ProgressBar value={ upload.progress } />
				) }
				<p>{ uploadStatusText( upload ) }</p>
				{ upload.phase === 'error' ? (
					<Button variant="secondary" onClick={ onReplace }>
						{ __(
							'Choose another video',
							'video-library-for-mux'
						) }
					</Button>
				) : (
					<Button variant="secondary" onClick={ onCancelUpload }>
						{ __( 'Cancel upload', 'video-library-for-mux' ) }
					</Button>
				) }
			</StateBox>
		);
	}

	if ( video.status === 'preparing' || upload ) {
		return (
			<StateBox>
				<Spinner />
				<p>
					{ sprintf(
						/* translators: %s: video title. */
						__(
							'Mux is processing “%s”…',
							'video-library-for-mux'
						),
						video.title
					) }
				</p>
			</StateBox>
		);
	}

	if ( video.status === 'uploading' ) {
		return (
			<StateBox>
				<p>
					{ sprintf(
						/* translators: %s: video title. */
						__(
							'“%s” has not finished uploading. If the upload stopped, upload it again.',
							'video-library-for-mux'
						),
						video.title
					) }
				</p>
				<Button variant="secondary" onClick={ onReplace }>
					{ __( 'Choose another video', 'video-library-for-mux' ) }
				</Button>
			</StateBox>
		);
	}

	if ( video.status === 'errored' ) {
		return (
			<StateBox>
				<p>
					<strong>
						{ __(
							'Mux could not process this video.',
							'video-library-for-mux'
						) }
					</strong>
				</p>
				{ video.error && <p>{ video.error }</p> }
				<Button variant="secondary" onClick={ onReplace }>
					{ __( 'Choose another video', 'video-library-for-mux' ) }
				</Button>
			</StateBox>
		);
	}

	if ( ! video.playable ) {
		return (
			<StateBox video={ video }>
				<p>
					{ __(
						'This video uses signed playback. Add a signing key in Mux Videos → Settings so it can play.',
						'video-library-for-mux'
					) }
				</p>
			</StateBox>
		);
	}

	// Shows the player as visitors see it, except that it never starts on its
	// own: an open editor would otherwise stream the video the whole time.
	const options = playerOptions( attributes );
	const rates = options.playbackRates;

	return (
		<div className="vlfm-block-player">
			<MuxPlayer
				video={ video }
				style={ playerStyle( options ) }
				attributes={ {
					'max-resolution': options.maxResolution || undefined,
					poster: posterUrl,
					'accent-color': options.accentColor || config.accentColor,
					'start-time': options.startTime || undefined,
					title:
						options.showTitle && options.controls
							? video.title
							: undefined,
					'default-hidden-captions': options.hideCaptions,
					playbackrates: rates && rates !== 'off' ? rates : undefined,
				} }
			/>
			{ ! isSelected && <div className="vlfm-block-player__overlay" /> }
		</div>
	);
}

function TitleControl( { video } ) {
	const [ title, setTitle ] = useState( video.title );
	const { createErrorNotice, createSuccessNotice } =
		useDispatch( noticesStore );

	useEffect( () => {
		setTitle( video.title );
	}, [ video.title ] );

	const commit = async () => {
		const trimmed = title.trim();
		if ( ! trimmed || trimmed === video.title ) {
			setTitle( video.title );
			return;
		}
		try {
			await saveVideo( video.id, { title: trimmed } );
			createSuccessNotice(
				__( 'Video title updated.', 'video-library-for-mux' ),
				{ type: 'snackbar' }
			);
		} catch ( error ) {
			setTitle( video.title );
			createErrorNotice( errorMessage( error ), { type: 'snackbar' } );
		}
	};

	return (
		<TextControl
			label={ __( 'Title', 'video-library-for-mux' ) }
			help={ __(
				'Renames the video everywhere: the library, other pages and Mux.',
				'video-library-for-mux'
			) }
			value={ title }
			onChange={ setTitle }
			onBlur={ commit }
			onKeyDown={ ( event ) => {
				if ( event.key === 'Enter' ) {
					event.currentTarget.blur();
				}
			} }
			disabled={ ! video.can_edit }
			__nextHasNoMarginBottom
			__next40pxDefaultSize
		/>
	);
}

function PosterControl( { posterId, posterUrl, onChange } ) {
	return (
		<MediaUploadCheck>
			<BaseControl
				help={ __(
					'Shown before the video plays, instead of a frame from the video.',
					'video-library-for-mux'
				) }
				__nextHasNoMarginBottom
			>
				<BaseControl.VisualLabel>
					{ __( 'Poster image', 'video-library-for-mux' ) }
				</BaseControl.VisualLabel>
				{ posterUrl && (
					<img
						className="vlfm-poster-preview"
						src={ posterUrl }
						alt=""
					/>
				) }
				<div className="vlfm-poster-actions">
					<MediaUpload
						title={ __(
							'Select poster image',
							'video-library-for-mux'
						) }
						allowedTypes={ [ 'image' ] }
						value={ posterId }
						onSelect={ ( media ) => onChange( media.id ) }
						render={ ( { open } ) => (
							<Button
								variant="secondary"
								onClick={ open }
								__next40pxDefaultSize
							>
								{ posterId
									? __( 'Replace', 'video-library-for-mux' )
									: __( 'Select', 'video-library-for-mux' ) }
							</Button>
						) }
					/>
					{ posterId && (
						<Button
							variant="tertiary"
							onClick={ () => onChange( undefined ) }
							__next40pxDefaultSize
						>
							{ __( 'Remove', 'video-library-for-mux' ) }
						</Button>
					) }
				</div>
			</BaseControl>
		</MediaUploadCheck>
	);
}

export default function Edit( { attributes, setAttributes, isSelected } ) {
	const {
		videoId,
		background,
		autoplay,
		muted,
		loop,
		controls,
		showTitle,
		hideCaptions,
		playbackRates,
		preload,
		maxResolution,
		posterId,
		startTime,
		accentColor,
	} = attributes;
	const [ modalTab, setModalTab ] = useState( null );
	const [ pendingUploadKey, setPendingUploadKey ] = useState( null );
	const pendingUpload = useUpload( pendingUploadKey );
	const videoUpload = useUploadForVideo( videoId );
	const { video, error } = useVideo( videoId );
	const posterUrl = useSelect(
		( select ) =>
			posterId
				? select( coreStore ).getMedia( posterId, { context: 'view' } )
						?.source_url
				: undefined,
		[ posterId ]
	);
	const blockProps = useBlockProps();
	const { createErrorNotice } = useDispatch( noticesStore );

	const upload = ( files ) => {
		setModalTab( null );
		const [ key ] = uploadFiles(
			Array.from( files ).slice( 0, 1 ),
			createErrorNotice,
			{
				onCreated: ( created ) =>
					setAttributes( { videoId: created.id } ),
			}
		);
		if ( key ) {
			setPendingUploadKey( key );
		}
	};

	const select = ( chosen ) => {
		setVideo( chosen );
		setAttributes( { videoId: chosen.id } );
		setPendingUploadKey( null );
		setModalTab( null );
	};

	const modal = modalTab && (
		<LibraryModal
			initialTab={ modalTab }
			selectedId={ videoId }
			canUpload={ canUpload }
			onSelect={ select }
			onUpload={ upload }
			onClose={ () => setModalTab( null ) }
		/>
	);

	if ( ! videoId ) {
		const isPreparing = pendingUpload?.phase === 'creating';
		return (
			<div { ...blockProps }>
				<Placeholder
					icon={ <BlockIcon icon="video-alt3" /> }
					label={ __( 'Mux Video', 'video-library-for-mux' ) }
					instructions={ __(
						'Upload a video to Mux, choose one from your video library, or import one from a URL.',
						'video-library-for-mux'
					) }
					className="vlfm-placeholder"
				>
					{ canUpload && <DropZone onFilesDrop={ upload } /> }
					{ pendingUpload?.phase === 'error' && (
						<Notice status="error" isDismissible={ false }>
							{ pendingUpload.error }
						</Notice>
					) }
					{ isPreparing ? (
						<div className="vlfm-placeholder__progress">
							<Spinner />
							{ uploadStatusText( pendingUpload ) }
						</div>
					) : (
						<div className="vlfm-placeholder__actions">
							{ canUpload && (
								<FormFileUpload
									accept={ ACCEPT }
									onChange={ ( event ) =>
										upload( event.currentTarget.files )
									}
									render={ ( { openFileDialog } ) => (
										<Button
											variant="primary"
											onClick={ openFileDialog }
										>
											{ __(
												'Upload',
												'video-library-for-mux'
											) }
										</Button>
									) }
								/>
							) }
							<Button
								variant="secondary"
								onClick={ () => setModalTab( 'library' ) }
							>
								{ __(
									'Video library',
									'video-library-for-mux'
								) }
							</Button>
							{ canUpload && (
								<Button
									variant="tertiary"
									onClick={ () => setModalTab( 'url' ) }
								>
									{ __(
										'Import from URL',
										'video-library-for-mux'
									) }
								</Button>
							) }
						</div>
					) }
					<NotConfiguredHint />
				</Placeholder>
				{ modal }
			</div>
		);
	}

	return (
		<>
			<BlockControls group="other">
				<ToolbarButton onClick={ () => setModalTab( 'library' ) }>
					{ __( 'Replace', 'video-library-for-mux' ) }
				</ToolbarButton>
			</BlockControls>

			<InspectorControls>
				<PanelBody title={ __( 'Video', 'video-library-for-mux' ) }>
					{ video && (
						<>
							<TitleControl video={ video } />
							{ video.status === 'ready' && (
								<p className="vlfm-inspector-summary">
									{ [
										formatDuration( video.duration ),
										video.resolution,
									]
										.filter( Boolean )
										.join( ' · ' ) }
								</p>
							) }
						</>
					) }
					<div className="vlfm-inspector-actions">
						<Button
							variant="secondary"
							onClick={ () => setModalTab( 'library' ) }
						>
							{ __( 'Replace video', 'video-library-for-mux' ) }
						</Button>
						{ config.libraryUrl && (
							<ExternalLink href={ config.libraryUrl }>
								{ __(
									'Open the video library',
									'video-library-for-mux'
								) }
							</ExternalLink>
						) }
					</div>
				</PanelBody>
				<PanelBody title={ __( 'Playback', 'video-library-for-mux' ) }>
					<ToggleControl
						label={ __(
							'Background video',
							'video-library-for-mux'
						) }
						help={ __(
							'Plays muted and on a loop, with no controls, like a moving image. A click pauses it.',
							'video-library-for-mux'
						) }
						checked={ background }
						onChange={ ( value ) =>
							setAttributes( { background: value } )
						}
						__nextHasNoMarginBottom
					/>
					{ ! background && (
						<>
							<ToggleControl
								label={ __(
									'Autoplay',
									'video-library-for-mux'
								) }
								help={
									autoplay
										? __(
												'Browsers only autoplay muted videos, so it starts muted.',
												'video-library-for-mux'
											)
										: null
								}
								checked={ autoplay }
								onChange={ ( value ) =>
									setAttributes( { autoplay: value } )
								}
								__nextHasNoMarginBottom
							/>
							<ToggleControl
								label={ __( 'Muted', 'video-library-for-mux' ) }
								checked={ muted }
								onChange={ ( value ) =>
									setAttributes( { muted: value } )
								}
								__nextHasNoMarginBottom
							/>
							<ToggleControl
								label={ __( 'Loop', 'video-library-for-mux' ) }
								checked={ loop }
								onChange={ ( value ) =>
									setAttributes( { loop: value } )
								}
								__nextHasNoMarginBottom
							/>
						</>
					) }
					<TextControl
						type="number"
						min={ 0 }
						step={ 0.1 }
						label={ __(
							'Start at (seconds)',
							'video-library-for-mux'
						) }
						value={ startTime ?? '' }
						onChange={ ( value ) =>
							setAttributes( {
								startTime:
									value === ''
										? undefined
										: Math.max( 0, Number( value ) ),
							} )
						}
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					/>
					{ ! background && ! autoplay && (
						<SelectControl
							label={ __( 'Preload', 'video-library-for-mux' ) }
							help={ __(
								'None loads nothing until someone presses play.',
								'video-library-for-mux'
							) }
							value={ preload }
							options={ PRELOAD_OPTIONS }
							onChange={ ( value ) =>
								setAttributes( { preload: value } )
							}
							__nextHasNoMarginBottom
							__next40pxDefaultSize
						/>
					) }
				</PanelBody>
				<PanelBody title={ __( 'Player', 'video-library-for-mux' ) }>
					{ ! background && (
						<>
							<ToggleControl
								label={ __(
									'Show controls',
									'video-library-for-mux'
								) }
								help={ controlsHelp( controls, autoplay ) }
								checked={ controls }
								onChange={ ( value ) =>
									setAttributes( { controls: value } )
								}
								__nextHasNoMarginBottom
							/>
							{ controls && (
								<ToggleControl
									label={ __(
										'Show title in the player',
										'video-library-for-mux'
									) }
									checked={ showTitle }
									onChange={ ( value ) =>
										setAttributes( { showTitle: value } )
									}
									__nextHasNoMarginBottom
								/>
							) }
							<ToggleControl
								label={ __(
									'Start with captions off',
									'video-library-for-mux'
								) }
								help={
									controls
										? __(
												'Visitors can turn captions on in the player.',
												'video-library-for-mux'
											)
										: null
								}
								checked={ hideCaptions }
								onChange={ ( value ) =>
									setAttributes( { hideCaptions: value } )
								}
								__nextHasNoMarginBottom
							/>
							{ controls && (
								<SelectControl
									label={ __(
										'Speed menu',
										'video-library-for-mux'
									) }
									value={ playbackRates }
									options={ speedOptions( playbackRates ) }
									onChange={ ( value ) =>
										setAttributes( {
											playbackRates: value,
										} )
									}
									__nextHasNoMarginBottom
									__next40pxDefaultSize
								/>
							) }
						</>
					) }
					<SelectControl
						label={ __(
							'Maximum resolution',
							'video-library-for-mux'
						) }
						help={ __(
							'A lower limit uses less data for visitors and can lower Mux delivery costs.',
							'video-library-for-mux'
						) }
						value={ maxResolution }
						options={ RESOLUTION_OPTIONS }
						onChange={ ( value ) =>
							setAttributes( { maxResolution: value } )
						}
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					/>
					<PosterControl
						posterId={ posterId }
						posterUrl={ posterUrl }
						onChange={ ( value ) =>
							setAttributes( { posterId: value } )
						}
					/>
				</PanelBody>
			</InspectorControls>

			<InspectorControls group="styles">
				<PanelColorSettings
					title={ __( 'Color', 'video-library-for-mux' ) }
					colorSettings={ [
						{
							value: accentColor,
							onChange: ( value ) =>
								setAttributes( { accentColor: value } ),
							label: __(
								'Accent color',
								'video-library-for-mux'
							),
						},
					] }
				/>
			</InspectorControls>

			<figure { ...blockProps }>
				<Preview
					video={ video }
					error={ error }
					upload={ videoUpload }
					attributes={ attributes }
					posterUrl={ posterUrl }
					isSelected={ isSelected }
					onReplace={ () => setModalTab( 'library' ) }
					onCancelUpload={ () => {
						cancelUpload( videoUpload.key );
						setAttributes( { videoId: undefined } );
					} }
				/>
			</figure>

			{ modal }
		</>
	);
}
