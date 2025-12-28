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
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Base controller for API endpoints
 *
 * Provides standardized response methods and error handling for REST APIs.
 *
 * @example
 * ```php
 * class ProductController extends ApiControllers
 * {
 *     public function index(): Response
 *     {
 *         return $this->safeCall(
 *             fn () => $this->success(Product::paginate()),
 *             $this->throwValidationException()
 *         );
 *     }
 * }
 * ```
 */
abstract class ApiControllers extends BaseController
{
    use AuthorizesRequests;
    use DispatchesJobs;
    use HttpResponseTrait;
    use SafeCallable;
    use ValidatesRequests;

    /**
     * Return a closure that handles validation exceptions.
     * Use as catch handler for safeCall.
     */
    protected function throwValidationException(): Closure
    {
        return fn (Throwable $throwable) => $throwable instanceof LaravelValidationException
            ? $this->errorWithMessage($throwable->getMessage(), ApiCode::INVALID_VALIDATION->value, 422)
            : $this->errorWithMessage($throwable->getMessage());
    }

    /**
     * Execute action and return success response.
     */
    protected function executeAction(callable $action, ?string $successMessage = null): Response
    {
        return $this->safeCall(
            fn () => $successMessage
                ? $this->successWithMessage($successMessage)
                : $this->success($action()),
            $this->throwValidationException()
        );
    }

    /**
     * Get authenticated user.
     */
    protected function user(): mixed
    {
        return auth()->user();
    }

    /**
     * Get authenticated user ID.
     */
    protected function userId(): ?string
    {
        return auth()->id();
    }

    /**
     * Check if user is authenticated.
     */
    protected function isAuthenticated(): bool
    {
        return auth()->check();
    }
}
