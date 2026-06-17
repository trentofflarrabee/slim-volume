<?php
/**
 * Track admin release filter.
 *
 * @package SlimVolume
 */

declare(strict_types=1);

namespace SlimVolume\Admin;

use WP_Post;
use WP_Query;

if (! defined('ABSPATH')) {
    exit;
}

final class TrackReleaseFilter
{
    private const FILTER_KEY = 'sv_release_filter';

    public static function register(): void
    {
        add_action('restrict_manage_posts', [self::class, 'render_filter'], 10, 2);
        add_action('pre_get_posts', [self::class, 'filter_query']);
    }

    public static function render_filter(string $post_type = '', string $which = 'top'): void
    {
        if ('sv_track' !== $post_type || 'top' !== $which) {
            return;
        }

        $selected_release_id = self::get_selected_release_id();
        $releases            = self::get_releases();

        if (! $releases) {
            return;
        }

        ?>
        <label class="screen-reader-text" for="sv-release-filter">
            <?php echo esc_html__('Filter tracks by release', 'slim-volume'); ?>
        </label>

        <select id="sv-release-filter" name="<?php echo esc_attr(self::FILTER_KEY); ?>">
            <option value="0">
                <?php echo esc_html__('All releases', 'slim-volume'); ?>
            </option>

            <?php foreach ($releases as $release) : ?>
                <?php
                $release_id    = (int) $release->ID;
                $release_title = get_the_title($release_id);

                if (! $release_title) {
                    $release_title = __('Untitled release', 'slim-volume');
                }

                if ('publish' !== $release->post_status) {
                    $status_object = get_post_status_object($release->post_status);
                    $status_label  = $status_object ? $status_object->label : $release->post_status;

                    $release_title = sprintf(
                        '%s — %s',
                        $release_title,
                        $status_label
                    );
                }
                ?>

                <option
                    value="<?php echo esc_attr((string) $release_id); ?>"
                    <?php selected($selected_release_id, $release_id); ?>
                >
                    <?php echo esc_html($release_title); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php
    }

    public static function filter_query(WP_Query $query): void
    {
        if (! is_admin() || ! $query->is_main_query()) {
            return;
        }

        if ('sv_track' !== self::get_current_post_type($query)) {
            return;
        }

        $release_id = self::get_selected_release_id();

        if ($release_id <= 0 || ! self::is_valid_release($release_id)) {
            return;
        }

        $track_ids = self::get_track_ids_for_release($release_id);

        $query->set('post__in', $track_ids ?: [0]);
    }

    /**
     * @return WP_Post[]
     */
    private static function get_releases(): array
    {
        return get_posts(
            [
                'post_type'      => 'sv_release',
                'post_status'    => ['publish', 'draft', 'pending', 'private', 'future'],
                'posts_per_page' => -1,
                'orderby'        => [
                    'title' => 'ASC',
                ],
                'order'          => 'ASC',
            ]
        );
    }

    /**
     * @return int[]
     */
    private static function get_track_ids_for_release(int $release_id): array
    {
        $meta_track_ids = get_posts(
            [
                'post_type'      => 'sv_track',
                'post_status'    => 'any',
                'posts_per_page' => -1,
                'fields'         => 'ids',
                'no_found_rows'  => true,
                'meta_key'       => '_sv_release_id',
                'meta_value'     => (string) $release_id,
            ]
        );

        $parent_track_ids = get_posts(
            [
                'post_type'      => 'sv_track',
                'post_status'    => 'any',
                'posts_per_page' => -1,
                'fields'         => 'ids',
                'no_found_rows'  => true,
                'post_parent'    => $release_id,
            ]
        );

        $track_ids = array_map(
            'absint',
            array_merge($meta_track_ids, $parent_track_ids)
        );

        $track_ids = array_values(array_unique(array_filter($track_ids)));

        sort($track_ids);

        return $track_ids;
    }

    private static function get_selected_release_id(): int
    {
        if (! array_key_exists(self::FILTER_KEY, $_GET)) {
            return 0;
        }

        return absint(wp_unslash($_GET[self::FILTER_KEY]));
    }

    private static function get_current_post_type(WP_Query $query): string
    {
        $post_type = $query->get('post_type');

        if (is_array($post_type)) {
            return (string) reset($post_type);
        }

        if (is_string($post_type) && $post_type) {
            return $post_type;
        }

        if (isset($_GET['post_type'])) {
            return sanitize_key(wp_unslash($_GET['post_type']));
        }

        return 'post';
    }

    private static function is_valid_release(int $release_id): bool
    {
        $release = get_post($release_id);

        return $release instanceof WP_Post && 'sv_release' === $release->post_type;
    }
}