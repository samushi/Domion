<?php

declare(strict_types=1);

namespace Samushi\Domion\Tasks;

use Illuminate\Console\Command;

class InstallAuth
{
    public function __construct(protected Command $command) {}

    public function run(string $driver): void
    {
        if ($driver === 'none') {
            return;
        }

        $this->command->info("Installing authentication driver: {$driver}...");

        $package = match ($driver) {
            'sanctum' => 'laravel/sanctum',
            'passport' => 'laravel/passport',
            default => null,
        };

        if ($package) {
            $this->installPackage($package);
            $this->publishConfiguration($driver);
        }
    }

    protected function installPackage(string $package): void
    {
        // Use quiet mode to avoid cluttering the console, unless error occurs
        exec("composer require {$package} --quiet", $output, $returnVar);

        if ($returnVar !== 0) {
            $this->command->error("Failed to install {$package}. Please check your composer configuration.");
        } else {
            $this->command->info("✓ Package {$package} installed successfully.");
        }
    }

    protected function publishConfiguration(string $driver): void
    {
        if ($driver === 'sanctum') {
            exec('php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"');
        } elseif ($driver === 'passport') {
            exec('php artisan vendor:publish --tag=passport-config');
            // Passport often requires migration/install
            // We leave actual migration running to the user or SetupCommand final steps
        }
    }
}