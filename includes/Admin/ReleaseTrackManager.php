<?php

declare(strict_types=1);

namespace SlimVolume\Admin;

use SlimVolume\PostTypes;

if (! defined('ABSPATH')) {
    exit;
}

final class ReleaseTrackManager
{
    public static function register(): void
    {
        add_meta_box(
            'sv_release_tracks',
            __('Slim Volume: Tracks on This Release', 'slim-volume'),
            [self::class, 'render'],
            PostTypes::RELEASE,
            'normal',
            'default'
        );
    }

    public static function render(\WP_Post $post): void
    {
        $release_id = (int) $post->ID;
        $tracks     = self::get_tracks_for_release($release_id);

        wp_nonce_field('sv_save_release_track_order', 'sv_release_track_order_nonce');

        $add_track_url = add_query_arg(
            [
                'post_type'     => PostTypes::TRACK,
                'sv_release_id' => $release_id,
            ],
            admin_url('post-new.php')
        );
        ?>

        <div class="sv-release-track-manager" data-sv-release-track-manager>
            <p>
                <a class="button button-primary" href="<?php echo esc_url($add_track_url); ?>">
                    <?php esc_html_e('Add Track to This Release', 'slim-volume'); ?>
                </a>
            </p>

            <?php if (! $tracks) : ?>
                <p class="description">
                    <?php esc_html_e('No tracks are attached to this release yet.', 'slim-volume'); ?>
                </p>
                <p class="description">
                    <?php esc_html_e('Use the button above to create a track with this release preselected.', 'slim-volume'); ?>
                </p>
                <?php
                return;
            endif;
            ?>

            <p class="description">
                <?php esc_html_e('Drag tracks to reorder them. Save/update the release to apply the new order.', 'slim-volume'); ?>
            </p>

            <table class="widefat striped sv-release-tracks-table">
                <thead>
                    <tr>
                        <th class="sv-release-tracks-table__handle">
                            <span class="screen-reader-text"><?php esc_html_e('Reorder', 'slim-volume'); ?></span>
                        </th>
                        <th class="sv-release-tracks-table__art">
                            <?php esc_html_e('Art', 'slim-volume'); ?>
                        </th>
                        <th class="sv-release-tracks-table__number">
                            <?php esc_html_e('#', 'slim-volume'); ?>
                        </th>
                        <th>
                            <?php esc_html_e('Track', 'slim-volume'); ?>
                        </th>
                        <th>
                            <?php esc_html_e('Duration', 'slim-volume'); ?>
                        </th>
                        <th>
                            <?php esc_html_e('Audio', 'slim-volume'); ?>
                        </th>
                        <th>
                            <?php esc_html_e('Lyrics', 'slim-volume'); ?>
                        </th>
                        <th>
                            <?php esc_html_e('Status', 'slim-volume'); ?>
                        </th>
                        <th>
                            <?php esc_html_e('Actions', 'slim-volume'); ?>
                        </th>
                    </tr>
                </thead>

                <tbody data-sv-track-sortable>
                    <?php foreach ($tracks as $index => $track) : ?>
                        <?php
                        $track_id     = (int) $track->ID;
                        $track_number = (int) get_post_meta($track_id, '_sv_track_number', true);
                        $duration     = (string) get_post_meta($track_id, '_sv_duration', true);
                        $lyrics       = trim((string) get_post_meta($track_id, '_sv_lyrics', true));

                        $edit_url = get_edit_post_link($track_id, '');
                        $view_url = get_permalink($track_id);

                        $display_number = $track_number > 0 ? $track_number : ($index + 1);
                        ?>
                        <tr data-sv-track-id="<?php echo esc_attr((string) $track_id); ?>">
                            <td class="sv-release-tracks-table__handle">
                                <span class="sv-track-sort-handle" aria-hidden="true">☰</span>
                                <input
                                    type="hidden"
                                    name="sv_track_order[]"
                                    value="<?php echo esc_attr((string) $track_id); ?>"
                                    data-sv-track-order-input
                                >
                            </td>

                            <td>
                                <?php self::render_artwork($track_id, $release_id); ?>
                            </td>

                            <td>
                                <span data-sv-track-number>
                                    <?php echo esc_html((string) $display_number); ?>
                                </span>
                            </td>

                            <td>
                                <?php if ($edit_url) : ?>
                                    <a href="<?php echo esc_url($edit_url); ?>">
                                        <strong><?php echo esc_html(get_the_title($track_id)); ?></strong>
                                    </a>
                                <?php else : ?>
                                    <strong><?php echo esc_html(get_the_title($track_id)); ?></strong>
                                <?php endif; ?>
                            </td>

                            <td>
                                <?php echo $duration ? esc_html($duration) : '&mdash;'; ?>
                            </td>

                            <td>
                                <?php self::render_audio_status($track_id); ?>
                            </td>

                            <td>
                                <?php echo $lyrics !== '' ? esc_html__('Yes', 'slim-volume') : '&mdash;'; ?>
                            </td>

                            <td>
                                <?php echo esc_html(get_post_status_object($track->post_status)->label ?? $track->post_status); ?>
                            </td>

                            <td>
                                <?php if ($edit_url) : ?>
                                    <a href="<?php echo esc_url($edit_url); ?>">
                                        <?php esc_html_e('Edit', 'slim-volume'); ?>
                                    </a>
                                <?php endif; ?>

                                <?php if ($track->post_status === 'publish' && $view_url) : ?>
                                    <span aria-hidden="true"> | </span>
                                    <a href="<?php echo esc_url($view_url); ?>" target="_blank" rel="noopener noreferrer">
                                        <?php esc_html_e('View', 'slim-volume'); ?>
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <p class="description">
                <?php esc_html_e('Saving this release will update each track number and menu order.', 'slim-volume'); ?>
            </p>
        </div>

        <?php
    }

    public static function save_order(int $release_id): void
    {
        if (! isset($_POST['sv_release_track_order_nonce'])) {
            return;
        }

        if (! wp_verify_nonce(
            sanitize_text_field(wp_unslash($_POST['sv_release_track_order_nonce'])),
            'sv_save_release_track_order'
        )) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (wp_is_post_revision($release_id)) {
            return;
        }

        if (! current_user_can('edit_post', $release_id)) {
            return;
        }

        if (! isset($_POST['sv_track_order']) || ! is_array($_POST['sv_track_order'])) {
            return;
        }

        $track_ids = array_map('absint', wp_unslash($_POST['sv_track_order']));
        $track_ids = array_values(array_filter($track_ids));

        if (! $track_ids) {
            return;
        }

        foreach ($track_ids as $index => $track_id) {
            $track = get_post($track_id);

            if (! $track || $track->post_type !== PostTypes::TRACK) {
                continue;
            }

            $track_number = $index + 1;

            update_post_meta($track_id, '_sv_release_id', $release_id);
            update_post_meta($track_id, '_sv_track_number', $track_number);

            wp_update_post(
                [
                    'ID'          => $track_id,
                    'menu_order'  => $track_number,
                    'post_parent' => $release_id,
                ]
            );
        }
    }

    /**
     * Return all editable tracks that canonically belong to a release.
     *
     * @return \WP_Post[]
     */
    private static function get_tracks_for_release(
        int $release_id
    ): array {
        return \SlimVolume\Relationships\TrackReleaseRelationship
            ::get_tracks_for_release(
                $release_id,
                [
                    'publish',
                    'draft',
                    'pending',
                    'private',
                    'future',
                ]
            );
    }

    private static function render_artwork(int $track_id, int $release_id): void
    {
        $thumb_id = get_post_thumbnail_id($track_id);

        if (! $thumb_id) {
            $thumb_id = get_post_thumbnail_id($release_id);
        }

        if (! $thumb_id) {
            echo '<span class="sv-admin-thumb sv-admin-thumb--empty" aria-hidden="true"></span>';
            return;
        }

        echo wp_get_attachment_image(
            $thumb_id,
            [56, 56],
            false,
            [
                'class' => 'sv-admin-thumb',
            ]
        );
    }

    private static function render_audio_status(int $track_id): void
    {
        $attachment_id = (int) get_post_meta($track_id, '_sv_audio_attachment_id', true);
        $external_url  = (string) get_post_meta($track_id, '_sv_audio_url', true);

        if ($attachment_id > 0) {
            $url = wp_get_attachment_url($attachment_id);
            $filename = $url ? basename(parse_url($url, PHP_URL_PATH) ?: $url) : __('Attachment', 'slim-volume');

            echo '<span class="sv-admin-status sv-admin-status--yes">';
            echo esc_html($filename);
            echo '</span>';
            return;
        }

        if ($external_url) {
            echo '<span class="sv-admin-status sv-admin-status--yes">';
            esc_html_e('External URL', 'slim-volume');
            echo '</span>';
            return;
        }

        echo '<span class="sv-admin-status sv-admin-status--no">';
        esc_html_e('Missing', 'slim-volume');
        echo '</span>';
    }
}