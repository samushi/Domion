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
     * Custom validation error
     */
    protected function failedValidation(Validator $validator): void
    {
        // If it's an Inertia request, we want Laravel's default behavior 
        // (redirect back with errors)
        if (!$this->expectsJson() && !($this->header('X-Inertia') || $this->header('X-Inertia-Partial-Data'))) {
            parent::failedValidation($validator);
            return;
        }

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
