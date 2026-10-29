/**
 * Extends the @wordpress/scripts build with the non-block entry points.
 *
 * - block/*       Found automatically from src/block/block.json.
 * - admin/index   The Mux Videos → Library screen.
 * - acf/index     The Mux Video field for Advanced Custom Fields.
 * - library/index Styles for the grid and dialogs, shared by the library screen,
 *                 the ACF field and the block editor (which renders dialogs
 *                 outside its iframe).
 * - player/index  The Mux Player web component, bundled so nothing loads from a CDN.
 *                 tools/no-cast-sdk-loader.js keeps it from loading Google's
 *                 Cast SDK.
 */
const path = require( 'path' );
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

module.exports = {
	...defaultConfig,
	entry: () => ( {
		...defaultConfig.entry(),
		'admin/index': path.resolve( __dirname, 'src/admin/index.js' ),
		'acf/index': path.resolve( __dirname, 'src/acf/index.js' ),
		'library/index': path.resolve( __dirname, 'src/shared/library.scss' ),
		'player/index': path.resolve( __dirname, 'src/player/index.js' ),
	} ),
	module: {
		...defaultConfig.module,
		rules: [
			...defaultConfig.module.rules,
			{
				test: /castable-video[\\/]castable-utils\.js$/,
				loader: path.resolve(
					__dirname,
					'tools/no-cast-sdk-loader.js'
				),
			},
		],
	},
	performance: {
		...defaultConfig.performance,
		// Mux Player includes its HLS engine; it only loads on pages with a video.
		hints: false,
	},
};
