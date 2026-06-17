<?php
/**
 * Prefill release relationships when creating tracks from a release.
 *
 * @package SlimVolume
 */

declare(strict_types=1);

namespace SlimVolume\Admin;

use WP_Post;

if (! defined('ABSPATH')) {
    exit;
}

final class TrackReleasePrefill
{
    public static function register(): void
    {
        add_filter('default_post_metadata', [self::class, 'default_release_meta'], 10, 5);
        add_action('admin_footer-post-new.php', [self::class, 'render_prefill_script']);
        add_action('save_post_sv_track', [self::class, 'save_release_relationship'], 20, 3);
    }

    public static function default_release_meta(
        mixed $value,
        int $object_id,
        string $meta_key,
        bool $single,
        string $meta_type
    ): mixed {
        if ('post' !== $meta_type || '_sv_release_id' !== $meta_key || ! $single) {
            return $value;
        }

        $post = get_post($object_id);

        if (! $post instanceof WP_Post || 'sv_track' !== $post->post_type || 'auto-draft' !== $post->post_status) {
            return $value;
        }

        $release_id = self::get_initial_release_id_from_request();

        return $release_id > 0 ? (string) $release_id : $value;
    }

    public static function render_prefill_script(): void
    {
        $release_id = self::get_initial_release_id_from_request();

        if ($release_id <= 0) {
            return;
        }

        ?>
        <input
            type="hidden"
            name="_sv_initial_release_id"
            value="<?php echo esc_attr((string) $release_id); ?>"
        >

        <script>
            document.addEventListener("DOMContentLoaded", function () {
                var releaseId = "<?php echo esc_js((string) $release_id); ?>";
                var selectors = [
                    'select[name="_sv_release_id"]',
                    'select[name="sv_release_id"]',
                    '[data-sv-release-select]'
                ];

                selectors.some(function (selector) {
                    var field = document.querySelector(selector);

                    if (!field) {
                        return false;
                    }

                    field.value = releaseId;
                    field.dispatchEvent(new Event("change", { bubbles: true }));

                    return true;
                });
            });
        </script>
        <?php
    }

    public static function save_release_relationship(int $post_id, WP_Post $post, bool $update): void
    {
        unset($update);

        if ('sv_track' !== $post->post_type) {
            return;
        }

        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
            return;
        }

        if (! current_user_can('edit_post', $post_id)) {
            return;
        }

        $release_id = self::get_release_id_from_post();

        if (null === $release_id) {
            $release_id = self::get_int_from_post('_sv_initial_release_id');
        }

        if ($release_id <= 0 || ! self::is_valid_release($release_id)) {
            return;
        }

        update_post_meta($post_id, '_sv_release_id', $release_id);

        if ((int) $post->post_parent !== $release_id) {
            remove_action('save_post_sv_track', [self::class, 'save_release_relationship'], 20);

            wp_update_post(
                [
                    'ID'          => $post_id,
                    'post_parent' => $release_id,
                ]
            );

            add_action('save_post_sv_track', [self::class, 'save_release_relationship'], 20, 3);
        }
    }

    private static function get_release_id_from_post(): ?int
    {
        $field_names = [
            '_sv_release_id',
            'sv_release_id',
        ];

        foreach ($field_names as $field_name) {
            if (array_key_exists($field_name, $_POST)) {
                return self::get_int_from_post($field_name);
            }
        }

        return null;
    }

    private static function get_int_from_post(string $key): int
    {
        if (! array_key_exists($key, $_POST)) {
            return 0;
        }

        return absint(wp_unslash($_POST[$key]));
    }

    private static function get_initial_release_id_from_request(): int
    {
        $post_type = isset($_GET['post_type'])
            ? sanitize_key(wp_unslash($_GET['post_type']))
            : 'post';

        if ('sv_track' !== $post_type) {
            return 0;
        }

        $release_id = isset($_GET['sv_release_id'])
            ? absint(wp_unslash($_GET['sv_release_id']))
            : 0;

        if ($release_id <= 0 || ! self::is_valid_release($release_id)) {
            return 0;
        }

        if (! current_user_can('edit_post', $release_id)) {
            return 0;
        }

        return $release_id;
    }

    private static function is_valid_release(int $release_id): bool
    {
        $release = get_post($release_id);

        return $release instanceof WP_Post && 'sv_release' === $release->post_type;
    }
}