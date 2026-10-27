# Video Library for Mux

WordPress plugin to upload videos to [Mux](https://www.mux.com/) from the admin, keep the titles you give them, search the library, and embed videos with a block, a shortcode or an ACF field. Works with WPGraphQL for headless front ends.

Not on WordPress.org yet; it will be [`video-library-for-mux`](https://wordpress.org/plugins/video-library-for-mux/). See [readme.txt](readme.txt) for the full documentation, the shortcode options, ACF and GraphQL examples, and the FAQ.

## Setup

1. In the Mux dashboard, go to **Settings → Access Tokens** and create a token with **Mux Video: Read and Write**. Add **System: Read and Write** if you want the plugin to create a signing key for signed playback.
2. In WordPress, go to **Mux Videos → Settings** and paste the token ID and secret key.
3. Upload videos in **Mux Videos → Library**, or click **Sync from Mux** to add the videos already in your account.

The token and signing key stay on the server: the admin UI talks to the plugin's REST routes (`vlfm/v1`), and WordPress calls Mux. Video files go straight from the browser to Mux.

## Development

The JavaScript and CSS in `build/` are compiled from `src/` with [@wordpress/scripts](https://www.npmjs.com/package/@wordpress/scripts). `build/` is committed, so the repository works as a plugin when downloaded.

```
npm install
npm run build        # or npm start to rebuild on changes
npx wp-env start     # local site at http://localhost:8888, admin / password
npm run plugin-zip   # the zip for WordPress.org
```

```
video-library-for-mux.php           bootstrap and constants
includes/class-plugin.php           hooks everything up
includes/class-videos.php           library post type, search, sync from Mux
includes/class-mux-client.php       Mux API requests
includes/class-playback.php         Mux Player markup and signed tokens
includes/class-rest-controller.php  REST routes for the admin UI
includes/class-webhooks.php         Mux webhooks
includes/class-blocks.php           Mux Video block and [vlfm_video] shortcode
includes/class-admin.php            Mux Videos menu and library screen
includes/class-settings.php         settings screen
includes/class-acf*.php             ACF Mux Video field
includes/class-graphql*.php         WPGraphQL MuxVideo type and queries
src/                                block, library screen, ACF field and player sources
uninstall.php                       removes the library and settings, if asked to
```

## Credits

Inspired by [Mux Input for Sanity](https://github.com/sanity-io/sanity-plugin-mux-input).

<a href="https://github.com/eeeurico/video-library-for-mux/graphs/contributors">
  <img src="https://contrib.rocks/image?repo=eeeurico/video-library-for-mux" />
</a>
