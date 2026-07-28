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
    public const MENU_SLUG = 'slim-volume-lyrics-sync';

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
        $document     = TimedLyrics::get_document($track->ID);
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
                    $summary['status_class']
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
                    $summary['timed_count'] > 0 ? 'draft' : 'none'
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
                        <p class="sv-timed-lyrics-panel__eyebrow">
                            <?php esc_html_e('Current line preview', 'slim-volume'); ?>
                        </p>
                        <p class="sv-timed-lyrics-current-line" data-sv-current-lyric>
                            <?php
                            echo esc_html(
                                $first_line !== ''
                                    ? $first_line
                                    : __('No lyric line is available yet.', 'slim-volume')
                            );
                            ?>
                        </p>
                        <p class="description">
                            <?php esc_html_e(
                                'The next phase connects Spacebar timing, undo, review, draft saving, and completion controls to this workspace.',
                                'slim-volume'
                            ); ?>
                        </p>
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
                                controls
                                preload="metadata"
                                src="<?php echo esc_url($summary['audio_url']); ?>"
                            ></audio>
                        <?php else : ?>
                            <p class="sv-timed-lyrics-empty">
                                <?php esc_html_e('No playable audio source is assigned to this track.', 'slim-volume'); ?>
                            </p>
                        <?php endif; ?>
                    </section>

                    <section class="sv-timed-lyrics-panel">
                        <div class="sv-timed-lyrics-panel__header">
                            <div>
                                <p class="sv-timed-lyrics-panel__eyebrow">
                                    <?php esc_html_e('Workflow', 'slim-volume'); ?>
                                </p>
                                <h2><?php esc_html_e('Next implementation phase', 'slim-volume'); ?></h2>
                            </div>
                        </div>

                        <ol class="sv-timed-lyrics-next-steps">
                            <li><?php esc_html_e('Start the audio and arm synchronization mode.', 'slim-volume'); ?></li>
                            <li><?php esc_html_e('Press Space slightly before each lyric should become active.', 'slim-volume'); ?></li>
                            <li><?php esc_html_e('Review, seek, undo, and nudge individual timestamps.', 'slim-volume'); ?></li>
                            <li><?php esc_html_e('Save a draft or mark the timing pass complete.', 'slim-volume'); ?></li>
                        </ol>
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
                                    data-line-index="<?php echo esc_attr((string) $index); ?>"
                                >
                                    <span class="sv-timed-lyrics-line-list__number">
                                        <?php echo esc_html(str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT)); ?>
                                    </span>

                                    <span class="sv-timed-lyrics-line-list__time">
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

    private static function render_summary_card(string $label, string $value, string $state): void
    {
        ?>
        <div class="sv-timed-lyrics-summary-card">
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
        $release_id = (int) get_post_meta($track_id, '_sv_release_id', true);

        if ($release_id <= 0) {
            $track = get_post($track_id);

            if ($track instanceof WP_Post) {
                $release_id = (int) $track->post_parent;
            }
        }

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
