<?php
/**
 * Track context metabox.
 *
 * @package SlimVolume
 */

declare(strict_types=1);

namespace SlimVolume\Admin;

use WP_Post;

if (! defined('ABSPATH')) {
    exit;
}

final class TrackContextMetaBox
{
    public static function register(): void
    {
        add_action('add_meta_boxes_sv_track', [self::class, 'add_meta_box']);
    }

    public static function add_meta_box(WP_Post $post): void
    {
        unset($post);

        add_meta_box(
            'slim-volume-track-context',
            __('Track Context', 'slim-volume'),
            [self::class, 'render'],
            'sv_track',
            'side',
            'high'
        );
    }

    public static function render(WP_Post $post): void
    {
        $track_id   = (int) $post->ID;
        $release_id = self::get_release_id($post);
        $track_url  = self::get_track_url($post);

        ?>
        <div class="sv-admin-track-context">
            <?php if ($release_id <= 0) : ?>
                <p>
                    <?php echo esc_html__('This track is not attached to a release yet.', 'slim-volume'); ?>
                </p>
            <?php else : ?>
                <?php
                $release          = get_post($release_id);
                $release_title    = $release instanceof WP_Post ? get_the_title($release_id) : '';
                $release_edit_url = get_edit_post_link($release_id, '');
                $release_view_url = self::get_release_url($release_id);
                $route_preview    = self::get_route_preview($post, $release);
                ?>

                <p class="sv-admin-track-context__label">
                    <?php echo esc_html__('Release', 'slim-volume'); ?>
                </p>

                <p class="sv-admin-track-context__release">
                    <strong>
                        <?php if ($release_edit_url) : ?>
                            <a href="<?php echo esc_url($release_edit_url); ?>">
                                <?php echo esc_html($release_title ?: __('Untitled release', 'slim-volume')); ?>
                            </a>
                        <?php else : ?>
                            <?php echo esc_html($release_title ?: __('Untitled release', 'slim-volume')); ?>
                        <?php endif; ?>
                    </strong>
                </p>

                <?php if ($route_preview) : ?>
                    <p class="sv-admin-track-context__label">
                        <?php echo esc_html__('Route Preview', 'slim-volume'); ?>
                    </p>

                    <input
                        class="widefat sv-admin-track-context__route"
                        type="text"
                        readonly
                        value="<?php echo esc_attr($route_preview); ?>"
                        onclick="this.select();"
                    >
                <?php endif; ?>

                <div class="sv-admin-track-context__actions">
                    <?php if ($release_edit_url) : ?>
                        <a class="button button-primary button-large" href="<?php echo esc_url($release_edit_url); ?>">
                            <?php echo esc_html__('Back to Release', 'slim-volume'); ?>
                        </a>
                    <?php endif; ?>

                    <?php if ($track_url) : ?>
                        <a class="button button-large" href="<?php echo esc_url($track_url); ?>" target="_blank" rel="noopener noreferrer">
                            <?php echo esc_html(self::is_published($track_id) ? __('View Track', 'slim-volume') : __('Preview Track', 'slim-volume')); ?>
                        </a>
                    <?php endif; ?>

                    <?php if ($release_view_url) : ?>
                        <a class="button button-large" href="<?php echo esc_url($release_view_url); ?>" target="_blank" rel="noopener noreferrer">
                            <?php echo esc_html__('View Release', 'slim-volume'); ?>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    private static function get_release_id(WP_Post $post): int
    {
        $release_id = (int) get_post_meta((int) $post->ID, '_sv_release_id', true);

        if ($release_id > 0) {
            return $release_id;
        }

        return (int) $post->post_parent;
    }

    private static function get_track_url(WP_Post $post): string
    {
        if (self::is_published((int) $post->ID)) {
            $url = get_permalink((int) $post->ID);
            return is_string($url) ? $url : '';
        }

        $url = get_preview_post_link($post);

        return is_string($url) ? $url : '';
    }

    private static function get_release_url(int $release_id): string
    {
        if (self::is_published($release_id)) {
            $url = get_permalink($release_id);
            return is_string($url) ? $url : '';
        }

        $url = get_preview_post_link($release_id);

        return is_string($url) ? $url : '';
    }

    private static function get_route_preview(WP_Post $track, ?WP_Post $release): string
    {
        if (! $release instanceof WP_Post) {
            return '';
        }

        $release_slug = self::get_preview_slug($release);
        $track_slug   = self::get_preview_slug($track);

        if (! $release_slug || ! $track_slug) {
            return '';
        }

        return home_url(
            sprintf(
                '/music/%s/%s/',
                rawurlencode($release_slug),
                rawurlencode($track_slug)
            )
        );
    }

    private static function get_preview_slug(WP_Post $post): string
    {
        if ($post->post_name) {
            return $post->post_name;
        }

        if ($post->post_title) {
            return sanitize_title($post->post_title);
        }

        return '';
    }

    private static function is_published(int $post_id): bool
    {
        return 'publish' === get_post_status($post_id);
    }
}