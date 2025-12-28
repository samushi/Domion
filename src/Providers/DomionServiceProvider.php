<?php

declare(strict_types=1);

namespace Samushi\Domion\Providers;

use Illuminate\Support\ServiceProvider;
use Samushi\Domion\Helpers\DomainHelpers;
use Illuminate\Database\Eloquent\Factories\Factory;
use Samushi\Domion\Commands\{
    MakeDomain,
    MakeDomainMigration,
    SetupCommand,
    LinkFrontend,
    MakeAction,
    MakeDto,
    MakeRepository
};

class DomionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/domion.php', 'domion');
        $this->configureBladeViewPaths();
        $this->registerDomainProviders();
    }

    public function boot(): void
    {
        $this->configureMigrationPaths();
        $this->registerDomainObservers();
        DomainHelpers::registerLivewireComponents();
        $this->registerFactories();
        $this->configureInertiaRootView();

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/domion.php' => config_path('domion.php'),
            ], 'domion-config');

            $this->commands([
                SetupCommand::class,
                MakeDomain::class,
                MakeAction::class,
                MakeDto::class,
                MakeRepository::class,
                MakeDomainMigration::class,
                LinkFrontend::class,
            ]);
        }
    }

    protected function configureBladeViewPaths(): void
    {
        DomainHelpers::loadAllResources();
    }

    protected function configureMigrationPaths(): void
    {
        $migrationPaths = DomainHelpers::getAllMigrationPaths();
        foreach ($migrationPaths as $path) {
            $this->loadMigrationsFrom($path);
        }
    }

    protected function registerDomainObservers(): void
    {
        $domains = DomainHelpers::getDomains();

        foreach ($domains as $domainPath) {
            $observerPath = $domainPath . '/Observers';
            if (is_dir($observerPath)) {
                $files = glob($observerPath . '/*.php');
                foreach ($files as $file) {
                    $observerClassName = basename($file, '.php');
                    $domainName = basename($domainPath);
                    $modelName = str_replace('Observer', '', $observerClassName);

                    // Check for Central/Tenant scope
                    $parentDir = basename(dirname($domainPath));
                    $scope = in_array($parentDir, ['Central', 'Tenant']) ? "{$parentDir}\\" : '';

                    $fullObserverClass = "App\\Domain\\{$scope}{$domainName}\\Observers\\{$observerClassName}";
                    $fullModelClass = "App\\Domain\\{$scope}{$domainName}\\Models\\{$modelName}";

                    if (class_exists($fullObserverClass) && class_exists($fullModelClass)) {
                        $fullModelClass::observe($fullObserverClass);
                    }
                }
            }
        }
    }

    protected function registerDomainProviders(): void
    {
        $domains = DomainHelpers::getDomains();

        foreach ($domains as $domainPath) {
            $providerPath = $domainPath . '/Providers';
            if (is_dir($providerPath)) {
                $files = glob($providerPath . '/*ServiceProvider.php');
                foreach ($files as $file) {
                    $className = basename($file, '.php');
                    $domainName = basename($domainPath);

                    // Check for Central/Tenant scope
                    $parentDir = basename(dirname($domainPath));
                    $scope = in_array($parentDir, ['Central', 'Tenant']) ? "{$parentDir}\\" : '';

                    $fullClassName = "App\\Domain\\{$scope}{$domainName}\\Providers\\{$className}";

                    if (class_exists($fullClassName)) {
                        $this->app->register($fullClassName);
                    }
                }
            }
        }
    }

    /**
     * Register domain-level factories using Laravel's factory discovery.
     */
    protected function registerFactories(): void
    {
        Factory::guessFactoryNamesUsing(function (string $modelName) {
            // Check if model belongs to a Domain
            if (str_contains($modelName, 'App\\Domain\\')) {
                $pieces = explode('\\', $modelName);

                // Handle Central/Tenant structure: App\Domain\Central\{DomainName}\Models\{ModelName}
                if (count($pieces) >= 6 && in_array($pieces[2], ['Central', 'Tenant']) && $pieces[4] === 'Models') {
                    $scope = $pieces[2];
                    $domain = $pieces[3];
                    $name = end($pieces);
                    return "App\\Domain\\{$scope}\\{$domain}\\Database\\Factories\\{$name}Factory";
                }

                // Handle standard structure: App\Domain\{DomainName}\Models\{ModelName}
                if (count($pieces) >= 5 && $pieces[3] === 'Models') {
                    $domain = $pieces[2];
                    $name = end($pieces);
                    return "App\\Domain\\{$domain}\\Database\\Factories\\{$name}Factory";
                }
            }

            // Fallback for standard structure
            return 'Database\\Factories\\' . class_basename($modelName) . 'Factory';
        });
    }

    /**
     * Set the root view for Inertia if using DDD structure.
     */
    protected function configureInertiaRootView(): void
    {
        if (class_exists(\Inertia\Inertia::class)) {
            // Safety: Manually register Support namespace if it exists
            $supportPath = base_path('app/Support/Resources/views');
            if (is_dir($supportPath)) {
                view()->addNamespace('support', $supportPath);
            }

            if (view()->exists('support::app')) {
                \Inertia\Inertia::setRootView('support::app');
            } elseif (view()->exists('shared::app')) {
                \Inertia\Inertia::setRootView('shared::app');
            }
        }
    }
}
