/**
 * The Mux Video block.
 */
import { registerBlockType } from '@wordpress/blocks';

import metadata from './block.json';
import Edit from './edit';
import './style.scss';
import './editor.scss';

// Matches Blocks::is_on() in PHP.
const isTrue = ( value ) =>
	[ '1', 'true', 'on', 'yes' ].includes(
		String( value ?? '' )
			.trim()
			.toLowerCase()
	);

const lower = ( value ) =>
	String( value ?? '' )
		.trim()
		.toLowerCase();

registerBlockType( metadata.name, {
	edit: Edit,
	// Rendered in PHP, so title and thumbnail changes reach every page.
	save: () => null,
	transforms: {
		from: [
			{
				type: 'shortcode',
				tag: 'vlfm_video',
				attributes: {
					videoId: {
						type: 'number',
						shortcode: ( { named } ) =>
							parseInt( named.id, 10 ) || undefined,
					},
					background: {
						type: 'boolean',
						shortcode: ( { named } ) => isTrue( named.background ),
					},
					autoplay: {
						type: 'boolean',
						shortcode: ( { named } ) => isTrue( named.autoplay ),
					},
					muted: {
						type: 'boolean',
						shortcode: ( { named } ) => isTrue( named.muted ),
					},
					loop: {
						type: 'boolean',
						shortcode: ( { named } ) => isTrue( named.loop ),
					},
					controls: {
						type: 'boolean',
						shortcode: ( { named } ) =>
							named.controls === undefined ||
							isTrue( named.controls ),
					},
					showTitle: {
						type: 'boolean',
						shortcode: ( { named } ) => isTrue( named.title ),
					},
					hideCaptions: {
						type: 'boolean',
						shortcode: ( { named } ) =>
							lower( named.captions ) === 'off',
					},
					playbackRates: {
						type: 'string',
						shortcode: ( { named } ) => lower( named.speeds ),
					},
					preload: {
						type: 'string',
						shortcode: ( { named } ) => lower( named.preload ),
					},
					maxResolution: {
						type: 'string',
						shortcode: ( { named } ) =>
							lower( named.max_resolution ),
					},
					// Only Media Library posters: the block has no field for an image URL.
					posterId: {
						type: 'number',
						shortcode: ( { named } ) =>
							parseInt( named.poster, 10 ) || undefined,
					},
					startTime: {
						type: 'number',
						shortcode: ( { named } ) =>
							parseFloat( named.start ) || undefined,
					},
					accentColor: {
						type: 'string',
						shortcode: ( { named } ) =>
							named.accent_color || undefined,
					},
				},
			},
		],
	},
} );
