# Slim Volume

Slim Volume is a WordPress-native music catalog and audio player plugin for artists, bands, labels, and music projects.

It provides release archives, single release pages, track deep-dive pages, admin workflow tools, a persistent frontend audio player, responsive desktop/mobile player surfaces, queue management, theming settings, configurable catalog routing, music-specific SEO controls, and optional Butterchurn visualizer support.

## Current Status

`v0.7.1`

Slim Volume is still in beta and is intended for controlled production use and early customer projects. APIs, templates, settings, and markup may still change before the first stable release.

## Highlights in 0.7.1

- Normalized WordPress navigation menu state on Slim Volume archive, release, and track routes.
- Prevented the configured Blog posts page from appearing active while browsing Slim Volume catalog content.
- Improved active-state handling for catalog navigation links.
- Added clearer guidance for adding the Slim Volume virtual archive to site navigation with a Custom Link.
- Added the current catalog archive path to the settings UI so users can easily copy the correct navigation destination.

## Highlights in 0.7.0

- Added configurable catalog routing so the default `/music/` base can be changed to values such as `/discography/` or `/releases/`.
- Kept archive, release, and nested track URLs under one authoritative catalog base.
- Added configurable archive title and introductory content.
- Added URL-base conflict detection to prevent Slim Volume from claiming an existing WordPress route.
- Preserved unrelated valid settings when a requested catalog base is rejected.
- Added controlled rewrite-rule rebuilding only after a successful catalog-base change.
- Updated archive, release, and track breadcrumbs, admin route previews, AJAX navigation, and Slim Volume SEO to follow the configured catalog base.
- Kept archive intro content separate from the dedicated SEO archive description.
- Centralized catalog routing and archive identity through the `Catalog` authority.
- Removed active hardcoded `/music/` URL assumptions from routing, templates, navigation, admin previews, and Slim Volume SEO.

## Features

- Release and track custom post types
- Public music archive with configurable catalog base, defaulting to `/music/`
- Nested release and track URLs under the configured catalog base
- Configurable archive title and introductory content
- Catalog URL conflict validation for existing WordPress routes
- Release artwork via featured images
- Track artwork with release artwork fallback
- Artist and project attribution
- Music-specific structured data for artists, releases, and tracks
- Configurable SEO ownership modes: Off, Music Schema Only, and Full Music Metadata
- Fallback artist / project identity for single-artist catalogs
- Official artist / project profile URLs for music identity matching
- Hosted audio, external-link, and catalog-only workflows
- Plain lyrics and synchronized timed lyrics
- Release and track editorial content
- Portable JSON discography export for backup, migration, and catalog preservation
- Configurable editorial font family, size, line height, and link color
- Release and track frontend templates
- Persistent frontend audio player
- Responsive desktop and mobile player presentations
- Mobile mini-player and expanded Now Playing sheet
- Desktop queue drawer with reorder/remove controls
- Mobile queue with track selection, remove, and clear controls
- Release-level and per-track playback actions
- Accessible compact track hero playback controls
- Player state persistence
- AJAX music navigation
- Media Session integration on supported browsers
- Mobile background and lock-screen playback support where available
- Optional bars visualizer
- Optional Butterchurn visualizer
- Fullscreen visualizer mode
- Release and track search, including lyrics
- Admin release dashboard
- Track Context admin panel
- Release track management and relationship repair
- Release prefill when creating tracks
- Tracks admin release filter
- Themeable CSS variables
- Admin appearance presets and reset controls
- Theme template overrides

## Requirements

- WordPress 6.0+
- PHP 8.0+
- A theme that supports featured images
- Optional: Butterchurn vendor files for Butterchurn visualizer mode

## Installation and Permalinks

1. Upload the packaged Slim Volume ZIP through **Plugins → Add Plugin → Upload Plugin**.
2. Activate Slim Volume.
3. Open **Music → Settings** to configure the plugin.
4. Open **Settings → Permalinks** and select a pretty permalink structure such as **Post name** (`/%postname%/`).
5. Save the permalink settings.

By default, Slim Volume uses:

```text
/music/
/music/{release-slug}/
/music/{release-slug}/{track-slug}/
```

The catalog URL base can be changed under **Music → Settings**. For example, setting the base to `discography` produces:

```text
/discography/
/discography/{release-slug}/
/discography/{release-slug}/{track-slug}/
```

Slim Volume's catalog is a virtual archive rather than a WordPress Page. To add it to site navigation, add a **Custom Link** using the current archive path shown under **Music → Settings → Catalog**. For example, with the default configuration, use `/music/`. If the catalog base is changed later, update that navigation link to the new path.

Changing the catalog base changes archive, release, and track URLs. Existing links using the previous base may require redirects.

If the permalink structure contains `/index.php/`, WordPress may include `/index.php/` in Slim Volume URLs. This behavior comes from the WordPress or web-server permalink configuration rather than Slim Volume's routing.

## Responsive Player

Slim Volume uses one persistent playback engine with separate presentation surfaces:

- Desktop presentation above 760px
- Mobile presentation at 760px and below
- One authoritative `<audio>` element across both
- Shared track, queue, and playback state
- Transient presentation state for desktop drawer and mobile sheet behavior

Crossing the player breakpoint does not intentionally reload the active audio source, replace the audio element, or reset playback position.

The mobile player includes:

- Compact persistent mini-player
- Expanded Now Playing sheet
- Artwork and release metadata
- Play/pause, previous, and next controls
- Seek/progress display with visible seek thumb
- Mobile queue
- Scroll locking while expanded
- Escape-to-close
- Keyboard focus management and containment

## Butterchurn Visualizer

Butterchurn mode requires these files:

```text
assets/vendor/butterchurn/butterchurn.min.js
assets/vendor/butterchurn/butterchurn-presets.min.js
```

If those files are missing, Slim Volume falls back to the built-in bars visualizer option.

Visualizer presentation is optional; missing visualizer markup should not prevent the core player from initializing.

## Template Overrides

Themes can override plugin templates by copying files into:

```text
your-theme/slim-volume/
```

Examples:

```text
your-theme/slim-volume/archive-sv_release.php
your-theme/slim-volume/single-sv_release.php
your-theme/slim-volume/single-sv_track.php
your-theme/slim-volume/partials/player-shell.php
```

Player-shell overrides are a compatibility surface. When Slim Volume debug mode is enabled, incompatible player-shell markup can emit diagnostic warnings for missing or duplicate required player elements.

Existing template overrides continue to load and function. Overrides that hardcode archive labels or URLs should use Slim Volume's catalog helpers to reflect customized archive identity and routing.

## Settings

Slim Volume includes settings for:

- Catalog URL base
- Archive title
- Archive introductory content
- Frontend audio player / catalog-only mode
- Keep music playing between music pages
- Remember player state after refresh
- Music SEO ownership mode
- Fallback artist / project details
- Music catalog description and fallback artwork
- Visualizer enable/disable
- Visualizer mode
- Debug mode
- Appearance presets
- Player colors
- Button colors
- Card border color
- Editorial content font family
- Editorial content font size
- Editorial content line height
- Editorial content link color
- Border radius values

## Discography Export

Administrators can export the current Slim Volume music catalog through **Music → Tools → Export Discography Data**.

The JSON export preserves portable music-domain information including:

- Catalog fallback artist/project identity
- Artists and projects
- Releases and tracks
- Release and track relationships
- Publication and scheduling state
- Release dates and track ordering
- Editorial content and excerpts
- Lyrics and timed lyrics
- Credits
- Streaming, purchase, external, audio, and download destinations
- Descriptive artwork, audio, and download media references

Audio, artwork, and downloadable files themselves are not bundled into the export. Persistent descriptive references are preserved where available.

The export intentionally preserves explicit or canonical stored catalog data rather than materializing frontend fallbacks. For example, inherited release artwork and release-level track links do not become explicit track data.

The portable format is identified by:

```json
{
  "schema": {
    "format": "slim-volume-discography",
    "formatVersion": 1
  }
}
```

Export files may contain unpublished releases, tracks, lyrics, credits, links, and other private catalog information. Keep them private.

## Music SEO

Slim Volume owns music-specific semantics rather than the site's general SEO system.

Three SEO modes are available under **Music → Settings → SEO**:

- **Off** — Slim Volume does not output music SEO data.
- **Music Schema Only** — recommended when the site already uses a dedicated SEO plugin. Slim Volume adds music-specific structured data for artists, releases, and tracks while the SEO plugin continues handling normal site SEO.
- **Full Music Metadata** — intended for sites without another SEO plugin managing music pages. Slim Volume adds music structured data plus descriptions, social metadata, and music-aware page titles.

The visible archive introduction and dedicated SEO archive description are intentionally separate settings.

Slim Volume does not replace WordPress or a general SEO plugin for robots directives, XML sitemaps, redirects, or other site-wide SEO responsibilities.

## Development and Source Code

Slim Volume is developed publicly on GitHub:

https://github.com/trentofflarrabee/slim-volume

The repository contains the human-readable PHP, JavaScript, and CSS source used to build distributed releases.

Release packages are built with GitHub Actions. Distribution builds may minify staged CSS and JavaScript files while the readable source remains available in the public repository.

Slim Volume includes Butterchurn and Butterchurn preset distributions for the optional visualizer. Butterchurn is distributed under the MIT License. See the bundled third-party license information under `assets/vendor/butterchurn/`.

## Known Future Work

- Block editor polish
- Media field/admin UI polish
- Enhanced music search results
- Track-level lyric search result snippets
- Extra Butterchurn preset packs
- More template override documentation
- More developer hooks and filters
- Broader automated accessibility coverage
- PHPUnit/WP test coverage

## Development Notes

Primary identifiers:

```text
Plugin name: Slim Volume
Text domain: slim-volume
Namespace: SlimVolume
Function prefix: slim_volume_
CSS prefix: sv-
JS global: window.SVPlayer
```

The public `window.SVPlayer` object is treated as a compatibility facade. Internal presentation state, renderer ownership, and platform adapters are not exposed directly.

## Version

Current beta release:

`v0.7.1`
