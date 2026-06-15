<?php

use SlimVolume\Frontend\PlayerData;
use SlimVolume\Frontend\TemplateLoader;

if (! defined('ABSPATH')) {
    exit;
}

get_header();

$release_id = get_the_ID();
$config     = PlayerData::get_release_page_config($release_id);
$playlist   = $config['playlist'] ?? [];
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
                $release_date = (string) get_post_meta($release_id, '_sv_release_date', true);
                $release_type = (string) get_post_meta($release_id, '_sv_release_type', true);
                ?>

            <?php if ($release_type || $release_date) : ?>
            <p class="sv-release-hero__meta">
                <?php echo esc_html(trim($release_type . ' ' . $release_date)); ?>
            </p>
            <?php endif; ?>

            <?php
                    $release_links = [
                        'Spotify'     => (string) get_post_meta($release_id, '_sv_spotify_url', true),
                        'Apple Music' => (string) get_post_meta($release_id, '_sv_apple_music_url', true),
                        'YouTube'     => (string) get_post_meta($release_id, '_sv_youtube_url', true),
                        'Bandcamp'    => (string) get_post_meta($release_id, '_sv_bandcamp_url', true),
                        'Purchase'    => (string) get_post_meta($release_id, '_sv_purchase_url', true),
                    ];

                    $release_links = array_filter($release_links);
                    ?>

            <?php if ($release_links) : ?>
            <nav class="sv-link-list sv-release-links"
                aria-label="<?php esc_attr_e('Release links', 'slim-volume'); ?>">
                <?php foreach ($release_links as $label => $url) : ?>
                <a class="sv-link-pill" href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener noreferrer">
                    <?php echo esc_html($label); ?>
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
    <section class="sv-release-tracklist">
        <h2><?php esc_html_e('Tracks', 'slim-volume'); ?></h2>

    <?php if (! empty($playlist)) : ?>
        <div class="sv-release__queue-actions">
            <button
                type="button"
                class="sv-button sv-release__play-release"
                data-sv-page-queue-button="true"
                data-sv-page-queue-action="play"
                data-sv-page-queue-start-index="0"
            >
                <?php esc_html_e('Play This Release', 'slim-volume'); ?>
            </button>

            <button
                type="button"
                class="sv-button sv-button--secondary sv-release__add-release"
                data-sv-page-queue-button="true"
                data-sv-page-queue-action="append"
            >
                <?php esc_html_e('Add This Release to Queue', 'slim-volume'); ?>
            </button>
        </div>
    <?php endif; ?>

        <ol class="sv-track-list">
            <?php foreach ($playlist as $index => $track) : ?>
            <?php
                        $track_id    = (int) ($track['id'] ?? 0);
                        $release     = $track['release'] ?? [];
                        $release_id_from_track = (int) ($release['id'] ?? $release_id);
                        ?>
            <li class="sv-track-row" data-sv-track-row data-sv-track-id="<?php echo esc_attr((string) $track_id); ?>"
                data-sv-release-id="<?php echo esc_attr((string) $release_id_from_track); ?>"
                data-sv-track-slug="<?php echo esc_attr((string) ($track['slug'] ?? '')); ?>"
                data-sv-release-slug="<?php echo esc_attr((string) ($release['slug'] ?? '')); ?>"
                data-sv-track-index="<?php echo esc_attr((string) $index); ?>">

                <button type="button" 
                class="sv-track-row__play" 
                data-sv-play-button="true"
                    aria-label="<?php echo esc_attr(sprintf(__('Play %s', 'slim-volume'), $track['title'] ?? 'track')); ?>">
                    ▶
                </button>

                <button
                    type="button"
                    class="sv-track-row__queue"
                    data-sv-track-queue-button="true"
                    data-sv-track-index="<?php echo esc_attr((string) $index); ?>"
                >
                    <?php esc_html_e('Add to Queue', 'slim-volume'); ?>
                </button>

                <a class="sv-track-row__title" href="<?php echo esc_url((string) ($track['trackUrl'] ?? '#')); ?>">
                    <?php echo esc_html((string) ($track['title'] ?? '')); ?>
                </a>

                <?php if (! empty($track['duration'])) : ?>
                <span class="sv-track-row__duration">
                    <?php echo esc_html((string) $track['duration']); ?>
                </span>
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

    <?php PlayerData::render_page_config($config); ?>
    <?php endwhile; ?>
</main>

<?php require SLIM_VOLUME_PATH . 'templates/partials/player-shell.php'; ?>

<?php
get_footer();