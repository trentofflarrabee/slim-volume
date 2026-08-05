# Slim Volume

Slim Volume is a WordPress-native music catalog and audio player plugin for artists, bands, labels, and music projects.

It provides release archives, single release pages, track deep-dive pages, admin workflow tools, a persistent frontend audio player, queue drawer, theming settings, and optional Butterchurn visualizer support.

## Current Status

`v0.1.0-beta`

Slim Volume is ready for controlled production use and early customer projects. APIs, templates, settings, and markup may still change before the first stable release.

## Features

- Releases custom post type
- Tracks custom post type
- Public music archive at `/music`
- Nested track URLs: `/music/{release-slug}/{track-slug}/`
- Release artwork via featured images
- Track artwork with release artwork fallback
- Track audio metadata
- Track lyrics field
- Release and track frontend templates
- Persistent frontend audio player
- Queue drawer with reorder/remove controls
- Play release / queue release actions
- Per-track play and queue actions
- Player state persistence
- AJAX navigation support
- Optional bars visualizer
- Optional Butterchurn visualizer
- Fullscreen visualizer mode
- Admin release dashboard
- Track context admin panel
- Release prefill when creating tracks
- Tracks admin release filter
- Themeable CSS variables
- Admin appearance presets and reset controls

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

- AJAX navigation
- Player persistence
- Visualizer enable/disable
- Visualizer mode
- Debug mode
- Appearance presets
- Player colors
- Button colors
- Card border color
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

```text
v0.1.0
```