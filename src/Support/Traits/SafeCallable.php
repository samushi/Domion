<?php

declare(strict_types=1);

namespace Samushi\Domion\Support\Traits;

use Closure;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Trait for safe execution of callable functions
 *
 * Provides methods to safely execute code with proper error handling
 * and response formatting for API and web controllers.
 */
trait SafeCallable
{
    /**
     * Run a callback safely with error handling.
     *
     * @param callable $try The main callback to execute
     * @param callable|null $catch Optional error handler
     * @param bool $report Whether to report errors to Laravel's error handler
     * @return Response
     */
    public function safeCall(callable $try, ?callable $catch = null, bool $report = true): Response
    {
        try {
            return $try();
        } catch (ValidationException $e) {
            if ($catch) {
                return $catch($e);
            }
            throw $e;
        } catch (Throwable $e) {
            if ($report) {
                report($e);
            }

            if ($catch) {
                return $catch($e);
            }

            if (method_exists($this, 'errorWithMessage')) {
                return $this->errorWithMessage($e->getMessage());
            }

            throw $e;
        }
    }

    /**
     * Return a closure that throws a validation exception.
     * Useful as a catch handler for safeCall.
     */
    public function throwValidationException(): Closure
    {
        return fn (Throwable $e) => throw $e;
    }

    /**
     * Run a callback and return its result or a default value on failure.
     *
     * @param callable $callback
     * @param mixed $default
     * @return mixed
     */
    public function rescue(callable $callback, mixed $default = null): mixed
    {
        try {
            return $callback();
        } catch (Throwable) {
            return $default instanceof Closure ? $default() : $default;
        }
    }

    /**
     * Run a callback and retry on failure.
     *
     * @param callable $callback
     * @param int $times Number of attempts
     * @param int $sleepMilliseconds Delay between retries in milliseconds
     * @return mixed
     * @throws Throwable
     */
    public function retry(callable $callback, int $times = 3, int $sleepMilliseconds = 0): mixed
    {
        $attempts = 0;
        $lastException = null;

        while ($attempts < $times) {
            try {
                return $callback();
            } catch (Throwable $e) {
                $lastException = $e;
                $attempts++;

                if ($attempts < $times && $sleepMilliseconds > 0) {
                    usleep($sleepMilliseconds * 1000);
                }
            }
        }

        throw $lastException;
    }
}
