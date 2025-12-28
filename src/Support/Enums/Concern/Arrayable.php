<?php

declare(strict_types=1);

namespace Samushi\Domion\Support\Enums\Concern;


trait Arrayable
{
    /**
     * Convert to array
     */
    public static function toArray(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Convert to selectable array
     * @return array
     */
    public static function toSelectable(): array
    {
        return collect(self::cases())
            ->map(fn($enum) => [
                'id' => $enum->value,
                'name' => method_exists($enum, 'label') ? $enum->label() : (method_exists($enum, 'name') ? $enum->name() : $enum->name),
            ])
            ->toArray();
    }
}
