<?php

use SlimVolume\Admin\Settings;
use SlimVolume\Frontend\PlayerData;
use SlimVolume\Rewrite;

if (! defined('ABSPATH')) {
    exit;
}

get_header();

$track_id   = get_the_ID();
$release_id = Rewrite::get_track_release_id($track_id);
$settings   = Settings::get_settings();

$player_enabled = ! empty($settings['player_enabled']);
$config         = PlayerData::get_track_page_config($track_id);
$lyrics     = (string) get_post_meta($track_id, '_sv_lyrics', true);
$credits    = (string) get_post_meta($track_id, '_sv_track_credits', true);
$duration   = (string) get_post_meta($track_id, '_sv_duration', true);

$playlist = isset($config['playlist']) && is_array($config['playlist'])
    ? $config['playlist']
    : [];

$current_index = isset($config['currentIndex'])
    ? (int) $config['currentIndex']
    : 0;

$previous_track = $playlist[$current_index - 1] ?? null;
$next_track     = $playlist[$current_index + 1] ?? null;

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

$track_links = [
    'Spotify'     => (string) get_post_meta($track_id, '_sv_spotify_url', true),
    'Apple Music' => (string) get_post_meta($track_id, '_sv_apple_music_url', true),
    'YouTube'     => (string) get_post_meta($track_id, '_sv_youtube_url', true),
    'Bandcamp'    => (string) get_post_meta($track_id, '_sv_bandcamp_url', true),
    'Purchase'    => (string) get_post_meta($track_id, '_sv_purchase_url', true),
];

$download_url = (string) get_post_meta($track_id, '_sv_download_url', true);

if (! $download_url) {
    $download_attachment_id = (int) get_post_meta($track_id, '_sv_download_attachment_id', true);

    if ($download_attachment_id > 0) {
        $download_url = wp_get_attachment_url($download_attachment_id) ?: '';
    }
}

$can_download = (bool) get_post_meta($track_id, '_sv_can_download', true);

if ($can_download && $download_url) {
    $track_links['Download'] = $download_url;
}

$track_links = array_filter($track_links);
?>

<main id="primary" class="sv-track sv-track-single" data-sv-page-content>
    <?php while (have_posts()) : the_post(); ?>
        <p class="sv-breadcrumb">
            <a href="<?php echo esc_url(home_url('/')); ?>">Home</a>
            <span aria-hidden="true"> / </span>
            <a href="<?php echo esc_url(get_post_type_archive_link('sv_release')); ?>">Music</a>

            <?php if ($release_id) : ?>
                <span aria-hidden="true"> / </span>
                <a href="<?php echo esc_url(get_permalink($release_id)); ?>">
                    <?php echo esc_html(get_the_title($release_id)); ?>
                </a>
            <?php endif; ?>

            <span aria-hidden="true"> / </span>
            <span><?php the_title(); ?></span>
        </p>

        <header class="sv-track-hero">
            <?php if (has_post_thumbnail()) : ?>
                <div class="sv-track-hero__art">
                    <?php the_post_thumbnail('large'); ?>
                </div>
            <?php elseif ($release_id && has_post_thumbnail($release_id)) : ?>
                <div class="sv-track-hero__art">
                    <?php echo get_the_post_thumbnail($release_id, 'large'); ?>
                </div>
            <?php endif; ?>

            <div class="sv-track-hero__content">
                <?php
                    $track_number = $current_index >= 0 ? $current_index + 1 : 0;
                    $track_meta   = array_filter(
                        [
                            $track_number > 0
                                ? sprintf(
                                    /* translators: %d: track number. */
                                    __('Track %d', 'slim-volume'),
                                    $track_number
                                )
                                : '',
                            $duration,
                        ]
                    );
                    ?>

                    <p class="sv-section-kicker">
                        <?php esc_html_e('Track', 'slim-volume'); ?>
                    </p>

                    <h1><?php the_title(); ?></h1>

                    <?php if ($release_id) : ?>
                        <?php
                        $release_url   = get_permalink($release_id);
                        $release_title = get_the_title($release_id);
                        ?>

                        <p class="sv-track-hero__release">
                            <?php esc_html_e('From', 'slim-volume'); ?>
                            <a href="<?php echo esc_url($release_url); ?>">
                                <?php echo esc_html($release_title); ?>
                            </a>
                        </p>
                    <?php endif; ?>

                    <?php if ($track_meta) : ?>
                        <p class="sv-track-hero__meta">
                            <?php echo esc_html(implode(' · ', $track_meta)); ?>
                        </p>
                    <?php endif; ?>

                    <div
                        class="sv-track-hero__actions"
                        data-sv-track-index="<?php echo esc_attr((string) ($config['currentIndex'] ?? 0)); ?>"
                    >
                        <?php if ($player_enabled) : ?>
                        <button
                            type="button"
                            class="sv-button sv-track-hero__play"
                            data-sv-play-button="true"
                        >
                            <?php esc_html_e('Play Track', 'slim-volume'); ?>
                        </button>

                        <button
                            type="button"
                            class="sv-button sv-button--secondary sv-track-hero__queue"
                            data-sv-track-queue-button="true"
                        >
                            <?php esc_html_e('Queue Track', 'slim-volume'); ?>
                        </button>
                        <?php endif; ?>

                        <?php if ($release_id) : ?>
                            <a class="sv-button sv-button--ghost sv-track-hero__back" href="<?php echo esc_url(get_permalink($release_id)); ?>">
                                <?php esc_html_e('Back to Release', 'slim-volume'); ?>
                            </a>
                        <?php endif; ?>
                    </div>

                <?php if ($track_links) : ?>
                    <nav
                        class="sv-service-links sv-track-links"
                        aria-label="<?php esc_attr_e('Track links', 'slim-volume'); ?>"
                    >
                        <?php foreach ($track_links as $label => $url) : ?>
                            <?php $service_key = $service_key_from_link((string) $label, (string) $url); ?>
                            <a
                                class="sv-service-link sv-service-link--<?php echo esc_attr($service_key); ?>"
                                href="<?php echo esc_url($url); ?>"
                                aria-label="<?php echo esc_attr((string) $label); ?>"
                                title="<?php echo esc_attr((string) $label); ?>"
                                target="_blank"
                                rel="noopener noreferrer"
                                <?php if ($service_key === 'download') : ?>
                                    download
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

            </div>
        </header>

        <?php if ($lyrics) : ?>
            <section class="sv-track-lyrics">
                <h2><?php esc_html_e('Lyrics', 'slim-volume'); ?></h2>
                <div class="sv-rich-text">
                    <?php echo wp_kses_post(wpautop($lyrics)); ?>
                </div>
            </section>
        <?php endif; ?>

        <?php if (get_the_content()) : ?>
            <section class="sv-track-story">
                <h2><?php esc_html_e('Song Story', 'slim-volume'); ?></h2>
                <div class="sv-rich-text">
                    <?php the_content(); ?>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($credits) : ?>
            <section class="sv-track-credits">
                <h2><?php esc_html_e('Credits', 'slim-volume'); ?></h2>
                <div class="sv-rich-text">
                    <?php echo wp_kses_post(wpautop($credits)); ?>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($previous_track || $next_track) : ?>
            <nav class="sv-track-nav" aria-label="<?php esc_attr_e('Track navigation', 'slim-volume'); ?>">
                <div class="sv-track-nav__previous">
                    <?php if ($previous_track) : ?>
                        <a href="<?php echo esc_url((string) ($previous_track['trackUrl'] ?? '#')); ?>">
                            <span><?php esc_html_e('Previous Track', 'slim-volume'); ?></span>
                            <strong><?php echo esc_html((string) ($previous_track['title'] ?? '')); ?></strong>
                        </a>
                    <?php endif; ?>
                </div>

                <div class="sv-track-nav__next">
                    <?php if ($next_track) : ?>
                        <a href="<?php echo esc_url((string) ($next_track['trackUrl'] ?? '#')); ?>">
                            <span><?php esc_html_e('Next Track', 'slim-volume'); ?></span>
                            <strong><?php echo esc_html((string) ($next_track['title'] ?? '')); ?></strong>
                        </a>
                    <?php endif; ?>
                </div>
            </nav>
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