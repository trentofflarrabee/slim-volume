<?php

declare(strict_types=1);

namespace SlimVolume\Admin;

use SlimVolume\PostTypes;
use SlimVolume\TimedLyrics;
use WP_Post;
use WP_Query;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Track-editor status panel and dedicated Timed Lyrics admin workspace.
 *
 * This class intentionally owns only the admin workflow shell. Timestamp
 * authoring and persistence are added in the following implementation phase.
 */
final class TimedLyricsAdmin
{
    public const MENU_SLUG   = 'slim-volume-lyrics-sync';
    public const AJAX_ACTION = 'slim_volume_save_timed_lyrics';
    public const NONCE_ACTION = 'slim_volume_timed_lyrics';

    public static function register_page(): void
    {
        add_submenu_page(
            'edit.php?post_type=' . PostTypes::RELEASE,
            __('Lyrics Sync', 'slim-volume'),
            __('Lyrics Sync', 'slim-volume'),
            'edit_posts',
            self::MENU_SLUG,
            [self::class, 'render_page']
        );
    }

    public static function register_meta_box(): void
    {
        add_meta_box(
            'sv_track_timed_lyrics',
            __('Slim Volume: Timed Lyrics', 'slim-volume'),
            [self::class, 'render_meta_box'],
            PostTypes::TRACK,
            'side',
            'high'
        );
    }


    /**
     * Save a draft or complete timing document from the synchronization studio.
     */
    public static function ajax_save(): void
    {
        $track_id = isset($_POST['track_id'])
            ? absint($_POST['track_id'])
            : 0;

        check_ajax_referer(self::NONCE_ACTION . ':' . $track_id, 'nonce');

        $track = get_post($track_id);

        if (! $track instanceof WP_Post || $track->post_type !== PostTypes::TRACK) {
            wp_send_json_error(
                [
                    'message' => __('The requested Slim Volume track could not be found.', 'slim-volume'),
                    'errors'  => [],
                ],
                404
            );
        }

        if (! current_user_can('edit_post', $track_id)) {
            wp_send_json_error(
                [
                    'message' => __('You are not allowed to edit this track.', 'slim-volume'),
                    'errors'  => [],
                ],
                403
            );
        }

        $raw_document = $_POST['document'] ?? '';
        $document     = [];

        if (is_string($raw_document)) {
            $decoded = json_decode(wp_unslash($raw_document), true);
            $document = is_array($decoded) ? $decoded : [];
        } elseif (is_array($raw_document)) {
            $document = wp_unslash($raw_document);
        }

        if (! $document) {
            wp_send_json_error(
                [
                    'message' => __('The timed-lyrics document was missing or invalid.', 'slim-volume'),
                    'errors'  => [],
                ],
                400
            );
        }

        $result = TimedLyrics::save_document($track_id, $document);

        if (empty($result['saved'])) {
            $errors = is_array($result['errors'] ?? null)
                ? array_values($result['errors'])
                : [];

            wp_send_json_error(
                [
                    'message' => $errors
                        ? (string) ($errors[0]['message'] ?? __('Timed lyrics could not be saved.', 'slim-volume'))
                        : __('Timed lyrics could not be saved.', 'slim-volume'),
                    'errors'  => $errors,
                    'status'  => (string) ($result['status'] ?? TimedLyrics::STATUS_STALE),
                ],
                422
            );
        }

        $saved_document = is_array($result['document'] ?? null)
            ? $result['document']
            : [];
        $timed_count = count(
            array_filter(
                is_array($saved_document['lines'] ?? null) ? $saved_document['lines'] : [],
                static fn($line): bool =>
                    is_array($line)
                    && ($line['type'] ?? 'line') === 'line'
                    && isset($line['start'])
                    && is_numeric($line['start'])
            )
        );
        $status = (string) ($result['status'] ?? TimedLyrics::STATUS_DRAFT);

        wp_send_json_success(
            [
                'message'      => $status === TimedLyrics::STATUS_COMPLETE
                    ? __('Timed lyrics are complete and eligible for public display.', 'slim-volume')
                    : __('Timed lyrics draft saved.', 'slim-volume'),
                'status'       => $status,
                'statusLabel'  => self::status_label($status),
                'statusClass'  => self::status_class($status),
                'timedCount'   => $timed_count,
                'document'     => $saved_document,
            ]
        );
    }

    public static function render_meta_box(WP_Post $post): void
    {
        $summary       = self::track_summary($post->ID);
        $workspace_url = self::workspace_url($post->ID);
        ?>
        <div class="sv-timed-lyrics-card">
            <div class="sv-timed-lyrics-card__heading">
                <span
                    class="sv-timed-lyrics-status sv-timed-lyrics-status--<?php echo esc_attr($summary['status_class']); ?>"
                >
                    <?php echo esc_html($summary['status_label']); ?>
                </span>

                <span class="sv-timed-lyrics-card__count">
                    <?php
                    echo esc_html(
                        sprintf(
                            /* translators: 1: timed line count, 2: total lyric line count */
                            __('%1$d of %2$d lines timed', 'slim-volume'),
                            $summary['timed_count'],
                            $summary['line_count']
                        )
                    );
                    ?>
                </span>
            </div>

            <dl class="sv-timed-lyrics-readiness">
                <div>
                    <dt><?php esc_html_e('Lyrics', 'slim-volume'); ?></dt>
                    <dd>
                        <?php if ($summary['has_lyrics']) : ?>
                            <span class="sv-timed-lyrics-ready"><?php esc_html_e('Ready', 'slim-volume'); ?></span>
                        <?php else : ?>
                            <span class="sv-timed-lyrics-missing"><?php esc_html_e('Missing', 'slim-volume'); ?></span>
                        <?php endif; ?>
                    </dd>
                </div>

                <div>
                    <dt><?php esc_html_e('Audio', 'slim-volume'); ?></dt>
                    <dd>
                        <?php if ($summary['has_audio']) : ?>
                            <span class="sv-timed-lyrics-ready"><?php esc_html_e('Ready', 'slim-volume'); ?></span>
                        <?php else : ?>
                            <span class="sv-timed-lyrics-missing"><?php esc_html_e('Missing', 'slim-volume'); ?></span>
                        <?php endif; ?>
                    </dd>
                </div>
            </dl>

            <?php if ($post->post_status === 'auto-draft') : ?>
                <p class="description">
                    <?php esc_html_e('Save this track before opening the synchronization workspace.', 'slim-volume'); ?>
                </p>
                <span class="button button-secondary disabled" aria-disabled="true">
                    <?php esc_html_e('Open Lyrics Sync', 'slim-volume'); ?>
                </span>
            <?php elseif (! $summary['has_lyrics'] || ! $summary['has_audio']) : ?>
                <p class="description">
                    <?php esc_html_e('Add plain lyrics and a playable audio source, then save the track.', 'slim-volume'); ?>
                </p>
                <span class="button button-secondary disabled" aria-disabled="true">
                    <?php esc_html_e('Open Lyrics Sync', 'slim-volume'); ?>
                </span>
            <?php else : ?>
                <p class="description">
                    <?php echo esc_html(self::status_description($summary['status'])); ?>
                </p>
                <a class="button button-primary" href="<?php echo esc_url($workspace_url); ?>">
                    <?php
                    echo esc_html(
                        $summary['status'] === TimedLyrics::STATUS_NONE
                            ? __('Start Lyrics Sync', 'slim-volume')
                            : __('Open Lyrics Sync', 'slim-volume')
                    );
                    ?>
                </a>
            <?php endif; ?>
        </div>
        <?php
    }

    public static function render_page(): void
    {
        if (! current_user_can('edit_posts')) {
            wp_die(esc_html__('You are not allowed to access the Lyrics Sync workspace.', 'slim-volume'));
        }

        $track_id = isset($_GET['track_id'])
            ? absint($_GET['track_id'])
            : 0;

        if ($track_id <= 0) {
            self::render_track_picker();
            return;
        }

        $track = get_post($track_id);

        if (! $track instanceof WP_Post || $track->post_type !== PostTypes::TRACK) {
            self::render_invalid_track_notice();
            return;
        }

        if (! current_user_can('edit_post', $track_id)) {
            wp_die(esc_html__('You are not allowed to edit this track.', 'slim-volume'));
        }

        self::render_workspace($track);
    }

    private static function render_workspace(WP_Post $track): void
    {
        $summary      = self::track_summary($track->ID);
        $document     = TimedLyrics::get_authoring_document($track->ID);
        $stored_lines = isset($document['lines']) && is_array($document['lines'])
            ? array_values($document['lines'])
            : [];
        $lines        = $stored_lines ?: TimedLyrics::generate_lines(
            (string) get_post_meta($track->ID, '_sv_lyrics', true)
        );
        $first_line   = self::first_syncable_line($lines);
        $edit_url     = get_edit_post_link($track->ID, 'raw');
        $view_url     = get_permalink($track->ID);
        $release      = self::release_title($track->ID);
        ?>
        <div
            class="wrap sv-timed-lyrics-admin"
            data-sv-timed-lyrics-workspace
            data-track-id="<?php echo esc_attr((string) $track->ID); ?>"
        >
            <div class="sv-timed-lyrics-admin__header">
                <div>
                    <p class="sv-timed-lyrics-admin__eyebrow">
                        <?php esc_html_e('Slim Volume · Lyrics Sync', 'slim-volume'); ?>
                    </p>
                    <h1><?php echo esc_html(get_the_title($track)); ?></h1>

                    <?php if ($release !== '') : ?>
                        <p class="sv-timed-lyrics-admin__release">
                            <?php
                            echo esc_html(
                                sprintf(
                                    /* translators: %s: release title */
                                    __('From %s', 'slim-volume'),
                                    $release
                                )
                            );
                            ?>
                        </p>
                    <?php endif; ?>
                </div>

                <div class="sv-timed-lyrics-admin__header-actions">
                    <?php if (is_string($edit_url) && $edit_url !== '') : ?>
                        <a class="button" href="<?php echo esc_url($edit_url); ?>">
                            <?php esc_html_e('Edit Track', 'slim-volume'); ?>
                        </a>
                    <?php endif; ?>

                    <?php if (is_string($view_url) && $view_url !== '' && $track->post_status === 'publish') : ?>
                        <a class="button" href="<?php echo esc_url($view_url); ?>" target="_blank" rel="noopener noreferrer">
                            <?php esc_html_e('View Track', 'slim-volume'); ?>
                        </a>
                    <?php endif; ?>

                    <a
                        class="button"
                        href="<?php echo esc_url(admin_url('edit.php?post_type=' . PostTypes::TRACK)); ?>"
                    >
                        <?php esc_html_e('All Tracks', 'slim-volume'); ?>
                    </a>
                </div>
            </div>

            <div class="sv-timed-lyrics-summary-grid">
                <?php self::render_summary_card(
                    __('Sync status', 'slim-volume'),
                    $summary['status_label'],
                    $summary['status_class'],
                    'status'
                ); ?>

                <?php self::render_summary_card(
                    __('Plain lyrics', 'slim-volume'),
                    $summary['has_lyrics']
                        ? sprintf(
                            /* translators: %d: number of lyric lines */
                            _n('%d line', '%d lines', $summary['line_count'], 'slim-volume'),
                            $summary['line_count']
                        )
                        : __('Missing', 'slim-volume'),
                    $summary['has_lyrics'] ? 'ready' : 'missing'
                ); ?>

                <?php self::render_summary_card(
                    __('Audio source', 'slim-volume'),
                    $summary['has_audio'] ? __('Ready', 'slim-volume') : __('Missing', 'slim-volume'),
                    $summary['has_audio'] ? 'ready' : 'missing'
                ); ?>

                <?php self::render_summary_card(
                    __('Timed lines', 'slim-volume'),
                    sprintf(
                        /* translators: 1: timed line count, 2: total line count */
                        __('%1$d / %2$d', 'slim-volume'),
                        $summary['timed_count'],
                        $summary['line_count']
                    ),
                    $summary['timed_count'] > 0 ? 'draft' : 'none',
                    'timed-count'
                ); ?>
            </div>

            <?php if (! $summary['has_lyrics'] || ! $summary['has_audio']) : ?>
                <div class="notice notice-warning inline sv-timed-lyrics-admin__notice">
                    <p>
                        <?php
                        if (! $summary['has_lyrics'] && ! $summary['has_audio']) {
                            esc_html_e(
                                'This track needs plain lyrics and a playable audio source before it can be synchronized.',
                                'slim-volume'
                            );
                        } elseif (! $summary['has_lyrics']) {
                            esc_html_e(
                                'Add plain lyrics on the Track editor before synchronizing this track.',
                                'slim-volume'
                            );
                        } else {
                            esc_html_e(
                                'Add an audio attachment or audio URL on the Track editor before synchronizing this track.',
                                'slim-volume'
                            );
                        }
                        ?>
                    </p>
                </div>
            <?php endif; ?>

            <div class="sv-timed-lyrics-workspace-grid">
                <main class="sv-timed-lyrics-studio">
                    <section class="sv-timed-lyrics-panel sv-timed-lyrics-panel--current">
                        <div class="sv-timed-lyrics-current-line__meta">
                            <p class="sv-timed-lyrics-panel__eyebrow">
                                <?php esc_html_e('Current line', 'slim-volume'); ?>
                            </p>
                            <span data-sv-current-time>00:00.000</span>
                        </div>

                        <p class="sv-timed-lyrics-current-line" data-sv-current-lyric>
                            <?php
                            echo esc_html(
                                $first_line !== ''
                                    ? $first_line
                                    : __('No lyric line is available yet.', 'slim-volume')
                            );
                            ?>
                        </p>

                        <p class="sv-timed-lyrics-next-line">
                            <span><?php esc_html_e('Next:', 'slim-volume'); ?></span>
                            <strong data-sv-next-lyric><?php esc_html_e('—', 'slim-volume'); ?></strong>
                        </p>

                        <div class="sv-timed-lyrics-mode" data-sv-sync-mode aria-live="polite">
                            <?php esc_html_e('Ready. Start Sync when you are prepared to tap each line.', 'slim-volume'); ?>
                        </div>
                    </section>

                    <section class="sv-timed-lyrics-panel">
                        <div class="sv-timed-lyrics-panel__header">
                            <div>
                                <p class="sv-timed-lyrics-panel__eyebrow">
                                    <?php esc_html_e('Audio', 'slim-volume'); ?>
                                </p>
                                <h2><?php esc_html_e('Synchronization source', 'slim-volume'); ?></h2>
                            </div>
                        </div>

                        <?php if ($summary['has_audio']) : ?>
                            <audio
                                class="sv-timed-lyrics-audio"
                                data-sv-timed-lyrics-audio
                                controls
                                preload="metadata"
                                src="<?php echo esc_url($summary['audio_url']); ?>"
                            ></audio>
                        <?php else : ?>
                            <p class="sv-timed-lyrics-empty">
                                <?php esc_html_e('No playable audio source is assigned to this track.', 'slim-volume'); ?>
                            </p>
                        <?php endif; ?>

                        <div class="sv-timed-lyrics-transport" data-sv-sync-controls>
                            <button class="button button-primary" type="button" data-sv-start-sync <?php disabled(! $summary['has_audio'] || ! $summary['has_lyrics']); ?>>
                                <?php esc_html_e('Start Sync', 'slim-volume'); ?>
                            </button>
                            <button class="button" type="button" data-sv-toggle-play <?php disabled(! $summary['has_audio'] || ! $summary['has_lyrics']); ?>>
                                <?php esc_html_e('Play / Pause', 'slim-volume'); ?>
                            </button>
                            <button class="button" type="button" data-sv-review <?php disabled(! $summary['has_audio'] || ! $summary['has_lyrics']); ?>>
                                <?php esc_html_e('Review', 'slim-volume'); ?>
                            </button>
                            <button class="button" type="button" data-sv-undo <?php disabled(! $summary['has_audio'] || ! $summary['has_lyrics']); ?>>
                                <?php esc_html_e('Undo', 'slim-volume'); ?>
                            </button>
                        </div>
                    </section>

                    <section class="sv-timed-lyrics-panel">
                        <div class="sv-timed-lyrics-panel__header">
                            <div>
                                <p class="sv-timed-lyrics-panel__eyebrow">
                                    <?php esc_html_e('Selected line', 'slim-volume'); ?>
                                </p>
                                <h2><?php esc_html_e('Timing adjustments', 'slim-volume'); ?></h2>
                            </div>
                            <strong data-sv-selected-time>—</strong>
                        </div>

                        <div class="sv-timed-lyrics-adjustments">
                            <button class="button" type="button" data-sv-nudge="-0.5" <?php disabled(! $summary['has_audio'] || ! $summary['has_lyrics']); ?>>−0.50s</button>
                            <button class="button" type="button" data-sv-nudge="-0.1" <?php disabled(! $summary['has_audio'] || ! $summary['has_lyrics']); ?>>−0.10s</button>
                            <button class="button" type="button" data-sv-clear-line <?php disabled(! $summary['has_audio'] || ! $summary['has_lyrics']); ?>>
                                <?php esc_html_e('Clear', 'slim-volume'); ?>
                            </button>
                            <button class="button" type="button" data-sv-nudge="0.1" <?php disabled(! $summary['has_audio'] || ! $summary['has_lyrics']); ?>>+0.10s</button>
                            <button class="button" type="button" data-sv-nudge="0.5" <?php disabled(! $summary['has_audio'] || ! $summary['has_lyrics']); ?>>+0.50s</button>
                        </div>

                        <details class="sv-timed-lyrics-shortcuts">
                            <summary><?php esc_html_e('Keyboard shortcuts', 'slim-volume'); ?></summary>
                            <dl>
                                <div><dt><?php esc_html_e('Space', 'slim-volume'); ?></dt><dd><?php esc_html_e('Mark current line and advance', 'slim-volume'); ?></dd></div>
                                <div><dt><?php esc_html_e('Enter', 'slim-volume'); ?></dt><dd><?php esc_html_e('Play or pause audio', 'slim-volume'); ?></dd></div>
                                <div><dt><?php esc_html_e('Backspace', 'slim-volume'); ?></dt><dd><?php esc_html_e('Undo latest timestamp', 'slim-volume'); ?></dd></div>
                                <div><dt><?php esc_html_e('← / →', 'slim-volume'); ?></dt><dd><?php esc_html_e('Nudge selected line by 0.10 seconds', 'slim-volume'); ?></dd></div>
                                <div><dt><?php esc_html_e('Shift + ← / →', 'slim-volume'); ?></dt><dd><?php esc_html_e('Nudge selected line by 0.50 seconds', 'slim-volume'); ?></dd></div>
                            </dl>
                        </details>
                    </section>

                    <section class="sv-timed-lyrics-panel sv-timed-lyrics-save-panel">
                        <div>
                            <p class="sv-timed-lyrics-panel__eyebrow">
                                <?php esc_html_e('Save timing pass', 'slim-volume'); ?>
                            </p>
                            <p class="sv-timed-lyrics-save-status" data-sv-save-status aria-live="polite">
                                <?php esc_html_e('No unsaved changes.', 'slim-volume'); ?>
                            </p>
                        </div>

                        <div class="sv-timed-lyrics-save-actions">
                            <button class="button" type="button" data-sv-reset-timings <?php disabled(! $summary['has_audio'] || ! $summary['has_lyrics']); ?>>
                                <?php esc_html_e('Reset Timings', 'slim-volume'); ?>
                            </button>
                            <button class="button button-secondary" type="button" data-sv-save-draft <?php disabled(! $summary['has_audio'] || ! $summary['has_lyrics']); ?>>
                                <?php esc_html_e('Save Draft', 'slim-volume'); ?>
                            </button>
                            <button class="button button-primary" type="button" data-sv-save-complete <?php disabled(! $summary['has_audio'] || ! $summary['has_lyrics']); ?>>
                                <?php esc_html_e('Mark Complete', 'slim-volume'); ?>
                            </button>
                        </div>
                    </section>
                </main>

                <aside class="sv-timed-lyrics-lines-panel">
                    <div class="sv-timed-lyrics-lines-panel__header">
                        <div>
                            <p class="sv-timed-lyrics-panel__eyebrow">
                                <?php esc_html_e('Canonical lyrics', 'slim-volume'); ?>
                            </p>
                            <h2><?php esc_html_e('Line map', 'slim-volume'); ?></h2>
                        </div>
                        <span>
                            <?php
                            echo esc_html(
                                sprintf(
                                    /* translators: %d: number of lyric lines */
                                    _n('%d line', '%d lines', $summary['line_count'], 'slim-volume'),
                                    $summary['line_count']
                                )
                            );
                            ?>
                        </span>
                    </div>

                    <?php if ($lines) : ?>
                        <ol class="sv-timed-lyrics-line-list">
                            <?php foreach ($lines as $index => $line) : ?>
                                <?php
                                if (! is_array($line)) {
                                    continue;
                                }

                                $type  = sanitize_key((string) ($line['type'] ?? 'line'));
                                $text  = (string) ($line['text'] ?? '');
                                $start = isset($line['start']) && is_numeric($line['start'])
                                    ? (float) $line['start']
                                    : null;
                                ?>
                                <li
                                    class="sv-timed-lyrics-line-list__item sv-timed-lyrics-line-list__item--<?php echo esc_attr($type ?: 'line'); ?>"
                                    data-sv-lyric-row
                                    data-line-index="<?php echo esc_attr((string) $index); ?>"
                                    data-line-id="<?php echo esc_attr((string) ($line['id'] ?? '')); ?>"
                                    data-line-type="<?php echo esc_attr($type ?: 'line'); ?>"
                                    <?php if ($type === 'line') : ?>
                                        role="button"
                                        tabindex="0"
                                        aria-label="<?php echo esc_attr(sprintf(__('Select lyric line %d', 'slim-volume'), $index + 1)); ?>"
                                    <?php endif; ?>
                                >
                                    <span class="sv-timed-lyrics-line-list__number">
                                        <?php echo esc_html(str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT)); ?>
                                    </span>

                                    <span class="sv-timed-lyrics-line-list__time" data-sv-line-time>
                                        <?php echo esc_html(self::format_timestamp($start)); ?>
                                    </span>

                                    <span class="sv-timed-lyrics-line-list__text">
                                        <?php
                                        echo esc_html(
                                            $type === 'spacer'
                                                ? __('Verse break', 'slim-volume')
                                                : $text
                                        );
                                        ?>
                                    </span>
                                </li>
                            <?php endforeach; ?>
                        </ol>
                    <?php else : ?>
                        <p class="sv-timed-lyrics-empty">
                            <?php esc_html_e('No lyric lines are available.', 'slim-volume'); ?>
                        </p>
                    <?php endif; ?>
                </aside>
            </div>
        </div>
        <?php
    }

    private static function render_track_picker(): void
    {
        $search = isset($_GET['s'])
            ? sanitize_text_field(wp_unslash($_GET['s']))
            : '';

        $query = new WP_Query(
            [
                'post_type'      => PostTypes::TRACK,
                'post_status'    => ['publish', 'draft', 'pending', 'private'],
                'posts_per_page' => 100,
                'orderby'        => 'modified',
                'order'          => 'DESC',
                's'              => $search,
                'no_found_rows'  => true,
            ]
        );
        ?>
        <div class="wrap sv-timed-lyrics-admin">
            <div class="sv-timed-lyrics-admin__header">
                <div>
                    <p class="sv-timed-lyrics-admin__eyebrow">
                        <?php esc_html_e('Slim Volume', 'slim-volume'); ?>
                    </p>
                    <h1><?php esc_html_e('Lyrics Sync', 'slim-volume'); ?></h1>
                    <p class="sv-timed-lyrics-admin__release">
                        <?php esc_html_e('Choose a track to open its synchronization workspace.', 'slim-volume'); ?>
                    </p>
                </div>
            </div>

            <form class="sv-timed-lyrics-track-search" method="get">
                <input type="hidden" name="post_type" value="<?php echo esc_attr(PostTypes::RELEASE); ?>">
                <input type="hidden" name="page" value="<?php echo esc_attr(self::MENU_SLUG); ?>">

                <label class="screen-reader-text" for="sv-timed-lyrics-track-search">
                    <?php esc_html_e('Search tracks', 'slim-volume'); ?>
                </label>
                <input
                    type="search"
                    id="sv-timed-lyrics-track-search"
                    name="s"
                    value="<?php echo esc_attr($search); ?>"
                    placeholder="<?php esc_attr_e('Search tracks…', 'slim-volume'); ?>"
                >
                <button class="button button-secondary" type="submit">
                    <?php esc_html_e('Search', 'slim-volume'); ?>
                </button>

                <?php if ($search !== '') : ?>
                    <a class="button-link" href="<?php echo esc_url(self::workspace_url(0)); ?>">
                        <?php esc_html_e('Clear', 'slim-volume'); ?>
                    </a>
                <?php endif; ?>
            </form>

            <?php if ($query->have_posts()) : ?>
                <div class="sv-timed-lyrics-track-table-wrap">
                    <table class="widefat striped sv-timed-lyrics-track-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Track', 'slim-volume'); ?></th>
                                <th><?php esc_html_e('Release', 'slim-volume'); ?></th>
                                <th><?php esc_html_e('Lyrics', 'slim-volume'); ?></th>
                                <th><?php esc_html_e('Audio', 'slim-volume'); ?></th>
                                <th><?php esc_html_e('Status', 'slim-volume'); ?></th>
                                <th class="sv-timed-lyrics-track-table__action">
                                    <?php esc_html_e('Action', 'slim-volume'); ?>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($query->have_posts()) : ?>
                                <?php
                                $query->the_post();
                                $track_id = (int) get_the_ID();
                                $summary  = self::track_summary($track_id);
                                $edit_url = get_edit_post_link($track_id, 'raw');
                                ?>
                                <tr>
                                    <td>
                                        <strong><?php echo esc_html(get_the_title($track_id)); ?></strong>
                                    </td>
                                    <td><?php echo esc_html(self::release_title($track_id) ?: '—'); ?></td>
                                    <td>
                                        <?php
                                        echo esc_html(
                                            $summary['has_lyrics']
                                                ? sprintf(
                                                    _n('%d line', '%d lines', $summary['line_count'], 'slim-volume'),
                                                    $summary['line_count']
                                                )
                                                : __('Missing', 'slim-volume')
                                        );
                                        ?>
                                    </td>
                                    <td>
                                        <?php
                                        echo esc_html(
                                            $summary['has_audio']
                                                ? __('Ready', 'slim-volume')
                                                : __('Missing', 'slim-volume')
                                        );
                                        ?>
                                    </td>
                                    <td>
                                        <span
                                            class="sv-timed-lyrics-status sv-timed-lyrics-status--<?php echo esc_attr($summary['status_class']); ?>"
                                        >
                                            <?php echo esc_html($summary['status_label']); ?>
                                        </span>
                                    </td>
                                    <td class="sv-timed-lyrics-track-table__action">
                                        <?php if ($summary['has_lyrics'] && $summary['has_audio']) : ?>
                                            <a
                                                class="button button-secondary"
                                                href="<?php echo esc_url(self::workspace_url($track_id)); ?>"
                                            >
                                                <?php esc_html_e('Open Sync', 'slim-volume'); ?>
                                            </a>
                                        <?php elseif (is_string($edit_url) && $edit_url !== '') : ?>
                                            <a class="button" href="<?php echo esc_url($edit_url); ?>">
                                                <?php esc_html_e('Edit Track', 'slim-volume'); ?>
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <?php wp_reset_postdata(); ?>
            <?php else : ?>
                <div class="sv-timed-lyrics-empty-state">
                    <h2><?php esc_html_e('No tracks found', 'slim-volume'); ?></h2>
                    <p><?php esc_html_e('Try a different search or create a track first.', 'slim-volume'); ?></p>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    private static function render_invalid_track_notice(): void
    {
        ?>
        <div class="wrap sv-timed-lyrics-admin">
            <h1><?php esc_html_e('Lyrics Sync', 'slim-volume'); ?></h1>
            <div class="notice notice-error inline">
                <p><?php esc_html_e('The requested Slim Volume track could not be found.', 'slim-volume'); ?></p>
            </div>
            <p>
                <a class="button button-primary" href="<?php echo esc_url(self::workspace_url(0)); ?>">
                    <?php esc_html_e('Choose a Track', 'slim-volume'); ?>
                </a>
            </p>
        </div>
        <?php
    }

    private static function render_summary_card(
        string $label,
        string $value,
        string $state,
        string $key = ''
    ): void {
        ?>
        <div
            class="sv-timed-lyrics-summary-card"
            <?php if ($key !== '') : ?>
                data-sv-summary-card="<?php echo esc_attr($key); ?>"
            <?php endif; ?>
        >
            <span><?php echo esc_html($label); ?></span>
            <strong class="sv-timed-lyrics-summary-card__value sv-timed-lyrics-summary-card__value--<?php echo esc_attr($state); ?>">
                <?php echo esc_html($value); ?>
            </strong>
        </div>
        <?php
    }

    private static function track_summary(int $track_id): array
    {
        $lyrics       = (string) get_post_meta($track_id, '_sv_lyrics', true);
        $generated    = TimedLyrics::generate_lines($lyrics);
        $line_count   = count(
            array_filter(
                $generated,
                static fn(array $line): bool =>
                    ($line['type'] ?? 'line') === 'line'
                    && trim((string) ($line['text'] ?? '')) !== ''
            )
        );
        $document     = TimedLyrics::get_document($track_id);
        $stored_lines = isset($document['lines']) && is_array($document['lines'])
            ? $document['lines']
            : [];
        $timed_count  = count(
            array_filter(
                $stored_lines,
                static fn($line): bool =>
                    is_array($line)
                    && ($line['type'] ?? 'line') === 'line'
                    && isset($line['start'])
                    && is_numeric($line['start'])
            )
        );
        $audio_url = self::audio_url($track_id);
        $status    = TimedLyrics::get_status($track_id);

        return [
            'status'       => $status,
            'status_label' => self::status_label($status),
            'status_class' => self::status_class($status),
            'has_lyrics'   => TimedLyrics::normalize_lyrics($lyrics) !== '',
            'has_audio'    => $audio_url !== '',
            'audio_url'    => $audio_url,
            'line_count'   => $line_count,
            'timed_count'  => $timed_count,
        ];
    }

    private static function audio_url(int $track_id): string
    {
        $attachment_id = (int) get_post_meta($track_id, '_sv_audio_attachment_id', true);

        if ($attachment_id > 0) {
            $attachment_url = wp_get_attachment_url($attachment_id);

            if (is_string($attachment_url) && $attachment_url !== '') {
                return $attachment_url;
            }
        }

        $external_url = (string) get_post_meta($track_id, '_sv_audio_url', true);

        return esc_url_raw($external_url);
    }

    private static function release_title(int $track_id): string
    {
        $release_id =
            \SlimVolume\Relationships\TrackReleaseRelationship
                ::get_release_id($track_id);

        return $release_id > 0
            ? (string) get_the_title($release_id)
            : '';
    }

    private static function first_syncable_line(array $lines): string
    {
        foreach ($lines as $line) {
            if (
                is_array($line)
                && ($line['type'] ?? 'line') === 'line'
                && trim((string) ($line['text'] ?? '')) !== ''
            ) {
                return (string) $line['text'];
            }
        }

        return '';
    }

    private static function workspace_url(int $track_id): string
    {
        $url = add_query_arg(
            [
                'post_type' => PostTypes::RELEASE,
                'page'      => self::MENU_SLUG,
            ],
            admin_url('edit.php')
        );

        if ($track_id > 0) {
            $url = add_query_arg('track_id', $track_id, $url);
        }

        return $url;
    }

    private static function status_label(string $status): string
    {
        return match ($status) {
            TimedLyrics::STATUS_DRAFT    => __('Draft', 'slim-volume'),
            TimedLyrics::STATUS_COMPLETE => __('Complete', 'slim-volume'),
            TimedLyrics::STATUS_STALE    => __('Needs review', 'slim-volume'),
            default                      => __('Not synced', 'slim-volume'),
        };
    }

    private static function status_class(string $status): string
    {
        return match ($status) {
            TimedLyrics::STATUS_DRAFT    => 'draft',
            TimedLyrics::STATUS_COMPLETE => 'complete',
            TimedLyrics::STATUS_STALE    => 'stale',
            default                      => 'none',
        };
    }

    private static function status_description(string $status): string
    {
        return match ($status) {
            TimedLyrics::STATUS_DRAFT => __(
                'A draft timing pass exists. Continue syncing or review the saved timestamps.',
                'slim-volume'
            ),
            TimedLyrics::STATUS_COMPLETE => __(
                'This timing pass is complete and eligible for public synchronized lyrics.',
                'slim-volume'
            ),
            TimedLyrics::STATUS_STALE => __(
                'The lyrics or audio changed after synchronization. Review the timing pass before publishing it again.',
                'slim-volume'
            ),
            default => __(
                'The track is ready for its first line-by-line timing pass.',
                'slim-volume'
            ),
        };
    }

    private static function format_timestamp(?float $seconds): string
    {
        if ($seconds === null || $seconds < 0) {
            return '—';
        }

        $minutes = (int) floor($seconds / 60);
        $seconds = $seconds - ($minutes * 60);

        return sprintf('%02d:%06.3f', $minutes, $seconds);
    }
}
