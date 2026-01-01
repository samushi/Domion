<?php

declare(strict_types=1);

namespace Samushi\Domion\Providers;

use Illuminate\Support\ServiceProvider;
use Samushi\Domion\Support\DomainLoader;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Samushi\Domion\Commands\{
    SetupCommand,
    MakeDomain,
    MakeAction,
    MakeDto,
    MakeRepository,
    MakeDomainMigration,
    LinkFrontend
};

class DomionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/domion.php', 'domion');

        // Dynamically register all Domain Providers
        DomainLoader::registerDomainProviders($this->app);
    }

    public function boot(): void
    {
        // Register Observers
        DomainLoader::registerObservers();

        // Configure Factory Discovery for Domains
        $this->registerFactories();

        // Inertia Support View Namespace
        $this->configureInertiaRootView();

        // Register Commands
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

    protected function registerFactories(): void
    {
        Factory::guessFactoryNamesUsing(function (string $modelName) {
            // Maps App\Domain\User\Models\User -> App\Domain\User\Database\Factories\UserFactory
            if (Str::contains($modelName, 'Domain')) {
                return Str::replace('Models', 'Database\\Factories', $modelName) . 'Factory';
            }
            return 'Database\\Factories\\' . class_basename($modelName) . 'Factory';
        });
    }

    protected function configureInertiaRootView(): void
    {
        if (class_exists(\Inertia\Inertia::class)) {
            $supportPath = base_path('app/Support/Resources/views');
            if (is_dir($supportPath)) {
                view()->addNamespace('support', $supportPath);
            }
            if (view()->exists('support::app')) {
                \Inertia\Inertia::setRootView('support::app');
            }
        }
    }
}