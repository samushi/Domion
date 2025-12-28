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
        $domains = self::getDomains();

        foreach ($domains as $domainDir) {
            $viewDir = $domainDir . DIRECTORY_SEPARATOR . 'Resources' . DIRECTORY_SEPARATOR . 'views';
            $langDir = $domainDir . DIRECTORY_SEPARATOR . 'Resources' . DIRECTORY_SEPARATOR . 'Lang';
            $domainName = strtolower(basename($domainDir));

            if (is_dir($viewDir)) {
                $results['views'][$domainName] = $viewDir;
            }

            if (is_dir($langDir)) {
                $results['lang'][$domainName] = $langDir;
            }
        }

        // Scan Support directory for shared resources
        $supportPath = self::path()->support();
        if (is_dir($supportPath)) {
            $supportViewDir = $supportPath . DIRECTORY_SEPARATOR . 'Resources' . DIRECTORY_SEPARATOR . 'views';
            $supportLangDir = $supportPath . DIRECTORY_SEPARATOR . 'Resources' . DIRECTORY_SEPARATOR . 'Lang';

            if (is_dir($supportViewDir)) {
                $results['views']['support'] = $supportViewDir;
            }

            if (is_dir($supportLangDir)) {
                $results['lang']['support'] = $supportLangDir;
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
