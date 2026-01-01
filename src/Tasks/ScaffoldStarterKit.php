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
    }

    protected function scaffoldAuthDomain(string $mode): void
    {
        $this->command->info('Scaffolding Auth Domain...');
        $base = base_path('app/Domain/Auth');

        $this->ensureDirectories($base);

        // 1. Service Provider (Standard)
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
    }

    protected function scaffoldLandingDomain(string $mode): void
    {
        $this->command->info('Scaffolding Landing Domain (Root)...');
        $base = base_path('app/Domain/Landing');

        $this->ensureDirectories($base);

        // 1. Service Provider (Root Prefix)
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

        // 4. Frontend View
        if ($mode !== 'api') {
            $this->scaffoldLandingView($mode, $base);
        }
    }

    protected function scaffoldLandingView(string $mode, string $basePath): void
    {
        $stub = match($mode) {
            'react' => 'ReactLanding',
            'vue' => 'VueLanding',
            'livewire' => 'LivewireLanding', // Component class
            'blade' => 'BladeLanding',
        };

        $target = match($mode) {
            'react' => $basePath . '/Frontend/Pages/Landing.tsx',
            'vue' => $basePath . '/Frontend/Pages/Landing.vue',
            'livewire' => $basePath . '/Livewire/Landing.php',
            'blade' => $basePath . '/Resources/views/pages/landing.blade.php',
        };

        if ($mode === 'livewire') {
            // Livewire needs both Class and View
            $this->generateFile('LivewireLanding', $basePath . '/Livewire/Landing.php');
            $this->generateFile('LivewireLandingView', $basePath . '/Resources/views/livewire/landing.blade.php');
        } else {
            $this->generateFile($stub, $target);
        }
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
        File::ensureDirectoryExists($base . '/Frontend/Pages'); // Optional based on mode
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