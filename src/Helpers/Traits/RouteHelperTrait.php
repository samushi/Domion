<?php

declare(strict_types=1);

namespace Samushi\Domion\Helpers\Traits;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Samushi\Domion\Support\AbstractServiceProvider;

/**
 * Trait for loading domain routes with flexible configuration
 *
 * This trait handles automatic route discovery and loading from domains.
 * Each domain can customize its route behavior through its ServiceProvider.
 */
trait RouteHelperTrait
{
    /**
     * Cache for domain service providers
     */
    private static array $domainProviders = [];

    /**
     * Get all route files from all domains.
     *
     * @return array Array of route file paths
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
            // Skip Central/Tenant directories in multi-tenancy mode
            $baseName = basename($domainDir);
            if (in_array($baseName, ['Central', 'Tenant'])) {
                // Scan subdirectories for actual domains
                $subDomains = glob($domainDir . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR);
                foreach ($subDomains as $subDomainDir) {
                    $results = array_merge($results, self::getRouteFilesFromDomain($subDomainDir));
                }
            } else {
                $results = array_merge($results, self::getRouteFilesFromDomain($domainDir));
            }
        }

        return $results;
    }

    /**
     * Get route files from a specific domain directory
     */
    private static function getRouteFilesFromDomain(string $domainDir): array
    {
        $results = [];
        $files = ['api.php', 'web.php', 'routes.php'];

        foreach ($files as $file) {
            $path = $domainDir . DIRECTORY_SEPARATOR . $file;
            if (file_exists($path)) {
                $results[] = $path;
            }
        }

        return $results;
    }

    /**
     * Load all domain routes into the application.
     * Respects domain ServiceProvider configuration for prefix/name.
     */
    public static function loadDomainRoutes(): void
    {
        $routes = self::getAllDomainRoutes();

        foreach ($routes as $routePath) {
            self::loadRouteFile($routePath);
        }
    }

    /**
     * Load a single route file with appropriate configuration
     */
    private static function loadRouteFile(string $routePath): void
    {
        $domainDir = dirname($routePath);
        $domainName = basename($domainDir);
        $fileName = basename($routePath);

        // Check for Central/Tenant structure
        $parentDir = basename(dirname($domainDir));
        $scope = in_array($parentDir, ['Central', 'Tenant']) ? $parentDir : null;

        // Get domain configuration from its ServiceProvider
        $config = self::getDomainRouteConfig($domainName, $scope);

        // Build route prefix
        $prefix = self::resolveRoutePrefix($domainName, $config);

        // Build route name prefix
        $namePrefix = self::resolveRouteNamePrefix($domainName, $config);

        // Build middleware
        $middleware = self::resolveMiddleware($config, $fileName);

        // Create the route group
        $router = Route::prefix($prefix);

        if ($namePrefix !== '') {
            $router->as($namePrefix);
        }

        if (!empty($middleware)) {
            $router->middleware($middleware);
        }

        $router->group(fn () => require $routePath);
    }

    /**
     * Get the route configuration for a domain from its ServiceProvider
     */
    private static function getDomainRouteConfig(string $domainName, ?string $scope = null): array
    {
        $cacheKey = ($scope ? "{$scope}_{$domainName}" : $domainName);

        if (isset(self::$domainProviders[$cacheKey])) {
            return self::$domainProviders[$cacheKey];
        }

        // Build the provider class name
        $namespace = $scope
            ? "App\\Domain\\{$scope}\\{$domainName}\\Providers\\{$domainName}ServiceProvider"
            : "App\\Domain\\{$domainName}\\Providers\\{$domainName}ServiceProvider";

        // Check if provider exists and get config
        if (class_exists($namespace)) {
            try {
                $provider = app()->make($namespace);
                if ($provider instanceof AbstractServiceProvider) {
                    self::$domainProviders[$cacheKey] = $provider->getRouteConfig();
                    return self::$domainProviders[$cacheKey];
                }
            } catch (\Throwable) {
                // Provider couldn't be instantiated, use defaults
            }
        }

        // Return default config
        self::$domainProviders[$cacheKey] = [
            'prefix' => null,
            'name' => null,
            'middleware' => [],
            'requires_auth' => false,
            'guest_only' => false,
        ];

        return self::$domainProviders[$cacheKey];
    }

    /**
     * Resolve the route prefix based on configuration
     */
    private static function resolveRoutePrefix(string $domainName, array $config): string
    {
        $prefix = $config['prefix'] ?? null;

        // false = no prefix (root)
        if ($prefix === false) {
            return '';
        }

        // string = custom prefix
        if (is_string($prefix)) {
            return $prefix;
        }

        // null = default (kebab-case domain name)
        return Str::kebab($domainName);
    }

    /**
     * Resolve the route name prefix based on configuration
     */
    private static function resolveRouteNamePrefix(string $domainName, array $config): string
    {
        $name = $config['name'] ?? null;

        // false = no name prefix
        if ($name === false) {
            return '';
        }

        // string = custom name prefix
        if (is_string($name)) {
            return $name . '.';
        }

        // null = default (lowercase domain name)
        return strtolower($domainName) . '.';
    }

    /**
     * Resolve middleware based on configuration
     */
    private static function resolveMiddleware(array $config, string $fileName): array
    {
        $middleware = $config['middleware'] ?? [];

        // Add auth middleware if required
        if ($config['requires_auth'] ?? false) {
            $middleware[] = 'auth';
        }

        // Add guest middleware if guest only
        if ($config['guest_only'] ?? false) {
            $middleware[] = 'guest';
        }

        // API routes typically need api middleware
        if ($fileName === 'api.php' && !in_array('api', $middleware)) {
            array_unshift($middleware, 'api');
        }

        // Web routes typically need web middleware
        if ($fileName === 'web.php' && !in_array('web', $middleware)) {
            array_unshift($middleware, 'web');
        }

        return $middleware;
    }

    /**
     * Clear the cached domain providers
     */
    public static function clearRouteCache(): void
    {
        self::$domainProviders = [];
    }
}
