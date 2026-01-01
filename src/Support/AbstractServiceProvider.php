<?php

declare(strict_types=1);

namespace Samushi\Domion\Support;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

abstract class AbstractServiceProvider extends ServiceProvider
{
    /**
     * Get the Domain name (e.g., "Auth", "Catalog").
     */
    abstract public function getDomainName(): string;

    /**
     * Define the Route Prefix.
     * Return 'null' or empty string '' to use Root path (no prefix).
     * Default: returns the kebab-case domain name (e.g., 'user-profile').
     */
    public function getRoutePrefix(): ?string
    {
        return Str::kebab($this->getDomainName());
    }

    /**
     * Define the Route Name Prefix (e.g., 'auth.').
     * Return null for no name prefix.
     */
    public function getRouteNamePrefix(): ?string
    {
        $prefix = $this->getRoutePrefix();
        return $prefix ? $prefix . '.' : null;
    }

    /**
     * Define middleware for Web routes.
     */
    public function getWebMiddleware(): array
    {
        return ['web'];
    }

    /**
     * Define middleware for API routes.
     */
    public function getApiMiddleware(): array
    {
        return ['api'];
    }

    public function boot(): void
    {
        $this->registerRoutes();
        $this->registerMigrations();
        $this->registerViews();
        $this->registerTranslations();
    }

    protected function registerRoutes(): void
    {
        $domainPath = $this->getDomainPath();
        $prefix = $this->getRoutePrefix();
        $namePrefix = $this->getRouteNamePrefix();

        // Web Routes Registration
        if (file_exists($domainPath . '/web.php')) {
            $router = Route::middleware($this->getWebMiddleware());

            if ($prefix) {
                $router->prefix($prefix);
            }

            if ($namePrefix) {
                $router->name($namePrefix);
            }

            $router->group(function () use ($domainPath) {
                $this->loadRoutesFrom($domainPath . '/web.php');
            });
        }

        // API Routes Registration
        if (file_exists($domainPath . '/api.php')) {
            $apiPrefix = 'api';
            if ($prefix) {
                $apiPrefix .= '/' . $prefix;
            }

            $router = Route::middleware($this->getApiMiddleware())
                ->prefix($apiPrefix);

            if ($namePrefix) {
                $router->name('api.' . $namePrefix);
            }

            $router->group(function () use ($domainPath) {
                $this->loadRoutesFrom($domainPath . '/api.php');
            });
        }
    }

    protected function registerMigrations(): void
    {
        $path = $this->getDomainPath() . '/Database/Migrations';
        if (is_dir($path)) {
            $this->loadMigrationsFrom($path);
        }
    }

    protected function registerViews(): void
    {
        $path = $this->getDomainPath() . '/Frontend/Views';
        if (is_dir($path)) {
            // Example usage: view('auth::login')
            $this->loadViewsFrom($path, Str::lower($this->getDomainName()));
        }
    }

    protected function registerTranslations(): void
    {
        $path = $this->getDomainPath() . '/Frontend/Lang';
        if (is_dir($path)) {
            $this->loadTranslationsFrom($path, Str::lower($this->getDomainName()));
        }
    }

    protected function getDomainPath(): string
    {
        $reflection = new \ReflectionClass($this);
        return dirname($reflection->getFileName(), 2);
    }
}