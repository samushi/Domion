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

    public function authorize(): bool
    {
        return true;
    }

    protected function failedValidation(Validator $validator): void
    {
        if ($this->isApiRequest()) {
            $exception = new ValidationException($validator);
            
            throw new HttpResponseException(
                ResponseBuilder::asError(ApiCode::INVALID_VALIDATION->value)
                    ->withHttpCode(Response::HTTP_UNPROCESSABLE_ENTITY)
                    ->withMessage($exception->getMessage())
                    ->withData($exception->errors())
                    ->build()
            );
        }

        parent::failedValidation($validator);
    }

    protected function isApiRequest(): bool
    {
        $request = request();
        
        if ($request->header('X-Inertia')) {
            return false;
        }

        if ($request->is('api/*')) {
            return true;
        }

        if ($request->expectsJson() && !$request->header('X-Inertia')) {
            return true;
        }

        return false;
    }
}
