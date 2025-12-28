<?php

declare(strict_types=1);

namespace Samushi\Domion\Actions;

use Exception;

abstract class ActionFactory
{
    /**
     * Make instance
     */
    public static function make(): self
    {
        return app(static::class);
    }

    /**
     * Run Action
     *
     * @param  mixed  ...$args
     *
     * @throws Exception
     */
    public static function run(...$args): mixed
    {
        return static::make()->handle(...$args);
    }

    /**
     * Execute the action
     */
    abstract public function handle(...$args): mixed;
}
