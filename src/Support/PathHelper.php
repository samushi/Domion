<?php

declare(strict_types=1);

namespace Samushi\Domion\Support;

use Illuminate\Support\Facades\File;

class PathHelper
{
    /**
     * Get the relative path to the App namespace root (for PHP classes).
     * Returns 'app/App' if that structure exists, otherwise 'app'.
     */
    public static function getAppPath(): string
    {
        // Check for DDD structure first (app/App/Providers)
        if (File::exists(base_path('app/App/Providers/AppServiceProvider.php'))) {
            return 'app/App';
        }

        // Standard Laravel structure
        if (File::exists(base_path('app/Providers/AppServiceProvider.php'))) {
            return 'app';
        }

        // Fallback - read from composer.json
        $composerPath = base_path('composer.json');
        if (File::exists($composerPath)) {
            $composer = json_decode(File::get($composerPath), true);
            $path = $composer['autoload']['psr-4']['App\\'] ?? 'app/';
            $path = is_array($path) ? $path[0] : $path;
            return trim($path, '/');
        }

        return 'app';
    }

    /**
     * Get the base folder for the project structure (always 'app').
     * Frontend and Domain assets go here, regardless of PHP namespace mapping.
     */
    public static function getProjectAppRoot(): string
    {
        return 'app';
    }

    /**
     * Resolve an absolute path inside the App namespace (for PHP classes).
     */
    public static function resolveAppPath(string $subPath = ''): string
    {
        return base_path(self::getAppPath() . ($subPath ? '/' . ltrim($subPath, '/') : ''));
    }

    /**
     * Resolve an absolute path inside the project app folder (for Frontend/Domain).
     */
    public static function resolveProjectPath(string $subPath = ''): string
    {
        return base_path(self::getProjectAppRoot() . ($subPath ? '/' . ltrim($subPath, '/') : ''));
    }
}
