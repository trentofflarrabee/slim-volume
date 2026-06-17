<?php
/**
 * Track context admin notice.
 *
 * @package SlimVolume
 */

declare(strict_types=1);

namespace SlimVolume\Admin;

use WP_Post;

if (! defined('ABSPATH')) {
    exit;
}

final class TrackContextNotice
{
    public static function register(): void
    {
        add_action('admin_notices', [self::class, 'render']);
    }

    public static function render(): void
    {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;

        if (! $screen || 'sv_track' !== $screen->post_type) {
            return;
        }

        $track = self::get_current_track();

        if (! $track instanceof WP_Post) {
            return;
        }

        $release_id = self::get_release_id($track);

        if ($release_id <= 0) {
            return;
        }

        $release = get_post($release_id);

        if (! $release instanceof WP_Post || 'sv_release' !== $release->post_type) {
            return;
        }

        if (! current_user_can('edit_post', (int) $track->ID) || ! current_user_can('edit_post', $release_id)) {
            return;
        }

        $release_title    = get_the_title($release_id) ?: __('Untitled release', 'slim-volume');
        $release_edit_url = get_edit_post_link($release_id, '');
        $release_view_url = self::get_post_frontend_url($release);
        $track_view_url   = self::get_post_frontend_url($track);

        ?>
        <div class="notice notice-info sv-admin-track-context-notice">
            <p>
                <strong><?php echo esc_html__('Attached release:', 'slim-volume'); ?></strong>

                <?php if ($release_edit_url) : ?>
                    <a href="<?php echo esc_url($release_edit_url); ?>">
                        <?php echo esc_html($release_title); ?>
                    </a>
                <?php else : ?>
                    <?php echo esc_html($release_title); ?>
                <?php endif; ?>
            </p>

            <p class="sv-admin-track-context-notice__actions">
                <?php if ($release_edit_url) : ?>
                    <a class="button button-primary" href="<?php echo esc_url($release_edit_url); ?>">
                        <?php echo esc_html__('Back to Release', 'slim-volume'); ?>
                    </a>
                <?php endif; ?>

                <?php if ($track_view_url) : ?>
                    <a class="button" href="<?php echo esc_url($track_view_url); ?>" target="_blank" rel="noopener noreferrer">
                        <?php echo esc_html('publish' === $track->post_status ? __('View Track', 'slim-volume') : __('Preview Track', 'slim-volume')); ?>
                    </a>
                <?php endif; ?>

                <?php if ($release_view_url) : ?>
                    <a class="button" href="<?php echo esc_url($release_view_url); ?>" target="_blank" rel="noopener noreferrer">
                        <?php echo esc_html('publish' === $release->post_status ? __('View Release', 'slim-volume') : __('Preview Release', 'slim-volume')); ?>
                    </a>
                <?php endif; ?>
            </p>
        </div>
        <?php
    }

    private static function get_current_track(): ?WP_Post
    {
        global $post;

        if ($post instanceof WP_Post && 'sv_track' === $post->post_type) {
            return $post;
        }

        $post_id = isset($_GET['post']) ? absint(wp_unslash($_GET['post'])) : 0;

        if ($post_id <= 0) {
            return null;
        }

        $track = get_post($post_id);

        return $track instanceof WP_Post && 'sv_track' === $track->post_type ? $track : null;
    }

    private static function get_release_id(WP_Post $track): int
    {
        $release_id = (int) get_post_meta((int) $track->ID, '_sv_release_id', true);

        if ($release_id > 0) {
            return $release_id;
        }

        return (int) $track->post_parent;
    }

    private static function get_post_frontend_url(WP_Post $post): string
    {
        if ('publish' === $post->post_status) {
            $url = get_permalink((int) $post->ID);
            return is_string($url) ? $url : '';
        }

        $url = get_preview_post_link($post);

        return is_string($url) ? $url : '';
    }
}