<?php

declare(strict_types=1);

namespace Samushi\Domion\Commands;

use Illuminate\Console\Command;
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
        \Laravel\Prompts\spin(
            function () use ($config) {
                // Task: Structure & Cleanup (This also fixes the routes files)
                (new ConfigureArchitecture($this))->run($config['tenancy']);

                // Task: Frontend Setup
                if (in_array($config['mode'], ['react', 'vue', 'livewire'])) {
                    (new InstallFrontend($this))->run($config['mode']);
                }

                // Task: Starter Kit (Generates Auth/Landing Providers with correct prefixes)
                if ($config['starterKit']) {
                    (new InstallAuth($this))->run($config['auth']);
                    (new ScaffoldStarterKit($this))->run($config['mode']);
                }

                $this->saveConfiguration($config);
            },
            'Configuring Architecture...'
        );

        \Laravel\Prompts\outro('✅ Setup completed!');
        $this->info('Run: <fg=green>composer dump-autoload</>');

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