# Slim Volume

Slim Volume is a WordPress-native music catalog and audio player plugin for artists, bands, labels, and music projects.

It provides release archives, single release pages, track deep-dive pages, admin workflow tools, a persistent frontend audio player, queue drawer, theming settings, and optional Butterchurn visualizer support.

## Current Status

`v0.3.1-beta`

Slim Volume is ready for controlled production use and early customer projects. APIs, templates, settings, and markup may still change before the first stable release.

## Features

- Release and track custom post types
- Public music archive at `/music/`
- Nested track URLs at `/music/{release-slug}/{track-slug}/`
- Release artwork via featured images
- Track artwork with release artwork fallback
- Artist and project attribution
- Hosted audio, external-link, and catalog-only workflows
- Plain lyrics and synchronized timed lyrics
- Release and track editorial content
- Configurable editorial font family, size, line height, and link color
- Release and track frontend templates
- Persistent frontend audio player
- Queue drawer with reorder/remove controls
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

## Butterchurn Visualizer

Butterchurn mode requires these files:

```text
assets/vendor/butterchurn/butterchurn.min.js
assets/vendor/butterchurn/butterchurn-presets.min.js
```

If those files are missing, Slim Volume falls back to the built-in bars visualizer option.

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

## Settings

Slim Volume includes settings for:

- Frontend audio player / catalog-only mode
- AJAX music navigation
- Player persistence
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

## Known Future Work

- Block editor polish
- Media field/admin UI polish
- Enhanced music search results
- Track-level lyric search result snippets
- Extra Butterchurn preset packs
- More template override documentation
- More developer hooks and filters
- Accessibility audit pass
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

## Version

Current beta release:

`v0.3.1`