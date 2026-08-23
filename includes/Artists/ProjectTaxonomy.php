<?php

declare(strict_types=1);

namespace SlimVolume\Artists;

use SlimVolume\Admin\Settings;
use SlimVolume\PostTypes;
use WP_Error;
use WP_Post;
use WP_Term;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Registers and manages optional artist/project assignments for releases.
 *
 * Existing releases may remain unassigned and continue using Slim Volume's
 * global/default artist identity.
 */
final class ProjectTaxonomy
{
    public const TAXONOMY = 'sv_project';

    public const META_ENTITY_TYPE = '_sv_project_entity_type';
    public const META_URL         = '_sv_project_url';
    public const META_IMAGE_ID    = '_sv_project_image_id';
    public const META_SAME_AS     = '_sv_project_same_as';

    public const ENTITY_GROUP  = 'group';
    public const ENTITY_PERSON = 'person';

    private const NONCE_ACTION = 'sv_save_project_fields';
    private const NONCE_FIELD  = 'sv_project_fields_nonce';

    public static function is_enabled(): bool
    {
        $settings = Settings::get_settings();

        return ! empty($settings['projects_enabled']);
    }

    public static function register(): void
    {
        register_taxonomy(
            self::TAXONOMY,
            [PostTypes::RELEASE],
            [
                'labels' => [
                    'name'                       => __('Artists & Projects', 'slim-volume'),
                    'singular_name'              => __('Artist / Project', 'slim-volume'),
                    'menu_name'                  => __('Artists & Projects', 'slim-volume'),
                    'search_items'               => __('Search artists and projects', 'slim-volume'),
                    'popular_items'              => __('Popular artists and projects', 'slim-volume'),
                    'all_items'                  => __('All artists and projects', 'slim-volume'),
                    'edit_item'                  => __('Edit artist or project', 'slim-volume'),
                    'view_item'                  => __('View artist or project', 'slim-volume'),
                    'update_item'                => __('Update artist or project', 'slim-volume'),
                    'add_new_item'               => __('Add new artist or project', 'slim-volume'),
                    'new_item_name'              => __('New artist or project name', 'slim-volume'),
                    'separate_items_with_commas' => __('Separate artists and projects with commas', 'slim-volume'),
                    'add_or_remove_items'         => __('Add or remove artists and projects', 'slim-volume'),
                    'choose_from_most_used'       => __('Choose from the most used artists and projects', 'slim-volume'),
                    'not_found'                  => __('No artists or projects found.', 'slim-volume'),
                    'back_to_items'              => __('Back to artists and projects', 'slim-volume'),
                ],
                'public'             => false,
                'publicly_queryable' => false,
                'hierarchical'       => false,
                'show_ui'            => true,
                'show_in_menu'       => true,
                'show_in_nav_menus'  => false,
                'show_tagcloud'      => false,
                'show_admin_column'  => false,
                'show_in_quick_edit' => false,
                'show_in_rest'       => false,
                'rewrite'            => false,
                'query_var'          => false,

                // Slim Volume enforces one primary project through a dedicated
                // release metabox rather than WordPress's tag-style metabox.
                'meta_box_cb' => false,
            ]
        );

        self::register_term_meta();

        // Keep Artists & Projects manageable in wp-admin even when public
        // per-release attribution is disabled. The projects_enabled setting
        // controls resolution, selectors, frontend display, and SEO—not whether
        // stored artist/project records can be viewed or edited.
        add_action(self::TAXONOMY . '_add_form_fields', [self::class, 'render_add_fields']);
        add_action(self::TAXONOMY . '_edit_form_fields', [self::class, 'render_edit_fields']);
        add_action('created_' . self::TAXONOMY, [self::class, 'save_term_fields']);
        add_action('edited_' . self::TAXONOMY, [self::class, 'save_term_fields']);
    }

    public static function register_term_meta(): void
    {
        register_term_meta(
            self::TAXONOMY,
            self::META_ENTITY_TYPE,
            [
                'single'            => true,
                'type'              => 'string',
                'default'           => self::ENTITY_GROUP,
                'show_in_rest'      => false,
                'sanitize_callback' => [self::class, 'sanitize_entity_type'],
                'auth_callback'     => [self::class, 'can_manage_term_meta'],
            ]
        );

        register_term_meta(
            self::TAXONOMY,
            self::META_URL,
            [
                'single'            => true,
                'type'              => 'string',
                'default'           => '',
                'show_in_rest'      => false,
                'sanitize_callback' => 'esc_url_raw',
                'auth_callback'     => [self::class, 'can_manage_term_meta'],
            ]
        );

        register_term_meta(
            self::TAXONOMY,
            self::META_IMAGE_ID,
            [
                'single'            => true,
                'type'              => 'integer',
                'default'           => 0,
                'show_in_rest'      => false,
                'sanitize_callback' => 'absint',
                'auth_callback'     => [self::class, 'can_manage_term_meta'],
            ]
        );
        register_term_meta(
        self::TAXONOMY,
        self::META_SAME_AS,
        [
            'single'            => true,
            'type'              => 'string',
            'default'           => '',
            'show_in_rest'      => false,
            'sanitize_callback' => [self::class, 'sanitize_same_as'],
            'auth_callback'     => [self::class, 'can_manage_term_meta'],
        ]
    );
    }

    public static function render_add_fields(): void
    {
        wp_nonce_field(self::NONCE_ACTION, self::NONCE_FIELD);
        ?>

        <p>
    <?php esc_html_e(
        'Create an Artist / Project when releases on this site may belong to different artists, bands, aliases, or projects. If every release belongs to the same artist, you can usually use the fallback artist details under Music → Settings → SEO instead.',
        'slim-volume'
    ); ?>
</p>

        <div class="form-field">
            <label for="sv_project_entity_type">
                <?php esc_html_e('What kind of artist is this?', 'slim-volume'); ?>
            </label>
            <?php self::render_entity_type_select(self::ENTITY_GROUP); ?>
            <p>
                <?php esc_html_e(
                    'Choose Solo artist / person for an individual artist. Choose Band, group, alias, or project for everything else.',
                    'slim-volume'
                ); ?>
            </p>
        </div>

        <div class="form-field">
            <label for="sv_project_url">
                <?php esc_html_e('Official artist / project website', 'slim-volume'); ?>
            </label>
            <input
                type="url"
                id="sv_project_url"
                name="sv_project_url"
                value=""
                placeholder="https://example.com/"
            >
            <p>
                <?php esc_html_e(
    'Optional. Enter the main official website or public page for this artist or project. Leave blank if there is not one.',
    'slim-volume'
); ?>
            </p>
        </div>

        <div class="form-field">
    <label for="sv_project_same_as">
        <?php esc_html_e('Official profiles', 'slim-volume'); ?>
    </label>

    <textarea
        id="sv_project_same_as"
        name="sv_project_same_as"
        rows="6"
        class="large-text code"
        placeholder="https://open.spotify.com/artist/..."
    ></textarea>

<p>
    <?php esc_html_e(
        'Optional. Enter one official profile URL per line. Examples include Spotify artist, Apple Music artist, YouTube, Bandcamp, MusicBrainz, Discogs, Instagram, or another official profile. Do not add individual release or track links here.',
        'slim-volume'
    ); ?>
</p>
</div>

        <div class="form-field">
            <label><?php esc_html_e('Artist / project image', 'slim-volume'); ?></label>
            <?php self::render_image_field(0); ?>
            <p>
                <?php esc_html_e(
                    'Optional. Choose a portrait, band photo, logo, or other image that represents this artist or project.',
                    'slim-volume'
                ); ?>
            </p>
        </div>
        <?php
    }

    public static function render_edit_fields(WP_Term $term): void
    {
        $entity_type = self::sanitize_entity_type(
            get_term_meta($term->term_id, self::META_ENTITY_TYPE, true)
        );

        $url = (string) get_term_meta($term->term_id, self::META_URL, true);

        $same_as = (string) get_term_meta(
        $term->term_id,
        self::META_SAME_AS,
        true
    );

        $image_id = absint(
            get_term_meta($term->term_id, self::META_IMAGE_ID, true)
        );

        wp_nonce_field(self::NONCE_ACTION, self::NONCE_FIELD);
        ?>


        <tr class="form-field">
            <th scope="row">
                <label for="sv_project_entity_type">
                    <?php esc_html_e('What kind of artist is this?', 'slim-volume'); ?>
                </label>
            </th>
            <td>
                <?php self::render_entity_type_select($entity_type); ?>
                <p class="description">
                    <?php esc_html_e(
    'Choose Solo artist / person for an individual artist. Choose Band, group, alias, or project for everything else.',
    'slim-volume'
); ?>
                </p>
            </td>
        </tr>

        <tr class="form-field">
            <th scope="row">
                <label for="sv_project_url">
                    <?php esc_html_e('Official artist / project website', 'slim-volume'); ?>
                </label>
            </th>
            <td>
                <input
                    type="url"
                    id="sv_project_url"
                    name="sv_project_url"
                    value="<?php echo esc_attr($url); ?>"
                    class="regular-text code"
                    placeholder="https://example.com/"
                >
                <p class="description">
                    <?php esc_html_e(
    'Optional. Enter the main official website or public page for this artist or project. Leave blank if there is not one.',
    'slim-volume'
); ?>
                </p>
            </td>
        </tr>

        <tr class="form-field">
            <th scope="row">
                <label for="sv_project_same_as">
                    <?php esc_html_e(
                        'Official identity URLs',
                        'slim-volume'
                    ); ?>
                </label>
            </th>

            <td>
                <textarea
                    id="sv_project_same_as"
                    name="sv_project_same_as"
                    rows="6"
                    class="large-text code"
                ><?php echo esc_textarea($same_as); ?></textarea>

                <p class="description">
                    <?php esc_html_e(
                        'Optional. Enter one official profile URL per line. Examples include Spotify artist, Apple Music artist, YouTube, Bandcamp, MusicBrainz, Discogs, Instagram, or another official profile. Do not add individual release or track links here.',
                        'slim-volume'
                    ); ?>
                </p>
            </td>
        </tr>

        <tr class="form-field">
            <th scope="row">
                <?php esc_html_e('Artist/project image', 'slim-volume'); ?>
            </th>
            <td>
                <?php self::render_image_field($image_id); ?>
                <p class="description">
                    <?php esc_html_e('Optional logo, portrait, or project artwork.', 'slim-volume'); ?>
                </p>
            </td>
        </tr>
        <?php
    }

    private static function render_entity_type_select(string $selected): void
    {
        ?>
        <select id="sv_project_entity_type" name="sv_project_entity_type">
            <option value="<?php echo esc_attr(self::ENTITY_GROUP); ?>" <?php selected($selected, self::ENTITY_GROUP); ?>>
                <?php esc_html_e('Band, group, alias, or project', 'slim-volume'); ?>
            </option>
            <option value="<?php echo esc_attr(self::ENTITY_PERSON); ?>" <?php selected($selected, self::ENTITY_PERSON); ?>>
                <?php esc_html_e('Solo artist / person', 'slim-volume'); ?>
            </option>
        </select>
        <?php
    }

    private static function render_image_field(int $image_id): void
    {
        $image_url = $image_id > 0
            ? (string) wp_get_attachment_image_url($image_id, 'thumbnail')
            : '';

        ?>
        <div data-sv-project-image>
            <input
                type="hidden"
                name="sv_project_image_id"
                value="<?php echo esc_attr((string) $image_id); ?>"
                data-sv-project-image-id
            >

            <div
                data-sv-project-image-preview-wrap
                style="margin:0 0 10px;<?php echo $image_url === '' ? 'display:none;' : ''; ?>"
            >
                <img
                    src="<?php echo esc_url($image_url); ?>"
                    alt=""
                    data-sv-project-image-preview
                    style="display:block;width:96px;height:96px;object-fit:cover;border:1px solid #c3c4c7;border-radius:4px;"
                >
            </div>

            <button
                type="button"
                class="button"
                data-sv-project-image-select
            >
                <?php esc_html_e('Choose image', 'slim-volume'); ?>
            </button>

            <button
                type="button"
                class="button button-link-delete"
                data-sv-project-image-remove
                <?php echo $image_id <= 0 ? 'hidden' : ''; ?>
            >
                <?php esc_html_e('Remove image', 'slim-volume'); ?>
            </button>
        </div>
        <?php
    }

    public static function save_term_fields(int $term_id): void
    {
        if (! isset($_POST[self::NONCE_FIELD])) {
            return;
        }

        $nonce = sanitize_text_field(wp_unslash($_POST[self::NONCE_FIELD]));

        if (! wp_verify_nonce($nonce, self::NONCE_ACTION)) {
            return;
        }

        if (! self::can_manage_term_meta()) {
            return;
        }

        $entity_type = isset($_POST['sv_project_entity_type'])
            ? self::sanitize_entity_type(wp_unslash($_POST['sv_project_entity_type']))
            : self::ENTITY_GROUP;

$url = isset($_POST['sv_project_url'])
    ? esc_url_raw(
        wp_unslash($_POST['sv_project_url'])
    )
    : '';

$same_as = isset($_POST['sv_project_same_as'])
    ? self::sanitize_same_as(
        wp_unslash($_POST['sv_project_same_as'])
    )
    : '';

$image_id = isset($_POST['sv_project_image_id'])
            ? absint($_POST['sv_project_image_id'])
            : 0;

        update_term_meta($term_id, self::META_ENTITY_TYPE, $entity_type);

        if ($url !== '') {
            update_term_meta($term_id, self::META_URL, $url);
        } else {
            delete_term_meta($term_id, self::META_URL);
        }

        if ($same_as !== '') {
            update_term_meta(
                $term_id,
                self::META_SAME_AS,
                $same_as
            );
        } else {
            delete_term_meta(
                $term_id,
                self::META_SAME_AS
            );
}

        if ($image_id > 0) {
            update_term_meta($term_id, self::META_IMAGE_ID, $image_id);
        } else {
            delete_term_meta($term_id, self::META_IMAGE_ID);
        }
    }

    public static function sanitize_same_as($value): string
{
    if (! is_scalar($value)) {
        return '';
    }

    $lines = preg_split(
        '/\R+/',
        (string) $value
    );

    if (! is_array($lines)) {
        return '';
    }

    $urls = [];

    foreach ($lines as $line) {
        $url = esc_url_raw(
            trim($line),
            ['http', 'https']
        );

        if ($url === '') {
            continue;
        }

        $urls[] = $url;
    }

    return implode(
        "\n",
        array_values(
            array_unique($urls)
        )
    );
}

    public static function sanitize_entity_type($value): string
    {
        $value = sanitize_key((string) $value);

        return in_array($value, [self::ENTITY_GROUP, self::ENTITY_PERSON], true)
            ? $value
            : self::ENTITY_GROUP;
    }

    public static function can_manage_term_meta(): bool
    {
        $taxonomy = get_taxonomy(self::TAXONOMY);

        if (! $taxonomy) {
            return current_user_can('manage_categories');
        }

        return current_user_can($taxonomy->cap->manage_terms);
    }

    /**
     * Return the single primary project currently assigned to a release.
     *
     * If outside code has assigned multiple terms, the lowest term ID wins so
     * resolution remains deterministic.
     */
    public static function get_release_project_term(int $release_id): ?WP_Term
    {
        if ($release_id <= 0) {
            return null;
        }

        $release = get_post($release_id);

        if (! $release instanceof WP_Post || $release->post_type !== PostTypes::RELEASE) {
            return null;
        }

        $terms = wp_get_object_terms(
            $release_id,
            self::TAXONOMY,
            [
                'orderby' => 'term_id',
                'order'   => 'ASC',
            ]
        );

        if (is_wp_error($terms) || ! $terms) {
            return null;
        }

        $term = reset($terms);

        return $term instanceof WP_Term ? $term : null;
    }

    /**
     * Enforce one primary project on a release.
     *
     * Passing 0 clears the assignment and restores default-artist fallback.
     *
     * @return array<int,int>|WP_Error Term taxonomy IDs on success.
     */
    public static function assign_to_release(int $release_id, int $term_id)
    {
        $release = get_post($release_id);

        if (! $release instanceof WP_Post || $release->post_type !== PostTypes::RELEASE) {
            return new WP_Error(
                'slim_volume_invalid_release',
                __('A valid Slim Volume release is required.', 'slim-volume')
            );
        }

        if ($term_id <= 0) {
            return wp_set_object_terms($release_id, [], self::TAXONOMY, false);
        }

        $term = get_term($term_id, self::TAXONOMY);

        if (! $term instanceof WP_Term) {
            return new WP_Error(
                'slim_volume_invalid_project',
                __('A valid artist or project is required.', 'slim-volume')
            );
        }

        return wp_set_object_terms($release_id, [$term_id], self::TAXONOMY, false);
    }
}