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

    public static function save_release_relationship(
        int $post_id,
        WP_Post $post,
        bool $update
    ): void {
        unset($update);

        if ('sv_track' !== $post->post_type) {
            return;
        }

        if (
            wp_is_post_autosave($post_id)
            || wp_is_post_revision($post_id)
        ) {
            return;
        }

        if (! current_user_can('edit_post', $post_id)) {
            return;
        }

        /*
         * Resolve the stored relationship before applying the submitted
         * release selection. This applies the canonical-meta/parent-fallback
         * policy consistently when moving or unassigning legacy tracks.
         */
        $previous_release_id =
            \SlimVolume\Relationships\TrackReleaseRelationship
                ::get_release_id($post_id);
        $release_id = self::get_release_id_from_post();

        if (null === $release_id) {
            $release_id = self::get_int_from_post(
                '_sv_initial_release_id'
            );

            /*
             * A save request without either relationship field should not
             * modify an existing track relationship.
             */
            if ($release_id <= 0) {
                return;
            }
        }

        /*
         * An explicitly submitted zero means the editor selected
         * "Select a release". Clear both supported relationship fields and
         * close the numbering gap in the former release.
         */
        if ($release_id <= 0) {
            $relationship_cleared =
                \SlimVolume\Relationships\TrackReleaseRelationship
                    ::set_release_id(
                        $post_id,
                        0
                    );

            if (! $relationship_cleared) {
                return;
            }

            update_post_meta(
                $post_id,
                '_sv_track_number',
                0
            );

            if (
                $previous_release_id > 0
                && self::is_valid_release($previous_release_id)
            ) {
                self::renumber_release(
                    $previous_release_id,
                    $post_id
                );
            }

            return;
        }

        if (! self::is_valid_release($release_id)) {
            return;
        }

        $relationship_saved =
            \SlimVolume\Relationships\TrackReleaseRelationship
                ::set_release_id(
                    $post_id,
                    $release_id
                );

        if (! $relationship_saved) {
            return;
        }

        $current_track_number = (int) get_post_meta(
            $post_id,
            '_sv_track_number',
            true
        );

        $release_changed = (
            $previous_release_id !== $release_id
        );

        $current_track_is_listed = self::release_contains_track(
            $release_id,
            $post_id
        );

        /*
         * A new track, a track moved to another release, or a track without
         * an established position should be appended to the target release.
         */
        $should_append = (
            $release_changed
            || $current_track_number <= 0
            || ! $current_track_is_listed
        );

        if (! $should_append) {
            /*
             * Treat the saved track number as the requested position.
             *
             * Remove the current track from the ordered list, insert it at
             * the requested position, and then rewrite every position. This
             * prevents duplicate track numbers after a manual edit.
             */
            $ordered_track_ids = [];

            foreach (
                self::get_tracks_for_release($release_id)
                as $release_track
            ) {
                $release_track_id = (int) $release_track->ID;

                if ($release_track_id === $post_id) {
                    continue;
                }

                $ordered_track_ids[] = $release_track_id;
            }

            $maximum_position = count($ordered_track_ids) + 1;

            $requested_position = min(
                max(1, $current_track_number),
                $maximum_position
            );

            array_splice(
                $ordered_track_ids,
                $requested_position - 1,
                0,
                [$post_id]
            );

            self::save_release_order(
                $release_id,
                $ordered_track_ids
            );

            return;
        }

        /*
         * Remove the track from its former release and close any numbering
         * gap left behind.
         */
        if (
            $previous_release_id > 0
            && $previous_release_id !== $release_id
            && self::is_valid_release($previous_release_id)
        ) {
            self::renumber_release(
                $previous_release_id,
                $post_id
            );
        }

        /*
         * Add the current track to the end of the selected release and
         * renumber the complete target tracklist.
         */
        self::append_track_to_release(
            $post_id,
            $release_id
        );
    }

    private static function release_contains_track(
        int $release_id,
        int $track_id
    ): bool {
        foreach (
            self::get_tracks_for_release($release_id)
            as $release_track
        ) {
            if ((int) $release_track->ID === $track_id) {
                return true;
            }
        }

        return false;
    }

    private static function append_track_to_release(
        int $track_id,
        int $release_id
    ): void {
        $ordered_track_ids = [];

        foreach (
            self::get_tracks_for_release($release_id)
            as $release_track
        ) {
            $release_track_id = (int) $release_track->ID;

            if ($release_track_id === $track_id) {
                continue;
            }

            $ordered_track_ids[] = $release_track_id;
        }

        $ordered_track_ids[] = $track_id;

        self::save_release_order(
            $release_id,
            $ordered_track_ids
        );
    }

    private static function renumber_release(
        int $release_id,
        int $excluded_track_id = 0
    ): void {
        $ordered_track_ids = [];

        foreach (
            self::get_tracks_for_release($release_id)
            as $release_track
        ) {
            $release_track_id = (int) $release_track->ID;

            if (
                $excluded_track_id > 0
                && $release_track_id === $excluded_track_id
            ) {
                continue;
            }

            $ordered_track_ids[] = $release_track_id;
        }

        self::save_release_order(
            $release_id,
            $ordered_track_ids
        );
    }

    /**
     * Return tracks attached through either supported relationship field.
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

    /**
     * @param int[] $ordered_track_ids
     */
    private static function save_release_order(
        int $release_id,
        array $ordered_track_ids
    ): void {
        /*
         * wp_update_post() fires the track save hooks again. Temporarily
         * suspend the handlers that read the current editor's POST payload so
         * sibling tracks cannot accidentally receive the current track's
         * metadata.
         */
        remove_action(
            'save_post_sv_track',
            [TrackMetaBoxes::class, 'save']
        );

        remove_action(
            'save_post_sv_track',
            [self::class, 'save_release_relationship'],
            20
        );

        remove_action(
            'save_post_sv_track',
            [\SlimVolume\TimedLyrics::class, 'reconcile'],
            20
        );

        try {
            foreach ($ordered_track_ids as $index => $track_id) {
                $track_id = absint($track_id);

                if (
                    $track_id <= 0
                    || 'sv_track' !== get_post_type($track_id)
                ) {
                    continue;
                }

                $track_number = $index + 1;

                $relationship_saved =
                    \SlimVolume\Relationships\TrackReleaseRelationship
                        ::set_release_id(
                            $track_id,
                            $release_id
                        );

                if (! $relationship_saved) {
                    continue;
                }

                update_post_meta(
                    $track_id,
                    '_sv_track_number',
                    $track_number
                );

                self::update_track_menu_order(
                    $track_id,
                    $track_number
                );
            }
        } finally {
            add_action(
                'save_post_sv_track',
                [TrackMetaBoxes::class, 'save']
            );

            add_action(
                'save_post_sv_track',
                [self::class, 'save_release_relationship'],
                20,
                3
            );

            add_action(
                'save_post_sv_track',
                [\SlimVolume\TimedLyrics::class, 'reconcile'],
                20
            );
        }
    }

    private static function update_track_menu_order(
        int $track_id,
        int $track_number
    ): void {
        $track = get_post($track_id);

        if (! $track instanceof WP_Post) {
            return;
        }

        if ((int) $track->menu_order === $track_number) {
            return;
        }

        wp_update_post(
            [
                'ID'         => $track_id,
                'menu_order' => $track_number,
            ]
        );
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