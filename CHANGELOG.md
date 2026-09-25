# Changelog

All notable changes to Slim Volume are documented here.

## 0.7.1

### Changed
- Added clearer guidance for adding the Slim Volume catalog to site navigation using a Custom Link.
- Added current archive-path guidance to the catalog settings UI.

### Fixed
- Normalized WordPress navigation menu state on Slim Volume archive, release, and track routes.
- Prevented the configured Blog posts page from appearing active while browsing Slim Volume catalog content.
- Improved active-state handling for catalog navigation links.

## 0.7.0

### Added
- Added a configurable catalog URL base while preserving `/music/` as the default.
- Added configurable archive title and introductory content.
- Added deterministic catalog-base conflict validation for existing WordPress routes.
- Added an authoritative `Catalog` layer for catalog routing, archive URLs, archive title, and archive introduction.

### Changed
- Release archive and permalink registration now derive from the configured catalog base.
- Nested track rewrite rules and track permalink generation now derive from the configured catalog base.
- AJAX music navigation now receives the authoritative catalog archive URL from PHP rather than assuming `/music/`.
- Archive, release, and track breadcrumbs now reflect the configured archive identity.
- Admin track route previews now reflect the configured catalog base.
- Slim Volume archive SEO identity and SEO-facing URLs now follow the configured catalog settings.
- Archive title now acts as the public archive identity while archive intro remains visible editorial content.
- Preserved existing `with_front => false` permalink behavior.
- Updated catalog-related administration copy to avoid assuming a fixed `/music/` route.

### Fixed
- Prevented conflicting catalog URL bases from being saved when the requested top-level route is already in use.
- Preserved the previous valid catalog base when a requested base is invalid or conflicting.
- Preserved unrelated valid settings submitted in the same request when a catalog-base change is rejected.
- Prevented rewrite-rule flushing when the requested catalog base is rejected or unchanged.
- Removed active hardcoded `/music/` URL fallbacks from routing, templates, navigation, admin route previews, and Slim Volume SEO.
- Fixed WordPress Plugin Check issues in the catalog settings validation and archive URL display.

## 0.6.1

### Added
- Added a visible mobile seek thumb indicator to improve seek-bar affordance.

### Changed
- Refined desktop and mobile player visuals for clearer hierarchy, spacing, and control balance.
- Replaced legacy CSS-drawn transport glyphs with a consistent inline SVG icon system.
- Reworked the desktop queue toggle to use a compact queue icon while preserving accessible labeling.
- Harmonized desktop and mobile queue presentation with flatter rows, clearer current/next states, and quieter secondary actions.
- Improved desktop player-bar density and mobile Now Playing proportions.
- Improved responsive control sizing and interaction states across player presentations.

### Fixed
- Fixed desktop presentation subscriptions so desktop state changes render through the desktop drawer renderer.
- Fixed mobile presentation subscriptions so opening, minimizing, and closing the mobile player respond correctly to presentation-state changes.
- Fixed duplicate play/pause rendering caused by obsolete CSS pseudo-element transport icons.
- Improved queue-toggle accessibility by synchronizing its expanded state and accessible label.

## 0.6.0

### Added

- Dedicated mobile mini-player below the 760px player presentation breakpoint.
- Expanded mobile Now Playing sheet with artwork, metadata, transport controls, progress, seeking, and queue access.
- Mobile queue with current/next hierarchy, track selection, removal, and clear controls.
- Mobile sheet lifecycle behavior with scroll locking, Escape-to-close, focus entry/restore, and keyboard focus containment.
- Shared player-state subscription API for presentation renderers.
- Debug diagnostics for incompatible `player-shell.php` overrides, including missing or duplicate required audio markup.

### Changed

- Refactored the player around one authoritative audio element, one playback engine, one queue, and separate desktop/mobile presentation state.
- Desktop drawer rendering is now driven through presentation/state subscriptions rather than direct coupling from queue and playback mutations.
- Visualizer DOM ownership is isolated from the generic player element cache.
- Visualizer presentation is optional and no longer required for core player initialization.
- Media Session integration is isolated behind a dedicated adapter.
- Native mobile audio-environment detection and setup are isolated behind a dedicated adapter.
- AJAX player refresh behavior is presentation-agnostic and preserves the persistent audio element.
- AJAX navigation preserves player-owned body classes generically.
- Player presentation uses one documented 760px breakpoint across JavaScript and CSS.
- Mobile progress rendering remains isolated from high-frequency shared-state notifications.
- Responsive player CSS was cleaned up to remove obsolete desktop-on-mobile layout rules and duplicate overrides.

### Accessibility

- Added semantic dialog behavior to the expanded mobile player.
- Added focus movement into the sheet and restoration when minimized.
- Added keyboard focus containment while the sheet is open.
- Added Escape-to-close behavior.
- Improved touch targets, safe-area handling, focus-visible states, and reduced-motion coverage.

### Compatibility

- Preserved the public `window.SVPlayer` compatibility facade.
- Kept desktop player, drawer, queue, visualizer, Media Session, persistence, and AJAX playback behavior intact through the responsive architecture refactor.
- Theme `player-shell.php` overrides remain supported as a compatibility surface.

## 0.5.0

- Added Music > Tools with portable JSON discography export.
- Added export of artists/projects, releases, tracks, catalog identity, relationships, editorial content, publication state, lyrics, timed lyrics, credits, destination links, downloads, and descriptive media references.
- Added export-local portable relationship references without exposing WordPress post, term, or attachment IDs as catalog identity.
- Added preservation of drafts, private content, pending content, scheduled content, and unknown custom workflow statuses.
- Added export warnings for detectable relationship, media, lifecycle, artist-type, legacy-value, and timed-lyrics integrity problems.
- Preserved stale and incomplete timed-lyrics authoring work without regenerating it during export.
- Preserved legacy Slim Volume release genre and track external-destination data when present.
- Added private temporary export generation so a successful download does not begin until the complete JSON document has been generated.
- Fixed release button-label administration so a blank stored label remains blank instead of being persisted as the display fallback “Listen”.

## 0.4.1

- Fixed WordPress Plugin Check escaping compliance for music JSON-LD output.

## 0.4.0

- Added configurable music SEO modes: Off, Music Schema Only, and Full Music Metadata.
- Added music-specific structured data for artists and projects, releases, and tracks.
- Added stable music entity identifiers and improved artist/project identity resolution.
- Added official artist/project profile URLs for structured-data identity matching.
- Added music-aware page titles, descriptions, and social metadata in Full Music Metadata mode.
- Improved compatibility with dedicated SEO plugins.
- Improved archive and track structured data behavior.
- Added fallback artist/project settings for single-artist catalogs and unassigned releases.
- Improved Artists & Projects administration and music-settings wording.
- Migrated the legacy SEO enabled setting to the new SEO ownership modes.

## 0.3.1

- Added meta description tag to `/music/{release}` pages.

## 0.3.0

- Improved release and track presentation across desktop and mobile layouts.
- Added configurable editorial typography and link-color controls.
- Reworked track hero Play and Queue actions as compact accessible icon controls.
- Improved no-audio states and release track rows.

## 0.2.0

- Improved mobile release and track layouts.
- Reworked the earlier mobile persistent player layout.
- Added long-title overflow handling with reduced-motion support.
- Added animated Queue drawer behavior.
- Added Media Session integration and mobile background/lock-screen playback improvements.

## 0.1.0

- Added release and track catalog management.
- Added nested music routing and archive search.
- Added persistent audio playback, queue management, and visualizer support.
- Added artist/project attribution.
- Added synchronized timed lyrics.
- Added centralized release-to-track relationship management and repair.
- Added settings, template overrides, structured data, lifecycle version tracking, and uninstall handling.
