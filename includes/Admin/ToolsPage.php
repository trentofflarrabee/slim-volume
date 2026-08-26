<?php

declare(strict_types=1);

namespace SlimVolume\Admin;

use SlimVolume\Export\DiscographyExporter;
use SlimVolume\Export\ExportArtifact;
use SlimVolume\Export\ExportException;
use SlimVolume\PostTypes;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Administrator-facing Slim Volume tools.
 */
final class ToolsPage
{
    public const MENU_SLUG = 'slim-volume-tools';
    public const EXPORT_ACTION = 'sv_export_discography';

    private const NONCE_ACTION = 'sv_export_discography';

    public static function register(): void
    {
        add_action(
            'admin_menu',
            [self::class, 'register_page']
        );

        add_action(
            'admin_post_' . self::EXPORT_ACTION,
            [self::class, 'handle_export']
        );
    }

    public static function register_page(): void
    {
        add_submenu_page(
            'edit.php?post_type=' . PostTypes::RELEASE,
            __('Slim Volume Tools', 'slim-volume'),
            __('Tools', 'slim-volume'),
            'manage_options',
            self::MENU_SLUG,
            [self::class, 'render_page']
        );
    }

    public static function render_page(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(
                esc_html__(
                    'You do not have permission to access this page.',
                    'slim-volume'
                ),
                '',
                ['response' => 403]
            );
        }

        ?>
        <div class="wrap">
            <h1>
                <?php echo esc_html__('Slim Volume Tools', 'slim-volume'); ?>
            </h1>

            <h2>
                <?php echo esc_html__('Export Discography Data', 'slim-volume'); ?>
            </h2>

            <p>
                <?php
                echo esc_html__(
                    'Export the music information you have entered into Slim Volume for backup, migration, or use in another system.',
                    'slim-volume'
                );
                ?>
            </p>

            <p>
                <?php
                echo esc_html__(
                    'The export includes artists/projects, releases, tracks, lyrics, timed lyrics, credits, relationships, and entered destination links. Audio and artwork files are not included, but references to those files are preserved when available.',
                    'slim-volume'
                );
                ?>
            </p>

            <div class="notice notice-warning inline">
                <p>
                    <strong>
                        <?php
                        echo esc_html__(
                            'Keep your export file private.',
                            'slim-volume'
                        );
                        ?>
                    </strong>
                    <?php
                    echo esc_html__(
                        'It may contain unpublished releases, tracks, lyrics, credits, links, and other private catalog information.',
                        'slim-volume'
                    );
                    ?>
                </p>
            </div>

            <form
                method="post"
                action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
            >
                <input
                    type="hidden"
                    name="action"
                    value="<?php echo esc_attr(self::EXPORT_ACTION); ?>"
                >

                <?php wp_nonce_field(self::NONCE_ACTION); ?>

                <?php
                submit_button(
                    __('Export Discography Data', 'slim-volume'),
                    'primary',
                    'submit',
                    false
                );
                ?>
            </form>
        </div>
        <?php
    }

    public static function handle_export(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(
                esc_html__(
                    'You do not have permission to export Slim Volume discography data.',
                    'slim-volume'
                ),
                '',
                ['response' => 403]
            );
        }

        $request_method = isset($_SERVER['REQUEST_METHOD'])
            ? strtoupper(
                sanitize_text_field(
                    wp_unslash($_SERVER['REQUEST_METHOD'])
                )
            )
            : '';

        if ($request_method !== 'POST') {
            wp_die(
                esc_html__(
                    'Slim Volume discography exports must be requested with a POST action.',
                    'slim-volume'
                ),
                '',
                ['response' => 405]
            );
        }

        check_admin_referer(self::NONCE_ACTION);

        $artifact = null;

        try {
            /*
             * Generate the complete, valid artifact before any successful
             * download response headers are sent.
             */
            $artifact = (new DiscographyExporter())
                ->generate_artifact();

            /*
             * Remove accidental buffered output from other admin/plugin code
             * before starting the JSON download response.
             */
            self::discard_output_buffers();

            if (headers_sent()) {
                throw new ExportException(
                    'Slim Volume could not start the export download because response output had already begun.'
                );
            }

            ignore_user_abort(true);

            status_header(200);
            nocache_headers();

            header(
                'Content-Type: application/json; charset=utf-8'
            );
            header(
                'Content-Disposition: attachment; filename="'
                . self::download_filename()
                . '"'
            );
            header('X-Content-Type-Options: nosniff');

            try {
                $artifact->stream();
            } finally {
                $artifact->delete();
            }

            exit;
        } catch (\Throwable $exception) {
            if ($artifact instanceof ExportArtifact) {
                $artifact->delete();
            }

            self::log_failure($exception);

            /*
             * Once download headers/data have begun there is no safe way to
             * append an administrator-facing error without corrupting JSON.
             */
            if (headers_sent()) {
                exit;
            }

            wp_die(
                esc_html__(
                    'Slim Volume could not generate a complete discography export. No download was started. Please try again. If the problem continues, check the WordPress debug log or contact your site administrator.',
                    'slim-volume'
                ),
                esc_html__(
                    'Discography Export Failed',
                    'slim-volume'
                ),
                [
                    'response' => 500,
                    'back_link' => true,
                ]
            );
        }
    }

    private static function download_filename(): string
    {
        return 'slim-volume-discography-'
            . gmdate('Y-m-d')
            . '.json';
    }

    private static function discard_output_buffers(): void
    {
        while (ob_get_level() > 0) {
            if (! @ob_end_clean()) {
                break;
            }
        }
    }

    private static function log_failure(
        \Throwable $exception
    ): void {
        if (! defined('WP_DEBUG') || ! WP_DEBUG) {
            return;
        }

        error_log(
            sprintf(
                'Slim Volume discography export failed (%s): %s',
                get_class($exception),
                $exception->getMessage()
            )
        );
    }
}
