<?php

declare(strict_types=1);

namespace Samushi\Domion\Tasks;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

class InstallFortify
{
    public function __construct(protected Command $command) {}

    public function run(): void
    {
        $this->command->info('Installing and configuring Laravel Fortify (2FA)...');

        (new Process(['composer', 'require', 'laravel/fortify'], base_path()))
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
        $targetPath = base_path('app/Providers/FortifyServiceProvider.php');
        $stubPath = __DIR__ . '/../stubs/FortifyServiceProvider.stub';
        
        if (File::exists($stubPath)) {
            File::ensureDirectoryExists(dirname($targetPath));
            File::copy($stubPath, $targetPath);
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
        if (!File::exists($providersFile)) {
            return;
        }

        $content = File::get($providersFile);
        
        // Remove old registration if exists with wrong formatting
        $content = str_replace('App\Providers\FortifyServiceProvider::class,', '', $content);
        
        if (!str_contains($content, 'App\\Providers\\FortifyServiceProvider::class')) {
            $content = preg_replace(
                '/(return\s*\[)/',
                "$1\n    App\\Providers\\FortifyServiceProvider::class,",
                $content
            );
            File::put($providersFile, $content);
        }

        // Force clear cache so Laravel sees the new provider
        $this->command->call('optimize:clear');
    }

    protected function updateUserModel(): void
    {
        $userModel = base_path('app/Domain/User/Models/User.php');
        if (File::exists($userModel)) {
             $content = File::get($userModel);
             if (!str_contains($content, 'TwoFactorAuthenticatable')) {
                 $content = str_replace("use Illuminate\Notifications\Notifiable;", "use Illuminate\Notifications\Notifiable;\nuse Laravel\Fortify\TwoFactorAuthenticatable;", $content);
                 $content = str_replace("use HasApiTokens, HasFactory, Notifiable;", "use HasApiTokens, HasFactory, Notifiable, TwoFactorAuthenticatable;", $content);
                 $content = str_replace("use HasFactory, Notifiable;", "use HasFactory, Notifiable, TwoFactorAuthenticatable;", $content);
                 File::put($userModel, $content);
             }
        }
    }
}
