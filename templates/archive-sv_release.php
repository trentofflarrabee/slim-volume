<?php
/**
 * Release archive template.
 *
 * @package SlimVolume
 */

use SlimVolume\Admin\Settings;
use SlimVolume\Artists\ArtistResolver;
use SlimVolume\Artists\ProjectTaxonomy;
use SlimVolume\Frontend\ArchiveQuery;

if (! defined('ABSPATH')) {
    exit;
}

$settings = Settings::get_settings();

$projects_enabled = ! empty(
    $settings['projects_enabled']
);

$show_archive_artist = (
    $projects_enabled
    && ! empty($settings['projects_show_archive'])
);

$archive_state = ArchiveQuery::state($settings);

$search_query = (string) (
    $archive_state['search_query'] ?? ''
);

$show_project_filter = (bool) (
    $archive_state['show_project_filter'] ?? false
);

$selected_project_id = (int) (
    $archive_state['selected_project_id'] ?? 0
);

$selected_project = (
    $archive_state['selected_project'] ?? null
);

$project_filter_terms = [];

if ($show_project_filter) {
    $terms = get_terms(
        [
            'taxonomy'   => ProjectTaxonomy::TAXONOMY,
            'hide_empty' => true,
            'orderby'    => 'name',
            'order'      => 'ASC',
        ]
    );

    if (! is_wp_error($terms)) {
        $project_filter_terms = $terms;
    }
}


$release_card_link_behavior = isset($settings['release_card_link_behavior'])
    ? sanitize_key((string) $settings['release_card_link_behavior'])
    : 'internal';

if (! in_array($release_card_link_behavior, ['internal', 'external_when_available'], true)) {
    $release_card_link_behavior = 'internal';
}

$sort = (string) (
    $archive_state['sort'] ?? 'newest'
);

$paged = (int) (
    $archive_state['paged'] ?? 1
);

$release_query = ArchiveQuery::query($settings);

$archive_url = get_post_type_archive_link('sv_release');

if (! $archive_url) {
    $archive_url = home_url('/music/');
}

$format_release_meta = static function (int $release_id): array {
    $release_date_raw     = trim((string) get_post_meta($release_id, '_sv_release_date', true));
    $release_date_display = $release_date_raw;
    $release_type         = trim((string) get_post_meta($release_id, '_sv_release_type', true));

    if ($release_date_raw) {
        $release_date_object = DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            $release_date_raw,
            wp_timezone()
        );

        if ($release_date_object instanceof DateTimeImmutable) {
            $release_date_display = wp_date(
                get_option('date_format'),
                $release_date_object->getTimestamp()
            );
        }
    }

    return array_filter([$release_type, $release_date_display]);
};

get_header();
?>

<main id="primary" class="sv-archive sv-music-archive" data-sv-page-content>
    <header class="sv-page-header">
        <p class="sv-breadcrumb">
            <a href="<?php echo esc_url(home_url('/')); ?>">Home</a>
            <span aria-hidden="true"> / </span>
            <span>Music</span>
        </p>

        <h1><?php esc_html_e('Discography', 'slim-volume'); ?></h1>

        <p class="sv-page-header__intro">
            <?php esc_html_e('Browse releases, singles, and track-by-track deep dives.', 'slim-volume'); ?>
        </p>

        <form class="sv-release-archive-controls<?php echo $show_project_filter && $project_filter_terms ? ' has-project-filter' : ''; ?>" method="get" action="<?php echo esc_url($archive_url); ?>">
            <div class="sv-release-archive-controls__field sv-release-archive-controls__field--search">
                <label class="sv-release-archive-controls__label" for="sv-release-search">
                    <?php esc_html_e('Search music', 'slim-volume'); ?>
                </label>

                <div class="sv-release-archive-controls__input-wrap">
                    <span class="sv-release-archive-controls__search-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" focusable="false">
                            <circle cx="10.8" cy="10.8" r="6.6"></circle>
                            <path d="m16 16 4.2 4.2"></path>
                        </svg>
                    </span>

                    <input
                        id="sv-release-search"
                        class="sv-release-archive-controls__search"
                        type="search"
                        name="sv_release_q"
                        value="<?php echo esc_attr($search_query); ?>"
                        placeholder="<?php echo esc_attr__('Release, track, or lyric...', 'slim-volume'); ?>"
                    >
                </div>
            </div>

            <?php if ($show_project_filter && $project_filter_terms) : ?>
                <div class="sv-release-archive-controls__field sv-release-archive-controls__field--project">
                    <label class="sv-release-archive-controls__label" for="sv-release-project">
                        <?php esc_html_e('Artist / project', 'slim-volume'); ?>
                    </label>

                    <div class="sv-release-archive-controls__select-wrap">
                        <select
                            id="sv-release-project"
                            class="sv-release-archive-controls__sort"
                            name="sv_project"
                        >
                            <option value="0">
                                <?php esc_html_e('All artists/projects', 'slim-volume'); ?>
                            </option>

                            <?php foreach ($project_filter_terms as $project_term) : ?>
                                <option
                                    value="<?php echo esc_attr((string) $project_term->term_id); ?>"
                                    <?php selected($selected_project_id, (int) $project_term->term_id); ?>
                                >
                                    <?php echo esc_html($project_term->name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            <?php endif; ?>

            <div class="sv-release-archive-controls__field sv-release-archive-controls__field--sort">
                <label class="sv-release-archive-controls__label" for="sv-release-sort">
                    <?php esc_html_e('Sort by', 'slim-volume'); ?>
                </label>

                <div class="sv-release-archive-controls__select-wrap">
                    <select
                        id="sv-release-sort"
                        class="sv-release-archive-controls__sort"
                        name="sv_release_sort"
                    >
                        <option value="newest" <?php selected($sort, 'newest'); ?>>
                            <?php esc_html_e('Newest first', 'slim-volume'); ?>
                        </option>
                        <option value="oldest" <?php selected($sort, 'oldest'); ?>>
                            <?php esc_html_e('Oldest first', 'slim-volume'); ?>
                        </option>
                        <option value="title_asc" <?php selected($sort, 'title_asc'); ?>>
                            <?php esc_html_e('Title A–Z', 'slim-volume'); ?>
                        </option>
                        <option value="title_desc" <?php selected($sort, 'title_desc'); ?>>
                            <?php esc_html_e('Title Z–A', 'slim-volume'); ?>
                        </option>
                    </select>
                </div>
            </div>

            <div class="sv-release-archive-controls__actions">
                <button class="sv-button sv-release-archive-controls__submit" type="submit">
                    <?php esc_html_e('Apply', 'slim-volume'); ?>
                </button>

                <?php if ($search_query || 'newest' !== $sort || $selected_project_id > 0) : ?>
                    <a class="sv-release-archive-controls__clear" href="<?php echo esc_url($archive_url); ?>">
                        <?php esc_html_e('Clear', 'slim-volume'); ?>
                    </a>
                <?php endif; ?>
            </div>
        </form>

        <p class="sv-release-archive-summary" aria-live="polite">
            <span>
                <?php
                printf(
                    esc_html(
                        /* translators: %s: formatted number of releases found. */
                        _n(
                            '%s release',
                            '%s releases',
                            (int) $release_query->found_posts,
                            'slim-volume'
                        )
                    ),
                    esc_html(number_format_i18n((int) $release_query->found_posts))
                );
                ?>
            </span>

            <?php if ($search_query) : ?>
                <span>
                    <?php
                    printf(
                        /* translators: %s: music archive search query. */
                        esc_html__('matching “%s”', 'slim-volume'),
                        esc_html($search_query)
                    );
                    ?>
                </span>
            <?php endif; ?>

            <?php if ($selected_project instanceof WP_Term) : ?>
                <span>
                    <?php
                    printf(
                        /* translators: %s: selected artist or project name. */
                        esc_html__('by %s', 'slim-volume'),
                        esc_html($selected_project->name)
                    );
                    ?>
                </span>
            <?php endif; ?>
        </p>
    </header>

    <?php if ($release_query->have_posts()) : ?>
        <div class="sv-release-grid">
            <?php while ($release_query->have_posts()) : ?>
                <?php
                $release_query->the_post();

                $release_id   = get_the_ID();
                $release_meta = $format_release_meta($release_id);

                $release_artist = $show_archive_artist
                    ? ArtistResolver::for_release($release_id, $settings)
                    : [];

                $external_url     = esc_url_raw((string) get_post_meta($release_id, '_sv_external_url', true));
                $external_label   = trim((string) get_post_meta($release_id, '_sv_external_label', true));
                $external_new_tab = (bool) get_post_meta($release_id, '_sv_external_new_tab', true);

                if ($external_label === '') {
                    $external_label = __('Listen', 'slim-volume');
                }

                $use_external_link = $external_url !== '' && 'external_when_available' === $release_card_link_behavior;
                $card_url          = $use_external_link ? $external_url : get_permalink($release_id);
                $card_cta          = $use_external_link ? $external_label : __('View Release', 'slim-volume');
                ?>

                <article <?php post_class('sv-release-card'); ?>>
                    <a
                        class="sv-release-card__link"
                        href="<?php echo esc_url($card_url); ?>"
                        <?php if ($use_external_link && $external_new_tab) : ?>
                            target="_blank"
                            rel="noopener noreferrer"
                        <?php endif; ?>
                    >
                        <div class="sv-release-card__art">
                            <?php if (has_post_thumbnail()) : ?>
                                <?php the_post_thumbnail('medium_large'); ?>
                            <?php else : ?>
                                <span class="sv-release-card__art-placeholder" aria-hidden="true">
                                    <svg viewBox="0 0 48 48" focusable="false">
                                        <circle cx="24" cy="24" r="17"></circle>
                                        <circle cx="24" cy="24" r="5"></circle>
                                        <path d="M24 7v8M24 33v8M7 24h8M33 24h8"></path>
                                    </svg>
                                </span>
                            <?php endif; ?>
                        </div>

                        <div class="sv-release-card__body">
                            <h2 class="sv-release-card__title"><?php the_title(); ?></h2>

                            <?php if ($show_archive_artist && ! empty($release_artist['name'])) : ?>
                                <p class="sv-release-card__artist">
                                    <?php echo esc_html((string) $release_artist['name']); ?>
                                </p>
                            <?php endif; ?>

                            <?php if ($release_meta) : ?>
                                <p class="sv-release-card__meta">
                                    <?php echo esc_html(implode(' · ', $release_meta)); ?>
                                </p>
                            <?php endif; ?>

                            <span class="sv-release-card__cta">
                                <?php echo esc_html($card_cta); ?>
                            </span>
                        </div>
                    </a>
                </article>
            <?php endwhile; ?>
        </div>

        <?php
        $pagination_args = [
            'total'   => (int) $release_query->max_num_pages,
            'current' => $paged,
        ];

        $add_args = [];

        if ($search_query) {
            $add_args['sv_release_q'] = $search_query;
        }

        if ('newest' !== $sort) {
            $add_args['sv_release_sort'] = $sort;
        }

        if ($selected_project_id > 0) {
            $add_args['sv_project'] = $selected_project_id;
        }

        if ($add_args) {
            $pagination_args['add_args'] = $add_args;
        }

        $pagination = paginate_links($pagination_args);
        ?>

        <?php if ($pagination) : ?>
            <nav class="sv-pagination" aria-label="<?php echo esc_attr__('Release pagination', 'slim-volume'); ?>">
                <?php echo wp_kses_post($pagination); ?>
            </nav>
        <?php endif; ?>

        <?php wp_reset_postdata(); ?>
    <?php else : ?>
        <p class="sv-release-archive-empty">
            <?php esc_html_e('No releases matched the selected filters.', 'slim-volume'); ?>
        </p>
    <?php endif; ?>
</main>

<?php if (! empty($settings['player_enabled'])) : ?>
    <?php slim_volume_render_player_shell(); ?>
<?php endif; ?>

<?php
get_footer();