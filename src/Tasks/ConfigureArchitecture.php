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
        $this->setupMiddleware($mode);
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
                'vue' => 'ts',
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
            'vue' => 'ts',
            default => 'js'
        };

        $target = base_path("app/Support/Frontend/app.{$ext}");
        
        $stubName = match($mode) {
            'react' => 'ReactApp',
            'vue' => 'VueApp',
            default => null
        };

        if ($stubName) {
            $stubPath = __DIR__ . "/../stubs/{$stubName}.stub";
            if (File::exists($stubPath)) {
                $content = File::get($stubPath);
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
            base_path('app/Support'),
            base_path('app/Support/Frontend'),
            base_path('app/Support/Frontend/components'),
            base_path('app/Support/Frontend/lib'),
        ];
        
        if ($tenancy) array_push($dirs, 'app/Domain/Central', 'app/Domain/Tenant');

        foreach ($dirs as $dir) File::ensureDirectoryExists(base_path($dir));
    }

    protected function updateComposer(): void
    {
        $path = base_path('composer.json');
        if (!File::exists($path)) return;

        $json = json_decode(File::get($path), true);

        if (!isset($json['autoload']['psr-4'])) {
            $json['autoload']['psr-4'] = [];
        }

        // Remove old potential keys from previous versions or standard Laravel to avoid conflicts
        unset($json['autoload']['psr-4']['Domain\\']);
        unset($json['autoload']['psr-4']['Support\\']);
        unset($json['autoload']['psr-4']['App\\']);

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
                        $regCall = "\n        \\Samushi\\Domion\\Support\\DomainLoader::registerDomainProviders(\$this->app);";
                        // Find the register method and inject after the opening brace
                        $content = preg_replace('/(public function register\(\): void\s*\{)/', "$1" . $regCall, $content);
                    }

                    // 2. Inject Volt::mount in boot() if needed
                    if ($mode === 'livewire' && !str_contains($content, 'Volt::mount')) {
                        $bootCall = "\n        \\Livewire\\Volt\\Volt::mount([realpath(__DIR__.'/../../../app/Domain')]);";
                        // Find the boot method and inject after the opening brace
                        $content = preg_replace('/(public function boot\(\): void\s*\{)/', "$1" . $bootCall, $content);
                    }
                    
                    // 3. Ensure the class is properly closed if it got messed up (fallback)
                    if (substr(trim($content), -1) !== '}') {
                        $content = rtrim($content) . "\n}\n";
                    }
                }

                File::put(base_path('app/App/Providers/' . $file->getFilename()), $content);
            }
            File::deleteDirectory(base_path('app/Providers'));
        }

        // 2. Delete app/Http (not used in this DDD structure)
        File::deleteDirectory(base_path('app/Http'));

        // 3. Move User Model instead of deleting it
        $oldUserPath = base_path('app/Models/User.php');
        $targetUserPath = base_path('app/Domain/User/Models/User.php');

        if (File::exists($oldUserPath)) {
            File::ensureDirectoryExists(dirname($targetUserPath));
            $content = File::get($oldUserPath);
            $content = str_replace('namespace App\Models;', 'namespace App\Domain\User\Models;', $content);
            File::put($targetUserPath, $content);
        }

        File::deleteDirectory(base_path('app/Models'));
    }

    protected function setupBootstrap(): void
    {
        $path = base_path('bootstrap/app.php');
        if (File::exists($path)) {
            $content = File::get($path);

            if (!str_contains($content, 'useAppPath')) {
                $content = str_replace(
                    '->create()',
                    "->create()\n        ->useAppPath(realpath(__DIR__.'/../app/App'))",
                    $content
                );
            }

            if (!str_contains($content, 'HandleInertiaRequests::class')) {
                $middlewareClass = "\\App\\Support\\Middleware\\HandleInertiaRequests::class";
                
                if (str_contains($content, '->withMiddleware')) {
                    // If web(append: [...]) exists, add to it
                    if (preg_match('/\$middleware->web\(\s*append:\s*\[/', $content)) {
                        $content = preg_replace(
                            '/(\$middleware->web\(\s*append:\s*\[)/',
                            "$1\n            {$middlewareClass},",
                            $content
                        );
                    } 
                    // If $middleware->web(...) exists but without append or different syntax, try to insert new web call
                    elseif (str_contains($content, '$middleware->web(')) {
                         $content = preg_replace(
                            '/(\$middleware->web\(.*?\);)/s',
                            "$1\n        \$middleware->web(append: [{$middlewareClass}]);",
                            $content
                        );
                    }
                    // Otherwise, simply append inside the validation callback
                    else {
                        // Regex to match withMiddleware(function (Middleware $middleware) { ...
                        // Handles optional ": void" return type and whitespace
                        $pattern = '/(->withMiddleware\(function\s*\(Middleware\s*\$middleware\)(?:\s*:\s*void)?\s*\{)/';
                        
                        if (preg_match($pattern, $content)) {
                            $content = preg_replace(
                                $pattern,
                                "$1\n        \$middleware->web(append: [{$middlewareClass}]);",
                                $content
                            );
                        }
                    }
                } else {
                    // Create the whole block if withMiddleware doesn't exist
                    $replacement = "->withMiddleware(function (Middleware \$middleware) {\n" .
                        "        \$middleware->web(append: [\n" .
                        "            {$middlewareClass},\n" .
                        "        ]);\n" .
                        "    })";
                        
                    $content = str_replace(
                        '->create()',
                        $replacement . "\n    ->create()",
                        $content
                    );
                }
            }

            File::put($path, $content);
        }
        
        $this->updateProvidersConfig();
        $this->updateAuthConfig();
    }

    protected function updateProvidersConfig(): void
    {
        // For Laravel 11/12
        $path = base_path('bootstrap/providers.php');
        if (File::exists($path)) {
            $content = File::get($path);
            $content = str_replace('App\\Providers\\AppServiceProvider::class', 'App\\App\\Providers\\AppServiceProvider::class', $content);
            File::put($path, $content);
        }

        // For Laravel 10 or published config
        $configPath = base_path('config/app.php');
        if (File::exists($configPath)) {
            $content = File::get($configPath);
            $content = str_replace('App\\Providers\\AppServiceProvider::class', 'App\\App\\Providers\\AppServiceProvider::class', $content);
            File::put($configPath, $content);
        }

        $this->registerFrontendViews();
    }

    protected function registerFrontendViews(): void
    {
        $providerPath = base_path('app/App/Providers/AppServiceProvider.php');
        if (File::exists($providerPath)) {
            $content = File::get($providerPath);
            
            // 1. Fix Namespace if it's still the old one
            if (str_contains($content, 'namespace App\Providers;')) {
                $content = str_replace('namespace App\Providers;', 'namespace App\App\Providers;', $content);
            }

            // 2. Add View facade import if missing
            if (!str_contains($content, 'use Illuminate\Support\Facades\View;')) {
                // Insert after namespace
                $content = preg_replace(
                    '/(namespace App\\\\App\\\\Providers;)/',
                    "$1\n\nuse Illuminate\Support\Facades\View;",
                    $content
                );
            }

            // Add view location to boot method
            if (!str_contains($content, 'View::addLocation')) {
                $viewPathCode = "View::addLocation(base_path('app/Support/Frontend/Views'));";
                
                if (str_contains($content, 'public function boot(): void')) {
                    // Laravel 11 style
                    $content = preg_replace(
                        '/(public function boot\(\): void\s*\{)/',
                        "$1\n        {$viewPathCode}",
                        $content
                    );
                } elseif (str_contains($content, 'public function boot()')) {
                    // Older style
                    $content = preg_replace(
                        '/(public function boot\(\)\s*\{)/',
                        "$1\n        {$viewPathCode}",
                        $content
                    );
                }
            }
            
            File::put($providerPath, $content);
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

    protected function setupMiddleware(string $mode): void
    {
        if ($mode === 'api' || $mode === 'blade') {
            return;
        }

        $target = base_path('app/Support/Middleware/HandleInertiaRequests.php');
        File::ensureDirectoryExists(dirname($target));

        if (!File::exists($target)) {
            $stub = __DIR__ . '/../stubs/HandleInertiaRequests.stub';
            if (File::exists($stub)) {
                File::put($target, File::get($stub));
            }
        }
    }

    protected function updateAuthConfig(): void
    {
        $path = base_path('config/auth.php');
        if (File::exists($path)) {
            $content = File::get($path);
            $content = str_replace('App\\Models\\User::class', 'App\\Domain\\User\\Models\\User::class', $content);
            File::put($path, $content);
        }
    }
}