<?php

use SlimVolume\Frontend\TemplateLoader;

if (! defined('ABSPATH')) {
    exit;
}

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
    </header>

    <?php if (have_posts()) : ?>
        <div class="sv-release-grid">
            <?php while (have_posts()) : the_post(); ?>
                <article <?php post_class('sv-release-card'); ?>>
                    <a class="sv-release-card__link" href="<?php the_permalink(); ?>">
                        <?php if (has_post_thumbnail()) : ?>
                            <div class="sv-release-card__art">
                                <?php the_post_thumbnail('medium_large'); ?>
                            </div>
                        <?php endif; ?>

                        <div class="sv-release-card__body">
                            <h2 class="sv-release-card__title"><?php the_title(); ?></h2>

                            <?php
                            $release_date = (string) get_post_meta(get_the_ID(), '_sv_release_date', true);
                            $release_type = (string) get_post_meta(get_the_ID(), '_sv_release_type', true);
                            ?>

                            <?php if ($release_type || $release_date) : ?>
                                <p class="sv-release-card__meta">
                                    <?php echo esc_html(trim($release_type . ' ' . $release_date)); ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </a>
                </article>
            <?php endwhile; ?>
        </div>

        <div class="sv-pagination">
            <?php the_posts_pagination(); ?>
        </div>
    <?php else : ?>
        <p><?php esc_html_e('No releases found.', 'slim-volume'); ?></p>
    <?php endif; ?>

</main>

<?php slim_volume_render_player_shell(); ?>

<?php
get_footer();