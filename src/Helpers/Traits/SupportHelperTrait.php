<?php

declare(strict_types=1);

namespace Samushi\Domion\Helpers\Traits;

trait SupportHelperTrait
{
    /**
     * Support Path with optional dot notation.
     */
    public static function supportPath(?string $dotPath = null): string
    {
        return self::path()->support($dotPath ?? '');
    }
}
