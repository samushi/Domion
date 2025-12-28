<?php

declare(strict_types=1);

namespace Samushi\Domion\Support\Traits;

use Symfony\Component\HttpFoundation\Response;
use Throwable;

trait SafeCallable
{
    /**
     * Run a callback safely like rescue() but always return a valid response.
     */
    public function safeCall(callable $try, ?callable $catch = null, bool $report = true): Response
    {
        try {
            return $try();
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
}
