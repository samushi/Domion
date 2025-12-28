<?php

declare(strict_types=1);

namespace Samushi\Domion\Support;

use Illuminate\Support\ServiceProvider;

/**
 * Abstract Service Provider for Domain-specific providers
 *
 * Each domain can have its own service provider that extends this class.
 * This allows for domain-specific configuration like route prefixes and names.
 *
 * @example
 * ```php
 * class AuthServiceProvider extends AbstractServiceProvider
 * {
 *     public function setDomain(): string
 *     {
 *         return 'Auth';
 *     }
 *
 *     // Make routes available at root (/) instead of /auth
 *     public function routePrefix(): string|false
 *     {
 *         return false;
 *     }
 *
 *     // Use custom route name prefix
 *     public function routeNamePrefix(): string|false
 *     {
 *         return false; // route('login') instead of route('auth.login')
 *     }
 * }
 * ```
 */
abstract class AbstractServiceProvider extends ServiceProvider
{
    /**
     * Set Domain name
     */
    abstract public function setDomain(): string;

    /**
     * Define the route prefix for this domain.
     * Return false to disable prefix (routes at root).
     * Return string to use custom prefix.
     * Return null to use default (kebab-case domain name).
     *
     * @return string|false|null
     */
    public function routePrefix(): string|false|null
    {
        return null; // Use default
    }

    /**
     * Define the route name prefix for this domain.
     * Return false to disable name prefix.
     * Return string to use custom prefix.
     * Return null to use default (lowercase domain name).
     *
     * @return string|false|null
     */
    public function routeNamePrefix(): string|false|null
    {
        return null; // Use default
    }

    /**
     * Define the middleware for this domain's routes.
     *
     * @return array
     */
    public function routeMiddleware(): array
    {
        return [];
    }

    /**
     * Whether this domain's web routes require authentication
     *
     * @return bool
     */
    public function requiresAuth(): bool
    {
        return false;
    }

    /**
     * Whether this domain's web routes are for guests only
     *
     * @return bool
     */
    public function guestOnly(): bool
    {
        return false;
    }

    /**
     * Boot the service provider
     */
    public function boot(): void
    {
        //
    }

    /**
     * Register bindings in the container
     */
    public function register(): void
    {
        //
    }

    /**
     * Get domain name
     */
    public function getDomain(): string
    {
        return $this->setDomain();
    }

    /**
     * Get the route configuration for this domain
     *
     * @return array{prefix: string|false|null, name: string|false|null, middleware: array}
     */
    public function getRouteConfig(): array
    {
        return [
            'prefix' => $this->routePrefix(),
            'name' => $this->routeNamePrefix(),
            'middleware' => $this->routeMiddleware(),
            'requires_auth' => $this->requiresAuth(),
            'guest_only' => $this->guestOnly(),
        ];
    }
}
