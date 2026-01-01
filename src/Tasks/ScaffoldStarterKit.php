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

        $this->scaffoldUserDomain();
        $this->scaffoldAuthDomain($mode);
        $this->scaffoldLandingDomain($mode);
        $this->scaffoldDashboardDomain($mode);
    }

    protected function scaffoldAuthDomain(string $mode): void
    {
        $this->command->info('Scaffolding Auth Domain...');
        $base = base_path('app/Domain/Auth');

        $this->ensureDirectories($base, $mode);

        $replacements = [
            'actionsNamespace' => 'App\Domain\Auth\Actions',
            'dtoNamespace' => 'App\Domain\Auth\Dto',
            'requestsNamespace' => 'App\Domain\Auth\Requests',
            'repositoryNamespace' => 'App\Domain\Auth\Repository',
            'modelNamespace' => 'App\Domain\User\Models',
            'domain' => 'Auth',
        ];

        // 1. Service Provider
        $this->generateFile('AuthServiceProvider', $base . '/Providers/AuthServiceProvider.php', array_merge($replacements, [
            'namespace' => 'App\Domain\Auth\Providers',
        ]));

        // 2. Controller
        $controllerStub = $mode === 'api' ? 'ApiAuthController' : 'AuthController';
        $this->generateFile($controllerStub, $base . '/Controllers/AuthController.php', array_merge($replacements, [
            'namespace' => 'App\Domain\Auth\Controllers',
            'base_controller' => $this->getBaseController($mode)
        ]));

        // 3. Routes
        $this->generateFile('AuthRoutes', $base . '/web.php');

        // Actions
        $this->generateFile('LoginAction', $base . '/Actions/LoginAction.php', array_merge($replacements, ['namespace' => $replacements['actionsNamespace']]));
        $this->generateFile('LogoutAction', $base . '/Actions/LogoutAction.php', array_merge($replacements, ['namespace' => $replacements['actionsNamespace']]));
        $this->generateFile('RegisterAction', $base . '/Actions/RegisterAction.php', array_merge($replacements, ['namespace' => $replacements['actionsNamespace']]));
        $this->generateFile('ForgotPasswordAction', $base . '/Actions/ForgotPasswordAction.php', array_merge($replacements, ['namespace' => $replacements['actionsNamespace']]));
        $this->generateFile('ResetPasswordAction', $base . '/Actions/ResetPasswordAction.php', array_merge($replacements, ['namespace' => $replacements['actionsNamespace']]));
        
        // DTOs
        $this->generateFile('LoginDto', $base . '/Dto/LoginDto.php', array_merge($replacements, ['namespace' => $replacements['dtoNamespace']]));
        $this->generateFile('RegisterDto', $base . '/Dto/RegisterDto.php', array_merge($replacements, ['namespace' => $replacements['dtoNamespace']]));
        
        // Requests
        $this->generateFile('LoginRequest', $base . '/Requests/LoginRequest.php', array_merge($replacements, ['namespace' => $replacements['requestsNamespace'], 'class' => 'LoginRequest']));
        $this->generateFile('RegisterRequest', $base . '/Requests/RegisterRequest.php', array_merge($replacements, ['namespace' => $replacements['requestsNamespace']]));
        $this->generateFile('ForgotPasswordRequest', $base . '/Requests/ForgotPasswordRequest.php', array_merge($replacements, ['namespace' => $replacements['requestsNamespace']]));
        $this->generateFile('ResetPasswordRequest', $base . '/Requests/ResetPasswordRequest.php', array_merge($replacements, ['namespace' => $replacements['requestsNamespace']]));
        
        // Repository
        $this->generateFile('UserRepository', $base . '/Repository/UserRepository.php', array_merge($replacements, ['namespace' => $replacements['repositoryNamespace']]));

        // 5. Frontend
        if ($mode !== 'api') {
            $this->scaffoldAuthFrontend($mode, $base);
        }
    }

    protected function scaffoldDashboardDomain(string $mode): void
    {
        $this->command->info('Scaffolding Dashboard Domain...');
        $base = base_path('app/Domain/Dashboard');

        $this->ensureDirectories($base, $mode);

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

        $this->ensureDirectories($base, $mode);

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

        foreach ($pages as $page) {
            $stub = ucfirst($mode) . $page;
            $target = $this->getFrontendPath($mode, $basePath, $page);
            
            $replacements = [];
            if ($mode === 'livewire') {
                $replacements = [
                    'namespace' => 'App\Domain\Auth\Frontend\Livewire',
                    'domain' => 'auth'
                ];
                
                // Also generate the view for Livewire
                $viewStub = $stub . 'View';
                $viewTarget = $basePath . "/Frontend/Views/livewire/" . Str::lower($page) . ".blade.php";
                $this->generateFile($viewStub, $viewTarget);
            }
            
            $this->generateFile($stub, $target, $replacements);
        }
    }

    protected function scaffoldDashboardFrontend(string $mode, string $basePath): void
    {
        $stub = ucfirst($mode) . 'Dashboard';
        $target = $this->getFrontendPath($mode, $basePath, 'Dashboard');
        
        $replacements = [];
        if ($mode === 'livewire') {
            $replacements = [
                'namespace' => 'App\Domain\Dashboard\Frontend\Livewire',
                'domain' => 'dashboard'
            ];
            
            $this->generateFile('LivewireDashboardView', $basePath . '/Frontend/Views/livewire/dashboard.blade.php');
        }

        $this->generateFile($stub, $target, $replacements);
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
            'react' => base_path('app/Support/Frontend/Components/Logo.tsx'),
            'vue' => base_path('app/Support/Frontend/Components/Logo.vue'),
            default => base_path('app/Support/Frontend/Views/components/logo.blade.php'),
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

    protected function ensureDirectories(string $base, string $mode): void
    {
        $coreFolders = ['Actions', 'Controllers', 'Dto', 'Providers', 'Repository', 'Requests'];
        foreach ($coreFolders as $folder) {
            File::ensureDirectoryExists($base . '/' . $folder);
        }
        
        if (in_array($mode, ['react', 'vue'])) {
            File::ensureDirectoryExists($base . '/Frontend/Pages');
        }
        
        if (in_array($mode, ['blade', 'livewire'])) {
            File::ensureDirectoryExists($base . '/Frontend/Views/pages');
        }

        if ($mode === 'livewire') {
            File::ensureDirectoryExists($base . '/Frontend/Views/livewire');
            File::ensureDirectoryExists($base . '/Frontend/Livewire');
        }
    }

    protected function getBaseController(string $mode): string
    {
        return match($mode) {
            'react', 'vue' => 'InertiaControllers',
            'api' => 'ApiControllers',
            default => 'WebControllers'
        };
    }

    protected function scaffoldUserDomain(): void
    {
        $this->command->info('Scaffolding User Domain (Models & Core)...');
        $base = base_path('app/Domain/User');

        File::ensureDirectoryExists($base . '/Models');
        File::ensureDirectoryExists($base . '/Repository');

        // 1. User Model
        $targetUserPath = $base . '/Models/User.php';
        if (File::exists($targetUserPath)) {
            return;
        }

        $oldUserPath = base_path('app/Models/User.php');
        if (File::exists($oldUserPath)) {
            $content = File::get($oldUserPath);
            $content = str_replace('namespace App\Models;', 'namespace App\Domain\User\Models;', $content);
            File::put($targetUserPath, $content);
        } else {
            $this->generateFile('UserModel', $targetUserPath, [
                'namespace' => 'App\Domain\User\Models'
            ]);
        }
    }
}