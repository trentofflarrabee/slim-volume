<?php

declare(strict_types=1);

namespace SlimVolume\Admin;

use SlimVolume\Artists\ArtistResolver;
use SlimVolume\Artists\ProjectTaxonomy;
use SlimVolume\PostTypes;
use WP_Post;
use WP_Term;

if (! defined('ABSPATH')) {
    exit;
}

final class ReleaseProjectMetaBox
{
    private const NONCE_ACTION = 'sv_save_release_project';
    private const NONCE_FIELD  = 'sv_release_project_nonce';

    public static function register(): void
    {
        if (! ProjectTaxonomy::is_enabled()) {
            return;
        }

        add_meta_box(
            'sv_release_project',
            __('Slim Volume: Artist / Project', 'slim-volume'),
            [self::class, 'render'],
            PostTypes::RELEASE,
            'side',
            'default'
        );
    }

    public static function render(WP_Post $post): void
    {
        wp_nonce_field(self::NONCE_ACTION, self::NONCE_FIELD);

        $selected_term = ProjectTaxonomy::get_release_project_term($post->ID);
        $selected_id   = $selected_term instanceof WP_Term
            ? (int) $selected_term->term_id
            : 0;

        $default_artist = ArtistResolver::default_artist();

        $terms = get_terms(
            [
                'taxonomy'   => ProjectTaxonomy::TAXONOMY,
                'hide_empty' => false,
                'orderby'    => 'name',
                'order'      => 'ASC',
            ]
        );

        if (is_wp_error($terms)) {
            $terms = [];
        }

        ?>
        <p>
            <label for="sv_project_term_id">
                <strong><?php esc_html_e('Primary artist or project', 'slim-volume'); ?></strong>
            </label>
        </p>

        <select
            id="sv_project_term_id"
            name="sv_project_term_id"
            style="width:100%;"
        >
            <option value="0" <?php selected($selected_id, 0); ?>>
                <?php
                echo esc_html(
                    sprintf(
                        /* translators: %s: default artist/project name. */
                        __('Use default artist — %s', 'slim-volume'),
                        (string) ($default_artist['name'] ?? get_bloginfo('name'))
                    )
                );
                ?>
            </option>

            <?php foreach ($terms as $term) : ?>
                <?php
                if (! $term instanceof WP_Term) {
                    continue;
                }

                $entity_type = ProjectTaxonomy::sanitize_entity_type(
                    get_term_meta($term->term_id, ProjectTaxonomy::META_ENTITY_TYPE, true)
                );

                $entity_label = $entity_type === ProjectTaxonomy::ENTITY_PERSON
                    ? __('Solo artist', 'slim-volume')
                    : __('Group / project', 'slim-volume');
                ?>
                <option
                    value="<?php echo esc_attr((string) $term->term_id); ?>"
                    <?php selected($selected_id, (int) $term->term_id); ?>
                >
                    <?php
                    echo esc_html(
                        sprintf(
                            /* translators: 1: artist/project name, 2: entity type. */
                            __('%1$s — %2$s', 'slim-volume'),
                            $term->name,
                            $entity_label
                        )
                    );
                    ?>
                </option>
            <?php endforeach; ?>
        </select>

        <p class="description">
            <?php esc_html_e('Tracks inherit this release attribution automatically. Leave this on the default artist for a normal single-artist site.', 'slim-volume'); ?>
        </p>

        <?php if (! $terms) : ?>
            <p class="description">
                <?php esc_html_e('No artist/project records exist yet.', 'slim-volume'); ?>
            </p>
        <?php endif; ?>

        <p>
            <a
                href="<?php echo esc_url(self::manage_projects_url()); ?>"
                target="_blank"
                rel="noopener noreferrer"
            >
                <?php esc_html_e('Manage Artists & Projects', 'slim-volume'); ?>
            </a>
        </p>
        <?php
    }

    public static function save(int $post_id): void
    {
        if (! ProjectTaxonomy::is_enabled()) {
            return;
        }

        if (! isset($_POST[self::NONCE_FIELD])) {
            return;
        }

        $nonce = sanitize_text_field(wp_unslash($_POST[self::NONCE_FIELD]));

        if (! wp_verify_nonce($nonce, self::NONCE_ACTION)) {
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

        $term_id = isset($_POST['sv_project_term_id'])
            ? absint($_POST['sv_project_term_id'])
            : 0;

        ProjectTaxonomy::assign_to_release($post_id, $term_id);
    }

    private static function manage_projects_url(): string
    {
        return add_query_arg(
            [
                'taxonomy' => ProjectTaxonomy::TAXONOMY,
                'post_type' => PostTypes::RELEASE,
            ],
            admin_url('edit-tags.php')
        );
    }
}
