<?php

declare(strict_types=1);

namespace Samushi\Domion\Helpers\Traits;

use Illuminate\Support\Str;

trait MigrationHelperTrait
{
    /**
     * Get the migration path for a specific domain.
     */
    public static function migrationPath(string $rawDomain): string
    {
        return self::basePath($rawDomain) . '/Database/Migrations';
    }

    /**
     * Get all migration paths from all domains.
     */
    public static function getAllMigrationPaths(): array
    {
        $domainPath = self::path()->domain();

        if (!is_dir($domainPath)) {
            return [];
        }

        $results = [];
        $domains = glob($domainPath . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR);

        foreach ($domains as $domainDir) {
            $path = $domainDir . DIRECTORY_SEPARATOR . 'Database' . DIRECTORY_SEPARATOR . 'Migrations';
            if (is_dir($path)) {
                $results[] = $path;
            }
        }

        return $results;
    }
}
