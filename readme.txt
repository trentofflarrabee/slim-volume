=== Slim Volume ===
Tags: music, audio player, albums, lyrics, artists
Requires at least: 6.0
Tested up to: 7.1
Stable tag: 0.6.0
Requires PHP: 8.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Music catalog, timed lyrics, responsive persistent audio player, and queue management for WordPress.

== Description ==

Slim Volume is a WordPress-native music catalog and audio player for artists, bands, labels, and music projects.

It provides:

* Release and track content types.
* A public music archive at `/music/`.
* Nested track URLs at `/music/{release}/{track}/`.
* Release and track administration workflows.
* Portable JSON discography export for backup and migration.
* A persistent frontend audio player and queue.
* Separate desktop and mobile player presentations backed by one playback engine.
* A mobile mini-player and expanded Now Playing sheet.
* Mobile transport, progress, seeking, and queue controls.
* Hosted-audio, external-link, and catalog-only workflows.
* Plain and synchronized timed lyrics.
* Artist and project attribution.
* Music-focused structured data.
* Theme, player, and visualizer settings.
* Theme template overrides.

Version 0.6.0 remains a beta release intended for controlled production use and early customer projects. Back up the WordPress site before installing an update.

== Installation ==

1. Upload the `slim-volume` folder to `/wp-content/plugins/`, or install the packaged plugin ZIP through the WordPress Plugins screen.
2. Activate Slim Volume through the WordPress Plugins screen.
3. Open the Music menu in WordPress administration.
4. Configure the plugin through Music > Settings.
5. Create a release, then create or assign its tracks.
6. Visit `/music/` to view the public archive.

For clean music URLs such as `/music/`, open Settings > Permalinks and select a pretty permalink structure such as Post name (`/%postname%/`). The structure should not contain `/index.php/`. Save the permalink settings after making a change. On servers where URL rewriting is unavailable, WordPress may instead use URLs such as `/index.php/music/`.

== Frequently Asked Questions ==

= How does the responsive player work? =

Slim Volume keeps one authoritative audio element and shared playback/queue state while switching between separate desktop and mobile presentation surfaces at the player breakpoint.

On mobile, the persistent mini-player can open an expanded Now Playing sheet with artwork, metadata, transport controls, seeking, and queue access. Crossing the breakpoint does not intentionally reload the active audio source or reset playback position.

= Should I use Slim Volume SEO with another SEO plugin? =

Yes. If your site already uses a dedicated SEO plugin, open Music > Settings > SEO and choose Music Schema Only. Your SEO plugin can continue handling normal site SEO while Slim Volume adds music-specific structured data for artists, releases, and tracks.

Choose Full Music Metadata when you do not have another SEO plugin managing your music pages. This mode also adds descriptions, social metadata, and music-aware page titles.

Choose Off when another system already provides the music-specific structured data you need.

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

Player-shell overrides should preserve the required player root and single audio element. When debug mode is enabled, Slim Volume can warn about incompatible player-shell markup.

= How do I export my music catalog? =

Open Music > Tools in WordPress administration and choose Export Discography Data.

The JSON export includes artists/projects, releases, tracks, relationships, publication state, editorial content, lyrics, timed lyrics, credits, entered destination links, and descriptive media references. Audio and artwork files themselves are not bundled.

Export files may contain unpublished or private catalog information, so keep downloaded export files private.

= Does Slim Volume support synchronized lyrics? =

Yes. Tracks with plain lyrics and playable audio can be synchronized line by line through the Lyrics Sync administration workspace.

== Changelog ==

= 0.6.0 =

* Added a dedicated mobile mini-player below the 760px player presentation breakpoint.
* Added an expanded mobile Now Playing sheet with artwork, track/release metadata, transport controls, progress display, seeking, and queue access.
* Added mobile queue rendering with current/next hierarchy, track selection, removal, and clear controls.
* Added mobile sheet lifecycle behavior including body scroll locking, Escape-to-close, focus entry and restoration, and keyboard focus containment.
* Preserved one authoritative audio element and shared playback/queue state across desktop and mobile presentations.
* Added breakpoint-safe presentation switching without intentionally reloading the active audio source or resetting playback position.
* Added shared player-state subscriptions while keeping high-frequency time/progress updates isolated from unrelated rendering.
* Separated desktop drawer state and renderer ownership from shared playback state.
* Separated visualizer DOM ownership and made visualizer presentation optional for core player initialization.
* Isolated Media Session behavior and native mobile audio-environment handling behind dedicated adapters.
* Hardened AJAX player refresh behavior so persistent playback survives music-page navigation without replacing the active audio element.
* Preserved the public `window.SVPlayer` compatibility facade while keeping internal presentation and adapter state private.
* Hardened player-shell initialization and added debug diagnostics for missing or duplicate required audio markup.
* Improved responsive player accessibility, safe-area handling, reduced-motion behavior, touch targets, and small-screen layout.
* Cleaned up responsive player CSS and removed obsolete mobile layout rules for the desktop presentation.

= 0.5.0 =

* Added Music > Tools with portable JSON discography export.
* Added export of artists/projects, releases, tracks, catalog identity, relationships, editorial content, publication state, lyrics, timed lyrics, credits, destination links, downloads, and descriptive media references.
* Added export-local portable relationship references without exposing WordPress post, term, or attachment IDs as catalog identity.
* Added preservation of drafts, private content, pending content, scheduled content, and unknown custom workflow statuses.
* Added export warnings for detectable relationship, media, lifecycle, artist-type, legacy-value, and timed-lyrics integrity problems.
* Preserved stale and incomplete timed-lyrics authoring work without regenerating it during export.
* Preserved legacy Slim Volume release genre and track external-destination data when present.
* Added private temporary export generation so a successful download does not begin until the complete JSON document has been generated.
* Fixed release button-label administration so a blank stored label remains blank instead of being persisted as the display fallback “Listen”.

= 0.4.1 =

* Fixed WordPress Plugin Check escaping compliance for music JSON-LD output.

= 0.4.0 =

* Added configurable music SEO modes: Off, Music Schema Only, and Full Music Metadata.
* Added music-specific structured data for artists and projects, releases, and tracks using linked MusicGroup, Person, MusicAlbum, and MusicRecording entities.
* Added stable music entity identifiers and improved artist/project identity resolution.
* Added official artist/project profile URLs for structured-data identity matching.
* Added music-aware page titles, descriptions, and social metadata in Full Music Metadata mode.
* Improved compatibility with dedicated SEO plugins by keeping Music Schema Only focused on music-specific structured data.
* Improved archive structured data so it follows the releases currently shown by archive search, filtering, sorting, and pagination.
* Improved track structured data so track identity links represent the individual recording rather than inherited release destinations.
* Added fallback artist/project settings for single-artist catalogs and unassigned releases.
* Improved Artists & Projects administration and simplified wording throughout music settings, release editors, track editors, timed lyrics, and track context tools.
* Improved music-navigation and player-setting descriptions for clearer non-technical administration.
* Migrated the legacy SEO enabled setting to the new SEO ownership modes.

= 0.3.1 =

* Added meta description tag to /music/{release} pages.

= 0.3.0 =

* Improved release and track presentation across desktop and mobile layouts.
* Top-aligned release artwork alongside long-form release content while preserving centered mobile stacking.
* Added configurable font family, font size, line height, and link color controls for authored release and track content.
* Removed the hard-coded Song Story heading so track editorial content renders exactly as authored.
* Reworked track hero Play and Queue actions as compact accessible icon controls.
* Replaced redundant disabled playback controls with clear passive no-audio states on track and release pages.
* Improved release track rows so non-playable tracks no longer present dead playback controls.

= 0.2.0 =

* Improved mobile release and track layouts with centered hero presentation and denser track lists.
* Reworked the mobile persistent player with a top-mounted seek bar, safer edge spacing, and improved transport layout.
* Added overflow-aware scrolling for long player track titles with reduced-motion support.
* Kept Previous and Next track navigation side by side on small screens.
* Added a subtle animated Queue drawer with reduced-motion handling.
* Consolidated artist and release attribution into compact hero bylines.
* Improved secondary button contrast on dark presentation backgrounds.
* Added Media Session metadata and transport integration for supported browsers.
* Improved mobile lock-screen and background playback by using the native media path instead of the realtime visualizer path on mobile devices.
* Added mobile audio-session and playback-state synchronization where supported.

= 0.1.0 =

* Added release and track catalog management.
* Added nested music routing and archive search.
* Added persistent audio playback, queue management, and visualizer support.
* Added artist and project attribution.
* Added synchronized timed-lyrics authoring and frontend display.
* Added centralized release-to-track relationship management and repair.
* Added settings, template overrides, and music structured data.
* Added safe lifecycle version tracking and uninstall handling.
