<?php

declare(strict_types=1);

namespace Samushi\Domion\Tasks;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ConfigureArchitecture
{
    public function __construct(protected Command $command) {}

    public function run(bool $tenancy, string $mode = 'api'): void
    {
        $this->createFolders($tenancy);
        $this->updateComposer();
        $this->cleanup($mode);
        $this->setupBootstrap();
        $this->setupProviders();
        $this->setupRootView($mode);
        $this->cleanDefaultRoutes();
        $this->setupInertiaResolver($mode);
    }

    protected function setupRootView(string $mode): void
    {
        if ($mode === 'api') {
            return;
        }

        $target = base_path('app/Support/Frontend/Views/app.blade.php');
        if (File::exists($target)) {
            return;
        }

        File::ensureDirectoryExists(dirname($target));

        $stubName = 'AppView';

        $stubPath = __DIR__ . "/../stubs/{$stubName}.stub";
        if (File::exists($stubPath)) {
            $content = File::get($stubPath);
            
            // Set dynamic extension for Vite
            $ext = match($mode) {
                'react' => 'tsx',
                'vue' => 'vue',
                default => 'js'
            };
            
            $content = str_replace('{{ext}}', $ext, $content);
            $content = str_replace('{{viteReactRefresh}}', $mode === 'react' ? '@viteReactRefresh' : '', $content);
            
            File::put($target, $content);
        }
    }

    protected function setupProviders(): void
    {
        // 1. Laravel 11+ bootstrap/providers.php
        $providersPath = base_path('bootstrap/providers.php');
        if (File::exists($providersPath)) {
            $content = File::get($providersPath);
            $content = str_replace('App\Providers', 'App\App\Providers', $content);
            File::put($providersPath, $content);
        }

        // 2. config/app.php (for older versions or if still used)
        $configPath = base_path('config/app.php');
        if (File::exists($configPath)) {
            $content = File::get($configPath);
            $content = str_replace('App\Providers', 'App\App\Providers', $content);
            File::put($configPath, $content);
        }
    }

    protected function setupInertiaResolver(string $mode): void
    {
        if ($mode === 'api' || $mode === 'blade') {
            return;
        }

        $ext = match($mode) {
            'react' => 'tsx',
            'vue' => 'vue',
            'livewire' => 'js',
            default => 'js'
        };

        $target = base_path("app/Support/Frontend/app.{$ext}");
        
        // Match stub names to modes
        $stubName = match($mode) {
            'react' => 'ReactApp',
            'vue' => 'VueApp',
            default => null
        };

        if ($stubName) {
            $stubPath = __DIR__ . "/../stubs/{$stubName}.stub";
            if (File::exists($stubPath)) {
                $content = File::get($stubPath);
                
                // If we moved bootstrap.js, we should update the import or copy it
                // For now, let's keep it simple and assume bootstrap.js is in resources/js
                // or we can copy it to Support/Frontend
                if (File::exists(base_path('resources/js/bootstrap.js'))) {
                    File::ensureDirectoryExists(base_path('app/Support/Frontend'));
                    File::copy(base_path('resources/js/bootstrap.js'), base_path('app/Support/Frontend/bootstrap.js'));
                }

                File::put($target, $content);
            }
        }
        
        // Clean up the old resources/js if it exists to avoid confusion
        // But maybe the user wants to keep it? The user said "pjese e Support... jo ne resource"
        // So we can safely delete or at least warn.
        if (File::isDirectory(base_path('resources/js'))) {
            // File::deleteDirectory(base_path('resources/js')); 
            // Better to keep it for now but the entry point is moved in vite.config.js
        }
    }

    protected function createFolders(bool $tenancy): void
    {
        $dirs = [
            'app/App/Providers', 
            'app/Domain', 
            'app/Support/Frontend/Views',
            'app/Support/Frontend/Components',
        ];
        
        if ($tenancy) array_push($dirs, 'app/Domain/Central', 'app/Domain/Tenant');

        foreach ($dirs as $dir) File::ensureDirectoryExists(base_path($dir));
    }

    protected function updateComposer(): void
    {
        $path = base_path('composer.json');
        if (!File::exists($path)) return;

        $json = json_decode(File::get($path), true);

        $json['autoload']['psr-4'] = array_merge($json['autoload']['psr-4'], [
            "App\\App\\" => "app/App/",
            "App\\Domain\\" => "app/Domain/",
            "App\\Support\\" => "app/Support/"
        ]);

        File::put($path, json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    protected function cleanup(string $mode): void
    {
        // 1. Move Providers
        if (File::isDirectory(base_path('app/Providers'))) {
            foreach (File::files(base_path('app/Providers')) as $file) {
                $content = File::get($file);
                
                // Update Namespace
                $content = str_replace('namespace App\Providers;', 'namespace App\App\Providers;', $content);
                
                // Robust injection for AppServiceProvider
                if ($file->getFilename() === 'AppServiceProvider.php') {
                    // 1. Inject registerDomainProviders in register()
                    if (!str_contains($content, 'DomainLoader::registerDomainProviders')) {
                        $registration = "\n        \\Samushi\\Domion\\Support\\DomainLoader::registerDomainProviders(\$this->app);";
                        // Find register function and its opening brace. Inject right after it.
                        $content = preg_replace('/(public function register\(\): void\s*\{)/', "$1" . $registration, $content);
                    }

                    // 2. Inject Volt::mount in boot() if needed
                    if ($mode === 'livewire' && !str_contains($content, 'Volt::mount')) {
                        $bootInjection = "\n        \\Livewire\\Volt\\Volt::mount([realpath(__DIR__.'/../../../app/Domain')]);";
                        $content = preg_replace('/(public function boot\(\): void\s*\{)/', "$1" . $bootInjection, $content);
                    }
                    
                    // 3. Clean up any duplicate empty braces created by previous runs without breaking the class
                    $content = preg_replace('/\{\s*\{\s*\/\/\s*\}\s*\}/', "{\n        //\n    }", $content);
                }

                File::put(base_path('app/App/Providers/' . $file->getFilename()), $content);
            }
            File::deleteDirectory(base_path('app/Providers'));
        }

        // 2. Delete app/Http (not used in this DDD structure)
        File::deleteDirectory(base_path('app/Http'));

        // 3. Delete app/Models
        File::deleteDirectory(base_path('app/Models'));
    }

    protected function setupBootstrap(): void
    {
        $path = base_path('bootstrap/app.php');
        if (File::exists($path)) {
            $content = File::get($path);
            if (!str_contains($content, 'useAppPath')) {
                $content = str_replace(
                    '->create();',
                    "->create()\n        ->useAppPath(realpath(__DIR__.'/../app/App'));",
                    $content
                );
                File::put($path, $content);
            }
        }
    }

    /**
     * Remove the old Helper loader from global routes.
     * Service Providers now handle routing.
     */
    protected function cleanDefaultRoutes(): void
    {
        $webPath = base_path('routes/web.php');
        if (File::exists($webPath)) {
            File::put($webPath, "<?php\n\nuse Illuminate\Support\Facades\Route;\n\n// Global routes (e.g. health check)\n// Domain routes are loaded via their ServiceProviders\n");
        }

        $apiPath = base_path('routes/api.php');
        if (File::exists($apiPath)) {
            File::put($apiPath, "<?php\n\nuse Illuminate\Support\Facades\Route;\n\n// Global API routes\n");
        }
    }
}