<?php

if (! defined('ABSPATH')) {
    exit;
}
?>

<div class="sv-player" data-sv-player>
    <audio class="sv-player__audio" data-sv-audio preload="metadata"></audio>

    <div class="sv-player__bar">
        <div class="sv-player__art" data-sv-player-art aria-hidden="true"></div>

        <div class="sv-player__meta">
            <div class="sv-player__title" data-sv-player-title>
                <?php esc_html_e('Nothing playing', 'slim-volume'); ?>
            </div>
            <div class="sv-player__release" data-sv-player-release></div>
        </div>

        <div class="sv-player__controls">
            <button type="button" class="sv-player__button" data-sv-prev>
                <?php esc_html_e('Previous', 'slim-volume'); ?>
            </button>

            <button type="button" class="sv-player__button" data-sv-play-toggle>
                <?php esc_html_e('Play', 'slim-volume'); ?>
            </button>

            <button type="button" class="sv-player__button" data-sv-next>
                <?php esc_html_e('Next', 'slim-volume'); ?>
            </button>
        </div>

        <div class="sv-player__progress" data-sv-seek role="slider" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
            <div class="sv-player__progress-fill" data-sv-progress></div>
        </div>

        <div class="sv-player__time">
            <span data-sv-current-time>0:00</span>
            <span aria-hidden="true"> / </span>
            <span data-sv-duration>0:00</span>
        </div>
    </div>
</div>