<?php

declare(strict_types=1);

namespace Samushi\Domion\Actions;

use Illuminate\Contracts\Container\BindingResolutionException;
use Throwable;

/**
 * Base Action Factory for DDD Actions
 *
 * Actions are the heart of business logic in DDD.
 * Each action should do ONE thing and do it well.
 *
 * @example
 * ```php
 * class LoginAction extends ActionFactory
 * {
 *     protected function handle(LoginDto $dto): array
 *     {
 *         // Business logic here
 *     }
 * }
 *
 * // Usage:
 * LoginAction::run(LoginDto::fromArray($request->validated()));
 * ```
 */
abstract class ActionFactory
{
    /**
     * Make a new instance via Laravel's container.
     * This allows for dependency injection in constructors.
     *
     * @return static
     * @throws BindingResolutionException
     */
    public static function make(): static
    {
        return app(static::class);
    }

    /**
     * Create and execute the action in one call.
     *
     * @param mixed ...$args Arguments to pass to handle()
     * @return mixed
     */
    public static function run(mixed ...$args): mixed
    {
        return static::make()->handle(...$args);
    }

    /**
     * Execute the action and wrap result in a try-catch.
     * Useful for actions that might throw exceptions.
     *
     * @param mixed ...$args
     * @return array{success: bool, data?: mixed, error?: string}
     */
    public static function runSafe(mixed ...$args): array
    {
        try {
            $result = static::run(...$args);
            return ['success' => true, 'data' => $result];
        } catch (Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
