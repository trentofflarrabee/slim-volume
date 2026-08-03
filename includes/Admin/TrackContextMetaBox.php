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
        $track_id = (int) $post->ID;

        $relationship_state =
            \SlimVolume\Relationships\TrackReleaseRelationship
                ::get_state($track_id);

        $release_id = self::get_release_id($post);
        $track_url  = self::get_track_url($post);
        ?>
        <div class="sv-admin-track-context">
            <?php if ($relationship_state['needs_repair']) : ?>
                <div class="notice notice-warning inline">
                    <p>
                        <strong>
                            <?php
                            echo esc_html__(
                                'Release relationship needs repair.',
                                'slim-volume'
                            );
                            ?>
                        </strong>
                    </p>

                    <p>
                        <?php if ($relationship_state['has_conflict']) : ?>
                            <?php
                            echo esc_html__(
                                'The Slim Volume release selection and WordPress parent value point to different releases. The Slim Volume release selection will be retained when repaired.',
                                'slim-volume'
                            );
                            ?>
                        <?php else : ?>
                            <?php
                            echo esc_html__(
                                'One of the stored release relationship values is missing or invalid. Repairing it will synchronize both relationship fields.',
                                'slim-volume'
                            );
                            ?>
                        <?php endif; ?>
                    </p>
                </div>
            <?php endif; ?>

            <?php if ($release_id <= 0) : ?>
                <p>
                    <?php
                    echo esc_html__(
                        'This track is not attached to a release yet.',
                        'slim-volume'
                    );
                    ?>
                </p>

                <p class="description">
                    <?php
                    echo esc_html__(
                        'Select a release in Track Details to see its current tracklist and this track’s expected position.',
                        'slim-volume'
                    );
                    ?>
                </p>
            <?php else : ?>
                <?php
                $release          = get_post($release_id);
                $release_title    = $release instanceof WP_Post
                    ? get_the_title($release_id)
                    : '';
                $release_edit_url = get_edit_post_link($release_id, '');
                $release_view_url = self::get_release_url($release_id);
                $route_preview    = self::get_route_preview($post, $release);
                $release_tracks   = self::get_tracks_for_release($release_id);

                $current_track_is_listed = false;

                foreach ($release_tracks as $release_track) {
                    if ((int) $release_track->ID === $track_id) {
                        $current_track_is_listed = true;
                        break;
                    }
                }

                $append_position = count($release_tracks) + 1;
                ?>

                <p class="sv-admin-track-context__label">
                    <?php echo esc_html__('Release', 'slim-volume'); ?>
                </p>

                <p class="sv-admin-track-context__release">
                    <strong>
                        <?php if ($release_edit_url) : ?>
                            <a href="<?php echo esc_url($release_edit_url); ?>">
                                <?php
                                echo esc_html(
                                    $release_title
                                        ?: __('Untitled release', 'slim-volume')
                                );
                                ?>
                            </a>
                        <?php else : ?>
                            <?php
                            echo esc_html(
                                $release_title
                                    ?: __('Untitled release', 'slim-volume')
                            );
                            ?>
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

                <div class="sv-admin-track-context__tracklist">
                    <p class="sv-admin-track-context__label">
                        <?php
                        echo esc_html__(
                            'Current Release Tracklist',
                            'slim-volume'
                        );
                        ?>
                    </p>

                    <?php if (! $release_tracks) : ?>
                        <p class="description">
                            <?php
                            echo esc_html__(
                                'No saved tracks are currently attached to this release.',
                                'slim-volume'
                            );
                            ?>
                        </p>
                    <?php else : ?>
                        <ol class="sv-admin-track-context__track-list">
                            <?php
                            foreach ($release_tracks as $index => $release_track) :
                                $release_track_id = (int) $release_track->ID;
                                $is_current       = $release_track_id === $track_id;
                                $edit_url         = get_edit_post_link(
                                    $release_track_id,
                                    ''
                                );

                                $stored_number = (int) get_post_meta(
                                    $release_track_id,
                                    '_sv_track_number',
                                    true
                                );

                                $display_number = $stored_number > 0
                                    ? $stored_number
                                    : $index + 1;
                                ?>
                                <li
                                    class="<?php echo $is_current ? 'is-current' : ''; ?>"
                                    value="<?php echo esc_attr((string) $display_number); ?>"
                                >
                                    <?php if ($is_current) : ?>
                                        <strong>
                                            <?php
                                            echo esc_html(
                                                get_the_title($release_track_id)
                                            );
                                            ?>
                                        </strong>

                                        <span class="screen-reader-text">
                                            <?php
                                            echo esc_html__(
                                                'Current track',
                                                'slim-volume'
                                            );
                                            ?>
                                        </span>

                                        <span aria-hidden="true">
                                            <?php
                                            echo esc_html__(
                                                ' — Current',
                                                'slim-volume'
                                            );
                                            ?>
                                        </span>
                                    <?php elseif ($edit_url) : ?>
                                        <a href="<?php echo esc_url($edit_url); ?>">
                                            <?php
                                            echo esc_html(
                                                get_the_title($release_track_id)
                                            );
                                            ?>
                                        </a>
                                    <?php else : ?>
                                        <?php
                                        echo esc_html(
                                            get_the_title($release_track_id)
                                        );
                                        ?>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ol>
                    <?php endif; ?>

                    <?php
                    $release_track_ids = array_map(
                        static function (WP_Post $release_track): int {
                            return (int) $release_track->ID;
                        },
                        $release_tracks
                    );

                    $current_track_index = array_search(
                        $track_id,
                        $release_track_ids,
                        true
                    );

                    $can_move_up = (
                        false !== $current_track_index
                        && $current_track_index > 0
                    );

                    $can_move_down = (
                        false !== $current_track_index
                        && $current_track_index < count($release_track_ids) - 1
                    );

                    $move_up_url = '';

                    if ($can_move_up) {
                        $move_up_url = wp_nonce_url(
                            add_query_arg(
                                [
                                    'action'    => 'sv_move_track',
                                    'track_id'  => $track_id,
                                    'direction' => 'up',
                                ],
                                admin_url('admin-post.php')
                            ),
                            'sv_move_track_' . $track_id
                        );
                    }

                    $move_down_url = '';

                    if ($can_move_down) {
                        $move_down_url = wp_nonce_url(
                            add_query_arg(
                                [
                                    'action'    => 'sv_move_track',
                                    'track_id'  => $track_id,
                                    'direction' => 'down',
                                ],
                                admin_url('admin-post.php')
                            ),
                            'sv_move_track_' . $track_id
                        );
                    }
                    ?>

                    <?php if ($current_track_is_listed) : ?>
                        <div class="sv-admin-track-context__reorder">
                            <p class="sv-admin-track-context__label">
                                <?php
                                echo esc_html__(
                                    'Track Position',
                                    'slim-volume'
                                );
                                ?>
                            </p>

                            <div class="sv-admin-track-context__reorder-actions">
                                <?php if ($can_move_up) : ?>
                                    <a
                                        class="button"
                                        href="<?php echo esc_url($move_up_url); ?>"
                                    >
                                        <?php
                                        echo esc_html__(
                                            'Move Up',
                                            'slim-volume'
                                        );
                                        ?>
                                    </a>
                                <?php else : ?>
                                    <span
                                        class="button disabled"
                                        aria-disabled="true"
                                    >
                                        <?php
                                        echo esc_html__(
                                            'Move Up',
                                            'slim-volume'
                                        );
                                        ?>
                                    </span>
                                <?php endif; ?>

                                <?php if ($can_move_down) : ?>
                                    <a
                                        class="button"
                                        href="<?php echo esc_url($move_down_url); ?>"
                                    >
                                        <?php
                                        echo esc_html__(
                                            'Move Down',
                                            'slim-volume'
                                        );
                                        ?>
                                    </a>
                                <?php else : ?>
                                    <span
                                        class="button disabled"
                                        aria-disabled="true"
                                    >
                                        <?php
                                        echo esc_html__(
                                            'Move Down',
                                            'slim-volume'
                                        );
                                        ?>
                                    </span>
                                <?php endif; ?>
                            </div>

                            <p class="description">
                                <?php
                                echo esc_html__(
                                    'Moving this track updates and renumbers the complete release tracklist.',
                                    'slim-volume'
                                );
                                ?>
                            </p>
                        </div>
                    <?php else : ?>
                        <p class="description">
                            <?php
                            echo esc_html(
                                sprintf(
                                    /* translators: %d is the expected track position. */
                                    __(
                                        'When saved, this track will be appended as track %d.',
                                        'slim-volume'
                                    ),
                                    $append_position
                                )
                            );
                            ?>
                        </p>
                    <?php endif; ?>
                </div>

                <div class="sv-admin-track-context__actions">
                    <?php if ($release_edit_url) : ?>
                        <a
                            class="button button-primary button-large"
                            href="<?php echo esc_url($release_edit_url); ?>"
                        >
                            <?php
                            echo esc_html__(
                                'Back to Release',
                                'slim-volume'
                            );
                            ?>
                        </a>
                    <?php endif; ?>

                    <?php if ($track_url) : ?>
                        <a
                            class="button button-large"
                            href="<?php echo esc_url($track_url); ?>"
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            <?php
                            echo esc_html(
                                self::is_published($track_id)
                                    ? __('View Track', 'slim-volume')
                                    : __('Preview Track', 'slim-volume')
                            );
                            ?>
                        </a>
                    <?php endif; ?>

                    <?php if ($release_view_url) : ?>
                        <a
                            class="button button-large"
                            href="<?php echo esc_url($release_view_url); ?>"
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            <?php
                            echo esc_html__(
                                'View Release',
                                'slim-volume'
                            );
                            ?>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <?php
    }

    /**
     * Move the current track one position up or down within its release.
     */
    public static function handle_reorder(): void
    {
        $track_id = isset($_GET['track_id'])
            ? absint(wp_unslash($_GET['track_id']))
            : 0;

        $direction = isset($_GET['direction'])
            ? sanitize_key(wp_unslash($_GET['direction']))
            : '';

        if (
            $track_id <= 0
            || 'sv_track' !== get_post_type($track_id)
        ) {
            wp_die(
                esc_html__(
                    'The requested track could not be found.',
                    'slim-volume'
                )
            );
        }

        check_admin_referer('sv_move_track_' . $track_id);

        if (! current_user_can('edit_post', $track_id)) {
            wp_die(
                esc_html__(
                    'You are not allowed to reorder this track.',
                    'slim-volume'
                )
            );
        }

        if (! in_array($direction, ['up', 'down'], true)) {
            wp_die(
                esc_html__(
                    'The requested track movement is invalid.',
                    'slim-volume'
                )
            );
        }

        $track = get_post($track_id);

        if (! $track instanceof WP_Post) {
            wp_die(
                esc_html__(
                    'The requested track could not be loaded.',
                    'slim-volume'
                )
            );
        }

        $release_id = self::get_release_id($track);

        if (
            $release_id <= 0
            || 'sv_release' !== get_post_type($release_id)
        ) {
            wp_die(
                esc_html__(
                    'This track is not attached to a valid release.',
                    'slim-volume'
                )
            );
        }

        if (! current_user_can('edit_post', $release_id)) {
            wp_die(
                esc_html__(
                    'You are not allowed to reorder tracks on this release.',
                    'slim-volume'
                )
            );
        }

        $tracks        = self::get_tracks_for_release($release_id);
        $current_index = null;

        foreach ($tracks as $index => $release_track) {
            if ((int) $release_track->ID === $track_id) {
                $current_index = $index;
                break;
            }
        }

        if (null === $current_index) {
            wp_die(
                esc_html__(
                    'This track could not be found in the release tracklist.',
                    'slim-volume'
                )
            );
        }

        $target_index = 'up' === $direction
            ? $current_index - 1
            : $current_index + 1;

        if (
            $target_index >= 0
            && $target_index < count($tracks)
        ) {
            $current_track       = $tracks[$current_index];
            $tracks[$current_index] = $tracks[$target_index];
            $tracks[$target_index]  = $current_track;

            foreach ($tracks as $index => $release_track) {
                $release_track_id = (int) $release_track->ID;
                $track_number     = $index + 1;

                $relationship_saved =
                    \SlimVolume\Relationships\TrackReleaseRelationship
                        ::set_release_id(
                            $release_track_id,
                            $release_id
                        );

                if (! $relationship_saved) {
                    wp_die(
                        esc_html__(
                            'The track relationship could not be updated.',
                            'slim-volume'
                        )
                    );
                }

                update_post_meta(
                    $release_track_id,
                    '_sv_track_number',
                    $track_number
                );

                $post_update = wp_update_post(
                    [
                        'ID'         => $release_track_id,
                        'menu_order' => $track_number,
                    ],
                    true
                );

                if (is_wp_error($post_update)) {
                    wp_die(
                        esc_html__(
                            'The track order could not be updated.',
                            'slim-volume'
                        )
                    );
                }
            }
        }

        $redirect_url = get_edit_post_link($track_id, '');

        if (! is_string($redirect_url) || '' === $redirect_url) {
            $redirect_url = add_query_arg(
                [
                    'post'   => $track_id,
                    'action' => 'edit',
                ],
                admin_url('post.php')
            );
        }

        $redirect_url = add_query_arg(
            'sv_track_moved',
            $direction,
            $redirect_url
        );

        wp_safe_redirect($redirect_url);
        exit;
    }

    /**
     * Return all tracks attached to a release through either supported
     * relationship field.
     *
     * @return WP_Post[]
     */
    private static function get_tracks_for_release(
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

    private static function get_release_id(WP_Post $post): int
    {
        $release_id =
            \SlimVolume\Relationships\TrackReleaseRelationship
                ::get_release_id((int) $post->ID);

        if ($release_id > 0) {
            return $release_id;
        }

        /*
         * A newly created track may not have a saved relationship yet.
         * Preserve the release-prefill URL behavior for that editor state.
         */
        $requested_release_id = isset($_GET['sv_release_id'])
            ? absint(wp_unslash($_GET['sv_release_id']))
            : 0;

        if (
            $requested_release_id > 0
            && \SlimVolume\Relationships\TrackReleaseRelationship
                ::is_valid_release($requested_release_id)
        ) {
            return $requested_release_id;
        }

        return 0;
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