<?php

declare(strict_types=1);

namespace Samushi\Domion\Support;

use Samushi\Domion\Support\Enums\ApiCode;
use Samushi\Domion\Support\Traits\HttpResponseTrait;
use Samushi\Domion\Support\Traits\SafeCallable;
use Closure;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Validation\ValidationException as LaravelValidationException;
use Throwable;

abstract class ApiControllers extends BaseController
{
    use AuthorizesRequests;
    use DispatchesJobs;
    use HttpResponseTrait;
    use SafeCallable;
    use ValidatesRequests;

    /**
     * Validation throw exception
     */
    protected function throwValidationException(): Closure
    {
        return fn (Throwable $throwable) => $throwable instanceof LaravelValidationException ?
            $this->errorWithMessage($throwable->getMessage(), ApiCode::INVALID_VALIDATION->value, 422) :
            $this->errorWithMessage($throwable->getMessage());
    }
}
