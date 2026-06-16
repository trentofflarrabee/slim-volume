# Slim Volume Theming

Slim Volume exposes CSS variables so themes can customize the archive, release pages, track pages, buttons, and persistent audio player without editing plugin files.

Plugin CSS should be treated as the default theme layer. Site themes, child themes, and the WordPress admin settings screen can override the variables.

## Where to add custom CSS

Recommended places:

```text
wp-content/themes/your-theme/style.css
```

or:

```text
Appearance → Customize → Additional CSS
```

Do not edit:

```text
wp-content/plugins/slim-volume/assets/css/slim-volume.css
```

Plugin updates may overwrite changes made directly inside the plugin.

## Admin appearance settings

Slim Volume includes a settings screen at:

```text
Releases → Settings → Player Appearance
```

These settings output CSS custom properties on `:root` after the main Slim Volume stylesheet is loaded.

That means the admin settings act as convenient site-owner defaults, while theme CSS can still override the same variables if needed.

## Appearance presets

The settings screen includes appearance presets:

```text
Custom
Dark
Light
Warm Vintage
Neon
```

Choosing a preset and saving will fill the player appearance fields with that preset’s values.

Use **Custom** when manually editing individual colors, borders, or radius values.

## Reset appearance defaults

The settings screen includes a reset button for appearance values.

Resetting appearance defaults:

```text
keeps frontend feature toggles unchanged
returns the appearance preset to Custom
returns player colors to plugin defaults
returns button colors to plugin defaults
returns radius values to plugin defaults
updates the preview after reset
```

This is useful when experimenting with presets or custom CSS values.

## Appearance preview

The settings page includes a small player preview.

The preview updates after saving settings. It does not update live while typing.

The preview uses the same CSS variables as the frontend player, so it is intended to show the general look of the saved player style before checking the frontend.

## Core player variables

```css
:root {
  --sv-player-bg: #111;
  --sv-player-text: #fff;
  --sv-player-muted: rgba(255, 255, 255, 0.68);
  --sv-player-border: rgba(255, 255, 255, 0.14);
  --sv-player-accent: currentColor;
}
```

Example:

```css
:root {
  --sv-player-bg: #faf4ea;
  --sv-player-text: #201914;
  --sv-player-muted: rgba(32, 25, 20, 0.68);
  --sv-player-border: rgba(32, 25, 20, 0.18);
  --sv-player-accent: #b14d2a;
}
```

## Button variables

```css
:root {
  --sv-button-bg: #111;
  --sv-button-text: #fff;
  --sv-button-border: #111;
}
```

Example:

```css
:root {
  --sv-button-bg: #b14d2a;
  --sv-button-text: #fff8f0;
  --sv-button-border: #b14d2a;
}
```

## Border and radius variables

```css
:root {
  --sv-card-border: rgba(0, 0, 0, 0.12);

  --sv-radius-card: 16px;
  --sv-radius-art: 18px;
  --sv-radius-control: 14px;
  --sv-radius-small: 8px;
  --sv-radius-pill: 999px;
}
```

Example with sharper corners:

```css
:root {
  --sv-radius-card: 6px;
  --sv-radius-art: 8px;
  --sv-radius-control: 6px;
  --sv-radius-small: 4px;
  --sv-radius-pill: 6px;
}
```

## Timing variables

```css
:root {
  --sv-transition-fast: 160ms ease;
}
```

## Player height variables

```css
:root {
  --sv-player-bar-height: 76px;
  --sv-player-mobile-bar-height: 104px;
}
```

These are intended for layout coordination. They may be used by themes that need to account for the fixed player.

## Scoped player-only overrides

To customize only the persistent player, scope variables to `.sv-player`:

```css
.sv-player {
  --sv-player-bg: #050505;
  --sv-player-text: #f7f7f7;
  --sv-player-muted: rgba(247, 247, 247, 0.6);
  --sv-player-border: rgba(247, 247, 247, 0.16);
  --sv-player-accent: #ff4fd8;
}
```

## Light site, dark player

```css
.sv-player {
  --sv-player-bg: #111;
  --sv-player-text: #fff;
  --sv-player-muted: rgba(255, 255, 255, 0.68);
  --sv-player-border: rgba(255, 255, 255, 0.14);
}
```

## Warm vintage style

```css
:root {
  --sv-player-bg: #2b1d14;
  --sv-player-text: #fff3df;
  --sv-player-muted: rgba(255, 243, 223, 0.68);
  --sv-player-border: rgba(255, 243, 223, 0.18);
  --sv-player-accent: #d58a45;

  --sv-button-bg: #d58a45;
  --sv-button-text: #21140d;
  --sv-button-border: #d58a45;

  --sv-radius-card: 20px;
  --sv-radius-art: 22px;
}
```

## Neon style

```css
.sv-player {
  --sv-player-bg: #070714;
  --sv-player-text: #f8f7ff;
  --sv-player-muted: rgba(248, 247, 255, 0.62);
  --sv-player-border: rgba(255, 79, 216, 0.28);
  --sv-player-accent: #ff4fd8;
}
```

## Important template note

The persistent player must stay outside:

```html
[data-sv-page-content]
```

Correct:

```php
<main data-sv-page-content>
    Page content
</main>

<?php slim_volume_render_player_shell(); ?>
```

Incorrect:

```php
<main data-sv-page-content>
    Page content
    <?php slim_volume_render_player_shell(); ?>
</main>
```

If the player is inside `[data-sv-page-content]`, AJAX navigation will replace the audio element and playback will stop.

## Developer API

The WordPress settings UI and theme CSS both use the same CSS variable names.

The CSS variable names should remain the developer-facing theming API.

Themes can override admin-defined values by outputting more specific CSS later in the cascade.
