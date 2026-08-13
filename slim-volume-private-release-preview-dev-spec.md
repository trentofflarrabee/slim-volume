# Slim Volume — Private Release Preview Dev Spec

**Plugin:** Slim Volume  
**Proposed release:** 0.3.0  
**Feature name:** Private Release Preview  
**Status:** Proposed / pre-implementation  
**Prepared:** 2026-08-12

---

## 1. Summary

Slim Volume should support password-protected releases using WordPress’s existing **Password protected** post visibility.

A release owner could use this for press previews, fan-club early access, private demos, label/client review, or other controlled pre-release listening.

The implementation should use WordPress’s native password system rather than introducing a separate Slim Volume password field or authentication mechanism.

The primary rule is:

> If a release requires a password, Slim Volume must not expose its protected listening content until WordPress considers that password satisfied.

This applies not only to the release page, but also to individual tracks, player payloads, AJAX navigation, queue data, audio URLs supplied by Slim Volume, and related frontend APIs.

---

## 2. Goals

| Goal | Requirement |
|---|---|
| Native WordPress behavior | Use the existing WordPress Password Protected visibility setting |
| Simple sharing | Artist sends a release URL and password separately |
| Protect listening content | No track list, player data, audio URL, download link, or private metadata before authorization |
| Track inheritance | Tracks belonging to a protected release inherit the release gate |
| AJAX-safe | Slim Volume navigation/player requests cannot bypass protection |
| Existing player compatibility | Once unlocked, the release behaves exactly like an ordinary release |
| Non-destructive | Removing/changing the password never changes release or track data |
| Theme-independent | Protection remains owned by Slim Volume / WordPress, not the active theme |

---

## 3. Non-goals

This feature is **not DRM**.

It will not attempt to prevent an authorized listener from:

- inspecting browser network requests;
- copying an audio URL after access has been granted;
- recording system audio;
- redistributing downloaded/streamed media;
- sharing the password.

Slim Volume currently serves normal web-accessible media URLs. True leak-resistant streaming would require a substantially different system involving private storage, temporary signed URLs, access tokens, or a streaming proxy.

That is explicitly outside this feature.

---

## 4. Administrator UX

There should be **no new password field in Slim Volume**.

The administrator continues to use WordPress:

**Release → Status / Visibility → Password protected**

and enters the desired password there.

Slim Volume may add a small contextual note in the Release editor later, such as:

> Password-protected releases can be used for private previews. Tracks and player access inherit the release password.

But that is explanatory only. WordPress remains the source of truth.

This avoids duplicate passwords and avoids storing authentication state in Slim Volume options or post meta.

---

## 5. Visitor UX

### Locked release

A visitor opening a protected release without authorization should see a deliberately designed preview state.

**Visible:**

- Release artwork
- Release title
- Optional artist attribution
- A small lock/private-preview label
- Password entry form

Example:

```text
[ COVER ART ]

NEW ALBUM

Slim Volume

PRIVATE PREVIEW

This release is password protected.
Enter the password to listen.

[ Password                  ]
[ Unlock Release ]
```

**Do not expose before unlock:**

- Track names
- Track durations
- Track count, if treated as private release information
- Release description
- Lyrics
- Credits
- Streaming/service links
- Download/purchase links
- Player queue
- Audio URLs
- Visualizer/player metadata
- Any unreleased track permalink list

For 0.3.0, prefer a conservative gate: **artwork/title/artist only**.

### Unlocked release

Once WordPress accepts the password, the page renders normally.

No special “private mode” player is necessary.

The visitor receives the normal:

```text
release hero
track list
playback
queue
lyrics
links
metadata
AJAX navigation
```

for as long as WordPress considers the password authorized.

---

## 6. Track inheritance

A track belonging to a password-protected release must not become a back door.

Suppose:

```text
/music/secret-record/
```

is protected, while:

```text
/music/secret-record/first-single/
```

has no WordPress password of its own.

The track page must still require the **parent release password**.

The access rule should conceptually be:

```text
track explicitly protected
        OR
parent release protected
        ↓
Slim Volume content protected
```

This inherited check should be centralized in a reusable Slim Volume function rather than duplicated throughout templates.

Conceptually:

```php
slim_volume_content_requires_password( $post_id )
```

or:

```php
Protection::requires_password( $post_id )
```

A focused helper/module is sufficient; do not turn this into a large architectural rewrite.

---

## 7. Protection boundary

The gate must occur **before protected data is assembled**.

Bad architecture:

```php
$playlist = build_full_private_playlist();
$audio_urls = get_all_audio_urls();

if ( post_password_required() ) {
    show_password_form();
}
```

Even if PHP never visibly prints `$playlist`, those values may leak into localized JS, page source, REST responses, or future code paths.

Preferred architecture:

```php
if ( protection_required_and_not_authorized() ) {
    render_locked_state();
    return;
}

$playlist = build_playlist();
```

The same principle applies throughout the plugin.

---

## 8. Areas requiring enforcement

| Surface | Locked behavior |
|---|---|
| Single release template | Render private-preview/password screen |
| Single track template | Inherit parent release protection |
| PlayerData release config | Return no protected playlist/audio data |
| PlayerData track config | Return no protected track/audio data |
| AJAX navigation | Never return protected rendered content without authorization |
| Persistent player | Cannot enqueue/play protected track without authorization |
| Queue hydration/restoration | Previously stored protected tracks must not resurrect without authorization |
| Release archives | Respect chosen locked-release visibility behavior |
| Search | Respect chosen locked-release visibility behavior |
| REST/custom endpoints | Must not expose private listening payload |
| Embeds/shortcodes | Must enforce the same release protection |
| Download URLs generated by Slim Volume | Must not be emitted before unlock |
| Media Session metadata | Must not reveal protected track metadata before unlock |

This table is the core security checklist for the feature.

---

## 9. Archive behavior

Protected releases should remain visible in `/music/`, but as locked releases.

Example:

```text
[ artwork ]

New Album
Slim Volume

🔒 Private Preview
```

Clicking it takes the visitor to the password screen.

The archive card must not show track names or other protected content.

A future option could allow hiding protected releases from archives entirely, but that should be deferred. For 0.3.0, use one predictable behavior.

---

## 10. Search behavior

For the first implementation:

- Protected **releases** may appear as locked search results.
- Protected **child tracks** should not appear individually in public search while their parent release is locked.

Otherwise someone could infer the entire track listing from search results.

Preferred search result:

```text
New Album — Private Preview
```

Avoid exposing:

```text
Track One
Track Two
Secret Bonus Track
Track Four
```

before authentication.

---

## 11. Password form

Use WordPress’s native password flow.

Prefer WordPress’s existing password form generation and cookie handling rather than building a custom authentication endpoint.

Slim Volume may wrap/style the resulting form so it visually matches the release page.

Requirements:

- proper `<label>`
- password input
- submit button
- keyboard accessible
- visible focus
- translatable strings
- responsive layout
- no JavaScript required to unlock

JavaScript can enhance it later but must not be necessary.

---

## 12. AJAX navigation

If Slim Volume AJAX-navigates from:

```text
/public-release/
```

to:

```text
/private-release/
```

the AJAX response must return the **locked preview**, not the private release markup.

After successfully entering the password, AJAX requests should begin receiving the authorized content because WordPress’s password cookie is now present.

Do not invent a second AJAX authentication state.

---

## 13. Persistent queue edge case

Consider this sequence:

1. Visitor unlocks a private release.
2. Adds tracks to the persistent queue.
3. Password authorization later disappears or they open the site in another browser/session.
4. Slim Volume restores the saved queue.

The player must not blindly restore protected audio.

During queue restoration, each candidate track should be re-authorized.

For 0.3.0, unauthorized entries should be **removed from the playable queue**.

This is simpler, safer, and less confusing than keeping unavailable locked entries around.

---

## 14. Direct track URLs

A protected release should make its child tracks behave approximately like:

```text
/music/private-release/song/
        ↓
Private Release Preview
        ↓
Enter release password
```

After authorization, the visitor can see the normal track page.

Do not require a separate password for every child track.

The release password is authoritative unless someone explicitly password-protects a track independently.

---

## 15. WordPress password cookies

Rely on normal WordPress password-cookie behavior.

That means unlocking one protected release may also authorize another post that uses the same WordPress password according to WordPress’s native behavior.

Do **not** override this in 0.3.0.

Trying to make passwords release-specific would mean replacing WordPress’s authentication mechanism, which defeats much of the simplicity and reliability of this feature.

---

## 16. Admin preview

Administrators should still be able to test the actual password experience.

Do **not** automatically bypass protection for `manage_options` users.

Otherwise the person configuring the feature cannot easily verify what recipients will see.

If WordPress itself grants some logged-in behavior, follow WordPress; Slim Volume should not add another capability bypass.

---

## 17. Security considerations

The security requirement is not simply “hide the HTML.”

Before authorization, the generated response must contain **no protected audio URLs or listening payloads**.

That includes:

- HTML attributes
- inline JSON
- localized JavaScript
- `data-*` attributes
- REST/AJAX response bodies
- queue state
- Media Session metadata
- preload tags
- audio `src` attributes

QA must inspect page source and browser Network responses, not only what is visually hidden.

---

## 18. Media files

Uploaded audio remains in the normal WordPress uploads directory.

Therefore a listener who already knows:

```text
/wp-content/uploads/.../secret-song.mp3
```

may still be able to request it directly depending on server configuration.

The proposed feature protects **discovery and application-level access**, not the physical media object itself.

Documentation should say this plainly.

A future separate feature could be:

**Secure Media Delivery**

with private storage and expiring URLs.

That should not be bundled into Private Release Preview.

---

## 19. Compatibility with existing releases

No database migration should be necessary.

Existing releases:

```text
post_password = ''
```

continue behaving exactly as they do now.

Password-protected releases use the existing WordPress `post_password` field.

There should be no new option/schema version unless Slim Volume-specific private-preview settings are added later.

---

## 20. Extension/API boundary

Centralize the protection decision and expose only minimal extension points.

Conceptually:

```php
$requires_protection = apply_filters(
    'slim_volume_release_requires_password',
    $requires_protection,
    $release_id
);
```

Potentially add a future archive-card visibility filter.

Do not over-filter the first implementation. The important requirement is a single internal protection API so future secure-streaming or premium extensions have a clean boundary.

---

## 21. Accessibility

The locked state must meet the same standards as the rest of Slim Volume:

- Password label programmatically connected
- Submit via keyboard
- Visible focus
- No information conveyed solely through a lock icon
- Useful error state after incorrect password
- Screen-reader-readable “Private Preview” language
- Unlock must work without animation
- No focus stealing after failed submission

If a lock icon is used, it should be decorative or paired with actual text.

---

## 22. Translation

All new strings must use the `slim-volume` text domain.

Likely strings include:

```text
Private Preview
This release is password protected.
Enter the password to listen.
Password
Unlock Release
This track belongs to a private release.
```

Keep the copy provider-neutral and artist-neutral.

---

## 23. QA matrix

### Unauthenticated

Test:

- protected release
- direct protected track
- archive card
- search
- AJAX navigation into protected release
- queue restore
- page source inspection
- network inspection

### Authenticated

Test:

- correct password unlock
- track playback
- queue
- previous/next
- direct track URLs
- AJAX navigation
- lock-screen playback
- reload/new tab behavior

### Incorrect password

Confirm:

- remains locked
- no protected payload emitted
- useful error behavior

### Regression

Confirm ordinary public releases/tracks behave identically to 0.2.0.

Also test:

- desktop
- iPhone
- Safari
- DuckDuckGo
- no-JS password submission
- `WP_DEBUG_LOG`

---

## 24. Acceptance criteria

Do not call 0.3.0 complete unless all of these are true:

> A protected release cannot expose its track/audio payload before authorization.

> Direct child-track URLs cannot bypass the parent release password.

> AJAX and persistent-player behavior respect the same protection state.

> Correct password unlocks the normal Slim Volume experience without requiring another authentication system.

> Existing unprotected releases are unaffected.

> Deleting/changing a password does not alter release/track data.

> The implementation uses WordPress’s native password mechanism.

> Documentation clearly states that this is private-preview access control, not DRM or secure-media hosting.

---

## 25. Product decision for 0.3.0

The recommended first version is intentionally simple:

**Protected release visible as a locked archive card → artwork/title/artist visible → password required → entire normal release unlocks → child tracks inherit protection → no protected player/audio data before authorization.**

Do not include in 0.3.0:

- private object storage
- expiring signed URLs
- special fan accounts
- email capture
- unique passwords per recipient
- DRM
- proprietary streaming proxies

Those can be evaluated later as separate product features.

---

## 26. Suggested implementation order

1. Add a centralized protection helper/module.
2. Gate single release rendering before private data assembly.
3. Gate child track rendering via parent release inheritance.
4. Gate `PlayerData` release/track payload creation.
5. Gate AJAX navigation responses.
6. Gate queue restoration/hydration.
7. Update archive/search behavior.
8. Style the native WordPress password form for Slim Volume.
9. Add documentation and known-limitations language.
10. Run the full QA matrix above.
11. Release as Slim Volume `0.3.0` if all acceptance criteria pass.
