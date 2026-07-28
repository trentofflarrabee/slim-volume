# Slim Volume Timed Lyrics — Development Specification

**Status:** Implementation-ready draft  
**Target:** Post-MVP flagship feature  
**Feature name:** Timed Lyrics / Lyrics Sync  
**Plugin:** Slim Volume  
**Storage version:** 1

---

## 1. Purpose

Timed Lyrics adds line-by-line lyric synchronization to Slim Volume. A track editor can play the track inside WordPress and press the spacebar when each lyric line should become active. On the public track page, the current lyric line highlights as the Slim Volume player advances.

Timed Lyrics is an optional enhancement. It must never make ordinary tracks, releases, or catalog-only installations depend on lyrics, audio, synchronization data, JavaScript, or the frontend player.

The canonical content remains the existing plain lyrics field:

```text
_sv_lyrics
```

The synchronized timing data is stored separately:

```text
_sv_timed_lyrics
```

---

## 2. Product decisions

### 2.1 Core plugin, not a separate add-on

The first implementation lives inside Slim Volume because Slim Volume already owns:

- Track and release relationships
- Track audio selection
- Plain lyrics
- Frontend track templates
- The persistent audio player
- AJAX music navigation
- WordPress permissions and saving

The architecture should remain modular enough that the synchronization studio could be extracted into an add-on later.

### 2.2 Dedicated WordPress admin workspace

The full synchronization tool does not belong inside a normal metabox. The track editor gets a compact status panel and an **Open Lyrics Sync** button. That button opens a dedicated screen:

```text
/wp-admin/admin.php?page=slim-volume-lyrics-sync&track_id=123
```

The dedicated screen uses most of the available viewport and behaves like a focused application.

### 2.3 Line-level timing only

Version 1 supports line-level activation timestamps.

It does not support:

- Word-by-word karaoke timing
- Automatic transcription
- Automatic lyric alignment
- Multiple singers or colors
- Translations
- Collaborative editing

### 2.4 No automatic frontend offset

A timestamp means:

> The moment Slim Volume should visually activate this lyric line.

The synchronizer is expected to anticipate the lyric and press Space slightly before the sung line when that feels appropriate. The frontend uses the saved timestamp exactly as entered.

Review tools may nudge individual timestamps. A hidden advanced repair action may shift all timestamps, but no automatic offset is applied during playback.

### 2.5 Progressive enhancement

The public fallback order is strict:

1. Complete, valid, current timed lyrics + enabled player  
   → render synchronized lyrics.
2. Plain lyrics without valid timed data  
   → render existing static lyrics.
3. No lyrics  
   → omit the Lyrics section.
4. Draft, partial, invalid, or stale timed data  
   → ignore timing data and render static lyrics.
5. Frontend player disabled  
   → render static lyrics.
6. Timed Lyrics feature disabled  
   → render static lyrics and preserve saved timing data.
7. JavaScript unavailable  
   → synchronized markup remains readable as plain lyrics.

Release pages and release tracklists continue to work regardless of lyric status.

---

## 3. Current-code integration points

This specification is based on the current project files.

### `includes/Plugin.php`

Current relevant hooks:

- `Meta::register()` on `init`
- Track metabox registration
- Track save callback
- Frontend asset enqueue
- Admin asset enqueue
- Template loader
- SEO output

Timed Lyrics will add service, admin-screen, REST, and reconciliation hooks here.

### `includes/Meta.php`

Current track meta includes:

- `_sv_lyrics`
- `_sv_audio_attachment_id`
- `_sv_audio_url`
- `_sv_duration`
- `_sv_duration_seconds`

Timed Lyrics adds two registered meta keys:

- `_sv_timed_lyrics`
- `_sv_timed_lyrics_status`

### `includes/Admin/TrackMetaBoxes.php`

The existing Lyrics metabox renders `_sv_lyrics` as a textarea and saves it with the Track Details nonce. The Timed Lyrics status panel should be added beneath this textarea or as a separate side metabox.

The existing save callback already saves the lyrics and audio identifiers. A separate later-priority reconciliation hook should determine whether saved synchronization data has become stale.

### `includes/Assets.php`

Frontend CSS is loaded on Slim Volume music routes. Player JavaScript is skipped when `player_enabled` is false.

Admin assets currently load only on:

- `post.php`
- `post-new.php`
- `edit.php`

The dedicated Lyrics Sync page requires an explicit admin-page branch before that current hook guard.

### `includes/Frontend/PlayerData.php`

Current track data exposes:

- Track ID
- Audio URL
- Release
- Artwork
- External links
- Playlist context

Timed Lyrics does not need to be embedded into every playlist item. The single track page can render its own timed lyric payload. The player needs small event additions so a separate timed-lyrics script can react to player readiness, track changes, and AJAX page refreshes.

### `templates/single-sv_track.php`

Current lyrics output is:

```php
<?php if ($lyrics) : ?>
    <section class="sv-track-lyrics">
        <h2>Lyrics</h2>
        <div class="sv-rich-text">
            <?php echo wp_kses_post(wpautop($lyrics)); ?>
        </div>
    </section>
<?php endif; ?>
```

This becomes a conditional enhanced/static renderer while preserving the same section and heading.

### `assets/js/slim-volume-player.js`

The player already exposes:

- `audioElement`
- `play()`
- `pause()`
- `seek(seconds)`
- `getCurrentTime()`
- `getDuration()`
- `getCurrentTrack()`
- `refreshPage()`

The timed-lyrics module should use this public API rather than reach into private player state.

### `assets/js/admin-track-media.js`

The current media picker updates audio attachment fields. No immediate integration is required for version 1. Staleness is reconciled when the track is saved. A later polish pass may display a provisional warning as soon as the media field changes.

### `includes/Admin/Settings.php`

The current settings page has General, Catalog, SEO, and Appearance tabs. Add a Timed Lyrics checkbox to General rather than creating another top-level tab for one setting.

---

## 4. Feature setting

Add to `Settings::defaults()`:

```php
'timed_lyrics_enabled' => false,
```

Add to `Settings::sanitize_settings()`:

```php
'timed_lyrics_enabled' => ! empty($input['timed_lyrics_enabled']),
```

Add under the General tab:

```text
Timed Lyrics
[ ] Enable timed lyrics synchronization

Adds the Track editor status panel, dedicated Lyrics Sync workspace,
and synchronized lyric highlighting when valid timing data exists.

Saved timing data is preserved when this option is disabled.
Requires the frontend player for live public highlighting.
```

### Behavior when disabled

- Do not show the Lyrics Sync button/status panel.
- Dedicated sync URL displays an explanatory disabled-state message.
- Do not enqueue timed-lyrics frontend JavaScript.
- Render ordinary static lyrics.
- Do not delete `_sv_timed_lyrics`.
- Do not alter release or track routing.

---

## 5. Storage model

### 5.1 Primary meta

```text
_sv_timed_lyrics
```

Store one canonical JSON string in a single post-meta row.

Registration:

```php
register_post_meta(
    PostTypes::TRACK,
    '_sv_timed_lyrics',
    [
        'single'            => true,
        'type'              => 'string',
        'show_in_rest'      => false,
        'sanitize_callback' => [TimedLyrics::class, 'sanitize_json_meta'],
        'auth_callback'     => static function (): bool {
            return current_user_can('edit_posts');
        },
    ]
);
```

A custom REST route handles structured reads/writes and checks `edit_post` for the specific track.

### 5.2 Derived status meta

```text
_sv_timed_lyrics_status
```

Allowed values:

```text
none
draft
complete
stale
```

This is a query-friendly cache for future admin columns and bulk views. It is never trusted as the only validation source.

The JSON document is authoritative. The status meta is updated by the Timed Lyrics service after each save and after track lyrics/audio changes.

Registration:

```php
register_post_meta(
    PostTypes::TRACK,
    '_sv_timed_lyrics_status',
    [
        'single'            => true,
        'type'              => 'string',
        'default'           => 'none',
        'show_in_rest'      => false,
        'sanitize_callback' => [TimedLyrics::class, 'sanitize_status'],
        'auth_callback'     => static function (): bool {
            return current_user_can('edit_posts');
        },
    ]
);
```

### 5.3 Version 1 JSON shape

```json
{
  "version": 1,
  "status": "complete",
  "trackId": 123,
  "lyricsHash": "sha256:...",
  "audio": {
    "attachmentId": 456,
    "urlHash": "sha256:...",
    "duration": 235.418
  },
  "updatedAt": "2026-07-28T20:30:00Z",
  "lines": [
    {
      "id": "line-0001",
      "type": "line",
      "text": "I found your photos and your ticket stubs",
      "start": 12.42
    },
    {
      "id": "line-0002",
      "type": "line",
      "text": "I keep them close to mine",
      "start": 16.81
    },
    {
      "id": "spacer-0003",
      "type": "spacer",
      "text": "",
      "start": null
    },
    {
      "id": "section-0004",
      "type": "section",
      "text": "Chorus",
      "start": null
    }
  ]
}
```

### 5.4 Field definitions

#### `version`

Integer schema version. Version 1 is the only accepted value initially.

#### `status`

Stored authoring status:

```text
draft
complete
```

`stale` is computed by comparing the saved hashes/signature against current track content. It is not an author-selected status inside the document.

#### `trackId`

Stored for portability and validation. The server overwrites this with the route track ID.

#### `lyricsHash`

SHA-256 hash generated from normalized current `_sv_lyrics`.

#### `audio`

Server-generated descriptor for the exact audio source used during synchronization.

- `attachmentId`: selected `_sv_audio_attachment_id`, or `0`
- `urlHash`: SHA-256 hash of the final resolved audio URL
- `duration`: browser-reported duration rounded to three decimals, or `0`

The server must not trust a client-supplied attachment ID or URL hash. It resolves the current track audio using the same attachment-first precedence as the player.

#### `updatedAt`

Server-generated UTC timestamp in ISO 8601 format.

#### `lines`

Ordered line records.

Allowed line types:

- `line`: syncable lyric line; requires `start` when complete
- `section`: visible heading; does not require a timestamp
- `spacer`: preserved stanza break; empty text; no timestamp

#### `start`

Seconds from the beginning of the audio, rounded to three decimals.

An end timestamp is not stored. The active range ends when the next timed line begins. The final line remains active through the end of the track.

---

## 6. Normalization rules

### 6.1 Plain lyrics normalization

For hashing and initial line generation:

1. Convert CRLF and CR line endings to LF.
2. Decode HTML entities.
3. Strip HTML tags for the timed-lyrics line model.
4. Remove trailing spaces from each line.
5. Remove blank lines at the beginning and end.
6. Preserve interior blank lines.
7. Preserve case and punctuation.
8. Join normalized lines with `\n`.
9. Hash with SHA-256 and prefix the value with `sha256:`.

The existing static renderer may continue allowing safe HTML. Timed synchronization version 1 is line-based plain text. The admin screen should warn when significant HTML markup is detected.

### 6.2 Line generation

- Every non-empty normalized line defaults to `type: line`.
- Every interior blank line becomes `type: spacer`.
- IDs are generated server-side or by the admin client and normalized server-side.
- Recommended ID format:

```text
line-0001
spacer-0002
section-0003
```

The Prepare mode may let the user change a line from `line` to `section`.

### 6.3 Audio source normalization

Use the current player precedence:

1. `_sv_audio_attachment_id` → attachment URL
2. `_sv_audio_url` → external URL
3. No audio

Normalize the URL by trimming whitespace and removing a fragment. Do not remove meaningful query parameters from signed or CDN URLs before hashing.

---

## 7. Validation and public eligibility

Create a domain service:

```text
includes/TimedLyrics.php
```

Recommended public methods:

```php
TimedLyrics::get_document(int $track_id): array
TimedLyrics::save_document(int $track_id, array $payload): array
TimedLyrics::delete_document(int $track_id): void
TimedLyrics::get_status(int $track_id): string
TimedLyrics::reconcile(int $track_id): string
TimedLyrics::get_public_payload(int $track_id): array
TimedLyrics::normalize_lyrics(string $lyrics): string
TimedLyrics::lyrics_hash(string $lyrics): string
TimedLyrics::audio_descriptor(int $track_id, float $duration = 0): array
TimedLyrics::validate_document(int $track_id, array $document, bool $for_publish): array
TimedLyrics::sanitize_json_meta($value): string
TimedLyrics::sanitize_status($value): string
```

### 7.1 Draft validation

Draft data may contain:

- Missing timestamps
- Partially timed lines
- A duration of `0`
- Non-increasing timestamps while actively being edited

The server still sanitizes all values and rejects malformed structures.

### 7.2 Complete validation

A document may be marked complete only when:

- Track exists and is `sv_track`
- Current `_sv_lyrics` is not empty
- Current audio URL is available
- `lyricsHash` matches current normalized lyrics
- Audio attachment ID and URL hash match the current source
- At least one `type: line` exists
- Every `type: line` has a finite start time
- Every start is `>= 0`
- Timed-line starts are strictly increasing
- When duration is known, no start exceeds duration by more than 0.5 seconds
- Line count and text match the normalized authoring model
- JSON payload is within size limits

Recommended limits:

```text
Maximum JSON size: 512 KB
Maximum records: 2,000
Maximum text per record: 2,000 characters
Maximum start: 86,400 seconds
Timestamp precision: 3 decimals
```

### 7.3 Stale state

A complete document is stale when:

- Current lyrics hash differs
- Current audio attachment ID differs
- Current audio URL hash differs
- The audio source has been removed
- Current line model no longer matches stored line text/order
- Document schema fails validation

A duration difference greater than one second should produce a warning. It may mark data stale in version 1 because replacement audio commonly shifts all later timestamps.

Stale data is preserved but never used for public highlighting.

### 7.4 Reconciliation

Register:

```php
add_action(
    'save_post_' . PostTypes::TRACK,
    [TimedLyrics::class, 'reconcile'],
    20
);
```

Priority 20 ensures TrackMetaBoxes has already saved lyrics and audio meta.

Reconciliation:

1. Read existing document.
2. Return `none` if no document.
3. Validate document against current lyrics/audio.
4. Set `_sv_timed_lyrics_status` to:
   - `draft`
   - `complete`
   - `stale`
5. Never delete timing data automatically.

If the user changes content and later restores the exact original lyrics/audio, reconciliation may return the status to `complete`.

---

## 8. Admin architecture

### 8.1 New files

```text
includes/TimedLyrics.php
includes/Admin/TimedLyricsScreen.php
includes/Rest/TimedLyricsController.php

assets/js/admin-timed-lyrics.js
assets/css/admin-timed-lyrics.css
```

Optional later:

```text
includes/Admin/TimedLyricsDashboard.php
```

### 8.2 Plugin registration

Add requires in `includes/Plugin.php`.

Recommended hooks:

```php
add_action('admin_menu', [Admin\TimedLyricsScreen::class, 'register']);
add_action('rest_api_init', [Rest\TimedLyricsController::class, 'register_routes']);
add_action(
    'save_post_' . PostTypes::TRACK,
    [TimedLyrics::class, 'reconcile'],
    20
);
```

### 8.3 Track editor status panel

Add beneath the existing Lyrics textarea or as a side metabox.

#### No plain lyrics

```text
Timed Lyrics

Add and save plain lyrics before starting synchronization.
```

No sync button.

#### Lyrics but no audio

```text
Timed Lyrics

Select and save a streaming audio file before starting synchronization.
```

No sync button.

#### Ready but unsynced

```text
Timed Lyrics

42 lyric lines found
Status: Not synchronized

[Open Lyrics Sync]
```

#### Draft

```text
Timed Lyrics

27 of 42 lines synchronized
Status: Draft

[Resume Lyrics Sync]
```

#### Complete

```text
Timed Lyrics

42 of 42 lines synchronized
Status: Complete
Last updated: July 28, 2026

[Review Lyrics Sync]
[Clear Timings]
```

#### Stale

```text
Timed Lyrics

Status: Needs review

The lyrics or audio changed after this timing pass.
Visitors currently see ordinary static lyrics.

[Review Lyrics Sync]
```

Clearing timings must require a nonce-protected confirmation and must not clear `_sv_lyrics`.

### 8.4 Dedicated screen registration

Register a submenu page under the release/music menu:

```php
add_submenu_page(
    'edit.php?post_type=' . PostTypes::RELEASE,
    __('Lyrics Sync', 'slim-volume'),
    __('Lyrics Sync', 'slim-volume'),
    'edit_posts',
    'slim-volume-lyrics-sync',
    [TimedLyricsScreen::class, 'render']
);
```

For version 1, the visible menu item may open a track selector or instructions. The primary workflow launches the screen with `track_id`.

The screen must verify:

```php
current_user_can('edit_post', $track_id)
```

### 8.5 Screen layout

Recommended desktop layout:

```text
┌─────────────────────────────────────────────────────────────────────┐
│ Back to Track     Find You — Off The Grid       Draft / Saved       │
├──────────────────────────────────────┬──────────────────────────────┤
│ Audio timeline and transport         │ Full lyric line list         │
│                                      │                              │
│ Current line                         │ 00:12.420  First line         │
│ I found your photos...               │ 00:16.810  Second line        │
│                                      │ --:--.---  Third line         │
│ Next line                            │                              │
│ I keep them close to mine            │                              │
│                                      │                              │
│ [Start] [Pause] [Undo] [Save Draft]  │                              │
└──────────────────────────────────────┴──────────────────────────────┘
```

Mobile admin support should remain usable for review and edits, but live spacebar synchronization is a desktop-first workflow.

### 8.6 Modes

#### Prepare

- Display parsed lines.
- Preserve stanza breaks.
- Allow editing text before timing only if the user also confirms writing changes back to `_sv_lyrics`.
- Allow marking headings as `section`.
- Confirm current audio source.
- Choose first line to sync.
- Rebuilding lines preserves the previous JSON as recoverable until saved.

Preferred safety rule: the sync screen should not silently alter `_sv_lyrics`.

#### Sync

- User clicks Start Sync.
- Audio begins after a user gesture.
- Current line is large and visually prominent.
- Next line is visible.
- Pressing Space stores `audio.currentTime` for the current line and advances.
- No hidden offset is added.

#### Review

- Replay with live highlighting.
- Selecting a line seeks to its time.
- Edit individual timestamps.
- Change line type.
- Undo/redo adjustments.
- Mark the document complete only after validation passes.

### 8.7 Keyboard controls

Keyboard shortcuts are active only while the sync workspace is armed and focus is not inside an input, textarea, select, or editable element.

```text
Space                    Stamp current line and advance
Enter                    Play / pause
Backspace                Undo most recent stamp
Arrow Up / Arrow Down    Select previous / next syncable line
Alt + Arrow Left         Nudge selected timestamp -0.10 seconds
Alt + Arrow Right        Nudge selected timestamp +0.10 seconds
Shift + Alt + Arrow      Nudge selected timestamp ±0.50 seconds
Escape                   Leave sync mode / close confirmation
```

All keyboard actions must also have visible buttons.

Prevent browser navigation when Backspace is used by the armed sync tool.

### 8.8 Autosave

Use debounced draft autosave:

- Save after 1.5 seconds without changes.
- Multiple rapid Space presses are batched.
- Show `Saving…`, `Saved`, or `Save failed`.
- Use `aria-live="polite"` for save status only.
- Keep an in-memory undo stack.
- Add a `beforeunload` warning only while unsaved changes exist or the latest save failed.
- Explicit buttons:
  - Save Draft
  - Mark Complete
  - Reset Timings
  - Delete Timed Lyrics

No public enhancement occurs until Mark Complete succeeds.

---

## 9. REST API

Create namespace:

```text
slim-volume/v1
```

Route:

```text
/tracks/{id}/timed-lyrics
```

### 9.1 GET

Returns the authoring payload for an editable track.

Permission:

```php
current_user_can('edit_post', $track_id)
```

Example response:

```json
{
  "track": {
    "id": 123,
    "title": "Find You",
    "editUrl": "...",
    "releaseTitle": "Off The Grid"
  },
  "lyrics": {
    "raw": "...",
    "hash": "sha256:...",
    "lineCount": 42
  },
  "audio": {
    "url": "...",
    "attachmentId": 456,
    "duration": 235.418
  },
  "timedLyrics": {
    "status": "draft",
    "document": {}
  },
  "validation": {
    "isStale": false,
    "errors": [],
    "warnings": []
  }
}
```

The audio URL is returned only to an authorized editor.

### 9.2 POST

Accepts:

```json
{
  "status": "draft",
  "audioDuration": 235.418,
  "lines": []
}
```

The server overwrites:

- `version`
- `trackId`
- `lyricsHash`
- Audio attachment ID
- Audio URL hash
- `updatedAt`

When `status` is `complete`, run complete validation. On failure, return HTTP 400 with field-level errors and do not overwrite a previously complete document unless the user explicitly saves as draft.

### 9.3 DELETE

Deletes:

- `_sv_timed_lyrics`
- `_sv_timed_lyrics_status`

Does not delete `_sv_lyrics`.

### 9.4 Security

- Use `wp_rest` nonce.
- Require HTTPS in production through normal WordPress deployment.
- Require `edit_post`.
- Sanitize every line.
- Reject unknown types.
- Reject excessive payload size.
- Never accept arbitrary post IDs from the body when the route already contains the ID.
- Escape all admin output.
- Do not make authoring payloads publicly readable.

---

## 10. Admin asset loading

Update `Assets::enqueue_admin()`.

The current method returns before loading anything unless the hook is `post.php`, `post-new.php`, or `edit.php`. Handle the dedicated screen first:

```php
if ($hook === 'sv_release_page_slim-volume-lyrics-sync') {
    self::enqueue_timed_lyrics_admin();
    return;
}
```

The exact hook suffix should be confirmed from the registered parent menu.

Admin dependencies:

```text
wp-element is not required for version 1
wp-api-fetch
wp-i18n
```

A vanilla JavaScript implementation is sufficient and consistent with the current player.

Localize/configure:

```php
[
    'trackId'  => $track_id,
    'restUrl'  => rest_url('slim-volume/v1/tracks/' . $track_id . '/timed-lyrics'),
    'nonce'    => wp_create_nonce('wp_rest'),
    'strings'  => [...],
]
```

Use `wp_add_inline_script(..., 'before')` or `wp_localize_script()`.

---

## 11. Frontend rendering

### 11.1 New frontend helper

Use `TimedLyrics::get_public_payload($track_id)`.

It returns an empty array unless all conditions are true:

- Setting enabled
- Frontend player enabled
- Stored status is complete
- Current lyrics hash matches
- Current audio descriptor matches
- Document passes complete validation

### 11.2 Template change

In `single-sv_track.php`:

```php
$timed_lyrics = TimedLyrics::get_public_payload($track_id);
```

Keep the existing section condition based on plain lyrics:

```php
<?php if ($lyrics) : ?>
    <section class="sv-track-lyrics">
        <h2><?php esc_html_e('Lyrics', 'slim-volume'); ?></h2>

        <?php if ($timed_lyrics) : ?>
            <?php TimedLyrics::render_frontend($track_id, $timed_lyrics); ?>
        <?php else : ?>
            <div class="sv-rich-text">
                <?php echo wp_kses_post(wpautop($lyrics)); ?>
            </div>
        <?php endif; ?>
    </section>
<?php endif; ?>
```

### 11.3 Suggested markup

```html
<div
  class="sv-rich-text sv-timed-lyrics"
  data-sv-timed-lyrics
  data-sv-track-id="123"
>
  <p
    class="sv-timed-lyrics__line is-upcoming"
    data-sv-lyric-line
    data-sv-lyric-index="0"
    data-sv-lyric-start="12.420"
  >
    I found your photos and your ticket stubs
  </p>

  <p
    class="sv-timed-lyrics__line is-upcoming"
    data-sv-lyric-line
    data-sv-lyric-index="1"
    data-sv-lyric-start="16.810"
  >
    I keep them close to mine
  </p>

  <div class="sv-timed-lyrics__spacer" aria-hidden="true"></div>

  <p class="sv-timed-lyrics__section">Chorus</p>
</div>
```

Do not place the full JSON document in public HTML when start attributes are sufficient.

### 11.4 No-JavaScript behavior

Without JavaScript:

- All lyric lines remain visible.
- No line is hidden.
- No controls are required to read lyrics.
- The page remains indexable.
- Static story, credits, links, and navigation remain unchanged.

---

## 12. Frontend JavaScript

Create:

```text
assets/js/slim-volume-timed-lyrics.js
```

Expose:

```text
window.SVTimedLyrics
```

Recommended public methods:

```js
init()
refresh()
destroy()
getActiveIndex()
setFollowEnabled(enabled) // reserved for later
```

### 12.1 Player event contract

Add these events to `slim-volume-player.js`.

After `window.SVPlayer` is assigned:

```js
document.dispatchEvent(
  new CustomEvent("slimvolume:player-ready", {
    detail: { player: window.SVPlayer },
  }),
);
```

After `loadTrack()` changes the current track:

```js
document.dispatchEvent(
  new CustomEvent("slimvolume:track-change", {
    detail: { track: this.getCurrentTrack() },
  }),
);
```

At the end of `refreshPage()` after AJAX content bindings:

```js
document.dispatchEvent(
  new CustomEvent("slimvolume:page-refreshed"),
);
```

These events are useful beyond Timed Lyrics and create a stable plugin integration surface.

### 12.2 Initialization

The module:

1. Finds `[data-sv-timed-lyrics]`.
2. Reads the page track ID and line starts.
3. Obtains `window.SVPlayer`.
4. Binds to the player audio element.
5. Listens for player-ready, track-change, and page-refreshed events.
6. Refreshes after AJAX page replacement.
7. Does nothing when no timed lyric container exists.

### 12.3 Active-line algorithm

Only highlight when:

```js
String(window.SVPlayer.getCurrentTrack()?.id) === pageTrackId
```

If a different track is playing:

- Clear `.is-active`
- Remove `aria-current`
- Leave lines readable
- Do not make click-to-seek active

Find the active line as the greatest start time less than or equal to current audio time.

Use binary search over sorted timestamps.

States:

```text
.is-past
.is-active
.is-upcoming
```

The active line receives:

```html
aria-current="true"
```

Do not use `aria-live` for every lyric change; that would be disruptive.

### 12.4 Update loop

Use `requestAnimationFrame()` while audio is playing. Only update the DOM when the active index changes.

On pause, seek, metadata load, or track change, run one immediate update.

Cancel animation frames when:

- Audio pauses
- Page content changes
- Module is destroyed
- No matching timed lyric container exists

### 12.5 Click-to-seek

When the page track is the current player track:

- JS adds `tabindex="0"` and `role="button"` to timed lines.
- Click seeks to the line timestamp.
- Enter or Space seeks to the line timestamp.
- The accessible label communicates the target time.

When another track is active, lines remain ordinary text and do not switch tracks unexpectedly.

### 12.6 Auto-scroll

Auto-scroll is not required for the first functional commit.

Recommended polish phase:

- Scroll active line into view only when it is outside the lyrics viewport.
- Never move keyboard focus.
- Respect `prefers-reduced-motion`.
- Manual wheel/touch/scroll interaction disables follow until explicitly re-enabled or the track changes.
- Add a visible Follow Lyrics toggle if auto-scroll ships.

---

## 13. Frontend asset loading

When both settings are enabled:

```text
player_enabled
timed_lyrics_enabled
```

enqueue the timed-lyrics script on all Slim Volume music routes, not only hard-loaded track pages.

Reason: a visitor may start on `/music` or a release page and AJAX-navigate to a track page. Assets added only to the destination document head will not automatically execute during the current AJAX content swap.

Enqueue:

```php
wp_enqueue_script(
    'slim-volume-timed-lyrics',
    SLIM_VOLUME_URL . 'assets/js/slim-volume-timed-lyrics.js',
    ['slim-volume-player'],
    self::asset_version($timed_lyrics_js_path),
    true
);
```

CSS may be included in `slim-volume.css` or a separate stylesheet loaded on all music routes. Given the existing single frontend stylesheet, adding the timed-lyrics component styles there is reasonable.

When the player is disabled:

- Do not enqueue timed-lyrics JavaScript.
- Render static lyrics.

---

## 14. Frontend styling

Add a component section to `assets/css/slim-volume.css`.

Suggested behavior:

```css
.sv-timed-lyrics {
  display: grid;
  gap: 0.7rem;
}

.sv-timed-lyrics__line {
  margin: 0;
  opacity: 0.46;
  transition:
    opacity var(--sv-transition-fast),
    transform var(--sv-transition-fast);
}

.sv-timed-lyrics__line.is-past {
  opacity: 0.34;
}

.sv-timed-lyrics__line.is-active {
  opacity: 1;
  font-weight: 700;
  transform: translateX(0.35rem);
}

.sv-timed-lyrics__line.is-upcoming {
  opacity: 0.56;
}

.sv-timed-lyrics__line[role="button"] {
  cursor: pointer;
}

.sv-timed-lyrics__line:focus-visible {
  outline: 2px solid currentColor;
  outline-offset: 4px;
}

.sv-timed-lyrics__section {
  margin: 1.4rem 0 0.2rem;
  font-size: 0.78rem;
  font-weight: 800;
  letter-spacing: 0.1em;
  text-transform: uppercase;
  opacity: 0.65;
}

.sv-timed-lyrics__spacer {
  height: 0.8rem;
}
```

The active state must not rely on color alone. Weight, opacity, and/or a marker should distinguish it.

Reduced motion:

```css
@media (prefers-reduced-motion: reduce) {
  .sv-timed-lyrics__line {
    transition: none;
  }

  .sv-timed-lyrics__line.is-active {
    transform: none;
  }
}
```

Theme overrides remain possible through normal Slim Volume template and CSS customization.

---

## 15. State matrix

| Plain lyrics | Timed data | Current validity | Player | Public result |
|---|---|---|---|---|
| No | None | N/A | Any | No Lyrics section |
| Yes | None | N/A | On | Static lyrics |
| Yes | Draft | Valid draft | On | Static lyrics |
| Yes | Complete | Valid/current | On | Timed highlighting |
| Yes | Complete | Stale lyrics | On | Static lyrics |
| Yes | Complete | Stale audio | On | Static lyrics |
| Yes | Complete | Invalid JSON | On | Static lyrics |
| Yes | Complete | Valid/current | Off | Static lyrics |
| Yes | Complete | Valid/current | On, feature off | Static lyrics |
| Yes | Complete | Valid/current | JS off | Readable timed markup, no highlighting |
| No | Complete | Stale | On | No Lyrics section |
| Release has unsynced tracks | Mixed | Mixed | Any | Release page/tracklist unchanged |
| Release has no tracks | N/A | N/A | Any | Release/catalog behavior unchanged |

---

## 16. Accessibility requirements

### Public track page

- Lyrics remain readable without JavaScript.
- Active line does not use color alone.
- Use `aria-current="true"` for the active line.
- Do not announce every line through a live region.
- Click-to-seek lines receive keyboard behavior only when seeking is available.
- Focus is never moved automatically.
- Auto-scroll, if added, respects reduced motion.
- No automatic audio playback on page load.

### Admin synchronization screen

- Visible shortcut reference.
- Every shortcut has a clickable control.
- Shortcut capture is disabled inside form fields/editable content.
- Save status uses `aria-live="polite"`.
- Current line and next line have clear labels.
- Focus indicators use WordPress admin conventions.
- Dialogs and confirmations trap/restore focus correctly.
- Buttons have explicit names; no icon-only action lacks an accessible label.
- Audio controls remain keyboard operable.

---

## 17. Error handling

### No lyrics

Show:

```text
Add and save plain lyrics before opening Lyrics Sync.
```

### No playable audio

Show:

```text
Select a streaming audio attachment or external audio URL before opening Lyrics Sync.
```

### Audio fails to load

Show:

```text
Slim Volume could not load this audio source in the browser.
Confirm that the URL is public and playable, then try again.
```

Do not discard existing draft data.

### External audio has no reliable duration

Allow draft synchronization if `currentTime` works. Do not allow Complete until duration is known or the user confirms a review warning, depending on implementation policy. The safer version 1 behavior is to require a finite duration for Complete.

### Lyrics changed

Preserve old data and show:

```text
The plain lyrics changed after this timing pass.
Rebuild the line map or restore the previous lyrics before publishing timed lyrics.
```

### Audio changed

Preserve old data and show:

```text
The streaming audio changed after this timing pass.
Review or rebuild the timestamps before publishing timed lyrics.
```

### REST save failure

- Keep local edits in memory.
- Display retry action.
- Do not show Saved.
- Warn before leaving.

### Invalid public data

Fail closed: render static `_sv_lyrics`; do not output an error to visitors.

Log details only when Slim Volume debug mode or `WP_DEBUG` is enabled.

---

## 18. Search, SEO, and catalog behavior

Timed Lyrics must not change existing search behavior.

- `/music` lyric search continues searching `_sv_lyrics`.
- Do not search `_sv_timed_lyrics`.
- Plain lyrics remain the canonical indexable content.
- SEO metadata does not need new fields.
- Do not inject full lyrics into MusicRecording JSON-LD as part of this feature.
- Catalog mode, external release links, and player-disabled installations remain valid.
- Release JSON-LD and track JSON-LD remain unchanged.

---

## 19. Import/export roadmap

Not required for the first functional version, but the storage format must support later portability.

### Planned formats

- Slim Volume JSON import/export
- LRC import/export
- WebVTT later if useful

### Slim Volume JSON export

Export the canonical versioned document. Include:

- Track title as informational metadata
- Release title as informational metadata
- Timing data
- Lyrics hash
- Audio duration

Do not rely on WordPress post IDs during import to a different site.

### LRC considerations

LRC supports line-level timestamps naturally. Section and spacer records may need comments or formatting conventions. Define this during the import/export phase rather than complicating version 1 storage.

---

## 20. Scale roadmap

Version 1 launches from the Track editor.

A later at-scale dashboard under **Music → Lyrics Sync** can add:

- Release filter
- Status filter
- Search tracks
- Synced line counts
- Audio presence
- Last updated
- Needs review status
- Bulk export
- Open Sync action

Recommended statuses:

```text
Not synced
Draft
Complete
Needs review
Missing lyrics
Missing audio
```

The separate `_sv_timed_lyrics_status` meta exists partly to support this dashboard without decoding every JSON document during list queries.

---

## 21. File-by-file implementation plan

### New files

#### `includes/TimedLyrics.php`

- Constants for meta keys and schema version
- Normalize lyrics
- Generate hashes
- Resolve audio descriptor
- Decode/encode canonical JSON
- Validate/sanitize documents
- Save/delete
- Compute status
- Public eligibility
- Frontend renderer
- Reconciliation callback

#### `includes/Admin/TimedLyricsScreen.php`

- Register admin submenu
- Verify setting, track, post type, and capability
- Render dedicated workspace shell
- Provide back-to-track/release links
- Provide no-lyrics/no-audio/disabled states

#### `includes/Rest/TimedLyricsController.php`

- Register GET/POST/DELETE routes
- Permission checks
- Request validation
- Call TimedLyrics service
- Return structured errors

#### `assets/js/admin-timed-lyrics.js`

- Load authoring payload
- Prepare lines
- Audio transport
- Spacebar stamping
- Undo/redo
- Timestamp nudging
- Draft autosave
- Review playback
- Completion request
- Save status and error handling

#### `assets/css/admin-timed-lyrics.css`

- Full workspace layout
- Current/next line emphasis
- Line list states
- Timeline and controls
- Responsive behavior
- Focus and reduced-motion rules

#### `assets/js/slim-volume-timed-lyrics.js`

- Detect timed lyric markup
- Bind public player API
- Track current player track
- Active-line algorithm
- Click/keyboard seek
- AJAX refresh lifecycle
- Cleanup

### Modified files

#### `includes/Plugin.php`

- Require three new PHP classes
- Register admin menu
- Register REST routes
- Register track reconciliation
- Keep existing hooks unchanged

#### `includes/Meta.php`

- Register timed JSON and derived status meta

#### `includes/Admin/Settings.php`

- Add `timed_lyrics_enabled`
- Sanitize it
- Render General-tab checkbox

#### `includes/Admin/TrackMetaBoxes.php`

- Render sync status beneath Lyrics or register a status metabox
- Add Open/Resume/Review button
- Add clear action with nonce
- Do not save timed JSON through the normal track form

#### `includes/Assets.php`

- Enqueue dedicated admin screen assets
- Enqueue frontend timed-lyrics script when timed lyrics and player are enabled
- Add script dependencies/config
- Continue loading main frontend CSS for music routes

#### `includes/Frontend/PlayerData.php`

No timed payload required in playlist data.

Optional refactor: expose a shared audio-source resolver so PlayerData and TimedLyrics cannot diverge. If not refactored, add tests guaranteeing identical attachment-first resolution.

#### `templates/single-sv_track.php`

- Import/use TimedLyrics helper
- Render enhanced lyrics only for public-eligible data
- Preserve static fallback exactly

#### `assets/js/slim-volume-player.js`

- Dispatch player-ready event
- Dispatch track-change event
- Dispatch page-refreshed event
- Keep public API backward compatible

#### `assets/css/slim-volume.css`

- Add public timed-lyrics component styles
- Add reduced-motion handling

#### `assets/css/admin.css`

- Add compact Track editor status-panel styles only
- Keep dedicated workspace styles in its own CSS file

#### `assets/js/admin-track-media.js`

No required version 1 change. Optional later event for provisional stale warnings.

---

## 22. Recommended implementation phases and commits

### Phase 1 — Data foundation

Branch:

```text
timed-lyrics-foundation
```

Commit:

```text
Slim Volume: add timed lyrics data foundation
```

Scope:

- Meta keys
- TimedLyrics service
- Normalization
- Validation
- Status and reconciliation
- Unit-testable methods
- No public UI change

Acceptance:

- Draft document saves safely.
- Complete validation works.
- Changing lyrics/audio produces stale status.
- Existing tracks are unchanged.

### Phase 2 — Admin screen shell

Branch:

```text
timed-lyrics-admin
```

Commit:

```text
Slim Volume: add lyrics sync admin workspace
```

Scope:

- Setting
- Track status panel
- Dedicated screen
- REST GET/POST/DELETE
- Admin assets
- Empty/error states

Acceptance:

- Editors can open the correct track.
- Unauthorized users cannot read/write data.
- Draft data round-trips.

### Phase 3 — Synchronization workflow

Commit:

```text
Slim Volume: add manual line timing workflow
```

Scope:

- Audio playback
- Spacebar timestamping
- Undo
- Selection
- Timestamp nudging
- Autosave draft
- Mark Complete
- Review highlighting

Acceptance:

- A full track can be synchronized without page reload.
- Draft recovery works.
- Completion blocks invalid/partial timing.

### Phase 4 — Frontend playback

Branch:

```text
timed-lyrics-frontend
```

Commit:

```text
Slim Volume: add synchronized lyric highlighting
```

Scope:

- Track template enhancement
- Frontend timed script
- Player lifecycle events
- Active/past/upcoming states
- Click-to-seek
- AJAX navigation support
- Static fallback

Acceptance:

- Hard-loaded track page highlights correctly.
- AJAX navigation to/from track pages works.
- Different current track produces no false highlighting.
- Player disabled produces static lyrics.
- Draft/stale data produces static lyrics.

### Phase 5 — Hardening and polish

Commit:

```text
Slim Volume: harden timed lyrics accessibility and recovery
```

Scope:

- Reduced motion
- Keyboard review
- Better stale warnings
- Save failure recovery
- Long-song performance
- Optional follow/auto-scroll
- Documentation

---

## 23. Test plan

### PHP/data tests

- Empty meta returns `none`.
- Invalid JSON fails closed.
- Unknown schema version fails closed.
- Normalization handles CRLF/CR/LF.
- Interior blank lines are preserved.
- HTML is stripped for timed line matching.
- Draft accepts null starts.
- Complete rejects null starts.
- Complete rejects descending timestamps.
- Complete rejects negative timestamps.
- Complete rejects timestamps beyond duration.
- Lyrics edit causes stale.
- Audio attachment change causes stale.
- External audio URL change causes stale.
- Restoring original data can return complete.
- Deleting timed lyrics does not delete plain lyrics.
- Public payload is empty for draft/stale/disabled/player-off states.

### REST tests

- Editor with `edit_post` can GET/POST/DELETE.
- User without `edit_post` receives 403.
- Wrong post type receives 404 or 400.
- Oversized payload is rejected.
- Server overwrites hashes and IDs.
- Complete save returns validation errors when partial.
- DELETE preserves `_sv_lyrics`.

### Admin manual tests

- No lyrics state
- No audio state
- Unsynced state
- Resume draft
- Complete review
- Stale lyrics warning
- Stale audio warning
- Spacebar does not scroll page
- Shortcuts do not trigger in inputs
- Undo across multiple lines
- Autosave during rapid stamps
- Network failure and retry
- Leaving with unsaved data warning
- Track with 200+ lines
- External audio
- WordPress media attachment
- Mobile review layout

### Frontend manual tests

- No lyrics
- Plain unsynced lyrics
- Draft data
- Complete valid data
- Stale data
- Player disabled
- Timed Lyrics disabled
- JavaScript disabled
- Current page track playing
- Different track playing
- Pause/resume
- Seek backward/forward
- Previous/next track
- Queue reorder does not break state
- Refresh with persisted playback
- AJAX archive → release → track
- AJAX track → different track
- Click-to-seek
- Keyboard seek
- Reduced motion
- Theme template override
- Long lyric page
- Track without release
- Duplicate track/release title routes

---

## 24. Release acceptance criteria

Timed Lyrics is ready for release when all are true:

- Existing tracks with no lyrics render exactly as before.
- Existing tracks with plain lyrics render exactly as before until a valid complete sync exists.
- Draft and stale data never activates publicly.
- The syncer can finish a normal song with Space, Undo, Save Draft, and Mark Complete.
- Every saved timestamp is the exact captured player time; no hidden offset is applied.
- Frontend highlighting follows the persistent player.
- AJAX navigation refreshes the timed-lyrics module.
- Player-disabled/catalog-only mode remains fully functional.
- Audio or lyric changes safely invalidate public highlighting without deleting work.
- Unauthorized users cannot access authoring data.
- Public pages remain readable and indexable without JavaScript.
- The feature is documented as line-level timed lyrics, not word-level karaoke.

---

## 25. Explicit non-goals for version 1

- Automatic synchronization
- AI transcription
- Word-level timing
- Mobile-first live synchronization
- Multiple lyric languages
- Singer attribution
- Collaborative editing
- Public lyric editor
- Full waveform editor
- Third-party lyrics-service publishing
- Rich-result or SEO schema changes
- Changing release-page behavior
- Requiring lyrics for any track

---

## 26. Future enhancements

After the line-level tool is stable:

- Follow Lyrics / auto-scroll control
- Global shift-all repair tool
- LRC import/export
- Slim Volume JSON import/export
- Waveform visualization
- Bulk synchronization dashboard
- Track-list status columns
- Audio replacement fingerprint improvements
- Section-heading authoring shortcuts
- Multiple language versions
- REST hooks for external sync tools
- Optional extraction of the sync studio into an add-on

---

## 27. Developer hooks and filters

Add these only after the basic implementation is stable, but reserve names now:

```php
apply_filters('slim_volume_timed_lyrics_document', $document, $track_id);
apply_filters('slim_volume_timed_lyrics_public_payload', $payload, $track_id);
apply_filters('slim_volume_timed_lyrics_line_markup', $html, $line, $track_id);
do_action('slim_volume_timed_lyrics_saved', $track_id, $document, $status);
do_action('slim_volume_timed_lyrics_deleted', $track_id);
do_action('slim_volume_timed_lyrics_became_stale', $track_id, $reasons);
```

JavaScript events:

```text
slimvolume:player-ready
slimvolume:track-change
slimvolume:page-refreshed
slimvolume:timed-lyrics-active-line
```

Event names and payloads should be documented before third-party use.

---

## 28. Final architecture summary

```text
_sv_lyrics
    Canonical plain lyrics
          │
          ├── No valid timing data ──→ Static public lyrics
          │
          └── Lyrics Sync admin workspace
                    │
                    ├── Draft JSON
                    │      └── Static public lyrics
                    │
                    └── Complete JSON
                           │
                           ├── Lyrics/audio still match
                           │      └── Timed public highlighting
                           │
                           └── Lyrics/audio changed
                                  └── Stale status + static public lyrics
```

This design lets Slim Volume gain a distinctive synchronized-lyrics experience without making any existing catalog, release, track, or player workflow fragile.
