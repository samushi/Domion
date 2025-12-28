<?php

declare(strict_types=1);

namespace Samushi\Domion\Helpers\Traits;

use Livewire\Livewire;
use Illuminate\Support\Str;

trait LivewireHelperTrait
{
    /**
     * Register all Livewire components from domains.
     */
    public static function registerLivewireComponents(): void
    {
        if (!class_exists(Livewire::class)) {
            return;
        }

        $domainPath = self::path()->domain();

        if (!is_dir($domainPath)) {
            return;
        }

        $domains = glob($domainPath . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR);

        foreach ($domains as $domainPath) {
            $livewireDir = $domainPath . DIRECTORY_SEPARATOR . 'Livewire';
            if (is_dir($livewireDir)) {
                $domainName = basename($domainPath);
                self::discoverLivewireComponents($livewireDir, $domainName);
            }
        }
    }

    /**
     * Discover and register components in a directory.
     */
    protected static function discoverLivewireComponents(string $directory, string $domain, string $subNamespace = ''): void
    {
        $files = glob($directory . DIRECTORY_SEPARATOR . '*.php');

        foreach ($files as $file) {
            $componentName = basename($file, '.php');
            $relativeNamespace = $subNamespace ? $subNamespace . '\\' : '';
            $fullClassName = "App\\Domain\\{$domain}\\Livewire\\{$relativeNamespace}{$componentName}";
            
            if (class_exists($fullClassName)) {
                $aliasPrefix = strtolower($domain);
                $aliasSub = $subNamespace ? '.' . strtolower(str_replace(['\\', '/'], '.', $subNamespace)) : '';
                $alias = $aliasPrefix . $aliasSub . '.' . Str::kebab($componentName);
                
                Livewire::component($alias, $fullClassName);
            }
        }

        // Handle subdirectories
        $subDirs = glob($directory . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR);
        foreach ($subDirs as $subDir) {
            $dirName = basename($subDir);
            $newSubNamespace = $subNamespace ? $subNamespace . '\\' . $dirName : $dirName;
            self::discoverLivewireComponents($subDir, $domain, $newSubNamespace);
        }
    }
}
