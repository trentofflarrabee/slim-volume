<?php

use SlimVolume\Frontend\PlayerData;
use SlimVolume\Frontend\TemplateLoader;
use SlimVolume\Rewrite;

if (! defined('ABSPATH')) {
    exit;
}

get_header();

$track_id   = get_the_ID();
$release_id = Rewrite::get_track_release_id($track_id);
$config     = PlayerData::get_track_page_config($track_id);
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
                <h1><?php the_title(); ?></h1>

                <?php if ($release_id) : ?>
                    <p class="sv-track-hero__release">
                        <?php echo esc_html(get_the_title($release_id)); ?>
                    </p>
                <?php endif; ?>

                <?php if ($duration) : ?>
                    <p class="sv-track-hero__duration">
                        <?php echo esc_html($duration); ?>
                    </p>
                <?php endif; ?>

                <button
                    type="button"
                    class="sv-track-hero__play"
                    data-sv-play-button="true"
                    data-sv-track-index="<?php echo esc_attr((string) ($config['currentIndex'] ?? 0)); ?>"
                >
                    <?php esc_html_e('Play Track', 'slim-volume'); ?>
                </button>

                <?php if ($track_links) : ?>
                    <nav class="sv-link-list sv-track-links" aria-label="<?php esc_attr_e('Track links', 'slim-volume'); ?>">
                        <?php foreach ($track_links as $label => $url) : ?>
                            <a
                                class="sv-link-pill"
                                href="<?php echo esc_url($url); ?>"
                                target="_blank"
                                rel="noopener noreferrer"
                                <?php if ($label === 'Download') : ?>
                                    download
                                <?php endif; ?>
                            >
                                <?php echo esc_html($label); ?>
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

        <?php PlayerData::render_page_config($config); ?>
    <?php endwhile; ?>
</main>

<?php require SLIM_VOLUME_PATH . 'templates/partials/player-shell.php'; ?>

<?php
get_footer();