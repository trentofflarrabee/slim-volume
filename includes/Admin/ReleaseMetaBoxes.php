<?php

declare(strict_types=1);

namespace SlimVolume\Admin;

use SlimVolume\PostTypes;

if (! defined('ABSPATH')) {
    exit;
}

final class ReleaseMetaBoxes
{
    public static function register(): void
    {
        add_meta_box(
            'sv_release_details',
            __('Slim Volume: Release Details', 'slim-volume'),
            [self::class, 'render_details'],
            PostTypes::RELEASE,
            'normal',
            'high'
        );

        add_meta_box(
            'sv_release_links',
            __('Slim Volume: Streaming / Purchase Links', 'slim-volume'),
            [self::class, 'render_links'],
            PostTypes::RELEASE,
            'normal',
            'default'
        );

        add_meta_box(
            'sv_release_credits',
            __('Slim Volume: Release Credits', 'slim-volume'),
            [self::class, 'render_credits'],
            PostTypes::RELEASE,
            'normal',
            'default'
        );
    }

    public static function render_details(\WP_Post $post): void
    {
        wp_nonce_field('sv_save_release_details', 'sv_release_details_nonce');

        $release_date    = (string) get_post_meta($post->ID, '_sv_release_date', true);
        $release_type    = (string) get_post_meta($post->ID, '_sv_release_type', true);
        $label           = (string) get_post_meta($post->ID, '_sv_label', true);
        $catalog_number  = (string) get_post_meta($post->ID, '_sv_catalog_number', true);
        $featured        = (bool) get_post_meta($post->ID, '_sv_featured_release', true);

        $types = [
            ''            => __('Select type', 'slim-volume'),
            'LP'          => __('LP', 'slim-volume'),
            'EP'          => __('EP', 'slim-volume'),
            'Single'      => __('Single', 'slim-volume'),
            'Live'        => __('Live', 'slim-volume'),
            'Compilation' => __('Compilation', 'slim-volume'),
            'Demo'        => __('Demo', 'slim-volume'),
        ];
        ?>

        <p>
            <label for="sv_release_date">
                <strong><?php esc_html_e('Release Date', 'slim-volume'); ?></strong>
            </label>
            <br>
            <input
                type="date"
                id="sv_release_date"
                name="sv_release_date"
                value="<?php echo esc_attr($release_date); ?>"
                style="width:100%;max-width:220px;"
            >
        </p>

        <p>
            <label for="sv_release_type">
                <strong><?php esc_html_e('Release Type', 'slim-volume'); ?></strong>
            </label>
            <br>
            <select id="sv_release_type" name="sv_release_type" style="width:100%;max-width:220px;">
                <?php foreach ($types as $value => $label_text) : ?>
                    <option value="<?php echo esc_attr($value); ?>" <?php selected($release_type, $value); ?>>
                        <?php echo esc_html($label_text); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>

        <p>
            <label for="sv_label">
                <strong><?php esc_html_e('Label', 'slim-volume'); ?></strong>
            </label>
            <br>
            <input
                type="text"
                id="sv_label"
                name="sv_label"
                value="<?php echo esc_attr($label); ?>"
                style="width:100%;max-width:420px;"
            >
        </p>

        <p>
            <label for="sv_catalog_number">
                <strong><?php esc_html_e('Catalog Number', 'slim-volume'); ?></strong>
            </label>
            <br>
            <input
                type="text"
                id="sv_catalog_number"
                name="sv_catalog_number"
                value="<?php echo esc_attr($catalog_number); ?>"
                style="width:100%;max-width:220px;"
            >
        </p>

        <p>
            <label>
                <input
                    type="checkbox"
                    name="sv_featured_release"
                    value="1"
                    <?php checked($featured, true); ?>
                >
                <?php esc_html_e('Featured release', 'slim-volume'); ?>
            </label>
        </p>

        <?php
    }

    public static function render_links(\WP_Post $post): void
    {
        $external_url     = (string) get_post_meta($post->ID, '_sv_external_url', true);
        $external_label   = (string) get_post_meta($post->ID, '_sv_external_label', true);
        $external_new_tab = (bool) get_post_meta($post->ID, '_sv_external_new_tab', true);

        if ($external_label === '') {
            $external_label = __('Listen', 'slim-volume');
        }

        ?>
        <div class="sv-release-primary-link-fields">
            <p>
                <label for="sv_external_url">
                    <strong><?php esc_html_e('Primary external release link', 'slim-volume'); ?></strong>
                </label>
                <br>
                <input
                    type="url"
                    id="sv_external_url"
                    name="sv_external_url"
                    value="<?php echo esc_attr($external_url); ?>"
                    style="width:100%;max-width:680px;"
                    placeholder="<?php echo esc_attr__('https://bandcamp.com/album/example', 'slim-volume'); ?>"
                >
            </p>

            <p class="description">
            <?php esc_html_e(
                'Optional. Use this as the main external destination for this release, such as Bandcamp, Spotify, or a store page. Slim Volume can use it for release cards, the main external button, and music metadata.',
                'slim-volume'
            ); ?>     
                   </p>

            <p>
                <label for="sv_external_label">
                    <strong><?php esc_html_e('Button label', 'slim-volume'); ?></strong>
                </label>
                <br>
                <input
                    type="text"
                    id="sv_external_label"
                    name="sv_external_label"
                    value="<?php echo esc_attr($external_label); ?>"
                    style="width:100%;max-width:260px;"
                    placeholder="<?php echo esc_attr__('Listen', 'slim-volume'); ?>"
                >
            </p>

            <p>
                <label>
                    <input
                        type="checkbox"
                        name="sv_external_new_tab"
                        value="1"
                        <?php checked($external_new_tab, true); ?>
                    >
                    <?php esc_html_e('Open primary external link in a new tab', 'slim-volume'); ?>
                </label>
            </p>

            <hr>
        </div>
        <?php

        $fields = [
            'sv_spotify_url'     => ['_sv_spotify_url', __('Spotify URL', 'slim-volume')],
            'sv_apple_music_url' => ['_sv_apple_music_url', __('Apple Music URL', 'slim-volume')],
            'sv_youtube_url'     => ['_sv_youtube_url', __('YouTube URL', 'slim-volume')],
            'sv_bandcamp_url'    => ['_sv_bandcamp_url', __('Bandcamp URL', 'slim-volume')],
            'sv_purchase_url'    => ['_sv_purchase_url', __('Purchase URL', 'slim-volume')],
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

    public static function render_credits(\WP_Post $post): void
    {
        $credits = (string) get_post_meta($post->ID, '_sv_release_credits', true);
        ?>

        <p>
            <label for="sv_release_credits">
                <strong><?php esc_html_e('Release Credits', 'slim-volume'); ?></strong>
            </label>
        </p>

        <textarea
            id="sv_release_credits"
            name="sv_release_credits"
            rows="10"
            style="width:100%;"
        ><?php echo esc_textarea($credits); ?></textarea>

        <p class="description">
            <?php esc_html_e('Basic HTML is allowed. Line breaks will be preserved on the frontend.', 'slim-volume'); ?>
        </p>

        <?php
    }

    public static function save(int $post_id): void
    {
        if (! isset($_POST['sv_release_details_nonce'])) {
            return;
        }

        if (! wp_verify_nonce(
            sanitize_text_field(wp_unslash($_POST['sv_release_details_nonce'])),
            'sv_save_release_details'
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

        $string_fields = [
            'sv_release_date'   => '_sv_release_date',
            'sv_release_type'   => '_sv_release_type',
            'sv_label'          => '_sv_label',
            'sv_catalog_number' => '_sv_catalog_number',
            'sv_external_label' => '_sv_external_label',
        ];

        foreach ($string_fields as $field => $meta_key) {
            $value = isset($_POST[$field])
                ? sanitize_text_field(wp_unslash($_POST[$field]))
                : '';

            update_post_meta($post_id, $meta_key, $value);
        }

        $url_fields = [
            'sv_external_url'    => '_sv_external_url',
            'sv_spotify_url'     => '_sv_spotify_url',
            'sv_apple_music_url' => '_sv_apple_music_url',
            'sv_youtube_url'     => '_sv_youtube_url',
            'sv_bandcamp_url'    => '_sv_bandcamp_url',
            'sv_purchase_url'    => '_sv_purchase_url',
        ];

        foreach ($url_fields as $field => $meta_key) {
            $value = isset($_POST[$field])
                ? esc_url_raw(wp_unslash($_POST[$field]))
                : '';

            update_post_meta($post_id, $meta_key, $value);
        }

        $credits = isset($_POST['sv_release_credits'])
            ? wp_kses_post(wp_unslash($_POST['sv_release_credits']))
            : '';

        update_post_meta($post_id, '_sv_release_credits', $credits);

        $featured = isset($_POST['sv_featured_release']) ? '1' : '0';
        update_post_meta($post_id, '_sv_featured_release', $featured);

        $external_new_tab = isset($_POST['sv_external_new_tab']) ? '1' : '0';
        update_post_meta($post_id, '_sv_external_new_tab', $external_new_tab);
    }
}