<?php

declare(strict_types=1);

namespace Samushi\Domion\Support;

use Illuminate\Support\Facades\File;

class PathHelper
{
    /**
     * Get the relative path to the App namespace root.
     * Guaranteed to return a relative path like 'app' or 'app/App'.
     */
    public static function getAppPath(): string
    {
        // 1. Check most likely DDD structure first
        if (File::exists(base_path('app/App/Providers/AppServiceProvider.php'))) {
            return 'app/App';
        }

        // 2. Check standard Laravel
        if (File::exists(base_path('app/Providers/AppServiceProvider.php'))) {
            return 'app';
        }

        // 3. Fallback to reading composer.json properly
        $composerPath = base_path('composer.json');
        if (File::exists($composerPath)) {
            $composer = json_decode(File::get($composerPath), true);
            $path = $composer['autoload']['psr-4']['App\\'] ?? 'app/';
            return trim(is_array($path) ? $path[0] : $path, '/');
        }

        return 'app';
    }

    public static function resolveAppPath(string $subPath = ''): string
    {
        return base_path(self::getAppPath() . ($subPath ? '/' . ltrim($subPath, '/') : ''));
    }
}
