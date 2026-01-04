<?php

declare(strict_types=1);

namespace Samushi\Domion\Support;

use Illuminate\Support\Facades\File;

class PathHelper
{
    /**
     * Get the relative physical path for the 'App\' namespace.
     * Always returns a clean relative path (e.g. 'app' or 'app/App').
     */
    public static function getAppPath(): string
    {
        $composerPath = base_path('composer.json');
        
        if (File::exists($composerPath)) {
            $composer = json_decode(File::get($composerPath), true);
            $psr4 = $composer['autoload']['psr-4'] ?? [];
            
            foreach ($psr4 as $namespace => $path) {
                if ($namespace === 'App\\') {
                    $relativePath = is_array($path) ? $path[0] : $path;
                    
                    // CRITICAL: Strip any absolute path prefix if it exists
                    // We only want the part relative to the project root
                    $relativePath = str_replace(base_path() . '/', '', $relativePath);
                    $relativePath = ltrim($relativePath, '/');
                    
                    return rtrim($relativePath, '/');
                }
            }
        }

        return 'app';
    }

    /**
     * Resolve a path inside the App namespace storage.
     */
    public static function resolveAppPath(string $subPath = ''): string
    {
        $appPath = self::getAppPath();
        return base_path($appPath . ($subPath ? '/' . ltrim($subPath, '/') : ''));
    }

    /**
     * Get the root folder of the app (usually 'app')
     */
    public static function getAppRoot(): string
    {
        $appPath = self::getAppPath();
        $parts = explode('/', $appPath);
        return $parts[0] ?: 'app';
    }
}
