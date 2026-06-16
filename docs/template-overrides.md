# Slim Volume Template Overrides

Slim Volume includes default frontend templates for the music archive, release pages, track pages, and player shell.

Theme overrides are optional. The plugin works without them.

## Main templates

Default plugin templates live here:

wp-content/plugins/slim-volume/templates/archive-sv_release.php
wp-content/plugins/slim-volume/templates/single-sv_release.php
wp-content/plugins/slim-volume/templates/single-sv_track.php

Themes can override them by copying files to:

wp-content/themes/your-theme/slim-volume/archive-sv_release.php
wp-content/themes/your-theme/slim-volume/single-sv_release.php
wp-content/themes/your-theme/slim-volume/single-sv_track.php

## Player shell override

The persistent player shell lives here:

wp-content/plugins/slim-volume/templates/partials/player-shell.php

Themes can override it by copying it to:

wp-content/themes/your-theme/slim-volume/partials/player-shell.php
Important AJAX navigation rule

## Important AJAX navigation rule

Slim Volume’s persistent player depends on this page structure:

<?php get_header(); ?>

<main id="primary" class="site-main sv-release" data-sv-page-content>
    <!-- Page-specific music content goes here. -->

    <?php
    // Release and track pages should render their player config
    // inside data-sv-page-content.
    ?>
</main>

<?php slim_volume_render_player_shell(); ?>

<?php get_footer(); ?>

The player shell must be outside:

[data-sv-page-content]

Correct:

<main data-sv-page-content>
    Page content
    Page-specific player config JSON
</main>

<?php slim_volume_render_player_shell(); ?>

Incorrect:

<main data-sv-page-content>
    Page content
    <?php slim_volume_render_player_shell(); ?>
</main>

If the player is inside [data-sv-page-content], AJAX navigation will replace the audio element and playback will stop.

## Player config placement

Release and track templates should keep this inside [data-sv-page-content]:

<?php \SlimVolume\Frontend\PlayerData::render_page_config($config); ?>

That allows AJAX navigation to load the new page context while keeping the persistent audio player alive.

## Archive behavior

The music archive does not need a player config by default.

The archive should still include:

<main id="primary" class="site-main sv-archive" data-sv-page-content>
    Archive content
</main>

<?php slim_volume_render_player_shell(); ?>

## Helper function

Use this helper to render the player shell:

<?php slim_volume_render_player_shell(); ?>

Do not hardcode the plugin template path in theme overrides unless absolutely necessary.

## Safe customization areas

Themes can safely customize:

HTML layout
CSS classes
heading structure
artwork placement
release metadata display
track metadata display
button layout
Be careful changing

## Be careful when changing or removing these attributes:

data-sv-page-content
data-sv-player
data-sv-audio
data-sv-player-config
data-sv-play-button
data-sv-track-index
data-sv-track-row
data-sv-track-id
data-sv-release-id
data-sv-track-slug
data-sv-page-queue-button
data-sv-track-queue-button

Slim Volume JavaScript depends on these attributes for persistent playback, AJAX navigation, queue management, and player UI sync.

## Recommended override workflow
Copy the plugin template into your theme’s slim-volume folder.
Make layout changes in the theme copy.
Keep required data-sv-* attributes intact.
Test:
release page playback
track page playback
AJAX navigation
browser Back/Forward
queue drawer
refresh persistence

## Future note

Butterchurn or other advanced visualizer templates should also keep the player outside [data-sv-page-content] so the visualizer and audio context are not destroyed during music-page navigation.