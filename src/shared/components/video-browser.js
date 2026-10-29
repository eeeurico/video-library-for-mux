/**
 * Searchable grid of library videos, used by the library screen and the block's dialog.
 */
import {
	Button,
	Notice,
	SearchControl,
	SelectControl,
	Spinner,
} from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';

import { errorMessage, fetchVideos } from '../api';
import { getVideo, setVideos, useUploads } from '../store';
import VideoCard from './video-card';

const PER_PAGE = 40;
const SEARCH_DELAY = 300;

const STATUS_OPTIONS = [
	{ value: '', label: __( 'All videos', 'videoteca-for-mux' ) },
	{ value: 'ready', label: __( 'Ready', 'videoteca-for-mux' ) },
	{ value: 'processing', label: __( 'Processing', 'videoteca-for-mux' ) },
	{ value: 'errored', label: __( 'Failed', 'videoteca-for-mux' ) },
];

const SORT_OPTIONS = [
	{
		value: 'date-desc',
		label: __( 'Newest first', 'videoteca-for-mux' ),
	},
	{ value: 'date-asc', label: __( 'Oldest first', 'videoteca-for-mux' ) },
	{
		value: 'title-asc',
		label: __( 'Title, A to Z', 'videoteca-for-mux' ),
	},
	{
		value: 'title-desc',
		label: __( 'Title, Z to A', 'videoteca-for-mux' ),
	},
];

/**
 * @param {Object}   props
 * @param {string}   [props.mode]         manage (open details) or select (pick one).
 * @param {number}   [props.selectedId]   Selected video, in select mode.
 * @param {Function} [props.onSelect]     Called with a video when it is clicked.
 * @param {Function} [props.onActivate]   Called with a video on double click, in select mode.
 * @param {number[]} [props.addedIds]     Videos created on this page, shown first.
 * @param {number}   [props.reloadToken]  Change it to reload from the first page.
 * @param {Element}  [props.emptyActions] Shown when the library is empty.
 */
export default function VideoBrowser( {
	mode = 'manage',
	selectedId,
	onSelect,
	onActivate,
	addedIds = [],
	reloadToken = 0,
	emptyActions = null,
} ) {
	const [ searchInput, setSearchInput ] = useState( '' );
	const [ search, setSearch ] = useState( '' );
	const [ status, setStatus ] = useState( '' );
	const [ sort, setSort ] = useState( 'date-desc' );
	const [ page, setPage ] = useState( 1 );
	const [ ids, setIds ] = useState( [] );
	const [ total, setTotal ] = useState( 0 );
	const [ totalPages, setTotalPages ] = useState( 0 );
	const [ isLoading, setIsLoading ] = useState( true );
	const [ error, setError ] = useState( null );
	const [ retryToken, setRetryToken ] = useState( 0 );
	const uploads = useUploads();

	useEffect( () => {
		const timer = setTimeout( () => {
			setSearch( searchInput.trim() );
			setPage( 1 );
		}, SEARCH_DELAY );
		return () => clearTimeout( timer );
	}, [ searchInput ] );

	useEffect( () => {
		setPage( 1 );
	}, [ reloadToken ] );

	useEffect( () => {
		let cancelled = false;
		const [ orderby, order ] = sort.split( '-' );

		setIsLoading( true );
		setError( null );

		fetchVideos( {
			search,
			status,
			orderby,
			order,
			page,
			per_page: PER_PAGE,
		} )
			.then( ( result ) => {
				if ( cancelled ) {
					return;
				}
				setVideos( result.videos );
				const pageIds = result.videos.map( ( video ) => video.id );
				setIds( ( current ) =>
					page === 1
						? pageIds
						: [
								...current,
								...pageIds.filter(
									( id ) => ! current.includes( id )
								),
							]
				);
				setTotal( result.total );
				setTotalPages( result.totalPages );
			} )
			.catch( ( fetchError ) => {
				if ( ! cancelled ) {
					setError( errorMessage( fetchError ) );
				}
			} )
			.finally( () => {
				if ( ! cancelled ) {
					setIsLoading( false );
				}
			} );

		return () => {
			cancelled = true;
		};
	}, [ search, status, sort, page, reloadToken, retryToken ] );

	// Videos created on this page go first while the default view is showing.
	const showsNewestFirst = ! search && ! status && sort === 'date-desc';
	const freshIds = showsNewestFirst
		? [
				...addedIds,
				...uploads
					.map( ( upload ) => upload.videoId )
					.filter( Boolean ),
			].filter(
				( id, index, all ) =>
					all.indexOf( id ) === index && ! ids.includes( id )
			)
		: [];

	// The store drops deleted videos; the store subscription above re-renders.
	const listedIds = ids.filter( ( id ) => getVideo( id ) );
	const visibleIds = [
		...freshIds.filter( ( id ) => getVideo( id ) ),
		...listedIds,
	];
	const shownTotal = total + visibleIds.length - ids.length;
	const isFiltered = Boolean( search || status );

	return (
		<div className="vlfm-browser">
			<div className="vlfm-toolbar">
				<div className="vlfm-toolbar__filters">
					<SelectControl
						label={ __( 'Filter by status', 'videoteca-for-mux' ) }
						hideLabelFromVision
						value={ status }
						options={ STATUS_OPTIONS }
						onChange={ ( value ) => {
							setStatus( value );
							setPage( 1 );
						} }
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					/>
					<SelectControl
						label={ __( 'Sort by', 'videoteca-for-mux' ) }
						hideLabelFromVision
						value={ sort }
						options={ SORT_OPTIONS }
						onChange={ ( value ) => {
							setSort( value );
							setPage( 1 );
						} }
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					/>
				</div>
				<SearchControl
					className="vlfm-toolbar__search"
					label={ __( 'Search videos', 'videoteca-for-mux' ) }
					placeholder={ __(
						'Search by title, file name or ID',
						'videoteca-for-mux'
					) }
					value={ searchInput }
					onChange={ setSearchInput }
					__nextHasNoMarginBottom
				/>
			</div>

			{ error && (
				<Notice status="error" isDismissible={ false }>
					<p>{ error }</p>
					<Button
						variant="secondary"
						onClick={ () =>
							setRetryToken( ( token ) => token + 1 )
						}
					>
						{ __( 'Try again', 'videoteca-for-mux' ) }
					</Button>
				</Notice>
			) }

			{ isLoading && page === 1 && visibleIds.length === 0 && (
				<div className="vlfm-browser__loading">
					<Spinner />
				</div>
			) }

			{ ! isLoading && ! error && visibleIds.length === 0 && (
				<div className="vlfm-browser__empty">
					{ isFiltered ? (
						<p>
							{ search
								? sprintf(
										/* translators: %s: search terms. */
										__(
											'No videos match “%s”.',
											'videoteca-for-mux'
										),
										search
									)
								: __(
										'No videos with this status.',
										'videoteca-for-mux'
									) }
						</p>
					) : (
						<>
							<p className="vlfm-browser__empty-title">
								{ __(
									'Your Mux video library is empty.',
									'videoteca-for-mux'
								) }
							</p>
							{ emptyActions }
						</>
					) }
				</div>
			) }

			{ visibleIds.length > 0 && (
				<ul
					className="vlfm-grid"
					aria-busy={ isLoading }
					aria-label={ __( 'Videos', 'videoteca-for-mux' ) }
				>
					{ visibleIds.map( ( id ) => (
						<VideoCard
							key={ id }
							id={ id }
							highlight={ search }
							isSelected={
								mode === 'select'
									? id === selectedId
									: undefined
							}
							onClick={ onSelect }
							onDoubleClick={
								mode === 'select' ? onActivate : undefined
							}
						/>
					) ) }
				</ul>
			) }

			{ visibleIds.length > 0 && (
				<div className="vlfm-browser__footer">
					<p className="vlfm-browser__count">
						{ sprintf(
							/* translators: 1: number of videos shown, 2: total number of videos. */
							_n(
								'Showing %1$d of %2$d video',
								'Showing %1$d of %2$d videos',
								shownTotal,
								'videoteca-for-mux'
							),
							visibleIds.length,
							shownTotal
						) }
					</p>
					{ page < totalPages && (
						<Button
							variant="secondary"
							onClick={ () => setPage( page + 1 ) }
							isBusy={ isLoading }
							disabled={ isLoading }
							__next40pxDefaultSize
						>
							{ __( 'Load more', 'videoteca-for-mux' ) }
						</Button>
					) }
				</div>
			) }
		</div>
	);
}
