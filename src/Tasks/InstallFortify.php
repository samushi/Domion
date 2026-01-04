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
        $this->command->info('Installing and configuring Laravel Fortify (2FA)...');

        (new Process(['composer', 'require', 'laravel/fortify', 'pragmarx/google2fa-laravel'], base_path()))
            ->setTimeout(300)
            ->run(function ($type, $buffer) {
                // $this->command->getOutput()->write($buffer);
            });

        $this->command->call('vendor:publish', ['--provider' => 'Laravel\Fortify\FortifyServiceProvider']);
        $this->command->call('migrate');

        $this->configureConfig();
        $this->copyServiceProvider();
        $this->registerProvider();
        $this->updateUserModel();
    }

    protected function copyServiceProvider(): void
    {
        $targetPath = PathHelper::resolveAppPath('Providers/FortifyServiceProvider.php');
        $stubPath = __DIR__ . '/../stubs/FortifyServiceProvider.stub';
        
        if (File::exists($stubPath)) {
            File::ensureDirectoryExists(dirname($targetPath));
            File::put($targetPath, File::get($stubPath));
            $this->command->info("✓ FortifyServiceProvider copied to " . PathHelper::getAppPath() . "/Providers");
        }
    }

    protected function configureConfig(): void
    {
        $config = base_path('config/fortify.php');
        if (File::exists($config)) {
            $content = File::get($config);
            $content = str_replace("Features::registration(),", "// Features::registration(),", $content);
            $content = str_replace("Features::resetPasswords(),", "// Features::resetPasswords(),", $content);
            $content = str_replace("// Features::emailVerification(),", "// Features::emailVerification(),", $content);
            $content = str_replace("Features::updateProfileInformation(),", "// Features::updateProfileInformation(),", $content);
            $content = str_replace("Features::updatePasswords(),", "// Features::updatePasswords(),", $content);
            $content = str_replace("// Features::twoFactorAuthentication([", "Features::twoFactorAuthentication([", $content);
            File::put($config, $content);
        }
    }

    protected function registerProvider(): void
    {
        $providersFile = base_path('bootstrap/providers.php');

        if (File::exists($providersFile)) {
            $content = File::get($providersFile);
            
            // Cleanup any existing flawed variants
            $content = preg_replace('/\\s*App\\\\Providers\\\\FortifyServiceProvider::class,?/', '', $content);
            $content = str_replace('App\Providers\FortifyServiceProvider::class,', '', $content);
            
            // Add right at the beginning of the return array
            $content = preg_replace(
                '/(return\s*\[)/',
                "$1\n    App\\Providers\\FortifyServiceProvider::class,",
                $content
            );
            
            File::put($providersFile, $content);
        }

        // Also check config/app.php
        $appConfig = base_path('config/app.php');
        if (File::exists($appConfig)) {
            $content = File::get($appConfig);
            if (str_contains($content, "'providers' => [") && !str_contains($content, 'FortifyServiceProvider')) {
                $content = str_replace(
                    "'providers' => [",
                    "'providers' => [\n        App\\Providers\\FortifyServiceProvider::class,",
                    $content
                );
                File::put($appConfig, $content);
            }
        }

        $this->command->call('optimize:clear');
    }

    protected function updateUserModel(): void
    {
        $paths = [
            base_path('app/Domain/User/Models/User.php'),
            base_path('app/Models/User.php'),
            base_path('App/Domain/User/Models/User.php'),
        ];

        foreach ($paths as $userModel) {
            if (File::exists($userModel)) {
                $content = File::get($userModel);
                if (!str_contains($content, 'TwoFactorAuthenticatable')) {
                    // Add use statement
                    if (!str_contains($content, 'use Laravel\Fortify\TwoFactorAuthenticatable;')) {
                        $content = preg_replace(
                            '/namespace\s+[^;]+;/',
                            "$0\n\nuse Laravel\\Fortify\\TwoFactorAuthenticatable;",
                            $content
                        );
                    }
                    
                    // Add trait to class
                    $content = preg_replace(
                        '/use HasFactory, Notifiable;/',
                        'use HasFactory, Notifiable, TwoFactorAuthenticatable;',
                        $content
                    );

                    $content = preg_replace(
                        '/use HasApiTokens, HasFactory, Notifiable;/',
                        'use HasApiTokens, HasFactory, Notifiable, TwoFactorAuthenticatable;',
                        $content
                    );

                    File::put($userModel, $content);
                    $this->command->info("✓ TwoFactorAuthenticatable added to User model at {$userModel}");
                }
            }
        }
    }
}
