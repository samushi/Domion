<?php

declare(strict_types=1);

namespace Samushi\Domion\Helpers\Traits;

use Illuminate\Support\Str;

trait BaseHelperTrait
{
    /**
     * Get the destination class path for a specific domain.
     */
    public static function basePath(string $rawDomain): string
    {
        $domain = Str::studly($rawDomain);
        return self::path()->domain($domain);
    }

    /**
     * Get the base namespace for a specific domain.
     */
    public static function baseNamespace(string $rawDomain): string
    {
        $domain = Str::studly($rawDomain);
        return "App\\Domain\\{$domain}";
    }
}
