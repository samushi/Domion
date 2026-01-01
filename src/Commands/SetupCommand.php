<?php

declare(strict_types=1);

namespace Samushi\Domion\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Samushi\Domion\Tasks\ConfigureArchitecture;
use Samushi\Domion\Tasks\InstallAuth;
use Samushi\Domion\Tasks\InstallFrontend;
use Samushi\Domion\Tasks\ScaffoldStarterKit;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'domion:setup', description: 'Setup the DDD architecture folders and initial structure')]
class SetupCommand extends Command
{
    public function handle(): int
    {
        \Laravel\Prompts\intro('🏗️  Domion: Professional DDD Setup');

        if (!\Laravel\Prompts\confirm('Initialize Domion architecture?', true)) {
            return self::FAILURE;
        }

        // 1. Gather Configuration
        $config = $this->gatherConfiguration();

        // 2. Execute Tasks
        
        // Task 1: Architecture
        \Laravel\Prompts\spin(
            fn() => (new ConfigureArchitecture($this))->run($config['tenancy'], $config['mode']),
            'Configuring Architecture folders and namespaces...'
        );

        // Task 2: Refresh Autoloader & Discovery
        \Laravel\Prompts\spin(
            function() {
                // Pre-emptively clear cache if possible to avoid discovery errors
                File::delete(base_path('bootstrap/cache/services.php'));
                File::delete(base_path('bootstrap/cache/packages.php'));
                File::delete(base_path('bootstrap/cache/config.php'));

                exec('composer dump-autoload 2>&1', $output, $returnVar);
            },
            'Refreshing autoloader and package discovery...'
        );

        // Task 3: Frontend Setup
        if (in_array($config['mode'], ['react', 'vue', 'livewire'])) {
            $frontend = new InstallFrontend($this);
            
            \Laravel\Prompts\spin(
                fn() => $frontend->configureVite(),
                'Configuring Vite and Aliases...'
            );
            
            \Laravel\Prompts\spin(
                fn() => $frontend->configureTailwind($config['mode']),
                'Configuring Tailwind CSS...'
            );

            \Laravel\Prompts\spin(
                fn() => $frontend->installNpmDependencies($config['mode']),
                'Installing NPM dependencies (this may take a minute)...'
            );

            if (in_array($config['mode'], ['react', 'vue'])) {
                \Laravel\Prompts\spin(
                    fn() => $frontend->setupShadcn($config['mode']),
                    'Initializing Shadcn UI and Components...'
                );
            }
        }

        // Task 4: Starter Kit
        if ($config['starterKit']) {
            \Laravel\Prompts\spin(
                function() use ($config) {
                    (new InstallAuth($this))->run($config['auth']);
                    (new ScaffoldStarterKit($this))->run($config['mode']);
                },
                'Scaffolding Auth and Dashboard domains...'
            );
        }

        // Task 5: Database Migrations
        \Laravel\Prompts\spin(
            function() {
                exec('php artisan migrate --force', $output, $returnVar);
            },
            'Running database migrations...'
        );

        // Task 6: Permissions & Cleanup
        \Laravel\Prompts\spin(
            fn() => $this->saveConfiguration($config),
            'Finalizing configuration...'
        );

        \Laravel\Prompts\outro('✅ Setup completed successfully!');
        
        $this->info("\n🚀 Your DDD project is ready!");
        $this->line("• Domains created: <fg=green>Auth, Dashboard, Landing (Root)</>");
        $this->line("• Frontend: <fg=green>{$config['mode']}</>");
        $this->line("• Shadcn UI: <fg=green>Initialized</>");

        if ($this->confirm('Do you want to start the development server now?', true)) {
            $this->info("\n✨ Starting development server (npm run dev)...");
            passthru('npm run dev');
        }

        return self::SUCCESS;
    }

    protected function gatherConfiguration(): array
    {
        return [
            'tenancy' => \Laravel\Prompts\select('Architecture:', ['standard' => 'Standard', 'tenancy' => 'Multi-tenancy'], 'standard') === 'tenancy',
            'mode' => \Laravel\Prompts\select('Frontend Stack:', ['api', 'react', 'vue', 'livewire'], 'react'),
            'auth' => \Laravel\Prompts\select('Auth Driver:', ['sanctum', 'passport', 'none'], 'sanctum'),
            'starterKit' => \Laravel\Prompts\confirm('Install Starter Kit (Auth/Dashboard)?', true),
        ];
    }

    protected function saveConfiguration(array $config): void
    {
        $content = "<?php\n\nreturn [\n    'auth' => '{$config['auth']}',\n    'mode' => '{$config['mode']}',\n    'tenancy' => " . ($config['tenancy'] ? 'true' : 'false') . ",\n];";
        file_put_contents(config_path('domion.php'), $content);
    }
}