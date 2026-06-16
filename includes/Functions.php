<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

if (! function_exists('slim_volume_render_player_shell')) {
    /**
     * Render the persistent Slim Volume player shell.
     *
     * Themes can override this later by adding:
     *
     * /your-theme/slim-volume/partials/player-shell.php
     */
    function slim_volume_render_player_shell(): void
    {
        $theme_template = locate_template(
            [
                'slim-volume/partials/player-shell.php',
            ]
        );

        if ($theme_template) {
            require $theme_template;
            return;
        }

        $plugin_template = SLIM_VOLUME_PATH . 'templates/partials/player-shell.php';

        if (file_exists($plugin_template)) {
            require $plugin_template;
        }
    }
}