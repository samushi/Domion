<?php

declare(strict_types=1);

namespace Samushi\Domion\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
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

        // --- Execution Phase ---

        \Laravel\Prompts\spin(
            fn() => $this->setupFolders($tenancy),
            'Scaffolding directory structure...'
        );

        $this->setupDependencies($mode, $auth, $tenancy);

        \Laravel\Prompts\spin(
            function() use ($mode) {
                $this->setupRoutes();
                $this->setupBootstrap();
                $this->setupComposer();
                
                if (in_array($mode, ['react', 'vue'])) {
                    $this->setupFrontend($mode);
                    $this->setupVite();
                    $this->setupJsConfig();
                }
            },
            'Configuring application core...'
        );

        $this->saveConfiguration($auth, $mode, $tenancy);

        \Laravel\Prompts\outro('✅ Domion Setup completed successfully!');
        $this->info('Please run: <fg=green;options=bold>composer dump-autoload</>');
        
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
            exec("composer require {$packages}");
        }

        if (!empty($npmPackages) && $this->confirm('Install JS dependencies (NPM)?', true)) {
            $this->info('Installing: ' . implode(', ', $npmPackages));
            $packages = implode(' ', $npmPackages);
            exec("npm install {$packages} --save-dev");
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
            'app/Domain', 
            'app/Support',
            'app/Support/Contracts',
            'app/Support/Constants',
            'app/Support/Traits',
            'app/Support/ValueObjects',
        ];

        if ($tenancy) {
            $folders[] = 'app/Domain/Central';
            $folders[] = 'app/Domain/Tenant';
        }
        
        $this->output->progressStart(count($folders));

        foreach ($folders as $folder) {
            if (!File::isDirectory(base_path($folder))) {
                File::makeDirectory(base_path($folder), 0755, true);
            }
            $this->output->progressAdvance();
            usleep(100000); 
        }

        $this->output->progressFinish();
        $this->components->twoColumnDetail('Directory Structure', '<fg=green;options=bold>READY</>');
    }

    protected function setupRoutes(): void
    {
        $apiPath = base_path('routes/api.php');
        
        if (File::exists($apiPath)) {
            if ($this->confirm('Do you want to automate Domain Routes loading in routes/api.php?', true)) {
                $content = File::get($apiPath);
                
                if (!str_contains($content, 'DomainHelpers::loadDomainRoutes()')) {
                    $newContent = "<?php\n\nuse Samushi\Domion\Helpers\DomainHelpers;\n\nDomainHelpers::loadDomainRoutes();\n";
                    File::put($apiPath, $newContent);
                    $this->components->twoColumnDetail('Route Configuration (api.php)', '<fg=green;options=bold>MODIFIED</>');
                } else {
                    $this->components->twoColumnDetail('Route Configuration (api.php)', '<fg=yellow;options=bold>EXISTS</>');
                }
            }
        }
    }

    protected function setupBootstrap(): void
    {
        $bootstrapPath = base_path('bootstrap/app.php');

        if (File::exists($bootstrapPath)) {
            if ($this->confirm('Do you want to attempt automatic modification of bootstrap/app.php?', true)) {
                $content = File::get($bootstrapPath);

                if (!str_contains($content, 'useAppPath')) {
                    $pattern = '/->create\(\)/';
                    $replacement = "->create()\n    ->useAppPath(realpath(__DIR__.'/../app/App'))";
                    
                    if (preg_match($pattern, $content)) {
                        $newContent = preg_replace($pattern, $replacement, $content);
                        File::put($bootstrapPath, $newContent);
                        $this->components->twoColumnDetail('Bootstrap Path Update', '<fg=green;options=bold>SUCCESS</>');
                    } else {
                        $this->warn('Could not find ->create() call. Please add useAppPath manually.');
                    }
                } else {
                    $this->components->twoColumnDetail('Bootstrap Path Update', '<fg=yellow;options=bold>EXISTS</>');
                }
            }
        }
    }

    protected function setupComposer(): void
    {
        $composerPath = base_path('composer.json');

        if (File::exists($composerPath)) {
            if ($this->confirm('Do you want to automatically update PSR-4 in composer.json?', true)) {
                $composer = json_decode(File::get($composerPath), true);

                $composer['autoload']['psr-4']['App\\'] = 'app/App/';
                $composer['autoload']['psr-4']['App\\Domain\\'] = 'app/Domain/';
                $composer['autoload']['psr-4']['App\\Support\\'] = 'app/Support/';

                File::put(
                    $composerPath, 
                    json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL
                );

                $this->components->twoColumnDetail('composer.json Update', '<fg=green;options=bold>SUCCESS</>');
            }
        }
    }
}
