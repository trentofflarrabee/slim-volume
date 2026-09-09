# Slim Volume

Slim Volume is a WordPress-native music catalog and audio player plugin for artists, bands, labels, and music projects.

It provides release archives, single release pages, track deep-dive pages, admin workflow tools, a persistent frontend audio player, responsive desktop/mobile player surfaces, queue management, theming settings, and optional Butterchurn visualizer support.

## Current Status

`v0.6.0`

Slim Volume is still in beta and is intended for controlled production use and early customer projects. APIs, templates, settings, and markup may still change before the first stable release.

## Highlights in 0.6.0

- Added a dedicated mobile mini-player below the 760px player breakpoint.
- Added an expanded mobile Now Playing sheet with artwork, metadata, transport controls, progress, seeking, and queue access.
- Preserved one authoritative audio element and shared playback/queue state across desktop and mobile presentations.
- Added uninterrupted breakpoint switching without reloading audio or resetting playback position.
- Added mobile sheet lifecycle behavior including scroll locking, Escape-to-close, focus entry/restore, and keyboard focus containment.
- Added a dedicated mobile queue with current/next hierarchy, track selection, removal, and clear controls.
- Refactored desktop drawer and visualizer presentation ownership away from shared playback state.
- Added shared player state subscriptions while keeping high-frequency progress updates isolated.
- Hardened AJAX refresh behavior so the persistent player survives navigation without replacing the active audio element.
- Isolated Media Session and native mobile audio environment handling behind adapters.
- Hardened `player-shell.php` compatibility validation and debug diagnostics.
- Preserved the public `window.SVPlayer` compatibility facade.
- Cleaned up responsive player CSS and removed obsolete mobile desktop-bar rules.

## Features

- Release and track custom post types
- Public music archive at `/music/`
- Nested track URLs at `/music/{release-slug}/{track-slug}/`
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

Clean music URLs should use formats such as:

```text
/music/
/music/{release-slug}/
/music/{release-slug}/{track-slug}/
```

If the permalink structure contains `/index.php/`, WordPress may instead generate URLs such as `/index.php/music/`. This behavior comes from the WordPress or web-server permalink configuration rather than Slim Volume's routing.

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
- Seek/progress display
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

## Settings

Slim Volume includes settings for:

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

Slim Volume does not replace WordPress or a general SEO plugin for canonical URLs, robots directives, XML sitemaps, redirects, or other site-wide SEO responsibilities.

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

`v0.6.0`
