<?php

declare(strict_types=1);

namespace Samushi\Domion\Support\Exceptions;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException as DefaultValidationException;

abstract class ValidationException extends DefaultValidationException
{
    public function __construct()
    {
        parent::__construct(Validator::make([], []));
        $this->message = $this->message();
    }

    abstract protected function message(): string;
}
