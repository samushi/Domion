<?php

declare(strict_types=1);

namespace Samushi\Domion\Commands;

use Samushi\Domion\Helpers\DomainHelpers;
use Exception;
use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Facades\File;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command as ConsoleCommand;
use Symfony\Component\Console\Input\InputArgument;

#[AsCommand(
    name: 'domion:make:domain',
    description: 'Create a new domain structure',
)]
class MakeDomain extends GeneratorCommand
{
    /**
     * Handle the command.
     */
    public function handle(): int
    {
        try {
            $domain = $this->argument('domain');
            
            \Laravel\Prompts\intro("Creating domain: {$domain}");

            $tenancy = config('domion.tenancy', false);
            $scope = '';
            
            if ($tenancy) {
                $scope = \Laravel\Prompts\select(
                    label: 'Is this a Central or Tenant domain?',
                    options: ['Central', 'Tenant'],
                    default: 'Tenant'
                );
            }

            $basePath = DomainHelpers::basePath($domain, $scope);
            $namespace = DomainHelpers::baseNamespace($domain, $scope);
            
            File::ensureDirectoryExists($basePath);
            
            // 1. Core Folders
            $folders = [
                'Actions',
                'Controllers',
                'Database/Migrations',
                'Database/Seeders',
                'Dto',
                'Events',
                'Listeners',
                'Jobs',
                'Models',
                'Observers',
                'Providers',
                'Repository',
                'Requests',
                'Resources/Lang',
                'Tests/Unit',
                'Tests/Feature',
                'ValueObjects',
            ];

            // 2. Mode-specific Resource Folders
            $mode = config('domion.mode', 'api');

            if (in_array($mode, ['react', 'vue'])) {
                $folders[] = 'Frontend/Pages';
                $folders[] = 'Frontend/Components';
                $folders[] = 'Frontend/' . ($mode === 'react' ? 'Hooks' : 'Composables');
                $folders[] = 'Frontend/Utils';
                $folders[] = 'Resources/Lang';
            } elseif ($mode === 'livewire') {
                $folders[] = 'Livewire';
                $folders[] = 'Resources/views/livewire';
                $folders[] = 'Resources/views/components';
                $folders[] = 'Resources/Lang';
            } elseif ($mode === 'blade') {
                $folders[] = 'Resources/views/pages';
                $folders[] = 'Resources/views/components';
                $folders[] = 'Resources/Lang';
            }

            foreach ($folders as $folder) {
                File::ensureDirectoryExists($basePath . DIRECTORY_SEPARATOR . $folder);
            }

            $this->createBaseController($domain, $mode);
            $this->createBaseRepository($domain);
            $this->createExampleAction($domain);
            $this->createExampleJob($domain);
            $this->createExampleEvent($domain);
            $this->createApiRoutes($domain);
            $this->createServiceProvider($domain);

            $this->info("Domain '{$domain}' structure created successfully!");
            $this->newLine();
            $this->comment("Next steps:");
            $this->comment("1. Add your logic to {$basePath}");
            if (in_array($mode, ['react', 'vue'])) {
                $this->comment("2. Create your Frontend Pages in {$basePath}/Frontend/Pages");
            } elseif ($mode === 'livewire') {
                $this->comment("2. Create your Livewire components in {$basePath}/Livewire");
            }

            return ConsoleCommand::SUCCESS;
        } catch (Exception $e) {
            $this->error("Failed to create domain: {$e->getMessage()}");
            return ConsoleCommand::FAILURE;
        }
    }

    /**
     * Create base controller for the domain.
     */
    protected function createBaseController(string $domain, string $mode): void
    {
        $controllerPath = DomainHelpers::basePath($domain) . "/Controllers/{$domain}Controller.php";

        if (File::exists($controllerPath)) {
            return;
        }

        $namespace = DomainHelpers::baseNamespace($domain) . "\\Controllers";
        
        $baseController = match($mode) {
            'react', 'vue' => 'InertiaControllers',
            'api' => 'ApiControllers',
            default => 'WebControllers',
        };
        
        $useStatement = "use Samushi\\DddArchitect\\Support\\{$baseController};";

        $content = "<?php\n\ndeclare(strict_types=1);\n\nnamespace {$namespace};\n\n{$useStatement}\n\nclass {$domain}Controller extends {$baseController}\n{\n    // Base controller for {$domain}\n}\n";

        File::put($controllerPath, $content);
        $this->info("Base controller created: {$controllerPath}");
    }

    /**
     * Create base repository for the domain.
     */
    protected function createBaseRepository(string $domain): void
    {
        $repoPath = DomainHelpers::basePath($domain) . "/Repository/{$domain}Repository.php";

        if (File::exists($repoPath)) {
            return;
        }

        $namespace = DomainHelpers::baseNamespace($domain) . "\\Repository";
        
        $content = "<?php\n\ndeclare(strict_types=1);\n\nnamespace {$namespace};\n\nuse Samushi\Domion\Support\Repositories;\nuse Illuminate\Database\Eloquent\Model;\nuse App\\Domain\\{$domain}\\Models\\{$domain};\n\nclass {$domain}Repository extends Repositories\n{\n    protected function getModel(): Model\n    {\n        return new {$domain}();\n    }\n}\n";

        File::put($repoPath, $content);
        $this->info("Repository created: {$repoPath}");
    }

    /**
     * Create example action for the domain.
     */
    protected function createExampleAction(string $domain): void
    {
        $actionPath = DomainHelpers::basePath($domain) . "/Actions/Get{$domain}ListAction.php";

        if (File::exists($actionPath)) {
            return;
        }

        $namespace = DomainHelpers::baseNamespace($domain) . "\\Actions";
        $repoNamespace = DomainHelpers::baseNamespace($domain) . "\\Repository\\{$domain}Repository";
        
        $content = "<?php\n\ndeclare(strict_types=1);\n\nnamespace {$namespace};\n\nuse Samushi\Domion\Actions\ActionFactory;\nuse {$repoNamespace};\n\nclass Get{$domain}ListAction extends ActionFactory\n{\n    public function __construct(\n        protected {$domain}Repository \$repository\n    ) {}\n\n    public function handle(): mixed\n    {\n        // Business logic goes here\n        return \$this->repository->all();\n    }\n}\n";

        File::put($actionPath, $content);
        $this->info("Example Action created: {$actionPath}");
    }

    /**
     * Create example job for the domain.
     */
    protected function createExampleJob(string $domain): void
    {
        $jobPath = DomainHelpers::basePath($domain) . "/Jobs/Process{$domain}Job.php";

        if (File::exists($jobPath)) return;

        $namespace = DomainHelpers::baseNamespace($domain) . "\\Jobs";
        
        $content = "<?php\n\ndeclare(strict_types=1);\n\nnamespace {$namespace};\n\nuse Illuminate\Bus\Queueable;\nuse Illuminate\Contracts\Queue\ShouldQueue;\nuse Illuminate\Foundation\Bus\Dispatchable;\nuse Illuminate\Queue\InteractsWithQueue;\nuse Illuminate\Queue\SerializesModels;\n\nclass Process{$domain}Job implements ShouldQueue\n{\n    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;\n\n    public function __construct() {}\n\n    public function handle(): void\n    {\n        // Process enterprise logic here\n    }\n}\n";

        File::put($jobPath, $content);
        $this->info("Example Job created: {$jobPath}");
    }

    /**
     * Create example event and listener for the domain.
     */
    protected function createExampleEvent(string $domain): void
    {
        $eventPath = DomainHelpers::basePath($domain) . "/Events/{$domain}Created.php";
        $listenerPath = DomainHelpers::basePath($domain) . "/Listeners/NotifyAdminOf{$domain}.php";

        if (!File::exists($eventPath)) {
            $namespace = DomainHelpers::baseNamespace($domain) . "\\Events";
            $content = "<?php\n\ndeclare(strict_types=1);\n\nnamespace {$namespace};\n\nuse Illuminate\Foundation\Events\Dispatchable;\nuse Illuminate\Queue\SerializesModels;\n\nclass {$domain}Created\n{\n    use Dispatchable, SerializesModels;\n\n    public function __construct(public mixed \$entity) {}\n}\n";
            File::put($eventPath, $content);
            $this->info("Example Event created: {$eventPath}");
        }

        if (!File::exists($listenerPath)) {
            $namespace = DomainHelpers::baseNamespace($domain) . "\\Listeners";
            $eventClass = DomainHelpers::baseNamespace($domain) . "\\Events\\{$domain}Created";
            $content = "<?php\n\ndeclare(strict_types=1);\n\nnamespace {$namespace};\n\nuse {$eventClass};\n\nclass NotifyAdminOf{$domain}\n{\n    public function handle({$domain}Created \$event): void\n    {\n        // Notify admin logic\n    }\n}\n";
            File::put($listenerPath, $content);
            $this->info("Example Listener created: {$listenerPath}");
        }
    }

    /**
     * Create api.php routes file.
     */
    protected function createApiRoutes(string $domain): void
    {
        $apiPath = DomainHelpers::basePath($domain) . "/api.php";

        if (File::exists($apiPath)) {
            $this->warn("api.php already exists: {$apiPath}");
            return;
        }

        $stub = "<?php\n\ndeclare(strict_types=1);\n\nuse Illuminate\Support\Facades\Route;\n\nRoute::group([], function () {\n    // Routes for {$domain}\n});\n";

        File::put($apiPath, $stub);
        $this->info("Routes file created: {$apiPath}");
    }

    /**
     * Create ServiceProvider.
     */
    protected function createServiceProvider(string $domain): void
    {
        $providersPath = DomainHelpers::basePath($domain) . "/Providers";
        File::ensureDirectoryExists($providersPath);

        $className = "{$domain}ServiceProvider";
        $providerPath = "{$providersPath}/{$className}.php";

        if (File::exists($providerPath)) {
            $this->warn("ServiceProvider already exists: {$providerPath}");
            return;
        }

        $namespace = DomainHelpers::baseNamespace($domain) . "\\Providers";
        
        $content = "<?php\n\ndeclare(strict_types=1);\n\nnamespace {$namespace};\n\nuse Samushi\Domion\Support\AbstractServiceProvider;\n\nclass {$className} extends AbstractServiceProvider\n{\n    public function setDomain(): string\n    {\n        return '{$domain}';\n    }\n}\n";

        File::put($providerPath, $content);
        $this->info("ServiceProvider created: {$providerPath}");
    }

    protected function getStub(): string
    {
        return '';
    }

    protected function getArguments(): array
    {
        return [
            ["domain", InputArgument::REQUIRED, "The domain name"]
        ];
    }
}
