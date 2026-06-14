<?php

declare(strict_types=1);

namespace SlimVolume\Admin;

use SlimVolume\PostTypes;

if (! defined('ABSPATH')) {
    exit;
}

final class TrackMetaBoxes
{
    public static function register(): void
    {
        add_meta_box(
            'sv_track_details',
            __('Slim Volume: Track Details', 'slim-volume'),
            [self::class, 'render'],
            PostTypes::TRACK,
            'normal',
            'high'
        );
    }

    public static function render(\WP_Post $post): void
    {
        wp_nonce_field('sv_save_track_details', 'sv_track_details_nonce');

        $release_id   = (int) get_post_meta($post->ID, '_sv_release_id', true);
        $track_number = (int) get_post_meta($post->ID, '_sv_track_number', true);
        $duration     = (string) get_post_meta($post->ID, '_sv_duration', true);
        $audio_url    = (string) get_post_meta($post->ID, '_sv_audio_url', true);

        $releases = get_posts(
            [
                'post_type'      => PostTypes::RELEASE,
                'post_status'    => ['publish', 'draft', 'private'],
                'posts_per_page' => -1,
                'orderby'        => 'title',
                'order'          => 'ASC',
            ]
        );
        ?>

        <p>
            <label for="sv_release_id">
                <strong><?php esc_html_e('Release', 'slim-volume'); ?></strong>
            </label>
            <br>
            <select id="sv_release_id" name="sv_release_id" style="width:100%;max-width:420px;">
                <option value="0"><?php esc_html_e('Select a release', 'slim-volume'); ?></option>

                <?php foreach ($releases as $release) : ?>
                    <option value="<?php echo esc_attr((string) $release->ID); ?>" <?php selected($release_id, (int) $release->ID); ?>>
                        <?php echo esc_html(get_the_title($release)); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>

        <p>
            <label for="sv_track_number">
                <strong><?php esc_html_e('Track Number', 'slim-volume'); ?></strong>
            </label>
            <br>
            <input
                type="number"
                id="sv_track_number"
                name="sv_track_number"
                value="<?php echo esc_attr((string) $track_number); ?>"
                min="0"
                step="1"
                style="width:100%;max-width:160px;"
            >
        </p>

        <p>
            <label for="sv_duration">
                <strong><?php esc_html_e('Duration', 'slim-volume'); ?></strong>
            </label>
            <br>
            <input
                type="text"
                id="sv_duration"
                name="sv_duration"
                value="<?php echo esc_attr($duration); ?>"
                placeholder="3:42"
                style="width:100%;max-width:160px;"
            >
        </p>

        <p>
            <label for="sv_audio_url">
                <strong><?php esc_html_e('Audio URL', 'slim-volume'); ?></strong>
            </label>
            <br>
            <input
                type="url"
                id="sv_audio_url"
                name="sv_audio_url"
                value="<?php echo esc_attr($audio_url); ?>"
                placeholder="https://example.com/song.mp3"
                style="width:100%;max-width:680px;"
            >
        </p>

        <p class="description">
            <?php esc_html_e('This is a temporary v0.1 meta box. We will add media picker/audio attachment support later.', 'slim-volume'); ?>
        </p>

        <?php
    }

    public static function save(int $post_id): void
    {
        if (! isset($_POST['sv_track_details_nonce'])) {
            return;
        }

        if (! wp_verify_nonce(
            sanitize_text_field(wp_unslash($_POST['sv_track_details_nonce'])),
            'sv_save_track_details'
        )) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (! current_user_can('edit_post', $post_id)) {
            return;
        }

        $release_id = isset($_POST['sv_release_id'])
            ? absint($_POST['sv_release_id'])
            : 0;

        $track_number = isset($_POST['sv_track_number'])
            ? absint($_POST['sv_track_number'])
            : 0;

        $duration = isset($_POST['sv_duration'])
            ? sanitize_text_field(wp_unslash($_POST['sv_duration']))
            : '';

        $audio_url = isset($_POST['sv_audio_url'])
            ? esc_url_raw(wp_unslash($_POST['sv_audio_url']))
            : '';

        update_post_meta($post_id, '_sv_release_id', $release_id);
        update_post_meta($post_id, '_sv_track_number', $track_number);
        update_post_meta($post_id, '_sv_duration', $duration);
        update_post_meta($post_id, '_sv_audio_url', $audio_url);

        /*
         * Optional but useful: mirror track order into menu_order.
         */
        if ($track_number > 0) {
            remove_action('save_post_' . PostTypes::TRACK, [self::class, 'save']);

            wp_update_post(
                [
                    'ID'         => $post_id,
                    'menu_order' => $track_number,
                ]
            );

            add_action('save_post_' . PostTypes::TRACK, [self::class, 'save']);
        }
    }
}