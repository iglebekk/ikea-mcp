<?php

namespace App\Support;

/**
 * Composer hook (post-autoload-dump) that guarantees the storage skeleton
 * exists before any artisan command runs. Zero-downtime deployments symlink
 * storage/ to a shared directory that may have been created from an empty
 * repository; without these directories, view:cache and log writing fail.
 */
class ComposerScripts
{
    private const DIRECTORIES = [
        'storage/app/private',
        'storage/app/public',
        'storage/framework/cache/data',
        'storage/framework/sessions',
        'storage/framework/testing',
        'storage/framework/views',
        'storage/logs',
        'bootstrap/cache',
    ];

    public static function ensureStorageDirectories(): void
    {
        $root = dirname(__DIR__, 2);

        foreach (self::DIRECTORIES as $directory) {
            $path = "{$root}/{$directory}";

            if (! is_dir($path)) {
                mkdir($path, 0775, true);
            }
        }
    }
}
