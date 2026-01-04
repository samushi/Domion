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
            ->run();

        // Register the provider and copy stubs
        $this->copyServiceProvider();
        $this->registerProvider();
        $this->configureConfig();
        $this->updateUserModel();

        $this->command->info('✓ Fortify prepared.');
    }

    protected function getAppPath(): string
    {
        return PathHelper::getAppPath();
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
            $content = str_replace("'features' => [", "'features' => [\n        Features::twoFactorAuthentication([\n            'confirmPassword' => true,\n        ]),", $content);
            File::put($configPath, $content);
        }
    }

    protected function updateUserModel(): void
    {
        $appPath = PathHelper::getAppPath();
        $paths = [
            base_path($appPath . '/Domain/User/Models/User.php'),
            base_path('app/Domain/User/Models/User.php'),
            base_path('app/Models/User.php'),
        ];

        foreach ($paths as $userModel) {
            if (File::exists($userModel)) {
                $content = File::get($userModel);
                if (!str_contains($content, 'TwoFactorAuthenticatable')) {
                    $content = preg_replace('/(namespace\s+[^;]+;)/', "$1\n\nuse Laravel\\Fortify\\TwoFactorAuthenticatable;", $content);
                    $content = preg_replace('/use\s+([^;]+)Notifiable;/', "use $1Notifiable, TwoFactorAuthenticatable;", $content);
                    File::put($userModel, $content);
                }
            }
        }
    }
}
