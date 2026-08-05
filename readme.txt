=== Slim Volume ===
Tags: music, audio player, albums, lyrics, artists
Requires at least: 6.0
Tested up to: 7.0
Stable tag: 0.1.0
Requires PHP: 8.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Music catalog, release archive, timed lyrics, and persistent audio player for WordPress.

== Description ==

Slim Volume is a WordPress-native music catalog and audio player for artists, bands, labels, and music projects.

It provides:

* Release and track content types.
* A public music archive at `/music/`.
* Nested track URLs at `/music/{release}/{track}/`.
* Release and track administration workflows.
* A persistent frontend audio player and queue.
* Hosted-audio, external-link, and catalog-only workflows.
* Plain and synchronized timed lyrics.
* Artist and project attribution.
* Music-focused structured data.
* Theme, player, and visualizer settings.
* Theme template overrides.

Version 0.1.0 is a beta release intended for controlled production use and early customer projects. Back up the WordPress site before installing an update.

== Installation ==

1. Upload the `slim-volume` folder to `/wp-content/plugins/`, or install the packaged plugin ZIP through the WordPress Plugins screen.
2. Activate Slim Volume through the WordPress Plugins screen.
3. Open the Music menu in WordPress administration.
4. Configure the plugin through Music > Settings.
5. Create a release, then create or assign its tracks.
6. Visit `/music/` to view the public archive.

For clean music URLs such as `/music/`, open Settings > Permalinks and select a pretty permalink structure such as Post name (`/%postname%/`). The structure should not contain `/index.php/`. Save the permalink settings after making a change. On servers where URL rewriting is unavailable, WordPress may instead use URLs such as `/index.php/music/`.
== Frequently Asked Questions ==

= Does uninstalling Slim Volume delete releases and tracks? =

No. Slim Volume preserves customer-created releases, tracks, lyrics, timed-lyrics documents, taxonomy assignments, metadata, and settings by default.

The uninstall handler currently removes only Slim Volume's internal installed-version bookkeeping option.

= Does every track require an uploaded audio file? =

No. Tracks can use an uploaded audio attachment, an external audio or destination URL, or catalog-only presentation depending on the configured workflow.

= Where are track pages located? =

Tracks use nested URLs in this format:

`/music/{release-slug}/{track-slug}/`

= Why do my music URLs contain index.php or return a 404? =

Open Settings > Permalinks and select a pretty permalink structure such as Post name (`/%postname%/`), then save the settings.

If the permalink structure contains `/index.php/`, WordPress will generate music URLs such as `/index.php/music/`. This is a WordPress or web-server permalink configuration rather than a Slim Volume routing error.

= Can a theme override Slim Volume templates? =

Yes. Copy the desired plugin template into:

`your-theme/slim-volume/`

Keep the template's relative path beneath that directory.

= Does Slim Volume support synchronized lyrics? =

Yes. Tracks with plain lyrics and playable audio can be synchronized line by line through the Lyrics Sync administration workspace.

== Changelog ==

= 0.1.0 =

* Added release and track catalog management.
* Added nested music routing and archive search.
* Added persistent audio playback, queue management, and visualizer support.
* Added artist and project attribution.
* Added synchronized timed-lyrics authoring and frontend display.
* Added centralized release-to-track relationship management and repair.
* Added settings, template overrides, and music structured data.
* Added safe lifecycle version tracking and uninstall handling.