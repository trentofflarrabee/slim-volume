# Slim Volume Theming

Slim Volume exposes CSS variables so themes can customize the archive, release pages, track pages, buttons, and persistent audio player without editing plugin files.

Plugin CSS should be treated as the default theme layer. Site themes and child themes can override the variables.

## Where to add custom CSS

Recommended places:

wp-content/themes/your-theme/style.css

or:

Appearance → Customize → Additional CSS

Do not edit:

wp-content/plugins/slim-volume/assets/css/slim-volume.css

Plugin updates may overwrite changes made directly inside the plugin.

Core player variables
```
:root {
  --sv-player-bg: #111;
  --sv-player-text: #fff;
  --sv-player-muted: rgba(255, 255, 255, 0.68);
  --sv-player-border: rgba(255, 255, 255, 0.14);
  --sv-player-accent: currentColor;
}
```
Example:
```
:root {
  --sv-player-bg: #faf4ea;
  --sv-player-text: #201914;
  --sv-player-muted: rgba(32, 25, 20, 0.68);
  --sv-player-border: rgba(32, 25, 20, 0.18);
  --sv-player-accent: #b14d2a;
}
```
Button variables
```
:root {
  --sv-button-bg: #111;
  --sv-button-text: #fff;
  --sv-button-border: #111;
}
```
Example:
```
:root {
  --sv-button-bg: #b14d2a;
  --sv-button-text: #fff8f0;
  --sv-button-border: #b14d2a;
}
```
Border and radius variables
```
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
```
:root {
  --sv-radius-card: 6px;
  --sv-radius-art: 8px;
  --sv-radius-control: 6px;
  --sv-radius-small: 4px;
  --sv-radius-pill: 6px;
}
```
Timing variables
```
:root {
  --sv-transition-fast: 160ms ease;
}
```
Player height variables
```
:root {
  --sv-player-bar-height: 76px;
  --sv-player-mobile-bar-height: 104px;
}
```
These are intended for layout coordination. They may be used by themes that need to account for the fixed player.

Scoped player-only overrides

To customize only the persistent player, scope variables to .sv-player:
```
.sv-player {
  --sv-player-bg: #050505;
  --sv-player-text: #f7f7f7;
  --sv-player-muted: rgba(247, 247, 247, 0.6);
  --sv-player-border: rgba(247, 247, 247, 0.16);
  --sv-player-accent: #ff4fd8;
}
```
Light site, dark player
```
.sv-player {
  --sv-player-bg: #111;
  --sv-player-text: #fff;
  --sv-player-muted: rgba(255, 255, 255, 0.68);
  --sv-player-border: rgba(255, 255, 255, 0.14);
}
```
Warm vintage style
```
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
Neon style
```
.sv-player {
  --sv-player-bg: #070714;
  --sv-player-text: #f8f7ff;
  --sv-player-muted: rgba(248, 247, 255, 0.62);
  --sv-player-border: rgba(255, 79, 216, 0.28);
  --sv-player-accent: #ff4fd8;
}
```
Important template note

The persistent player must stay outside:
```
[data-sv-page-content]
```
Correct:
```
<main data-sv-page-content>
    Page content
</main>

<?php slim_volume_render_player_shell(); ?>
```
Incorrect:
```
<main data-sv-page-content>
    Page content
    <?php slim_volume_render_player_shell(); ?>
</main>
```
If the player is inside [data-sv-page-content], AJAX navigation will replace the audio element and playback will stop.

Future admin appearance settings

A future Slim Volume settings screen may expose these same variables through a WordPress admin UI.

The CSS variable names should remain the developer-facing theming API.
