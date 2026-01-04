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
        (new InstallFortify($this->command))->run();
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
        // Namespace adjustments are handled via composer.json default mapping.
        // We do not modify bootstrap/providers.php or config/app.php namespaces here anymore.
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

        // Scaffold Layouts
        if ($mode === 'react' || $mode === 'vue') {
            $layoutStub = $mode === 'react' ? 'ReactLayout' : 'VueLayout';
            $ext = $mode === 'react' ? 'tsx' : 'vue';
            $layoutTarget = base_path("app/Support/Frontend/Layouts/AuthenticatedLayout.{$ext}");
            
            File::ensureDirectoryExists(dirname($layoutTarget));
            
            $stubPath = __DIR__ . "/../stubs/{$layoutStub}.stub";
            if (File::exists($stubPath)) {
                File::put($layoutTarget, File::get($stubPath));
            }
        }

        // Scaffold Sidebar
        if ($mode === 'react' || $mode === 'vue') {
            $sidebarStub = $mode === 'react' ? 'ReactSidebar' : 'VueSidebar';
            $ext = $mode === 'react' ? 'tsx' : 'vue';
            $sidebarTarget = base_path("app/Support/Frontend/components/app-sidebar.{$ext}");
            
            File::ensureDirectoryExists(dirname($sidebarTarget));
            
            $stubPath = __DIR__ . "/../stubs/{$sidebarStub}.stub";
            if (File::exists($stubPath)) {
                File::put($sidebarTarget, File::get($stubPath));
            }
        }

        // Scaffold Settings Layout
        if ($mode === 'react' || $mode === 'vue') {
            $settingsLayoutStub = $mode === 'react' ? 'ReactSettingsLayout' : 'VueSettingsLayout';
            $ext = $mode === 'react' ? 'tsx' : 'vue';
            $layoutTarget = base_path("app/Support/Frontend/Layouts/SettingsLayout.{$ext}");
            
            File::ensureDirectoryExists(dirname($layoutTarget));
            $stubPath = __DIR__ . "/../stubs/{$settingsLayoutStub}.stub";
            if (File::exists($stubPath)) {
                File::put($layoutTarget, File::get($stubPath));
            }
        }

        // Scaffold ThemeProvider
        if ($mode === 'react') {
            $target = base_path("app/Support/Frontend/components/theme-provider.tsx");
            File::ensureDirectoryExists(dirname($target));
            $stubPath = __DIR__ . "/../stubs/ReactThemeProvider.stub";
             if (File::exists($stubPath)) {
                File::put($target, File::get($stubPath));
            }
        }

        // Scaffold Global Components
        $this->scaffoldGlobalComponents($mode);

        // Scaffold Hooks (React) or Composables (Vue)
        $this->scaffoldHooksOrComposables($mode);

        // Scaffold Types
        $this->scaffoldTypes($mode);
        
        // Clean up the old resources/js if it exists to avoid confusion
        // But maybe the user wants to keep it? The user said "pjese e Support... jo ne resource"
        // So we can safely delete or at least warn.
        // Clean up the old resources/js and css if they exist to avoid confusion
        if (File::isDirectory(base_path('resources/js'))) {
            File::deleteDirectory(base_path('resources/js'));
        }
        if (File::isDirectory(base_path('resources/css'))) {
            File::deleteDirectory(base_path('resources/css'));
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
            "App\\" => "app/App/",
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
                
                // Update Namespace (KEPT AS App\Providers)
                // $content = str_replace('namespace App\Providers;', 'namespace App\App\Providers;', $content);
                
                // Robust injection for AppServiceProvider
                if ($file->getFilename() === 'AppServiceProvider.php') {
                    // 1. Inject registerDomainProviders in register()
                    if (!str_contains($content, 'DomainLoader::registerDomainProviders')) {
                        $regCall = "\n        \\Samushi\\Domion\\Support\\DomainLoader::registerDomainProviders(\$this->app);";
                        // Find the register method and inject after the opening brace
                        $content = preg_replace('/(public function register\(\): void\s*\{)/', "$1" . $regCall, $content);
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
        // 1. Clean up wrong App\App namespace in providers configs if present (from previous runs)
        $files = [base_path('bootstrap/providers.php'), base_path('config/app.php')];
        foreach ($files as $path) {
            if (File::exists($path)) {
                $content = File::get($path);
                if (str_contains($content, 'App\\App\\Providers\\AppServiceProvider::class')) {
                    $content = str_replace(
                        'App\\App\\Providers\\AppServiceProvider::class',
                        'App\\Providers\\AppServiceProvider::class',
                        $content
                    );
                    File::put($path, $content);
                }
            }
        }

        // Namespaces remain App\Providers, so no need to update bootstrap/providers.php
        $this->registerFrontendViews();
    }

    protected function registerFrontendViews(): void
    {
        $providerPath = base_path('app/App/Providers/AppServiceProvider.php');
        if (File::exists($providerPath)) {
            $content = File::get($providerPath);
            
            // Add View facade import if missing
            if (!str_contains($content, 'use Illuminate\Support\Facades\View;')) {
                // Insert after namespace App\Providers;
                $content = preg_replace(
                    '/(namespace App\\\\Providers;)/',
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

    protected function scaffoldGlobalComponents(string $mode): void
    {
        if (!in_array($mode, ['react', 'vue'])) {
            return;
        }

        $ext = $mode === 'react' ? 'tsx' : 'vue';
        $folder = $mode === 'react' ? 'react' : 'vue';
        
        $components = [
            'AppContent', 'AppHeader', 'AppLogo', 'AppLogoIcon', 'AppShell',
            'AppSidebarHeader', 'AlertError', 'Breadcrumbs', 'Heading', 
            'HeadingSmall', 'InputError', 'NavMain', 'NavUser', 'NavFooter',
            'TextLink', 'UserInfo'
        ];

        $stubDir = __DIR__ . "/../stubs/Support/Frontend/components/{$folder}";
        
        foreach ($components as $component) {
            $stubPath = "{$stubDir}/{$component}.{$ext}.stub";
            if (File::exists($stubPath)) {
                $targetName = $mode === 'react' 
                    ? strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', $component)) 
                    : $component;
                $target = base_path("app/Support/Frontend/components/{$targetName}.{$ext}");
                File::ensureDirectoryExists(dirname($target));
                File::put($target, File::get($stubPath));
            }
        }
    }

    protected function scaffoldHooksOrComposables(string $mode): void
    {
        if (!in_array($mode, ['react', 'vue'])) {
            return;
        }

        if ($mode === 'react') {
            $hooks = [
                'use-appearance.tsx',
                'use-mobile.tsx',
                'use-initials.tsx',
                'use-clipboard.ts',
                'use-mobile-navigation.ts',
                'use-two-factor-auth.ts'
            ];

            $stubDir = __DIR__ . '/../stubs/Support/Frontend/hooks';
            $targetDir = base_path('app/Support/Frontend/hooks');
            File::ensureDirectoryExists($targetDir);

            foreach ($hooks as $hook) {
                $stubPath = "{$stubDir}/{$hook}.stub";
                if (File::exists($stubPath)) {
                    File::put("{$targetDir}/{$hook}", File::get($stubPath));
                }
            }
        } else {
            $composables = [
                'useAppearance.ts',
                'useInitials.ts',
                'useTwoFactorAuth.ts'
            ];

            $stubDir = __DIR__ . '/../stubs/Support/Frontend/composables';
            $targetDir = base_path('app/Support/Frontend/composables');
            File::ensureDirectoryExists($targetDir);

            foreach ($composables as $composable) {
                $stubPath = "{$stubDir}/{$composable}.stub";
                if (File::exists($stubPath)) {
                    File::put("{$targetDir}/{$composable}", File::get($stubPath));
                }
            }
        }
    }

    protected function scaffoldTypes(string $mode): void
    {
        if (!in_array($mode, ['react', 'vue'])) {
            return;
        }

        $stubDir = __DIR__ . '/../stubs/Support/Frontend/types';
        $targetDir = base_path('app/Support/Frontend/types');
        File::ensureDirectoryExists($targetDir);

        $types = ['index.d.ts', 'vite-env.d.ts'];

        foreach ($types as $type) {
            $stubPath = "{$stubDir}/{$type}.stub";
            if (File::exists($stubPath)) {
                File::put("{$targetDir}/{$type}", File::get($stubPath));
            }
        }

        $libStubPath = __DIR__ . '/../stubs/Support/Frontend/lib/utils.ts.stub';
        $libTargetPath = base_path('app/Support/Frontend/lib/utils.ts');
        if (File::exists($libStubPath)) {
            File::ensureDirectoryExists(dirname($libTargetPath));
            File::put($libTargetPath, File::get($libStubPath));
        }
    }
}