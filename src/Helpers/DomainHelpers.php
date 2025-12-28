<?php

declare(strict_types=1);

namespace Samushi\Domion\Helpers;

use Illuminate\Support\Str;
use Samushi\Domion\Helpers\Traits\BaseHelperTrait;
use Samushi\Domion\Helpers\Traits\BladeHelperTrait;
use Samushi\Domion\Helpers\Traits\MigrationHelperTrait;
use Samushi\Domion\Helpers\Traits\PathResolverTrait;
use Samushi\Domion\Helpers\Traits\RouteHelperTrait;
use Samushi\Domion\Helpers\Traits\SupportHelperTrait;
use Samushi\Domion\Helpers\Traits\InertiaHelperTrait;
use Samushi\Domion\Helpers\Traits\LivewireHelperTrait;

class DomainHelpers
{
    use BaseHelperTrait;
    use BladeHelperTrait;
    use MigrationHelperTrait;
    use PathResolverTrait;
    use RouteHelperTrait;
    use SupportHelperTrait;
    use InertiaHelperTrait;
    use LivewireHelperTrait;

    /**
     * Get the base path for a specific domain, optionally within a scope.
     */
    public static function basePath(string $rawDomain, string $scope = ''): string
    {
        $domain = Str::studly($rawDomain);
        $path = !empty($scope) ? $scope . DIRECTORY_SEPARATOR . $domain : $domain;
        return self::path()->domain($path);
    }

    /**
     * Get the base namespace for a specific domain, optionally within a scope.
     */
    public static function baseNamespace(string $rawDomain, string $scope = ''): string
    {
        $domain = Str::studly($rawDomain);
        $ns = !empty($scope) ? "App\\Domain\\{$scope}\\{$domain}" : "App\\Domain\\{$domain}";
        return $ns;
    }

    /**
     * Get all domain directories.
     * @return array Array of absolute directory paths
     */
    public static function getDomains(): array
    {
        $path = self::domain();
        
        if (!is_dir($path)) {
            return [];
        }

        $tenancy = config('domion.tenancy', false);
        
        if ($tenancy) {
            $central = glob($path . '/Central/*', GLOB_ONLYDIR);
            $tenant = glob($path . '/Tenant/*', GLOB_ONLYDIR);
            return array_merge($central ?: [], $tenant ?: []);
        }

        return array_filter(glob($path . '/*'), 'is_dir');
    }
}
