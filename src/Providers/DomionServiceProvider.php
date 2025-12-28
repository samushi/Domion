<?php

declare(strict_types=1);

namespace Samushi\Domion\Providers;

use Illuminate\Support\ServiceProvider;
use Samushi\Domion\Helpers\DomainHelpers;
use Samushi\Domion\Commands\{
    MakeDomain,
    MakeDomainMigration,
    SetupCommand,
    LinkFrontend,
    MakeAction
};

class DomionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/domion.php', 'domion');
        $this->registerDomainProviders();
    }

    public function boot(): void
    {
        $this->configureBladeViewPaths();
        $this->configureMigrationPaths();
        $this->registerDomainObservers();
        DomainHelpers::registerLivewireComponents();

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/domion.php' => config_path('domion.php'),
            ], 'architect-config');

            $this->commands([
                MakeDomain::class,
                MakeDomainMigration::class,
                SetupCommand::class,
                LinkFrontend::class,
                MakeAction::class,
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
                    
                    $fullObserverClass = "App\\Domain\\{$domainName}\\Observers\\{$observerClassName}";
                    $fullModelClass = "App\\Domain\\{$domainName}\\Models\\{$modelName}";
                    
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
                    $fullClassName = "App\\Domain\\{$domainName}\\Providers\\{$className}";
                    
                    if (class_exists($fullClassName)) {
                        $this->app->register($fullClassName);
                    }
                }
            }
        }
    }
}
