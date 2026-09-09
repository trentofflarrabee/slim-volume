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

                    <div class="sv-player__visualizer" data-sv-visualizer>
                        <div class="sv-player__visualizer-header">
                            <div class="sv-player__visualizer-heading">
                                <span class="sv-player__visualizer-label">
                                    <?php echo esc_html__('Visualizer', 'slim-volume'); ?>
                                </span>

                                <span
                                    class="sv-player__visualizer-preset"
                                    data-sv-visualizer-preset-name
                                >
                                    <?php echo esc_html__('Bars', 'slim-volume'); ?>
                                </span>
                            </div>
                        </div>

                        <canvas
                            class="sv-player__visualizer-canvas"
                            data-sv-visualizer-canvas
                            width="640"
                            height="160"
                        ></canvas>

                        <div class="sv-player__visualizer-actions">
                            <button
                                class="sv-player__button sv-player__visualizer-next-preset"
                                type="button"
                                data-sv-visualizer-next-preset
                                hidden
                            >
                                <?php echo esc_html__('Random Preset', 'slim-volume'); ?>
                            </button>

                            <button
                                class="sv-player__button sv-player__visualizer-fullscreen"
                                type="button"
                                data-sv-visualizer-fullscreen
                                data-sv-fullscreen-label="<?php echo esc_attr__('Fullscreen', 'slim-volume'); ?>"
                                data-sv-exit-fullscreen-label="<?php echo esc_attr__('Exit Fullscreen', 'slim-volume'); ?>"
                                aria-pressed="false"
                            >
                                <?php echo esc_html__('Fullscreen', 'slim-volume'); ?>
                            </button>

                            <button
                                class="sv-player__button sv-player__visualizer-toggle"
                                type="button"
                                data-sv-visualizer-toggle
                                aria-pressed="true"
                            >
                                <?php echo esc_html__('Hide Viz', 'slim-volume'); ?>
                            </button>
                        </div>
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

    <div
            class="sv-player__bar"
            data-sv-desktop-player
        >
        <div class="sv-player__art" data-sv-player-art></div>

        <div class="sv-player__meta">
            <div class="sv-player__title" data-sv-player-title>
                <?php esc_html_e('Nothing playing', 'slim-volume'); ?>
            </div>

            <div class="sv-player__release" data-sv-player-release></div>
        </div>

        <div class="sv-player__controls">
            <button type="button" class="sv-player__button sv-player__icon-button" data-sv-prev aria-label="<?php esc_attr_e('Previous track', 'slim-volume'); ?>">
                <span aria-hidden="true">
                    <svg
                        class="sv-player__icon"
                        viewBox="0 0 24 24"
                        aria-hidden="true"
                        focusable="false"
                        >
                        <path
                            d="M6 5v14M18 6l-8 6 8 6V6z"
                            fill="currentColor"
                        />
                        </svg>
                </span>
            </button>

            <button
                type="button"
                class="sv-player__button sv-player__icon-button sv-player__play-button"
                data-sv-play-toggle
                aria-label="<?php esc_attr_e('Play', 'slim-volume'); ?>"
            >
                <span data-sv-play-toggle-icon aria-hidden="true">
                    <svg
                        class="sv-player__icon sv-player__icon--play"
                        viewBox="0 0 24 24"
                        aria-hidden="true"
                        focusable="false"
                    >
                        <path
                            d="M8 5v14l11-7z"
                            fill="currentColor"
                        />
                    </svg>
                </span>

                <span
                    data-sv-pause-toggle-icon
                    aria-hidden="true"
                    hidden
                >
                    <svg
                        class="sv-player__icon sv-player__icon--pause"
                        viewBox="0 0 24 24"
                        aria-hidden="true"
                        focusable="false"
                    >
                        <path
                            d="M7 5h4v14H7zM13 5h4v14h-4z"
                            fill="currentColor"
                        />
                    </svg>
                </span>
            </button>

            <button type="button" class="sv-player__button sv-player__icon-button" data-sv-next aria-label="<?php esc_attr_e('Next track', 'slim-volume'); ?>">
                <span aria-hidden="true">
                    <svg
                        class="sv-player__icon"
                        viewBox="0 0 24 24"
                        aria-hidden="true"
                        focusable="false"
                        >
                        <path
                            d="M18 5v14M6 6l8 6-8 6V6z"
                            fill="currentColor"
                        />
                        </svg>
                </span>
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

    <div
        class="sv-player-mobile"
        data-sv-mobile-player
        hidden
    >
        <div
            class="sv-player-mobile__mini"
            data-sv-mobile-mini
            role="button"
            tabindex="0"
            aria-label="<?php esc_attr_e('Open now playing', 'slim-volume'); ?>"
        >
            <div
                class="sv-player-mobile__art"
                data-sv-mobile-art
                aria-hidden="true"
            ></div>

            <div class="sv-player-mobile__meta">
                <div
                    class="sv-player-mobile__title"
                    data-sv-mobile-title
                >
                    <?php esc_html_e('Nothing playing', 'slim-volume'); ?>
                </div>

                <div
                    class="sv-player-mobile__release"
                    data-sv-mobile-release
                ></div>
            </div>

            <button
                type="button"
                class="sv-player__button sv-player-mobile__play"
                data-sv-mobile-play-toggle
                aria-label="<?php esc_attr_e('Play', 'slim-volume'); ?>"
            >
                <span
                    data-sv-mobile-play-icon
                    aria-hidden="true"
                >
                    <svg
                        class="sv-player__icon sv-player__icon--play"
                        viewBox="0 0 24 24"
                        aria-hidden="true"
                        focusable="false"
                    >
                        <path
                            d="M8 5v14l11-7z"
                            fill="currentColor"
                        />
                    </svg>
                </span>

                <span
                    data-sv-mobile-pause-icon
                    aria-hidden="true"
                    hidden
                >
                    <svg
                        class="sv-player__icon sv-player__icon--pause"
                        viewBox="0 0 24 24"
                        aria-hidden="true"
                        focusable="false"
                    >
                        <path
                            d="M7 5h4v14H7zM13 5h4v14h-4z"
                            fill="currentColor"
                        />
                    </svg>
                </span>
            </button>
        </div>
        <div
            class="sv-player-mobile__sheet"
            data-sv-mobile-sheet
            role="dialog"
            aria-modal="true"
            aria-label="<?php esc_attr_e('Now playing', 'slim-volume'); ?>"
            tabindex="-1"
            hidden
        >
            <div class="sv-player-mobile__sheet-header">
                <button
                    type="button"
                    class="sv-player__button sv-player-mobile__minimize"
                    data-sv-mobile-minimize
                    aria-label="<?php esc_attr_e('Minimize player', 'slim-volume'); ?>"
                >
                    <span aria-hidden="true">
                        <svg
                        class="sv-player__icon"
                        viewBox="0 0 24 24"
                        aria-hidden="true"
                        focusable="false"
                        >
                        <path
                            d="M6 9l6 6 6-6"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        />
                        </svg>
                    </span>
                </button>
            </div>

            <div class="sv-player-mobile__sheet-content">
                <div
                    class="sv-player-mobile__sheet-art"
                    data-sv-mobile-sheet-art
                    aria-hidden="true"
                ></div>

                <div
                    class="sv-player-mobile__sheet-title"
                    data-sv-mobile-sheet-title
                >
                    <?php esc_html_e('Nothing playing', 'slim-volume'); ?>
                </div>

                <div
                    class="sv-player-mobile__sheet-release"
                    data-sv-mobile-sheet-release
                ></div>
                <div class="sv-player-mobile__progress">
                    <button
                        type="button"
                        class="sv-player-mobile__seek"
                        data-sv-mobile-seek
                        aria-label="<?php esc_attr_e('Seek', 'slim-volume'); ?>"
                    >
                        <span
                            class="sv-player-mobile__seek-fill"
                            data-sv-mobile-seek-fill
                        ></span>
                    </button>

                    <div class="sv-player-mobile__time">
                        <span data-sv-mobile-current-time>0:00</span>
                        <span data-sv-mobile-duration>0:00</span>
                    </div>
                </div>

                <div class="sv-player-mobile__transport">
                    <button
                        type="button"
                        class="sv-player__button"
                        data-sv-mobile-prev
                        aria-label="<?php esc_attr_e('Previous track', 'slim-volume'); ?>"
                    >
                        <span aria-hidden="true">
                            <svg
                            class="sv-player__icon"
                            viewBox="0 0 24 24"
                            aria-hidden="true"
                            focusable="false"
                            >
                            <path
                                d="M6 5v14M18 6l-8 6 8 6V6z"
                                fill="currentColor"
                            />
                            </svg>
                        </span>
                    </button>

                    <button
                        type="button"
                        class="sv-player__button sv-player-mobile__sheet-play"
                        data-sv-mobile-sheet-play
                        aria-label="<?php esc_attr_e('Play', 'slim-volume'); ?>"
                    >
                        <span
                            data-sv-mobile-sheet-play-icon
                            aria-hidden="true"
                        >
                            <svg
                                class="sv-player__icon sv-player__icon--play"
                                viewBox="0 0 24 24"
                                aria-hidden="true"
                                focusable="false"
                            >
                                <path
                                    d="M8 5v14l11-7z"
                                    fill="currentColor"
                                />
                            </svg>
                        </span>

                        <span
                            data-sv-mobile-sheet-pause-icon
                            aria-hidden="true"
                            hidden
                        >
                            <svg
                                class="sv-player__icon sv-player__icon--pause"
                                viewBox="0 0 24 24"
                                aria-hidden="true"
                                focusable="false"
                            >
                                <path
                                    d="M7 5h4v14H7zM13 5h4v14h-4z"
                                    fill="currentColor"
                                />
                            </svg>
                        </span>
                    </button>

                    <button
                        type="button"
                        class="sv-player__button"
                        data-sv-mobile-next
                        aria-label="<?php esc_attr_e('Next track', 'slim-volume'); ?>"
                    >
                        <span aria-hidden="true">
                            <svg
                                class="sv-player__icon"
                                viewBox="0 0 24 24"
                                aria-hidden="true"
                                focusable="false"
                                >
                                <path
                                    d="M18 5v14M6 6l8 6-8 6V6z"
                                    fill="currentColor"
                                />
                                </svg>
                        </span>
                    </button>
                </div>
                <div class="sv-player-mobile__queue-section">
                    <div class="sv-player-mobile__queue-header">
                        <div class="sv-player-mobile__queue-heading">
                            <?php esc_html_e('Up next', 'slim-volume'); ?>

                            <span
                                class="sv-player-mobile__queue-count"
                                data-sv-mobile-queue-count
                            ></span>
                        </div>

                        <button
                            type="button"
                            class="sv-player-mobile__queue-clear"
                            data-sv-mobile-clear-queue
                        >
                            <?php esc_html_e('Clear', 'slim-volume'); ?>
                        </button>
                    </div>

                    <div
                        class="sv-player-mobile__queue"
                        data-sv-mobile-queue
                    ></div>
                </div>
            </div>
        </div>
    </div>

</div>