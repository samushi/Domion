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
            $this->info('Starting Artisan Serve...');
            exec('php artisan serve --quiet > /dev/null 2>&1 &');
            $this->info('Server started at http://127.0.0.1:8000');
            
            if (in_array($mode, ['react', 'vue'])) {
                $this->info('Starting Vite...');
                exec('npm run dev --silent > /dev/null 2>&1 &');
            }
        }

        return self::SUCCESS;
    }

    protected function setupFrontend(string $mode): void
    {
        $extensions = ['js', 'jsx', 'tsx'];
        $appPath = null;

        foreach ($extensions as $ext) {
            $path = base_path("resources/js/app.{$ext}");
            if (File::exists($path)) {
                $appPath = $path;
                break;
            }
        }

        if (!$appPath) {
            $this->warn('Could not find resources/js/app.js (or .jsx/.tsx). Manual Inertia setup required.');
            return;
        }

        $content = File::get($appPath);
        
        if (str_contains($content, 'app/Domain')) {
            $this->components->twoColumnDetail('Frontend Auto-Resolver', '<fg=yellow;options=bold>EXISTS</>');
            return;
        }

        $globPattern = "../../app/Domain/*/Frontend/Pages/**/*.{js,jsx,ts,tsx,vue}";
        $injection = "\nconst domainPages = import.meta.glob('{$globPattern}');\n";
        
        if (str_contains($content, 'createInertiaApp')) {
            $content = str_replace('createInertiaApp', $injection . 'createInertiaApp', $content);
            $resolvePattern = '/resolve:\s*\(name\)\s*=>\s*resolvePageComponent\(/';
            $replacement = "resolve: (name) => {\n        if (name.includes('::')) {\n            const [domain, page] = name.split('::');\n            const path = Object.keys(domainPages).find(p => p.toLowerCase().includes(`/domain/\${domain.toLowerCase()}/frontend/pages/\${page.toLowerCase()}`));\n            if (path) return domainPages[path]();\n        }\n        return resolvePageComponent(";
            
            $content = preg_replace($resolvePattern, $replacement, $content);
            File::put($appPath, $content);
            $this->components->twoColumnDetail('Frontend Auto-Resolver', '<fg=green;options=bold>INSTALLED</>');
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
        
        $authValue = match (true) {
            str_contains($auth, 'Sanctum') => 'sanctum',
            str_contains($auth, 'Passport') => 'passport',
            default => 'none',
        };

        $modeValue = match (true) {
            str_contains($mode, 'React') => 'react',
            str_contains($mode, 'Vue') => 'vue',
            str_contains($mode, 'Livewire') => 'livewire',
            str_contains($mode, 'Blade') => 'blade',
            default => 'api',
        };

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
        $apiPath = base_path('routes/api.php');
        
        if (File::exists($apiPath)) {
            $content = File::get($apiPath);
            
            if (!str_contains($content, 'DomainHelpers::loadDomainRoutes()')) {
                $newContent = "<?php\n\nuse Samushi\Domion\Helpers\DomainHelpers;\n\nDomainHelpers::loadDomainRoutes();\n";
                File::put($apiPath, $newContent);
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

            if (!str_contains($content, 'useAppPath')) {
                $pattern = '/->create\(\)/';
                $replacement = "->useAppPath(realpath(__DIR__.'/../app/App'))\n    ->create()";
                
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
        $basePath = base_path('app/Domain/Auth');
        $folders = [
            $basePath . '/Actions',
            $basePath . '/Controllers',
            $basePath . '/Models',
            $basePath . '/Frontend/Pages',
        ];

        foreach ($folders as $folder) {
            if (!File::isDirectory($folder)) {
                File::makeDirectory($folder, 0755, true);
            }
        }

        // 1. Create Example Action (Extending ActionFactory)
        $actionContent = "<?php\n\nnamespace App\Domain\Auth\Actions;\n\nuse Samushi\Domion\Actions\ActionFactory;\n\nclass LoginAction extends ActionFactory\n{\n    public function handle(...\$args): mixed\n    {\n        // \$credentials = \$args[0];\n        // return auth()->attempt(\$credentials);\n        \n        return true;\n    }\n}\n";
        File::put($basePath . '/Actions/LoginAction.php', $actionContent);

        // 2. Create Example Controller
        $controllerContent = "<?php\n\nnamespace App\Domain\Auth\Controllers;\n\nuse App\App\Providers\RouteServiceProvider; // Fallback or custom\nuse Illuminate\Routing\Controller;\n\nclass LoginController extends Controller\n{\n    public function index()\n    {\n        return inertia('Auth/Login');\n    }\n}\n";
        File::put($basePath . '/Controllers/LoginController.php', $controllerContent);

        // 3. Create Example Frontend Page if React
        if ($mode === 'react') {
            $jsxContent = "import React from 'react';\n\nexport default function Login() {\n    return (\n        <div className=\"min-h-screen flex items-center justify-center bg-gray-100\">\n            <div className=\"bg-white p-8 rounded shadow-md w-96\">\n                <h1 className=\"text-2xl font-bold mb-6 text-center text-indigo-600\">Domion Starter Kit</h1>\n                <form className=\"space-y-4\">\n                    <div>\n                        <label className=\"block text-sm font-medium text-gray-700\">Email</label>\n                        <input type=\"email\" className=\"mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500\" defaultValue=\"admin@domion.com\" />\n                    </div>\n                    <div>\n                        <label className=\"block text-sm font-medium text-gray-700\">Password</label>\n                        <input type=\"password\" className=\"mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500\" defaultValue=\"password\" />\n                    </div>\n                    <button type=\"button\" className=\"w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700\">\n                        Sign in\n                    </button>\n                </form>\n            </div>\n        </div>\n    );\n}\n";
            File::put($basePath . '/Frontend/Pages/Login.tsx', $jsxContent);
        }
    }
}
