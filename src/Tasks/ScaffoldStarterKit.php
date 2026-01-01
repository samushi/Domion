<?php

declare(strict_types=1);

namespace Samushi\Domion\Tasks;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ScaffoldStarterKit
{
    public function __construct(protected Command $command) {}

    public function run(string $mode): void
    {
        if ($mode !== 'api') {
            $this->generateLogo($mode);
        }

        $this->scaffoldAuthDomain($mode);
        $this->scaffoldLandingDomain($mode);
        $this->scaffoldDashboardDomain($mode);
    }

    protected function scaffoldAuthDomain(string $mode): void
    {
        $this->command->info('Scaffolding Auth Domain...');
        $base = base_path('app/Domain/Auth');

        $this->ensureDirectories($base);

        // 1. Service Provider
        $this->generateFile('AuthServiceProvider', $base . '/Providers/AuthServiceProvider.php', [
            'namespace' => 'App\Domain\Auth\Providers',
            'domain' => 'Auth'
        ]);

        // 2. Controller
        $controllerStub = $mode === 'api' ? 'ApiAuthController' : 'AuthController';
        $this->generateFile($controllerStub, $base . '/Controllers/AuthController.php', [
            'namespace' => 'App\Domain\Auth\Controllers',
            'base_controller' => $this->getBaseController($mode)
        ]);

        // 3. Routes
        $this->generateFile('AuthRoutes', $base . '/web.php');

        // 4. Frontend
        if ($mode !== 'api') {
            $this->scaffoldAuthFrontend($mode, $base);
        }
    }

    protected function scaffoldDashboardDomain(string $mode): void
    {
        $this->command->info('Scaffolding Dashboard Domain...');
        $base = base_path('app/Domain/Dashboard');

        $this->ensureDirectories($base);

        // 1. Service Provider
        $this->generateFile('DashboardServiceProvider', $base . '/Providers/DashboardServiceProvider.php', [
            'namespace' => 'App\Domain\Dashboard\Providers'
        ]);

        // 2. Controller
        $this->generateFile('DashboardController', $base . '/Controllers/DashboardController.php', [
            'namespace' => 'App\Domain\Dashboard\Controllers',
            'base_controller' => $this->getBaseController($mode)
        ]);

        // 3. Routes
        $this->generateFile('DashboardRoutes', $base . '/web.php');

        // 4. Frontend
        if ($mode !== 'api') {
            $this->scaffoldDashboardFrontend($mode, $base);
        }
    }

    protected function scaffoldLandingDomain(string $mode): void
    {
        $this->command->info('Scaffolding Landing Domain (Root)...');
        $base = base_path('app/Domain/Landing');

        $this->ensureDirectories($base);

        // 1. Service Provider
        $this->generateFile('LandingServiceProvider', $base . '/Providers/LandingServiceProvider.php', [
            'namespace' => 'App\Domain\Landing\Providers',
            'domain' => 'Landing'
        ]);

        // 2. Controller
        $this->generateFile('LandingController', $base . '/Controllers/LandingController.php', [
            'namespace' => 'App\Domain\Landing\Controllers',
            'base_controller' => $this->getBaseController($mode)
        ]);

        // 3. Routes
        $this->generateFile('LandingRoutes', $base . '/web.php');

        // 4. Frontend
        if ($mode !== 'api') {
            $this->scaffoldLandingView($mode, $base);
        }
    }

    protected function scaffoldAuthFrontend(string $mode, string $basePath): void
    {
        $pages = ['Login', 'Register', 'ForgotPassword', 'ResetPassword'];
        $ext = $this->getExtension($mode);

        foreach ($pages as $page) {
            $stub = ucfirst($mode) . $page;
            $target = $this->getFrontendPath($mode, $basePath, $page);
            
            $replacements = [];
            if ($mode === 'livewire') {
                $replacements = [
                    'namespace' => 'App\Domain\Auth\Frontend\Livewire',
                    'domain' => 'auth'
                ];
            }
            
            $this->generateFile($stub, $target, $replacements);
        }
    }

    protected function scaffoldDashboardFrontend(string $mode, string $basePath): void
    {
        $stub = ucfirst($mode) . 'Dashboard';
        $target = $this->getFrontendPath($mode, $basePath, 'Dashboard');
        $this->generateFile($stub, $target);
    }

    protected function scaffoldLandingView(string $mode, string $basePath): void
    {
        $stub = ucfirst($mode) . 'Landing';
        $target = $this->getFrontendPath($mode, $basePath, 'Landing');

        if ($mode === 'livewire') {
            $this->generateFile('LivewireLanding', $basePath . '/Frontend/Livewire/Landing.php', [
                'namespace' => 'App\Domain\Landing\Frontend\Livewire',
                'domain' => 'landing'
            ]);
            $this->generateFile('LivewireLandingView', $basePath . '/Frontend/Views/livewire/landing.blade.php');
        } else {
            $this->generateFile($stub, $target);
        }
    }

    protected function getFrontendPath(string $mode, string $basePath, string $name): string
    {
        return match($mode) {
            'react' => $basePath . "/Frontend/Pages/{$name}.tsx",
            'vue' => $basePath . "/Frontend/Pages/{$name}.vue",
            'livewire' => $basePath . "/Frontend/Livewire/{$name}.php",
            'blade' => $basePath . "/Frontend/Views/pages/" . Str::lower($name) . ".blade.php",
            default => $basePath . "/Frontend/{$name}.js"
        };
    }

    protected function getExtension(string $mode): string
    {
        return match($mode) {
            'react' => 'tsx',
            'vue' => 'vue',
            'livewire', 'blade' => 'php',
            default => 'js'
        };
    }

    protected function generateLogo(string $mode): void
    {
        $stub = 'Logo';
        // Logic to determine target based on stack
        $target = match($mode) {
            'react' => resource_path('js/components/Logo.tsx'),
            'vue' => resource_path('js/components/Logo.vue'),
            default => resource_path('views/components/logo.blade.php'),
        };

        // Special handling for wrapping SVG in Component syntax
        $content = file_get_contents(__DIR__ . '/../stubs/Logo.stub');

        if ($mode === 'react') {
            $content = "export default function Logo(props: React.SVGProps<SVGSVGElement>) { return ({$content}); }";
            $content = str_replace('{{attributes}}', '{...props}', $content);
        } elseif ($mode === 'vue') {
            $content = "<template>{$content}</template>";
            $content = str_replace('{{attributes}}', '', $content);
        } else {
            $content = str_replace('{{attributes}}', '{{ $attributes }}', $content);
        }

        File::ensureDirectoryExists(dirname($target));
        File::put($target, $content);
    }

    protected function generateFile(string $stubName, string $targetPath, array $replacements = []): void
    {
        $stubPath = __DIR__ . "/../stubs/{$stubName}.stub";

        if (!File::exists($stubPath)) {
            // Fallback or Error
            $this->command->error("Stub not found: $stubName");
            return;
        }

        $content = File::get($stubPath);

        foreach ($replacements as $key => $value) {
            $content = str_replace("{{{$key}}}", $value, $content);
        }

        File::put($targetPath, $content);
    }

    protected function ensureDirectories(string $base): void
    {
        File::ensureDirectoryExists($base . '/Providers');
        File::ensureDirectoryExists($base . '/Controllers');
        File::ensureDirectoryExists($base . '/Frontend/Pages');
        File::ensureDirectoryExists($base . '/Frontend/Views/pages');
        File::ensureDirectoryExists($base . '/Frontend/Views/livewire');
        File::ensureDirectoryExists($base . '/Frontend/Livewire');
    }

    protected function getBaseController(string $mode): string
    {
        return match($mode) {
            'react', 'vue' => 'InertiaControllers',
            'api' => 'ApiControllers',
            default => 'WebControllers'
        };
    }
}