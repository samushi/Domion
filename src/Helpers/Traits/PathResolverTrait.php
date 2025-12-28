<?php

declare(strict_types=1);

namespace Samushi\Domion\Helpers\Traits;

use Samushi\Domion\Helpers\PathResolver;

trait PathResolverTrait
{
    /**
     * Cached PathResolver instance for fluent API access.
     */
    protected static ?PathResolver $pathResolverInstance = null;

    /**
     * Get PathResolver instance for fluent path operations.
     */
    public static function path(): PathResolver
    {
        if (self::$pathResolverInstance === null) {
            self::$pathResolverInstance = new PathResolver();
        }

        return self::$pathResolverInstance;
    }

    /**
     * Get PathResolver instance.
     */
    public static function getPath(): PathResolver
    {
        return self::path();
    }

    /**
     * Reset cached PathResolver instance.
     */
    public static function clearPathCache(): void
    {
        self::$pathResolverInstance = null;
    }

    /**
     * Direct static shortcut for domain paths.
     */
    public static function domain(string $path = ''): string
    {
        return self::path()->domain($path);
    }

    /**
     * Direct static shortcut for support directory paths.
     */
    public static function support(string $path = ''): string
    {
        return self::path()->support($path);
    }
}
