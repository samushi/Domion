<?php

declare(strict_types=1);

namespace Samushi\Domion\Support;

use Illuminate\Support\Facades\File;

class PathHelper
{
    /**
     * Get the physical path for the 'App\' namespace from composer.json.
     */
    public static function getAppPath(): string
    {
        $composerPath = base_path('composer.json');
        
        if (File::exists($composerPath)) {
            $composer = json_decode(File::get($composerPath), true);
            $psr4 = $composer['autoload']['psr-4'] ?? [];
            
            foreach ($psr4 as $namespace => $path) {
                if ($namespace === 'App\\') {
                    return rtrim($path, '/');
                }
            }
        }

        // Fallback for standard Laravel
        return 'app';
    }

    /**
     * Get the full path to a class within the App namespace.
     */
    public static function resolveAppPath(string $subPath = ''): string
    {
        $appPath = self::getAppPath();
        return base_path($appPath . ($subPath ? '/' . ltrim($subPath, '/') : ''));
    }
}
