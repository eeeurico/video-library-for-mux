/**
 * The Mux Video field type for Advanced Custom Fields.
 *
 * The field saves a library video ID in a hidden input; the picker sets it.
 */
import { createRoot } from '@wordpress/element';

import { setVideo } from '../shared/store';
import VideoField from './video-field';
import './acf.scss';

const { acf } = window;

const Field = acf.Field.extend( {
	type: 'vlfm_video',

	$input() {
		return this.$( 'input[type="hidden"]:first' );
	},

	initialize() {
		const control = this.$( '.vlfm-acf-video' ).get( 0 );
		const app = this.$( '.vlfm-acf-video__app' ).get( 0 );
		if ( ! control || ! app ) {
			return;
		}

		const video = JSON.parse( control.dataset.video || 'null' );
		if ( video ) {
			setVideo( video );
		}

		// A fresh container, as repeater rows are copied with their markup.
		const container = document.createElement( 'div' );
		app.replaceChildren( container );

		this.root = createRoot( container );
		this.root.render(
			<VideoField
				initialId={ video?.id ?? null }
				onChange={ ( id ) =>
					acf.val( this.$input(), id ? String( id ) : '' )
				}
			/>
		);
	},
} );

acf.registerFieldType( Field );

// Stops polling and uploads watching a field that is gone, such as a removed repeater row.
acf.addAction( 'remove_field/type=vlfm_video', ( field ) =>
	field.root?.unmount()
);
