<?php
/**
 * Release archive template.
 *
 * @package SlimVolume
 */

use SlimVolume\Admin\Settings;

if (! defined('ABSPATH')) {
    exit;
}

$search_query = isset($_GET['sv_release_q'])
    ? sanitize_text_field(wp_unslash($_GET['sv_release_q']))
    : '';

$settings = Settings::get_settings();

$release_card_link_behavior = isset($settings['release_card_link_behavior'])
    ? sanitize_key((string) $settings['release_card_link_behavior'])
    : 'internal';

if (! in_array($release_card_link_behavior, ['internal', 'external_when_available'], true)) {
    $release_card_link_behavior = 'internal';
}

$sort = isset($_GET['sv_release_sort'])
    ? sanitize_key(wp_unslash($_GET['sv_release_sort']))
    : 'newest';

$allowed_sorts = ['newest', 'oldest', 'title_asc', 'title_desc'];

if (! in_array($sort, $allowed_sorts, true)) {
    $sort = 'newest';
}

$paged = max(
    1,
    (int) get_query_var('paged'),
    (int) get_query_var('page')
);

$get_matching_release_ids = static function (string $search_query): array {
    if ('' === trim($search_query)) {
        return [];
    }

    $release_ids = get_posts(
        [
            'post_type'      => 'sv_release',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
            's'              => $search_query,
        ]
    );

    $track_title_or_content_ids = get_posts(
        [
            'post_type'      => 'sv_track',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
            's'              => $search_query,
        ]
    );

    $track_lyrics_ids = get_posts(
        [
            'post_type'      => 'sv_track',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
            'meta_query'     => [
                [
                    'key'     => '_sv_lyrics',
                    'value'   => $search_query,
                    'compare' => 'LIKE',
                ],
            ],
        ]
    );

    $track_ids = array_values(
        array_unique(
            array_map(
                'absint',
                array_merge($track_title_or_content_ids, $track_lyrics_ids)
            )
        )
    );

    foreach ($track_ids as $track_id) {
        $release_id = (int) get_post_meta($track_id, '_sv_release_id', true);

        if ($release_id <= 0) {
            $release_id = (int) get_post_field('post_parent', $track_id);
        }

        if ($release_id <= 0) {
            continue;
        }

        $release = get_post($release_id);

        if (
            $release instanceof WP_Post
            && 'sv_release' === $release->post_type
            && 'publish' === $release->post_status
        ) {
            $release_ids[] = $release_id;
        }
    }

    $release_ids = array_values(
        array_unique(
            array_filter(
                array_map('absint', $release_ids)
            )
        )
    );

    return $release_ids;
};

$query_args = [
    'post_type'      => 'sv_release',
    'post_status'    => 'publish',
    'posts_per_page' => (int) get_option('posts_per_page'),
    'paged'          => $paged,
];

if ($search_query) {
    $matching_release_ids = $get_matching_release_ids($search_query);

    $query_args['post__in'] = $matching_release_ids ?: [0];
}

switch ($sort) {
    case 'oldest':
        $query_args['meta_key'] = '_sv_release_date';
        $query_args['orderby']  = [
            'meta_value' => 'ASC',
            'title'      => 'ASC',
        ];
        break;

    case 'title_asc':
        $query_args['orderby'] = 'title';
        $query_args['order']   = 'ASC';
        break;

    case 'title_desc':
        $query_args['orderby'] = 'title';
        $query_args['order']   = 'DESC';
        break;

    case 'newest':
    default:
        $query_args['meta_key'] = '_sv_release_date';
        $query_args['orderby']  = [
            'meta_value' => 'DESC',
            'title'      => 'ASC',
        ];
        break;
}

$release_query = new WP_Query($query_args);

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

        <form class="sv-release-archive-controls" method="get" action="<?php echo esc_url($archive_url); ?>">
            <label class="screen-reader-text" for="sv-release-search">
                <?php esc_html_e('Search releases', 'slim-volume'); ?>
            </label>

            <input
                id="sv-release-search"
                class="sv-release-archive-controls__search"
                type="search"
                name="sv_release_q"
                value="<?php echo esc_attr($search_query); ?>"
                placeholder="<?php echo esc_attr__('Search releases, tracks, or lyrics...', 'slim-volume'); ?>"
            >

            <label class="screen-reader-text" for="sv-release-sort">
                <?php esc_html_e('Sort releases', 'slim-volume'); ?>
            </label>

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

            <button class="sv-button sv-release-archive-controls__submit" type="submit">
                <?php esc_html_e('Apply', 'slim-volume'); ?>
            </button>

            <?php if ($search_query || 'newest' !== $sort) : ?>
                <a class="sv-release-archive-controls__clear" href="<?php echo esc_url($archive_url); ?>">
                    <?php esc_html_e('Clear', 'slim-volume'); ?>
                </a>
            <?php endif; ?>
        </form>
    </header>

    <?php if ($release_query->have_posts()) : ?>
        <div class="sv-release-grid">
            <?php while ($release_query->have_posts()) : ?>
                <?php
                $release_query->the_post();

                $release_id   = get_the_ID();
                $release_meta = $format_release_meta($release_id);

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
                        <?php if (has_post_thumbnail()) : ?>
                            <div class="sv-release-card__art">
                                <?php the_post_thumbnail('medium_large'); ?>
                            </div>
                        <?php endif; ?>

                        <div class="sv-release-card__body">
                            <h2 class="sv-release-card__title"><?php the_title(); ?></h2>

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
            <?php esc_html_e('No releases matched your search.', 'slim-volume'); ?>
        </p>
    <?php endif; ?>
</main>

<?php if (! empty($settings['player_enabled'])) : ?>
    <?php slim_volume_render_player_shell(); ?>
<?php endif; ?>

<?php
get_footer();