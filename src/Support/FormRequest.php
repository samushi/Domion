<?php

declare(strict_types=1);

namespace Samushi\Domion\Support;

use Samushi\Domion\Support\Enums\ApiCode;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest as OFormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\ValidationException;
use MarcinOrlowski\ResponseBuilder\ResponseBuilder;
use Symfony\Component\HttpFoundation\Response;

abstract class FormRequest extends OFormRequest
{
    abstract public function rules(): array;

    // We remove forced expectsJson/wantsJson to allow Laravel/Inertia 
    // to determine the response type naturally.

    public function authorize(): bool
    {
        return true;
    }

    /**
     * Custom validation error handling.
     * For Inertia/Web requests: redirect back with errors (Laravel default)
     * For API requests: return JSON response
     */
    protected function failedValidation(Validator $validator): void
    {
        // Check if this is an Inertia request
        $isInertia = $this->header('X-Inertia') || $this->header('X-Inertia-Partial-Data');
        
        // For Inertia and standard web requests, use Laravel's default behavior
        // which throws ValidationException and redirects back with errors
        if ($isInertia || !$this->expectsJson()) {
            throw (new ValidationException($validator))
                ->errorBag($this->errorBag)
                ->redirectTo($this->getRedirectUrl());
        }

        // For API requests, return structured JSON
        $validators = (new ValidationException($validator));
        $message = $validators->getMessage();
        $errors = $validators->errors();

        throw new HttpResponseException(
            ResponseBuilder::asError(ApiCode::INVALID_VALIDATION->value)
                ->withHttpCode(Response::HTTP_UNPROCESSABLE_ENTITY)
                ->withMessage($message)
                ->withData($errors)
                ->build()
        );
    }
}
