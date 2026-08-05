<?php
/**
 * Release dashboard metabox.
 *
 * @package SlimVolume
 */

declare(strict_types=1);

namespace SlimVolume\Admin;

use WP_Post;

if (! defined('ABSPATH')) {
    exit;
}

final class ReleaseDashboardMetaBox
{
    public static function register(): void
    {
        add_action('add_meta_boxes_sv_release', [self::class, 'add_meta_box']);
    }

    public static function add_meta_box(WP_Post $post): void
    {
        add_meta_box(
            'slim-volume-release-dashboard',
            __('Release Dashboard', 'slim-volume'),
            [self::class, 'render'],
            'sv_release',
            'normal',
            'high'
        );
    }

    public static function render(WP_Post $post): void
    {
        $release_id = (int) $post->ID;
        $tracks     = self::get_release_tracks($release_id);

        $add_track_url = add_query_arg(
            [
                'post_type'     => 'sv_track',
                'sv_release_id' => $release_id,
            ],
            admin_url('post-new.php')
        );

        ?>
        <div class="sv-admin-release-dashboard">
            <div class="sv-admin-release-dashboard__header">
                <div>
                    <strong><?php echo esc_html(get_the_title($release_id)); ?></strong>
                    <p>
                        <?php
                        printf(
                            esc_html(
                                /* translators: %d: number of tracks attached to the release */
                                _n(
                                    '%d attached track',
                                    '%d attached tracks',
                                    count($tracks),
                                    'slim-volume'
                                )
                            ),
                            count($tracks)
                        );
                        ?>
                    </p>
                </div>

                <div class="sv-admin-release-dashboard__actions">
                    <a class="button button-primary" href="<?php echo esc_url($add_track_url); ?>">
                        <?php echo esc_html__('Add New Track', 'slim-volume'); ?>
                    </a>

                    <?php if ('publish' === get_post_status($release_id)) : ?>
                        <a class="button" href="<?php echo esc_url(get_permalink($release_id)); ?>" target="_blank" rel="noopener noreferrer">
                            <?php echo esc_html__('View Release', 'slim-volume'); ?>
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (! $tracks) : ?>
                <div class="sv-admin-release-dashboard__empty">
                    <p><?php echo esc_html__('No tracks are attached to this release yet.', 'slim-volume'); ?></p>
                    <p>
                        <a class="button button-primary" href="<?php echo esc_url($add_track_url); ?>">
                            <?php echo esc_html__('Create the first track', 'slim-volume'); ?>
                        </a>
                    </p>
                </div>
            <?php else : ?>
                <table class="widefat striped sv-admin-release-dashboard__table">
                    <thead>
                        <tr>
                            <th><?php echo esc_html__('#', 'slim-volume'); ?></th>
                            <th><?php echo esc_html__('Track', 'slim-volume'); ?></th>
                            <th><?php echo esc_html__('Status', 'slim-volume'); ?></th>
                            <th><?php echo esc_html__('Actions', 'slim-volume'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tracks as $index => $track) : ?>
                            <?php
                            $track_id      = (int) $track->ID;
                            $track_status  = (string) get_post_status($track_id);
                            $status_object = get_post_status_object($track_status);
                            $status_label  = $status_object ? $status_object->label : $track_status;
                            $edit_url      = get_edit_post_link($track_id, '');
                            $view_url      = 'publish' === $track_status
                                ? get_permalink($track_id)
                                : get_preview_post_link($track_id);
                            ?>
                            <tr>
                                <td>
                                    <?php echo esc_html((string) ($index + 1)); ?>
                                </td>

                                <td>
                                    <strong>
                                        <?php echo esc_html(get_the_title($track_id)); ?>
                                    </strong>
                                </td>

                                <td>
                                    <span class="sv-admin-status sv-admin-status--<?php echo esc_attr($track_status); ?>">
                                        <?php echo esc_html($status_label); ?>
                                    </span>
                                </td>

                                <td>
                                    <?php if ($edit_url) : ?>
                                        <a href="<?php echo esc_url($edit_url); ?>">
                                            <?php echo esc_html__('Edit', 'slim-volume'); ?>
                                        </a>
                                    <?php endif; ?>

                                    <?php if ($view_url) : ?>
                                        <span aria-hidden="true"> | </span>
                                        <a href="<?php echo esc_url($view_url); ?>" target="_blank" rel="noopener noreferrer">
                                            <?php echo esc_html__('View', 'slim-volume'); ?>
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Return tracks that canonically belong to the release.
     *
     * @return WP_Post[]
     */
    private static function get_release_tracks(
        int $release_id
    ): array {
        return \SlimVolume\Relationships\TrackReleaseRelationship
            ::get_tracks_for_release(
                $release_id,
                [
                    'publish',
                    'draft',
                    'pending',
                    'private',
                    'future',
                ]
            );
    }
}