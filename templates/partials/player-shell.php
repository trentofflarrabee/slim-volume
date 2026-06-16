<?php

if (! defined('ABSPATH')) {
    exit;
}

?>

<div class="sv-player" data-sv-player data-sv-drawer-state="closed">
    <audio data-sv-audio preload="metadata" crossorigin="anonymous"></audio>

    <section
        id="sv-player-drawer"
        class="sv-player__drawer"
        data-sv-drawer
        aria-label="<?php esc_attr_e('Player queue', 'slim-volume'); ?>"
        hidden
    >
        <div class="sv-player__drawer-inner">
            <div class="sv-player__drawer-header">
                <h2 class="sv-player__drawer-heading">
                    <?php esc_html_e('Now Playing', 'slim-volume'); ?>
                </h2>

                <button
                    type="button"
                    class="sv-player__button sv-player__drawer-close"
                    data-sv-drawer-close
                >
                    <?php esc_html_e('Close', 'slim-volume'); ?>
                </button>
            </div>

            <div class="sv-player__drawer-grid">
                <section class="sv-player__drawer-current" aria-label="<?php esc_attr_e('Current track', 'slim-volume'); ?>">
                    <div class="sv-player__drawer-art" data-sv-drawer-art></div>

                    <div class="sv-player__drawer-meta">
                        <h3 class="sv-player__drawer-title" data-sv-drawer-title>
                            <?php esc_html_e('Nothing playing', 'slim-volume'); ?>
                        </h3>

                        <p class="sv-player__drawer-release" data-sv-drawer-release></p>

                        <nav class="sv-player__drawer-primary-links" aria-label="<?php esc_attr_e('Current track pages', 'slim-volume'); ?>">
                            <a data-sv-drawer-track-link hidden>
                                <?php esc_html_e('Track Page', 'slim-volume'); ?>
                            </a>

                            <a data-sv-drawer-release-link hidden>
                                <?php esc_html_e('Release Page', 'slim-volume'); ?>
                            </a>
                        </nav>

                        <nav
                            class="sv-link-list sv-player__drawer-links"
                            data-sv-drawer-links
                            aria-label="<?php esc_attr_e('Current track links', 'slim-volume'); ?>"
                        ></nav>
                    </div>

                    <div class="sv-player__visualizer" data-sv-visualizer aria-hidden="true">
                        <canvas
                            class="sv-player__visualizer-canvas"
                            data-sv-visualizer-canvas
                            width="640"
                            height="160"
                        ></canvas>
                    </div>

                </section>

            <section class="sv-player__drawer-queue" aria-label="<?php esc_attr_e('Queue', 'slim-volume'); ?>">
                <div class="sv-player__drawer-queue-header">
                    <h3 class="sv-player__drawer-subheading">
                        <?php esc_html_e('Queue', 'slim-volume'); ?>
                    </h3>

                    <button
                        type="button"
                        class="sv-player__button sv-player__clear-queue"
                        data-sv-clear-queue
                        hidden
                    >
                        <?php esc_html_e('Clear Queue', 'slim-volume'); ?>
                    </button>
                </div>

                <ol class="sv-player__queue" data-sv-queue></ol>
            </section>
            </div>
        </div>
    </section>

    <div class="sv-player__bar">
        <div class="sv-player__art" data-sv-player-art></div>

        <div class="sv-player__meta">
            <div class="sv-player__title" data-sv-player-title>
                <?php esc_html_e('Nothing playing', 'slim-volume'); ?>
            </div>

            <div class="sv-player__release" data-sv-player-release></div>
        </div>

        <div class="sv-player__controls">
            <button type="button" class="sv-player__button sv-player__icon-button" data-sv-prev aria-label="<?php esc_attr_e('Previous track', 'slim-volume'); ?>">
                <span aria-hidden="true">⏮</span>
            </button>

            <button type="button" class="sv-player__button sv-player__icon-button sv-player__play-button" data-sv-play-toggle aria-label="<?php esc_attr_e('Play', 'slim-volume'); ?>">
                <span data-sv-play-toggle-icon aria-hidden="true">▶</span>
            </button>

            <button type="button" class="sv-player__button sv-player__icon-button" data-sv-next aria-label="<?php esc_attr_e('Next track', 'slim-volume'); ?>">
                <span aria-hidden="true">⏭</span>
            </button>
        </div>

        <div
            class="sv-player__progress"
            data-sv-seek
            role="slider"
            aria-label="<?php esc_attr_e('Seek', 'slim-volume'); ?>"
            aria-valuemin="0"
            aria-valuemax="100"
            aria-valuenow="0"
            tabindex="0"
        >
            <div class="sv-player__progress-fill" data-sv-progress></div>
        </div>

        <div class="sv-player__time">
            <span data-sv-current-time>0:00</span>
            <span aria-hidden="true"> / </span>
            <span data-sv-duration>0:00</span>
        </div>

        <button
            type="button"
            class="sv-player__button sv-player__drawer-toggle"
            data-sv-drawer-toggle
            aria-controls="sv-player-drawer"
            aria-expanded="false"
        >
            <span data-sv-drawer-toggle-label><?php esc_html_e('Queue', 'slim-volume'); ?></span>
            <span class="sv-player__queue-count" data-sv-queue-count hidden>0</span>
        </button>
    </div>
</div>