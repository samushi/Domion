<?php

declare(strict_types=1);

namespace Samushi\Domion\Support\Traits;

use Samushi\Domion\Support\Enums\ApiCode;
use Closure;
use Exception;
use MarcinOrlowski\ResponseBuilder\ResponseBuilder;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

trait HttpResponseTrait
{
    /**
     * Success Response
     */
    final protected function success(array|object $data, ?string $message = null): HttpResponse
    {
        return ResponseBuilder::asSuccess()
            ->withData($data)
            ->withMessage($message)
            ->build();
    }

    /**
     * Response without data
     */
    final protected function noContentClosure(Closure $closure): HttpResponse
    {
        try {
            $closure();

            return ResponseBuilder::asSuccess()->build();
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    /**
     * Success response with message
     */
    final protected function successWithMessage(string $message): HttpResponse
    {
        return ResponseBuilder::asSuccess()->withMessage($message)->build();
    }

    /**
     * Error with message
     */
    final protected function errorWithMessage(string $message, int $apiCode = ApiCode::SOMETHING_WENT_WRONG->value, int $httpCode = 400): HttpResponse
    {
        return ResponseBuilder::asError($apiCode)
            ->withMessage($message)
            ->withHttpCode($httpCode)
            ->build();
    }

    /**
     * Bad request error
     */
    final protected function badRequest(int $apiCode): HttpResponse
    {
        return $this->error($apiCode, 400);
    }

    /**
     * Something is wrong
     */
    final protected function somethingWrong(int $apiCode): HttpResponse
    {
        $apiCode = $apiCode === 0 ? ApiCode::SOMETHING_WENT_WRONG->value : $apiCode;

        return $this->error($apiCode, HttpResponse::HTTP_BAD_REQUEST);
    }

    /**
     * Unauthorized error
     */
    final protected function unAuthorized(?string $message): HttpResponse
    {
        return ResponseBuilder::asError(ApiCode::INVALID_CREDENTIALS->value)
            ->withHttpCode(HttpResponse::HTTP_UNAUTHORIZED)
            ->withMessage($message)
            ->build();
    }

    /**
     * Error response
     */
    final protected function error(int $apiCode, int $httpCode): HttpResponse
    {
        return ResponseBuilder::asError($apiCode)->withHttpCode($httpCode)->build();
    }
}
