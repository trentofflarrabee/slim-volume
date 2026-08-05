<?php

declare(strict_types=1);

if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/*
 * Preserve customer-created releases, tracks, taxonomies, metadata, and
 * settings by default. Only Slim Volume's internal version bookkeeping is
 * removed during uninstall.
 */
delete_option('slim_volume_version');