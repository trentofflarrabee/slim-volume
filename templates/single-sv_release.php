<?php

use SlimVolume\Admin\Settings;
use SlimVolume\Artists\ArtistResolver;
use SlimVolume\Frontend\PlayerData;

if (! defined('ABSPATH')) {
    exit;
}

get_header();

$release_id = get_the_ID();
$settings   = Settings::get_settings();

$projects_enabled    = ! empty($settings['projects_enabled']);
$show_release_artist = $projects_enabled && ! empty($settings['projects_show_release']);
$release_artist      = $show_release_artist
    ? ArtistResolver::for_release($release_id, $settings)
    : [];


$player_enabled = ! empty($settings['player_enabled']);
$config         = PlayerData::get_release_page_config($release_id);
$playlist       = $config['playlist'] ?? [];

$primary_external_url     = esc_url_raw((string) get_post_meta($release_id, '_sv_external_url', true));
$primary_external_label   = trim((string) get_post_meta($release_id, '_sv_external_label', true));
$primary_external_new_tab = (bool) get_post_meta($release_id, '_sv_external_new_tab', true);

if ($primary_external_label === '') {
    $primary_external_label = __('Listen', 'slim-volume');
}

$service_icon_svg = static function (string $service): string {
    $icons = [
        'spotify' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="9.25"></circle><path d="M6.8 9.15c3.55-1.05 7.75-.75 10.65.85"></path><path d="M7.4 12.25c3.05-.78 6.55-.48 9.05.82"></path><path d="M8.05 15.25c2.5-.58 5.3-.28 7.35.78"></path></svg>',
        'apple-music' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><rect x="4.25" y="3.75" width="15.5" height="16.5" rx="3"></rect><path d="M14.75 7.15v8.1"></path><path d="M14.75 8.1 9.6 9.2v6.6"></path><circle cx="8.25" cy="16.25" r="1.7"></circle><circle cx="13.4" cy="14.95" r="1.7"></circle></svg>',
        'youtube' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><rect x="3.25" y="6.25" width="17.5" height="11.5" rx="3.25"></rect><path class="sv-service-icon__fill" d="m10 9.25 5 2.75-5 2.75Z"></path></svg>',
        'bandcamp' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path class="sv-service-icon__fill" d="M7.15 7.25h12.1l-4.4 9.5H2.75Z"></path></svg>',
        'purchase' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M6.5 8.25h11l1 11h-13Z"></path><path d="M9 8.25V6.8a3 3 0 0 1 6 0v1.45"></path></svg>',
        'download' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 4.5v10.25"></path><path d="m8.25 11.25 3.75 3.75 3.75-3.75"></path><path d="M5.25 18.5h13.5"></path></svg>',
        'external' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M13.5 5.25h5.25v5.25"></path><path d="m11.25 12.75 7.5-7.5"></path><path d="M18 13.25v4.5a1.5 1.5 0 0 1-1.5 1.5H6.25a1.5 1.5 0 0 1-1.5-1.5V7.5A1.5 1.5 0 0 1 6.25 6h4.5"></path></svg>',
    ];

    return $icons[$service] ?? $icons['external'];
};

$service_key_from_link = static function (string $label, string $url): string {
    $label_normalized = strtolower(trim($label));
    $host             = strtolower((string) wp_parse_url($url, PHP_URL_HOST));

    if (str_contains($label_normalized, 'spotify') || str_contains($host, 'spotify.')) {
        return 'spotify';
    }

    if (
        str_contains($label_normalized, 'apple')
        || str_contains($host, 'music.apple.')
        || str_contains($host, 'itunes.apple.')
    ) {
        return 'apple-music';
    }

    if (
        str_contains($label_normalized, 'youtube')
        || str_contains($host, 'youtube.')
        || str_contains($host, 'youtu.be')
    ) {
        return 'youtube';
    }

    if (str_contains($label_normalized, 'bandcamp') || str_contains($host, 'bandcamp.')) {
        return 'bandcamp';
    }

    if (
        str_contains($label_normalized, 'purchase')
        || str_contains($label_normalized, 'buy')
        || str_contains($label_normalized, 'shop')
    ) {
        return 'purchase';
    }

    if (str_contains($label_normalized, 'download')) {
        return 'download';
    }

    return 'external';
};
?>

<main id="primary" class="sv-release sv-release-single" data-sv-page-content>
    <?php while (have_posts()) : the_post(); ?>
    <p class="sv-breadcrumb">
        <a href="<?php echo esc_url(home_url('/')); ?>">Home</a>
        <span aria-hidden="true"> / </span>
        <a href="<?php echo esc_url(get_post_type_archive_link('sv_release')); ?>">Music</a>
        <span aria-hidden="true"> / </span>
        <span><?php the_title(); ?></span>
    </p>

    <header class="sv-release-hero">
        <?php if (has_post_thumbnail()) : ?>
        <div class="sv-release-hero__art">
            <?php the_post_thumbnail('large'); ?>
        </div>
        <?php endif; ?>

        <div class="sv-release-hero__content">
            <h1><?php the_title(); ?></h1>

        <?php
        $release_date_raw = trim(
            (string) get_post_meta(
                $release_id,
                '_sv_release_date',
                true
            )
        );

        $release_date_display = $release_date_raw;

        $release_type = trim(
            (string) get_post_meta(
                $release_id,
                '_sv_release_type',
                true
            )
        );

        if ($release_date_raw) {
            $release_date_object =
                DateTimeImmutable::createFromFormat(
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

        $release_meta = array_filter(
            [
                $release_type,
                $release_date_display,
            ]
        );

        $has_release_artist = (
            $show_release_artist
            && ! empty($release_artist['name'])
        );
        ?>

        <?php if ($has_release_artist || $release_meta) : ?>
            <div class="sv-hero-byline sv-release-hero__byline">

                <?php if ($has_release_artist) : ?>
                    <span class="sv-hero-byline__item sv-artist-attribution sv-release-hero__artist">
                        <span><?php esc_html_e('by', 'slim-volume'); ?></span>

                        <?php if (! empty($release_artist['url'])) : ?>
                            <a href="<?php echo esc_url((string) $release_artist['url']); ?>">
                                <?php echo esc_html((string) $release_artist['name']); ?>
                            </a>
                        <?php else : ?>
                            <strong><?php echo esc_html((string) $release_artist['name']); ?></strong>
                        <?php endif; ?>
                    </span>
                <?php endif; ?>

                <?php if ($release_meta) : ?>
                    <span class="sv-hero-byline__item sv-release-hero__meta">
                        <?php echo esc_html(implode(' · ', $release_meta)); ?>
                    </span>
                <?php endif; ?>

            </div>
        <?php endif; ?>

            <?php
                    $release_links = [];

                    if ($primary_external_url !== '') {
                        $release_links[$primary_external_label] = $primary_external_url;
                    }

                    $release_links = array_merge(
                        $release_links,
                        [
                            'Spotify'     => (string) get_post_meta($release_id, '_sv_spotify_url', true),
                            'Apple Music' => (string) get_post_meta($release_id, '_sv_apple_music_url', true),
                            'YouTube'     => (string) get_post_meta($release_id, '_sv_youtube_url', true),
                            'Bandcamp'    => (string) get_post_meta($release_id, '_sv_bandcamp_url', true),
                            'Purchase'    => (string) get_post_meta($release_id, '_sv_purchase_url', true),
                        ]
                    );

                    $release_links = array_unique(array_filter($release_links));
                    ?>

            <?php if ($release_links) : ?>
                <nav
                    class="sv-service-links sv-release-links"
                    aria-label="<?php esc_attr_e('Release links', 'slim-volume'); ?>"
                >
                    <?php foreach ($release_links as $label => $url) : ?>
                        <?php
                        $service_key = $service_key_from_link((string) $label, (string) $url);
                        $is_primary  = $primary_external_url !== ''
                            && untrailingslashit((string) $url) === untrailingslashit($primary_external_url);
                        $new_tab     = $is_primary ? $primary_external_new_tab : true;
                        ?>
                        <a
                            class="sv-service-link sv-service-link--<?php echo esc_attr($service_key); ?>"
                            href="<?php echo esc_url($url); ?>"
                            aria-label="<?php echo esc_attr((string) $label); ?>"
                            title="<?php echo esc_attr((string) $label); ?>"
                            <?php if ($new_tab) : ?>
                                target="_blank"
                                rel="noopener noreferrer"
                            <?php endif; ?>
                        >
                            <?php
                            // The SVG is selected from the hard-coded icon map above.
                            echo $service_icon_svg($service_key); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                            ?>
                        </a>
                    <?php endforeach; ?>
                </nav>
            <?php endif; ?>

            <div class="sv-release-hero__description">
                <?php the_content(); ?>
            </div>
        </div>
    </header>

<?php if ($playlist) : ?>
    <section class="sv-release-tracklist" aria-labelledby="sv-release-tracklist-heading">
        <div class="sv-release-tracklist__header">
            <div>
                <h2 id="sv-release-tracklist-heading">
                    <?php esc_html_e('Tracks', 'slim-volume'); ?>
                </h2>

                <p class="sv-release-tracklist__count">
                    <?php
                    printf(
                        esc_html(
                            /* translators: %d: number of tracks in the release. */
                            _n(
                                '%d track',
                                '%d tracks',
                                count($playlist),
                                'slim-volume'
                            )
                        ),
                        count($playlist)
                    );
                    ?>
                </p>
            </div>

            <?php if ($player_enabled) : ?>
            <div class="sv-release__queue-actions">
                <button
                    type="button"
                    class="sv-button sv-release__play-release"
                    data-sv-page-queue-button="true"
                    data-sv-page-queue-action="play"
                    data-sv-page-queue-start-index="0"
                >
                    <?php esc_html_e('Play Release', 'slim-volume'); ?>
                </button>

                <button
                    type="button"
                    class="sv-button sv-button--secondary sv-release__add-release"
                    data-sv-page-queue-button="true"
                    data-sv-page-queue-action="append"
                >
                    <?php esc_html_e('Queue Release', 'slim-volume'); ?>
                </button>
            </div>
            <?php endif; ?>
        </div>

        <ol class="sv-track-list sv-release-tracklist__list">
            <?php foreach ($playlist as $index => $track) : ?>
                <?php
                $track_id              = (int) ($track['id'] ?? 0);
                $release              = $track['release'] ?? [];
                $release_id_from_track = (int) ($release['id'] ?? $release_id);
                ?>

                <li
                    class="sv-track-row"
                    data-sv-track-row
                    data-sv-track-id="<?php echo esc_attr((string) $track_id); ?>"
                    data-sv-release-id="<?php echo esc_attr((string) $release_id_from_track); ?>"
                    data-sv-track-slug="<?php echo esc_attr((string) ($track['slug'] ?? '')); ?>"
                    data-sv-release-slug="<?php echo esc_attr((string) ($release['slug'] ?? '')); ?>"
                    data-sv-track-index="<?php echo esc_attr((string) $index); ?>"
                >
                    <span class="sv-track-row__number">
                        <?php echo esc_html(str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT)); ?>
                    </span>

                    <?php if ($player_enabled) : ?>
                    <button
                        type="button"
                        class="sv-track-row__play"
                        data-sv-play-button="true"
                        aria-label="<?php
                        echo esc_attr(
                            sprintf(
                                /* translators: %s: track title. */
                                __('Play %s', 'slim-volume'),
                                (string) ($track['title'] ?? 'track')
                            )
                        );
                        ?>"                    >
                        <?php esc_html_e('Play', 'slim-volume'); ?>
                    </button>
                    <?php endif; ?>

                    <div class="sv-track-row__body">
                        <a class="sv-track-row__title" href="<?php echo esc_url((string) ($track['trackUrl'] ?? '#')); ?>">
                            <?php echo esc_html((string) ($track['title'] ?? '')); ?>
                        </a>
                    </div>

                    <?php if (! empty($track['duration'])) : ?>
                        <span class="sv-track-row__duration">
                            <?php echo esc_html((string) $track['duration']); ?>
                        </span>
                    <?php endif; ?>

                    <?php if ($player_enabled) : ?>
                    <div class="sv-track-row__actions">
                        <button
                            type="button"
                            class="sv-track-row__queue"
                            data-sv-track-queue-button="true"
                        >
                            <?php esc_html_e('Queue', 'slim-volume'); ?>
                        </button>
                    </div>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ol>
    </section>
<?php endif; ?>

    <?php
        $credits = (string) get_post_meta($release_id, '_sv_release_credits', true);
        ?>

    <?php if ($credits) : ?>
    <section class="sv-release-credits">
        <h2><?php esc_html_e('Credits', 'slim-volume'); ?></h2>
        <div class="sv-rich-text">
            <?php echo wp_kses_post(wpautop($credits)); ?>
        </div>
    </section>
    <?php endif; ?>

    <?php if ($player_enabled) : ?>
        <?php PlayerData::render_page_config($config); ?>
    <?php endif; ?>
    <?php endwhile; ?>
</main>

<?php if ($player_enabled) : ?>
    <?php slim_volume_render_player_shell(); ?>
<?php endif; ?>

<?php
get_footer();