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
            [self::class, 'render_details'],
            PostTypes::TRACK,
            'normal',
            'high'
        );

        add_meta_box(
            'sv_track_links',
            __('Slim Volume: Streaming / Purchase Links', 'slim-volume'),
            [self::class, 'render_links'],
            PostTypes::TRACK,
            'normal',
            'default'
        );

        add_meta_box(
            'sv_track_lyrics',
            __('Slim Volume: Lyrics', 'slim-volume'),
            [self::class, 'render_lyrics'],
            PostTypes::TRACK,
            'normal',
            'default'
        );

        add_meta_box(
            'sv_track_credits',
            __('Slim Volume: Track Credits', 'slim-volume'),
            [self::class, 'render_credits'],
            PostTypes::TRACK,
            'normal',
            'default'
        );
    }

    public static function render_details(\WP_Post $post): void
    {
        wp_nonce_field('sv_save_track_details', 'sv_track_details_nonce');

        $release_id =
            \SlimVolume\Relationships\TrackReleaseRelationship
                ::get_release_id((int) $post->ID);

        /*
         * A newly created track may not have a persisted relationship yet.
         * Preserve the release-prefill URL for that unsaved editor state.
         */
        $requested_release_id = (
            isset($_GET['sv_release_id'])
            && is_string($_GET['sv_release_id'])
        )
            ? absint(wp_unslash($_GET['sv_release_id']))
            : 0;

        if (
            $release_id <= 0
            && $requested_release_id > 0
            && \SlimVolume\Relationships\TrackReleaseRelationship
                ::is_valid_release($requested_release_id)
        ) {
            $release_id = $requested_release_id;
        }
        
        $track_number        = (int) get_post_meta($post->ID, '_sv_track_number', true);
        $disc_number         = (int) get_post_meta($post->ID, '_sv_disc_number', true);
        $duration            = (string) get_post_meta($post->ID, '_sv_duration', true);
        $duration_seconds    = (int) get_post_meta($post->ID, '_sv_duration_seconds', true);
        $audio_url           = (string) get_post_meta($post->ID, '_sv_audio_url', true);
        $audio_attachment_id    = (int) get_post_meta($post->ID, '_sv_audio_attachment_id', true);
        $download_attachment_id = (int) get_post_meta($post->ID, '_sv_download_attachment_id', true);
        $can_download           = (bool) get_post_meta($post->ID, '_sv_can_download', true);

        $audio_attachment_url = $audio_attachment_id > 0
            ? wp_get_attachment_url($audio_attachment_id)
            : '';

        $download_attachment_url = $download_attachment_id > 0
            ? wp_get_attachment_url($download_attachment_id)
            : '';

        $attachment_duration = self::get_audio_attachment_duration(
            $audio_attachment_id
        );

        $duration_is_derived = $attachment_duration['seconds'] > 0;

        if ($duration_is_derived) {
            $duration         = $attachment_duration['label'];
            $duration_seconds = $attachment_duration['seconds'];
        }        
        
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
            <label for="sv_disc_number">
                <strong><?php esc_html_e('Disc Number', 'slim-volume'); ?></strong>
            </label>
            <br>
            <input
                type="number"
                id="sv_disc_number"
                name="sv_disc_number"
                value="<?php echo esc_attr((string) $disc_number); ?>"
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
                <?php wp_readonly($duration_is_derived); ?>
            >
        </p>

        <p>
            <label for="sv_duration_seconds">
                <strong><?php esc_html_e('Duration in seconds', 'slim-volume'); ?></strong>
            </label>
            <br>
            <input
                type="number"
                id="sv_duration_seconds"
                name="sv_duration_seconds"
                value="<?php echo esc_attr((string) $duration_seconds); ?>"
                min="0"
                step="1"
                placeholder="222"
                style="width:100%;max-width:160px;"
                <?php wp_readonly($duration_is_derived); ?>
            >
        </p>

        <p class="description">
            <?php
            esc_html_e(
                'Selecting a WordPress audio file fills both duration fields from its media metadata. Without an uploaded audio attachment, you can enter the duration manually for previews or external audio.',
                'slim-volume'
            );
            ?>
        </p>

        <div
            class="sv-admin-media-field"
            data-sv-media-field
            data-sv-duration-source
        >
        
        <label for="sv_audio_attachment_id">
                <strong><?php esc_html_e('Player audio file', 'slim-volume'); ?></strong>
            </label>

            <p class="description">
                <?php esc_html_e('Recommended: MP3 for fast, reliable browser playback.', 'slim-volume'); ?>
            </p>

            <input
                type="hidden"
                id="sv_audio_attachment_id"
                name="sv_audio_attachment_id"
                value="<?php echo esc_attr((string) $audio_attachment_id); ?>"
                data-sv-media-input
            >

            <p>
                <button
                    type="button"
                    class="button"
                    data-sv-media-select
                    data-sv-media-title="<?php echo esc_attr__('Select Streaming Audio', 'slim-volume'); ?>"
                    data-sv-media-button="<?php echo esc_attr__('Use this audio file', 'slim-volume'); ?>"
                >
                    <?php esc_html_e('Select Streaming Audio', 'slim-volume'); ?>
                </button>

                <button
                    type="button"
                    class="button"
                    data-sv-media-remove
                    <?php disabled($audio_attachment_id <= 0); ?>
                >
                    <?php esc_html_e('Remove', 'slim-volume'); ?>
                </button>
            </p>

            <p class="description" data-sv-media-preview>
                <?php echo $audio_attachment_url ? esc_html($audio_attachment_url) : esc_html__('No streaming audio selected.', 'slim-volume'); ?>
            </p>
        </div>

        <p>
            <label for="sv_audio_url">
                <strong><?php esc_html_e('External Audio URL', 'slim-volume'); ?></strong>
            </label>

            <p class="description">
                <?php esc_html_e(
                    'Use this only when the player audio is hosted outside WordPress. A WordPress audio file selected above takes priority.',
                    'slim-volume'
                ); ?>
            </p>

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

        <p>
            <label>
                <input
                    type="checkbox"
                    name="sv_can_download"
                    value="1"
                    <?php checked($can_download, true); ?>
                >
                <?php esc_html_e('Allow download link', 'slim-volume'); ?>
            </label>
        </p>

        <div class="sv-admin-media-field" data-sv-media-field>
            <label for="sv_download_attachment_id">
                <strong><?php esc_html_e('Optional Download File', 'slim-volume'); ?></strong>
            </label>

            <p class="description">
                <?php esc_html_e('Optional. Can be MP3, WAV, FLAC, or another audio file WordPress allows. Lossless is supported but never required.', 'slim-volume'); ?>
            </p>

            <input
                type="hidden"
                id="sv_download_attachment_id"
                name="sv_download_attachment_id"
                value="<?php echo esc_attr((string) $download_attachment_id); ?>"
                data-sv-media-input
            >

            <p>
                <button
                    type="button"
                    class="button"
                    data-sv-media-select
                    data-sv-media-title="<?php echo esc_attr__('Select Download Audio', 'slim-volume'); ?>"
                    data-sv-media-button="<?php echo esc_attr__('Use this download file', 'slim-volume'); ?>"
                >
                    <?php esc_html_e('Select Download File', 'slim-volume'); ?>
                </button>

                <button
                    type="button"
                    class="button"
                    data-sv-media-remove
                    <?php disabled($download_attachment_id <= 0); ?>
                >
                    <?php esc_html_e('Remove', 'slim-volume'); ?>
                </button>
            </p>

            <p class="description" data-sv-media-preview>
                <?php echo $download_attachment_url ? esc_html($download_attachment_url) : esc_html__('No download file selected.', 'slim-volume'); ?>
            </p>
        </div>

        <?php
    }

    public static function render_links(\WP_Post $post): void
    {
        ?>
            <p class="description">
                <?php esc_html_e(
                    'Add track-specific destinations when you have them. If a track link is left blank, Slim Volume can use the matching release-level link for player and frontend buttons. Track-specific links also help identify the individual recording in music metadata.',
                    'slim-volume'
                ); ?>
            </p>
            <?php
        $fields = [
            'sv_spotify_url'     => ['_sv_spotify_url', __('Spotify URL', 'slim-volume')],
            'sv_apple_music_url' => ['_sv_apple_music_url', __('Apple Music URL', 'slim-volume')],
            'sv_youtube_url'     => ['_sv_youtube_url', __('YouTube URL', 'slim-volume')],
            'sv_bandcamp_url'    => ['_sv_bandcamp_url', __('Bandcamp URL', 'slim-volume')],
            'sv_purchase_url'    => ['_sv_purchase_url', __('Purchase URL', 'slim-volume')],
            'sv_download_url'    => ['_sv_download_url', __('Download URL', 'slim-volume')],
        ];

        foreach ($fields as $field_id => [$meta_key, $label]) :
            $value = (string) get_post_meta($post->ID, $meta_key, true);
            ?>
            <p>
                <label for="<?php echo esc_attr($field_id); ?>">
                    <strong><?php echo esc_html($label); ?></strong>
                </label>
                <br>
                <input
                    type="url"
                    id="<?php echo esc_attr($field_id); ?>"
                    name="<?php echo esc_attr($field_id); ?>"
                    value="<?php echo esc_attr($value); ?>"
                    style="width:100%;max-width:680px;"
                >
            </p>
            <?php
        endforeach;
    }

    public static function render_lyrics(\WP_Post $post): void
    {
        $lyrics = (string) get_post_meta($post->ID, '_sv_lyrics', true);
        ?>

        <p>
            <label for="sv_lyrics">
                <strong><?php esc_html_e('Lyrics', 'slim-volume'); ?></strong>
            </label>
        </p>

        <textarea
            id="sv_lyrics"
            name="sv_lyrics"
            rows="16"
            style="width:100%;font-family:monospace;"
        ><?php echo esc_textarea($lyrics); ?></textarea>

        <p class="description">
            <?php esc_html_e('Line breaks will be preserved on the frontend.', 'slim-volume'); ?>
        </p>

        <?php
    }

    public static function render_credits(\WP_Post $post): void
    {
        $credits = (string) get_post_meta($post->ID, '_sv_track_credits', true);
        ?>

        <p>
            <label for="sv_track_credits">
                <strong><?php esc_html_e('Track Credits', 'slim-volume'); ?></strong>
            </label>
        </p>

        <textarea
            id="sv_track_credits"
            name="sv_track_credits"
            rows="8"
            style="width:100%;"
        ><?php echo esc_textarea($credits); ?></textarea>

        <p class="description">
            <?php esc_html_e('Basic HTML is allowed. Line breaks will be preserved on the frontend.', 'slim-volume'); ?>
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

        if (wp_is_post_revision($post_id)) {
            return;
        }

        if (! current_user_can('edit_post', $post_id)) {
            return;
        }

        $integer_fields = [
            'sv_track_number'           => '_sv_track_number',
            'sv_disc_number'            => '_sv_disc_number',
            'sv_audio_attachment_id'    => '_sv_audio_attachment_id',
            'sv_download_attachment_id' => '_sv_download_attachment_id',
        ];

        foreach ($integer_fields as $field => $meta_key) {
            $value = isset($_POST[$field])
                ? absint($_POST[$field])
                : 0;

            update_post_meta($post_id, $meta_key, $value);
        }

        $duration = isset($_POST['sv_duration'])
            ? sanitize_text_field(wp_unslash($_POST['sv_duration']))
            : '';

        $duration_seconds = isset($_POST['sv_duration_seconds'])
            ? absint($_POST['sv_duration_seconds'])
            : 0;

        $audio_attachment_id = isset($_POST['sv_audio_attachment_id'])
            ? absint($_POST['sv_audio_attachment_id'])
            : 0;

        $attachment_duration = self::get_audio_attachment_duration(
            $audio_attachment_id
        );

        if ($attachment_duration['seconds'] > 0) {
            $duration         = $attachment_duration['label'];
            $duration_seconds = $attachment_duration['seconds'];
        }

        update_post_meta($post_id, '_sv_duration', $duration);
        update_post_meta(
            $post_id,
            '_sv_duration_seconds',
            $duration_seconds
        );

        $url_fields = [
            'sv_audio_url'       => '_sv_audio_url',
            'sv_spotify_url'     => '_sv_spotify_url',
            'sv_apple_music_url' => '_sv_apple_music_url',
            'sv_youtube_url'     => '_sv_youtube_url',
            'sv_bandcamp_url'    => '_sv_bandcamp_url',
            'sv_purchase_url'    => '_sv_purchase_url',
            'sv_download_url'    => '_sv_download_url',
        ];

        foreach ($url_fields as $field => $meta_key) {
            $value = isset($_POST[$field])
                ? esc_url_raw(wp_unslash($_POST[$field]))
                : '';

            update_post_meta($post_id, $meta_key, $value);
        }

        $previous_lyrics = (string) get_post_meta(
            $post_id,
            '_sv_lyrics',
            true
        );

        $lyrics = isset($_POST['sv_lyrics'])
            ? wp_kses_post(wp_unslash($_POST['sv_lyrics']))
            : '';

        update_post_meta(
            $post_id,
            '_sv_lyrics',
            $lyrics
        );

        \SlimVolume\TimedLyrics::reconcile_lyrics_edit(
            $post_id,
            $previous_lyrics,
            $lyrics
        );
        $credits = isset($_POST['sv_track_credits'])
            ? wp_kses_post(wp_unslash($_POST['sv_track_credits']))
            : '';

        update_post_meta($post_id, '_sv_track_credits', $credits);

        $can_download = isset($_POST['sv_can_download']) ? '1' : '0';
        update_post_meta($post_id, '_sv_can_download', $can_download);

        $track_number = isset($_POST['sv_track_number'])
            ? absint($_POST['sv_track_number'])
            : 0;

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

    /**
     * Read an audio attachment's duration from WordPress attachment metadata.
     *
     * @return array{label:string,seconds:int}
     */
    private static function get_audio_attachment_duration(int $attachment_id): array
    {
        $empty = [
            'label'   => '',
            'seconds' => 0,
        ];

        if ($attachment_id <= 0) {
            return $empty;
        }

        if ('attachment' !== get_post_type($attachment_id)) {
            return $empty;
        }

        $mime_type = (string) get_post_mime_type($attachment_id);

        if (0 !== strpos($mime_type, 'audio/')) {
            return $empty;
        }

        $metadata = wp_get_attachment_metadata($attachment_id);

        if (! is_array($metadata)) {
            return $empty;
        }

        $seconds = isset($metadata['length'])
            ? max(0, (int) round((float) $metadata['length']))
            : 0;

        if ($seconds <= 0) {
            return $empty;
        }

        $label = isset($metadata['length_formatted'])
            ? sanitize_text_field((string) $metadata['length_formatted'])
            : '';

        if ('' === $label) {
            $label = self::format_duration($seconds);
        }

        return [
            'label'   => $label,
            'seconds' => $seconds,
        ];
    }

    private static function format_duration(int $total_seconds): string
    {
        $total_seconds = max(0, $total_seconds);

        $hours   = intdiv($total_seconds, 3600);
        $minutes = intdiv($total_seconds % 3600, 60);
        $seconds = $total_seconds % 60;

        if ($hours > 0) {
            return sprintf(
                '%d:%02d:%02d',
                $hours,
                $minutes,
                $seconds
            );
        }

        return sprintf(
            '%d:%02d',
            $minutes,
            $seconds
        );
    }
}