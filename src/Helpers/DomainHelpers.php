<?php

declare(strict_types=1);

namespace Samushi\Domion\Helpers;

use Illuminate\Support\Str;
use Samushi\Domion\Helpers\Traits\BaseHelperTrait;
use Samushi\Domion\Helpers\Traits\PathResolverTrait;

class DomainHelpers
{
    use BaseHelperTrait;
    use PathResolverTrait;

    /**
     * Get the base path for a specific domain.
     */
    public static function basePath(string $rawDomain, string $scope = ''): string
    {
        $domain = Str::studly($rawDomain);
        $path = !empty($scope) ? $scope . DIRECTORY_SEPARATOR . $domain : $domain;
        return self::path()->domain($path);
    }

    /**
     * Get base namespace for a specific domain.
     */
    public static function baseNamespace(string $rawDomain, string $scope = ''): string
    {
        $domain = Str::studly($rawDomain);
        $ns = !empty($scope) ? "App\\Domain\\{$scope}\\{$domain}" : "App\\Domain\\{$domain}";
        return $ns;
    }

    /**
     * Get all domain directories.
     */
    public static function getDomains(): array
    {
        $path = self::path()->domain();

        if (!is_dir($path)) {
            return [];
        }

        $tenancy = config('domion.tenancy', false);
        $allDomains = [];

        if ($tenancy) {
            $central = glob($path . '/Central/*', GLOB_ONLYDIR);
            $tenant = glob($path . '/Tenant/*', GLOB_ONLYDIR);
            $allDomains = array_merge($central ?: [], $tenant ?: []);
        } else {
            $allDomains = array_filter(glob($path . '/*'), 'is_dir');
        }

        return $allDomains;
    }
}