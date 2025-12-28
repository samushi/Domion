<?php

declare(strict_types=1);

namespace Samushi\Domion\Helpers\Traits;

use Illuminate\Support\Facades\View;

trait BladeHelperTrait
{
    /**
     * Get all Resource directories from domains.
     * @return array [
     *   'views' => ['Domain' => 'path'],
     *   'lang' => ['Domain' => 'path']
     * ]
     */
    public static function getAllDomainResources(): array
    {
        $domainPath = self::path()->domain();

        if (!is_dir($domainPath)) {
            return ['views' => [], 'lang' => []];
        }

        $results = ['views' => [], 'lang' => []];
        $domains = glob($domainPath . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR);

        foreach ($domains as $domainPath) {
            $viewDir = $domainPath . DIRECTORY_SEPARATOR . 'Resources' . DIRECTORY_SEPARATOR . 'views';
            $langDir = $domainPath . DIRECTORY_SEPARATOR . 'Resources' . DIRECTORY_SEPARATOR . 'Lang';
            $domainName = strtolower(basename($domainPath));

            if (is_dir($viewDir)) {
                $results['views'][$domainName] = $viewDir;
            }

            if (is_dir($langDir)) {
                $results['lang'][$domainName] = $langDir;
            }
        }

        return $results;
    }

    /**
     * Load all domain resources (views and lang) into the application.
     */
    public static function loadAllResources(): void
    {
        $resources = self::getAllDomainResources();
        
        foreach ($resources['views'] as $namespace => $path) {
            View::addNamespace($namespace, $path);
        }

        foreach ($resources['lang'] as $namespace => $path) {
            app('translator')->addNamespace($namespace, $path);
        }
    }
}
