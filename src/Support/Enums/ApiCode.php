<?php

declare(strict_types=1);

namespace Samushi\Domion\Support\Enums;

use Illuminate\Support\Facades\Lang;

enum ApiCode: int
{
    case SOMETHING_WENT_WRONG = 250;
    case INVALID_CREDENTIALS = 251;
    case INVALID_VALIDATION = 252;
    case ROUTE_NOT_FOUND = 254;
    case UNAUTHORIZED = 255;
    case FORBIDDEN = 256;

    /**
     * Get Message By Status code
     * @return string
     */
    public function message(): string
    {
        return match ($this) {
            self::SOMETHING_WENT_WRONG => Lang::get('api.something_went_wrong'),
            self::INVALID_CREDENTIALS => Lang::get('api.invalid_credentials'),
            self::INVALID_VALIDATION => Lang::get('api.invalid_validation'),
            self::ROUTE_NOT_FOUND => Lang::get('api.route_not_found'),
            self::UNAUTHORIZED => Lang::get('api.unauthorized'),
            self::FORBIDDEN => Lang::get('api.forbidden')
        };
    }
}
