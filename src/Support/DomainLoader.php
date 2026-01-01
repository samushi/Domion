<?php

declare(strict_types=1);

namespace Samushi\Domion\Support;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Samushi\Domion\Helpers\DomainHelpers;

class DomainLoader
{
    /**
     * Automatically register all Domain Service Providers.
     */
    public static function registerDomainProviders($app): void
    {
        $domains = DomainHelpers::getDomains();

        foreach ($domains as $domainPath) {
            $providerPath = $domainPath . '/Providers';

            if (is_dir($providerPath)) {
                $files = glob($providerPath . '/*ServiceProvider.php');
                foreach ($files as $file) {
                    $className = self::getNamespaceFromFile($file);
                    if ($className && class_exists($className)) {
                        $app->register($className);
                    }
                }
            }
        }
    }

    /**
     * Register Observers based on naming convention or internal constant.
     */
    public static function registerObservers(): void
    {
        $domains = DomainHelpers::getDomains();

        foreach ($domains as $domainPath) {
            $observerPath = $domainPath . '/Observers';
            if (!is_dir($observerPath)) continue;

            foreach (glob($observerPath . '/*.php') as $file) {
                $observerClass = self::getNamespaceFromFile($file);
                if (!$observerClass) continue;

                // Priority 1: Check for const MODEL in Observer
                if (defined("{$observerClass}::MODEL")) {
                    $modelClass = $observerClass::MODEL;
                } else {
                    // Priority 2: Convention (UserObserver -> User)
                    $modelName = Str::replaceLast('Observer', '', class_basename($observerClass));

                    // Construct model namespace assuming standard structure
                    // app/Domain/User/Observers/UserObserver -> app/Domain/User/Models/User
                    $domainNamespace = substr($observerClass, 0, strpos($observerClass, '\\Observers'));
                    $modelClass = "{$domainNamespace}\\Models\\{$modelName}";
                }

                if (class_exists($modelClass) && class_exists($observerClass)) {
                    $modelClass::observe($observerClass);
                }
            }
        }
    }

    public static function getNamespaceFromFile(string $filePath): ?string
    {
        if (!file_exists($filePath)) return null;

        $content = file_get_contents($filePath);
        if (preg_match('/namespace\s+(.+?);/', $content, $matches)) {
            $namespace = $matches[1];
            $class = basename($filePath, '.php');
            return $namespace . '\\' . $class;
        }
        return null;
    }
}