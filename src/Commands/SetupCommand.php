<?php

declare(strict_types=1);

namespace Samushi\Domion\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(
    name: 'domion:setup',
    description: 'Setup the DDD architecture folders and initial structure',
)]
class SetupCommand extends Command
{
    public function handle(): int
    {
        \Laravel\Prompts\intro('🏗️  Domion: Professional DDD Setup');

        if (!\Laravel\Prompts\confirm('Do you want to initialize the Domion architecture?', true)) {
            \Laravel\Prompts\error('Setup cancelled.');
            return self::FAILURE;
        }

        // 1. Architecture Choice (Radio style)
        $tenancy = \Laravel\Prompts\select(
            label: 'Select your architecture type:',
            options: [
                'standard' => 'Standard (Single Application)',
                'tenancy' => 'Multi-tenancy (Central & Tenant Domains)'
            ],
            default: 'standard'
        ) === 'tenancy';

        // 2. Frontend Mode (Radio style)
        $mode = \Laravel\Prompts\select(
            label: 'Which frontend mode do you want to use?',
            options: [
                'api' => 'API Only (STALESS)',
                'react' => 'React (Inertia.js)',
                'vue' => 'Vue (Inertia.js)',
                'livewire' => 'Livewire (TALL)',
                'blade' => 'Standard Blade Views'
            ],
            default: 'react'
        );

        // 3. Auth Integration
        $auth = \Laravel\Prompts\select(
            label: 'Which authentication package are you using?',
            options: [
                'sanctum' => 'Sanctum (Bearer Tokens / SPA)',
                'passport' => 'Passport (Full OAuth2)',
                'none' => 'None / Custom'
            ],
            default: 'sanctum'
        );

        // 4. Cleanup Legacy Structure
        $cleanup = \Laravel\Prompts\confirm('Do you want to move default Providers to App/Providers and delete app/Models and app/Http?', true);

        // 5. Starter Kit
        $starterKit = \Laravel\Prompts\confirm('Do you want to install an example Auth domain (Login/Register/Dashboard)?', true);

        // 6. Install Dependencies FIRST (Interactive)
        $this->setupDependencies($mode, $auth, $tenancy);

        \Laravel\Prompts\spin(
            function() use ($mode, $tenancy, $cleanup, $starterKit, $auth) {
                $this->setupFolders($tenancy);
                
                if ($cleanup) {
                    $this->cleanupLaravelDefaults();
                }

                if ($starterKit) {
                    $this->installStarterKit($mode);
                }

                $this->setupRoutes();
                $this->setupBootstrap();
                $this->setupComposer();
                
                if (in_array($mode, ['react', 'vue'])) {
                    $this->setupFrontend($mode);
                    $this->setupVite();
                    $this->setupJsConfig();
                }
            },
            'Configuring Domion Architecture...'
        );

        $this->saveConfiguration($auth, $mode, $tenancy);

        \Laravel\Prompts\outro('✅ Domion Setup completed successfully!');
        $this->info('Please run: <fg=green;options=bold>composer dump-autoload</>');
        
        if (\Laravel\Prompts\confirm('Do you want to start the development server now?', true)) {
            $this->info('🚀 Starting development servers... (Press Ctrl+C to stop)');
            
            $command = 'php artisan serve';
            if (in_array($mode, ['react', 'vue'])) {
                // Run both in parallel and wait for them to catch Ctrl+C
                $command = 'php artisan serve & npm run dev --silent > /dev/null 2>&1 & wait';
            }

            passthru($command);
        }

        return self::SUCCESS;
    }

    protected function setupFrontend(string $mode): void
    {
        $extensions = ['js', 'jsx', 'tsx'];
        $appPath = null;
        $activeExtension = 'js';

        foreach ($extensions as $ext) {
            $path = base_path("resources/js/app.{$ext}");
            if (File::exists($path)) {
                $appPath = $path;
                $activeExtension = $ext;
                break;
            }
        }

        if (!$appPath) {
            $this->warn('Could not find resources/js/app.js (or .jsx/.tsx). Manual Inertia setup required.');
            return;
        }

        $content = File::get($appPath);
        
        // If app.js is basically empty or default Laravel, install the full starter kit app.js
        if (!str_contains($content, 'createInertiaApp')) {
            $stubName = $mode === 'react' ? 'ReactApp' : 'VueApp';
            $this->generateFromStub($stubName, $appPath);
            $content = File::get($appPath);
            $this->components->twoColumnDetail('Inertia Entry Point', '<fg=green;options=bold>INSTALLED</>');
        }

        if (str_contains($content, 'app/Domain')) {
            $this->components->twoColumnDetail('Frontend Auto-Resolver', '<fg=yellow;options=bold>EXISTS</>');
        } else if (str_contains($content, 'createInertiaApp')) {
            $globPattern = "../../app/Domain/*/Frontend/Pages/**/*.{js,jsx,ts,tsx,vue}";
            $injection = "\n// Domion DDD Resolver\n" .
                "const domainPages = import.meta.glob('{$globPattern}');\n" .
                "const resolveDomainPage = (name, domainPages, resolver) => {\n" .
                "    if (name.includes('::')) {\n" .
                "        const [domain, page] = name.split('::');\n" .
                "        const path = Object.keys(domainPages).find(p => p.toLowerCase().includes(`/domain/\${domain.toLowerCase()}/frontend/pages/\${page.toLowerCase()}`));\n" .
                "        if (path) return typeof domainPages[path] === 'function' ? domainPages[path]() : domainPages[path];\n" .
                "    }\n" .
                "    return resolver(name);\n" .
                "};\n\n";

            // Inject before the FIRST occurrence of createInertiaApp invocation (typically createInertiaApp({)
            // but NOT in the import statement
            if (preg_match('/(?<!import\s\{)(?<![a-zA-Z0-9])createInertiaApp\s*\(/', $content, $matches, PREG_OFFSET_CAPTURE)) {
                $pos = $matches[0][1];
                $content = substr($content, 0, $pos) . $injection . substr($content, $pos);
            } else {
                // Fallback: just append after imports or at top (less ideal if it's a mess)
                $content = $injection . $content;
            }
            
            // This regex captures the entire resolve line to ensure we keep its arguments and close correctly
            $resolvePattern = '/resolve:\s*\(?name\)?\s*=>\s*resolvePageComponent\((.*?)\),?/s';
            $replacement = "resolve: (name) => resolveDomainPage(name, domainPages, (name) => resolvePageComponent($1)),";
            
            if (preg_match($resolvePattern, $content)) {
                $content = preg_replace($resolvePattern, $replacement, $content);
                File::put($appPath, $content);
                $this->components->twoColumnDetail('Frontend Auto-Resolver', '<fg=green;options=bold>INSTALLED</>');
            } else {
                $this->warn('Could not find Inertia resolve block in app.js. Manual setup required.');
            }
        }
    // 3. Ensure app.blade.php exists in Support Resources
        $supportViewPath = base_path('app/Support/Resources/views');
        if (!File::isDirectory($supportViewPath)) {
            File::makeDirectory($supportViewPath, 0755, true);
        }

        $viewPath = $supportViewPath . '/app.blade.php';
        if (!File::exists($viewPath)) {
            $this->generateFromStub('AppView', $viewPath, [
                'mode' => $mode,
                'ext' => $activeExtension,
                'viteReactRefresh' => $mode === 'react' ? '@viteReactRefresh' : ''
            ]);
            $this->components->twoColumnDetail('Support Root View (DDD)', '<fg=green;options=bold>CREATED</>');
        }
    }

    protected function setupVite(): void
    {
        $path = base_path('vite.config.js');
        if (!File::exists($path)) return;

        $content = File::get($path);
        if (str_contains($content, "'@domain'")) {
            $this->components->twoColumnDetail('Vite Aliases (@domain)', '<fg=yellow;options=bold>EXISTS</>');
            return;
        }

        $aliasAddition = "alias: {\n                '@domain': '/app/Domain',\n                '@support': '/app/Support',\n                '@app': '/app/App',\n            },";

        if (str_contains($content, 'resolve: {')) {
            $content = str_replace('resolve: {', "resolve: {\n            {$aliasAddition}", $content);
        } else {
            $content = preg_replace('/plugins: \[.*?\],/s', "$0\n        resolve: {\n            {$aliasAddition}\n        },", $content);
        }

        File::put($path, $content);
        $this->components->twoColumnDetail('Vite Aliases (@domain)', '<fg=green;options=bold>INSTALLED</>');
    }

    protected function setupJsConfig(): void
    {
        $path = File::exists(base_path('tsconfig.json')) ? base_path('tsconfig.json') : base_path('jsconfig.json');
        $isNew = !File::exists($path);
        
        $config = $isNew ? ['compilerOptions' => ['baseUrl' => '.', 'paths' => []]] : json_decode(File::get($path), true);
        if (!isset($config['compilerOptions']['paths'])) $config['compilerOptions']['paths'] = [];

        $config['compilerOptions']['paths']['@domain/*'] = ['app/Domain/*'];
        $config['compilerOptions']['paths']['@support/*'] = ['app/Support/*'];
        $config['compilerOptions']['paths']['@app/*'] = ['app/App/*'];

        File::put($path, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $label = basename($path);
        $this->components->twoColumnDetail("IDE Paths ({$label})", "<fg=green;options=bold>" . ($isNew ? 'CREATED' : 'UPDATED') . "</>");
    }

    protected function setupDependencies(string $mode, string $auth, bool $tenancy = false): void
    {
        $composerPackages = [];
        $npmPackages = [];

        // Core DDD dependencies
        if (in_array($mode, ['react', 'vue'])) {
            $composerPackages[] = 'inertiajs/inertia-laravel';
            $composerPackages[] = 'tightenco/ziggy';
            $npmPackages[] = $mode === 'react' ? '@inertiajs/react react react-dom' : '@inertiajs/vue3 vue';
            $npmPackages[] = '@vitejs/plugin-react';
        }

        if ($mode === 'livewire') {
            $composerPackages[] = 'livewire/livewire';
        }

        // Multi-tenancy
        if ($tenancy) {
            $composerPackages[] = 'stancl/tenancy';
        }

        // Auth Integration
        if (str_contains($auth, 'Sanctum')) {
            $composerPackages[] = 'laravel/sanctum';
        } elseif (str_contains($auth, 'Passport')) {
            $composerPackages[] = 'laravel/passport';
        }

        // Spatie Permissions
        if ($this->confirm('Install Spatie Laravel Permission?', true)) {
            $composerPackages[] = 'spatie/laravel-permission';
        }

        if (!empty($composerPackages) && $this->confirm('Install PHP dependencies (Composer)?', true)) {
            $this->info('Installing: ' . implode(', ', $composerPackages));
            $packages = implode(' ', $composerPackages);
            exec("COMPOSER_NO_INTERACTION=1 composer require {$packages} --quiet");
        }

        if (!empty($npmPackages) && $this->confirm('Install JS dependencies (NPM)?', true)) {
            $this->info('Installing: ' . implode(', ', $npmPackages));
            $packages = implode(' ', $npmPackages);
            exec("npm install {$packages} --save-dev --silent");
        }
    }

    protected function saveConfiguration(string $auth, string $mode, bool $tenancy = false): void
    {
        $configPath = base_path('config/domion.php');
        
        $authValue = strtolower($auth);
        $modeValue = strtolower($mode);

        $content = "<?php\n\nreturn [\n    'auth' => '{$authValue}',\n    'mode' => '{$modeValue}',\n    'tenancy' => " . ($tenancy ? 'true' : 'false') . ",\n    'paths' => [\n        'apps' => 'app/App',\n        'domains' => 'app/Domain',\n        'support' => 'app/Support',\n    ]\n];\n";

        File::put($configPath, $content);
        $this->components->twoColumnDetail('Config File (config/domion.php)', '<fg=green;options=bold>CREATED</>');
    }

    protected function setupFolders(bool $tenancy = false): void
    {
        $folders = [
            'app/App', 
            'app/App/Providers',
            'app/Domain', 
            'app/Support',
        ];

        if ($tenancy) {
            $folders[] = 'app/Domain/Central';
            $folders[] = 'app/Domain/Tenant';
        }

        // Shared resources go to Support
        $folders[] = 'app/Support/Resources/views';

        foreach ($folders as $folder) {
            if (!File::isDirectory(base_path($folder))) {
                File::makeDirectory(base_path($folder), 0755, true);
            }
        }
    }

    protected function cleanupLaravelDefaults(): void
    {
        // 1. Delete Models and Http (Legacy)
        if (File::isDirectory(base_path('app/Models'))) {
            File::deleteDirectory(base_path('app/Models'));
        }
        if (File::isDirectory(base_path('app/Http'))) {
            File::deleteDirectory(base_path('app/Http'));
        }
        
        // Clean up Shared domain if it exists at root (we moved it to Support)
        if (File::isDirectory(base_path('app/Domain/Shared'))) {
            File::deleteDirectory(base_path('app/Domain/Shared'));
        }

        if (File::isDirectory(base_path('app/Http/Controllers'))) {
            // Keep the folder but remove default files if needed, 
            // actually we relocated the logic to Domains
        }

        $filesToDelete = [
            app_path('Http/Controllers/Controller.php'),
            base_path('resources/views/welcome.blade.php'),
            base_path('resources/views/app.blade.php'),
        ];

        foreach ($filesToDelete as $file) {
            if (File::exists($file)) File::delete($file);
        }

        // 2. Move Providers to app/App/Providers and fix namespaces
        if (File::isDirectory(base_path('app/Providers'))) {
            if (!File::isDirectory(base_path('app/App/Providers'))) {
                File::makeDirectory(base_path('app/App/Providers'), 0755, true);
            }
            
            $files = File::files(base_path('app/Providers'));
            foreach ($files as $file) {
                $content = File::get($file->getRealPath());
                $content = str_replace('namespace App\Providers;', 'namespace App\App\Providers;', $content);
                
                $targetFile = base_path('app/App/Providers/' . $file->getFilename());
                File::put($targetFile, $content);
            }
            File::deleteDirectory(base_path('app/Providers'));
        }
    }

    protected function setupRoutes(): void
    {
        // 1. API Discovery
        $apiPath = base_path('routes/api.php');
        if (File::exists($apiPath)) {
            $content = File::get($apiPath);
            if (!str_contains($content, 'DomainHelpers::loadDomainRoutes()')) {
                $newContent = "<?php\n\nuse Samushi\Domion\Helpers\DomainHelpers;\n\nDomainHelpers::loadDomainRoutes();\n";
                File::put($apiPath, $newContent);
            }
        }

        // 2. Web / Landing Support
        $webPath = base_path('routes/web.php');
        if (File::exists($webPath)) {
            $content = File::get($webPath);
            
            // Add Domain discovery to web.php as well
            if (!str_contains($content, 'DomainHelpers::loadDomainRoutes()')) {
                $content .= "\n\nuse Samushi\Domion\Helpers\DomainHelpers;\nDomainHelpers::loadDomainRoutes();\n";
                File::put($webPath, $content);
            }
        }
    }

    protected function setupBootstrap(): void
    {
        $bootstrapApp = base_path('bootstrap/app.php');
        $bootstrapProviders = base_path('bootstrap/providers.php');

        // 1. Handle app.php (Application Core)
        if (File::exists($bootstrapApp)) {
            $content = File::get($bootstrapApp);

            if (!str_contains($content, '->useAppPath')) {
                // Fluent chaining after create()
                $pattern = '/->create\(\);/s';
                $replacement = "->create()\n        ->useAppPath(realpath(__DIR__.'/../app/App'));";
                
                if (preg_match($pattern, $content)) {
                    $newContent = preg_replace($pattern, $replacement, $content);
                    File::put($bootstrapApp, $newContent);
                }
            }
        }
        
        // 2. Handle providers.php (Laravel 11+)
        if (File::exists($bootstrapProviders)) {
            $content = File::get($bootstrapProviders);
            
            // Fix relocated providers namespaces
            $content = str_replace('App\Providers\\', 'App\App\Providers\\', $content);
            
            // Register DomionServiceProvider if not present
            if (!str_contains($content, 'DomionServiceProvider::class')) {
                $providerEntry = "    Samushi\Domion\Providers\DomionServiceProvider::class,\n];";
                $content = str_replace('];', $providerEntry, $content);
            }
            
            File::put($bootstrapProviders, $content);
        }
    }

    protected function setupComposer(): void
    {
        $composerPath = base_path('composer.json');

        if (File::exists($composerPath)) {
            $composer = json_decode(File::get($composerPath), true);

            $composer['autoload']['psr-4']['App\\'] = 'app/App/';
            $composer['autoload']['psr-4']['App\\Domain\\'] = 'app/Domain/';
            $composer['autoload']['psr-4']['App\\Support\\'] = 'app/Support/';

            File::put(
                $composerPath, 
                json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL
            );
        }
    }

    protected function installStarterKit(string $mode): void
    {
        // 1. Setup Domains & Support
        $this->createDomainStructure('User', $mode);
        $this->createDomainStructure('Auth', $mode);
        
        // Ensure Support has view directory
        File::ensureDirectoryExists(base_path('app/Support/Resources/views'));

        $baseController = match ($mode) {
            'react', 'vue' => 'InertiaControllers',
            'api' => 'ApiControllers',
            default => 'WebControllers'
        };

        // 2. Relocate User Migration
        $this->relocateUserMigrations();

        // 3. Create User & Auth Actions
        $this->generateFromStub('Action', base_path('app/Domain/Auth/Actions/LoginAction.php'), [
            'namespace' => 'App\Domain\Auth\Actions',
            'class' => 'LoginAction'
        ]);

        if ($mode === 'api') {
            $this->generateFromStub('RefreshTokenAction', base_path('app/Domain/Auth/Actions/RefreshTokenAction.php'), [
                'namespace' => 'App\Domain\Auth\Actions'
            ]);
        }

        // 4. Create User Model
        $this->generateFromStub('UserModel', base_path('app/Domain/User/Models/User.php'), [
            'namespace' => 'App\Domain\User\Models'
        ]);

        // 5. Create Controllers
        $this->generateFromStub('StarterController', base_path('app/Domain/Auth/Controllers/LoginController.php'), [
            'namespace' => 'App\Domain\Auth\Controllers',
            'baseController' => $baseController,
            'class' => 'LoginController',
            'domain' => 'auth',
            'view' => 'Login'
        ]);

        $this->generateFromStub('StarterController', base_path('app/Domain/Auth/Controllers/DashboardController.php'), [
            'namespace' => 'App\Domain\Auth\Controllers',
            'baseController' => $baseController,
            'class' => 'DashboardController',
            'domain' => 'auth',
            'view' => 'Dashboard'
        ]);

        // 6. Create Seeders
        $this->generateFromStub('RolesAndPermissionsSeeder', base_path('app/Domain/User/Database/Seeders/RolesAndPermissionsSeeder.php'), [
            'namespace' => 'App\Domain\User\Database\Seeders',
            'class' => 'RolesAndPermissionsSeeder'
        ]);

        $this->generateFromStub('UserSeeder', base_path('app/Domain/User/Database/Seeders/UserSeeder.php'), [
            'namespace' => 'App\Domain\User\Database\Seeders',
            'class' => 'UserSeeder',
            'modelNamespace' => 'App\Domain\User\Models'
        ]);

        // 7. Create Factory
        $this->generateFromStub('Factory', base_path('app/Domain/User/Database/Factories/UserFactory.php'), [
            'namespace' => 'App\Domain\User\Database\Factories',
            'modelNamespace' => 'App\Domain\User\Models',
            'model' => 'User',
            'class' => 'UserFactory'
        ]);

        // 8. Create Routes
        $routeContent = "<?php\n\nuse Illuminate\Support\Facades\Route;\nuse App\Domain\Auth\Controllers\LoginController;\nuse App\Domain\Auth\Controllers\DashboardController;\n\nRoute::get('/', [LoginController::class, 'landing'])->name('landing');\nRoute::get('/login', [LoginController::class, 'index'])->name('login');\nRoute::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');\n";
        File::put(base_path('app/Domain/Auth/web.php'), $routeContent);

        // 8. Create Frontend Pages
        if ($mode === 'react') {
            $this->generateFromStub('ReactLanding', base_path('app/Domain/Auth/Frontend/Pages/Landing.tsx'));
            $this->generateFromStub('ReactLogin', base_path('app/Domain/Auth/Frontend/Pages/Login.tsx'));
            $this->generateFromStub('ReactDashboard', base_path('app/Domain/Auth/Frontend/Pages/Dashboard.tsx'));
        } elseif ($mode === 'vue') {
            $this->generateFromStub('VueLanding', base_path('app/Domain/Auth/Frontend/Pages/Landing.vue'));
            $this->generateFromStub('VueLogin', base_path('app/Domain/Auth/Frontend/Pages/Login.vue'));
            $this->generateFromStub('VueDashboard', base_path('app/Domain/Auth/Frontend/Pages/Dashboard.vue'));
        } else {
            // Blade, Livewire, or API (fallback to Blade views for presentation)
            $viewPath = base_path('app/Domain/Auth/Resources/views/pages');
            File::ensureDirectoryExists($viewPath);
            
            $this->generateFromStub('BladeLanding', $viewPath . '/Landing.blade.php');
            $this->generateFromStub('BladeLogin', $viewPath . '/Login.blade.php');
            $this->generateFromStub('BladeDashboard', $viewPath . '/Dashboard.blade.php');
        }
    }

    protected function updateConfigMode(string $mode): void
    {
        $configPath = config_path('domion.php');
        if (File::exists($configPath)) {
            $content = File::get($configPath);
            $content = preg_replace("/'mode' => '.*'/", "'mode' => '{$mode}'", $content);
            File::put($configPath, $content);
        }
    }

    protected function createDomainStructure(string $domain, string $mode = 'api'): void
    {
        $base = base_path("app/Domain/{$domain}");
        $folders = [
            $base . '/Actions',
            $base . '/Controllers',
            $base . '/Models',
            $base . '/Database/Migrations',
            $base . '/Database/Seeders',
            $base . '/Database/Factories',
        ];

        if (in_array($mode, ['react', 'vue'])) {
            $folders[] = $base . '/Frontend/Pages';
            $folders[] = $base . '/Frontend/Components';
            
            // Cleanup Resource folder if it was created by accident
            if (File::isDirectory($base . '/Resources')) {
                File::deleteDirectory($base . '/Resources');
            }
        } else {
            $folders[] = $base . '/Resources/views/pages';
            $folders[] = $base . '/Resources/views/components';
        }

        foreach ($folders as $folder) {
            if (!File::isDirectory($folder)) {
                File::makeDirectory($folder, 0755, true);
            }
        }
    }

    protected function relocateUserMigrations(): void
    {
        $migrationDir = base_path('database/migrations');
        $targetDir = base_path('app/Domain/User/Database/Migrations');

        if (!File::isDirectory($targetDir)) {
            File::makeDirectory($targetDir, 0755, true);
        }

        if (File::isDirectory($migrationDir)) {
            $files = File::files($migrationDir);
            foreach ($files as $file) {
                if (str_contains($file->getFilename(), 'create_users_table')) {
                    File::move($file->getRealPath(), $targetDir . '/' . $file->getFilename());
                    $this->info("Moved migration: " . $file->getFilename());
                }
            }
        }
    }

    protected function generateFromStub(string $stub, string $target, array $replacements = []): void
    {
        $stubPath = __DIR__ . '/../stubs/' . $stub . '.stub';
        
        if (!File::exists($stubPath)) {
            $this->error("Stub not found: {$stubPath}");
            return;
        }

        $content = File::get($stubPath);

        foreach ($replacements as $key => $value) {
            $content = str_replace('{{' . $key . '}}', $value, $content);
        }

        File::put($target, $content);
    }
}
