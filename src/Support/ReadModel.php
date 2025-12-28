<?php

declare(strict_types=1);

namespace Samushi\Domion\Support;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

abstract class ReadModel extends Model
{
    use HasFactory;
    use HasUuids;

    /**
     * Get the guarded attributes for the model.
     */
    public function getGuarded(): array
    {
        $mandatoryGuarded = [
            'id',
            'created_at',
            'updated_at',
            'deleted_at',
        ];

        return array_unique(array_merge((array)$this->guarded, $mandatoryGuarded));
    }
}
