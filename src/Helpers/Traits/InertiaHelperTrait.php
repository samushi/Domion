<?php

declare(strict_types=1);

namespace Samushi\Domion\Helpers\Traits;

trait InertiaHelperTrait
{
    /**
     * Get all Inertia page paths for modern frontend frameworks.
     * This allows using 'Domain::Page' syntax in controllers.
     * 
     * @return array ['Domain::Page' => 'absolute/path']
     */
    public static function getInertiaPages(): array
    {
        $domainPath = self::path()->domain();

        if (!is_dir($domainPath)) {
            return [];
        }

        $pages = [];
        $domains = glob($domainPath . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR);

        foreach ($domains as $domainDir) {
            $domainName = basename($domainDir);
            $pagesDir = $domainDir . DIRECTORY_SEPARATOR . 'Frontend' . DIRECTORY_SEPARATOR . 'Pages';
            
            if (is_dir($pagesDir)) {
                $files = self::globRecursive($pagesDir . DIRECTORY_SEPARATOR . '*.{js,jsx,ts,tsx,vue}', GLOB_BRACE);
                foreach ($files as $file) {
                    $relativePath = str_replace($pagesDir . DIRECTORY_SEPARATOR, '', $file);
                    // Remove extension
                    $cleanPath = preg_replace('/\.(js|jsx|ts|tsx|vue)$/', '', $relativePath);
                    // Create Alias (e.g., Auth::Login)
                    $pageName = $domainName . '::' . str_replace(DIRECTORY_SEPARATOR, '/', $cleanPath);
                    $pages[$pageName] = $file;
                }
            }
        }

        return $pages;
    }

    protected static function globRecursive(string $pattern, int $flags = 0): array
    {
        $files = glob($pattern, $flags);
        foreach (glob(dirname($pattern) . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR | GLOB_NOSORT) as $dir) {
            $files = array_merge($files, self::globRecursive($dir . DIRECTORY_SEPARATOR . basename($pattern), $flags));
        }
        return $files;
    }
}
