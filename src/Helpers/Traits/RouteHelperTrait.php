<?php

declare(strict_types=1);

namespace Samushi\Domion\Helpers\Traits;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

trait RouteHelperTrait
{
    /**
     * Get all api.php route files from all domains.
     */
    public static function getAllDomainRoutes(): array
    {
        $domainPath = self::path()->domain();

        if (!is_dir($domainPath)) {
            return [];
        }

        $results = [];
        $domains = glob($domainPath . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR);

        foreach ($domains as $domainDir) {
            $apiFile = $domainDir . DIRECTORY_SEPARATOR . 'api.php';
            if (file_exists($apiFile)) {
                $results[] = $apiFile;
            }
        }

        return $results;
    }

    /**
     * Load all domain routes into the application with domain name prefix.
     */
    public static function loadDomainRoutes(): void
    {
        $routes = self::getAllDomainRoutes();
        
        foreach ($routes as $routePath) {
            $domainName = basename(dirname($routePath));
            $prefix = Str::kebab($domainName);

            $router = Route::prefix($prefix);
            
            // Special case for Auth domain to avoid "Route [login] not defined" error
            if (strtolower($domainName) !== 'auth') {
                $router->as(strtolower($domainName) . ':');
            }

            $router->group(fn () => require $routePath);
        }
    }
}
