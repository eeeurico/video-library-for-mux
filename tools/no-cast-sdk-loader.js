/**
 * Webpack loader that stops Mux Player from loading Google's Cast SDK.
 *
 * castable-video, a part of Mux Player, adds a script tag for
 * https://www.gstatic.com/cv/js/sender/v1/cast_sender.js in Chrome. Plugins on
 * WordPress.org may not load code from other servers, so this loader turns that
 * off in castable-utils.js. The player then uses the browser's own Remote
 * Playback API, so AirPlay still works in Safari.
 *
 * The build fails if castable-video changes and the code is no longer found.
 *
 * @param {string} source castable-utils.js
 * @return {string} The same module without the Cast SDK loader.
 */
module.exports = function ( source ) {
	const replacements = [
		[
			/export function requiresCastFramework\(\) \{[\s\S]*?\n\}/,
			'export function requiresCastFramework() {\n  return false;\n}',
		],
		[
			/export function loadCastFramework\(\) \{[\s\S]*?\n\}/,
			'export function loadCastFramework() {}',
		],
		// Comments that name the SDK's files.
		[ /^\s*\/\/ https:\/\/www\.gstatic\.com\/.*$/gm, '' ],
	];

	let output = source;
	for ( const [ pattern, replacement ] of replacements ) {
		if ( ! pattern.test( output ) ) {
			throw new Error(
				`no-cast-sdk-loader: ${ pattern } not found in castable-utils.js.`
			);
		}
		pattern.lastIndex = 0;
		output = output.replace( pattern, replacement );
	}

	if ( output.includes( 'gstatic.com' ) ) {
		throw new Error(
			'no-cast-sdk-loader: castable-utils.js still loads from gstatic.com.'
		);
	}

	return output;
};
