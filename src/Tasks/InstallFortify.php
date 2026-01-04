<?php

declare(strict_types=1);

namespace Samushi\Domion\Tasks;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;
use Samushi\Domion\Support\PathHelper;

class InstallFortify
{
    public function __construct(protected Command $command) {}

    public function run(): void
    {
        $this->command->info('Installing Laravel Fortify (2FA)...');

        // Install Fortify package
        (new Process(['composer', 'require', 'laravel/fortify'], base_path()))
            ->setTimeout(300)
            ->run();

        // Publish Fortify config and migrations
        (new Process(['php', 'artisan', 'vendor:publish', '--provider=Laravel\Fortify\FortifyServiceProvider'], base_path()))
            ->setTimeout(60)
            ->run();

        // Copy our custom service provider (overwrites the default one)
        $this->copyServiceProvider();
        
        // Register the provider in bootstrap/providers.php
        $this->registerProvider();
        
        // Configure Fortify features
        $this->configureConfig();
        
        // Add TwoFactorAuthenticatable trait to User model
        $this->updateUserModel();

        $this->command->info('✓ Fortify configured.');
    }

    protected function copyServiceProvider(): void
    {
        // FortifyServiceProvider goes to the PHP namespace path (app/App/Providers or app/Providers)
        $targetPath = PathHelper::resolveAppPath('Providers/FortifyServiceProvider.php');
        $stubPath = __DIR__ . '/../stubs/FortifyServiceProvider.stub';
        
        if (File::exists($stubPath)) {
            File::ensureDirectoryExists(dirname($targetPath));
            File::put($targetPath, File::get($stubPath));
            $this->command->info("✓ FortifyServiceProvider created");
        }
    }

    protected function registerProvider(): void
    {
        $providersFile = base_path('bootstrap/providers.php');
        if (File::exists($providersFile)) {
            $content = File::get($providersFile);
            if (!str_contains($content, 'FortifyServiceProvider')) {
                $content = str_replace('];', "    App\\Providers\\FortifyServiceProvider::class,\n];", $content);
                File::put($providersFile, $content);
            }
        }
    }

    protected function configureConfig(): void
    {
        $configPath = base_path('config/fortify.php');
        if (File::exists($configPath)) {
            $content = File::get($configPath);
            // Enable 2FA feature with confirmation required
            if (!str_contains($content, 'twoFactorAuthentication')) {
                $content = str_replace(
                    "'features' => [",
                    "'features' => [\n        Features::twoFactorAuthentication([\n            'confirm' => true,\n            'confirmPassword' => true,\n        ]),",
                    $content
                );
                File::put($configPath, $content);
            }
        }
    }

    protected function updateUserModel(): void
    {
        // User model is in app/Domain, NOT in app/App/Domain
        $projectRoot = PathHelper::getProjectAppRoot();
        
        $paths = [
            base_path($projectRoot . '/Domain/User/Models/User.php'),
            base_path('app/Models/User.php'),
        ];

        foreach ($paths as $userModel) {
            if (File::exists($userModel)) {
                $content = File::get($userModel);
                
                // Skip if already has the trait
                if (str_contains($content, 'TwoFactorAuthenticatable')) {
                    continue;
                }
                
                // Add use statement after namespace
                if (!str_contains($content, 'use Laravel\Fortify\TwoFactorAuthenticatable;')) {
                    $content = preg_replace(
                        '/(namespace\s+[^;]+;)/',
                        "$1\n\nuse Laravel\\Fortify\\TwoFactorAuthenticatable;",
                        $content
                    );
                }
                
                // Add trait to use statement in class
                if (preg_match('/use\s+HasFactory,\s*Notifiable;/', $content)) {
                    $content = preg_replace(
                        '/use\s+HasFactory,\s*Notifiable;/',
                        'use HasFactory, Notifiable, TwoFactorAuthenticatable;',
                        $content
                    );
                }
                
                File::put($userModel, $content);
                $this->command->info("✓ User model updated with TwoFactorAuthenticatable");
                break; // Only update first found
            }
        }
    }
}
