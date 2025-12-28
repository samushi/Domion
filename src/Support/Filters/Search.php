<?php

declare(strict_types=1);

namespace Samushi\Domion\Support\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Samushi\QueryFilter\Filter;

class Search extends Filter
{
    /**
     * Apply the search filter
     *
     * @param Builder $builder
     * @return Builder
     */
    protected function applyFilter(Builder $builder): Builder
    {
        $searchTerm = $this->getValue();
        $model = $builder->getModel();

        // Check if model defines searchable columns
        if (method_exists($model, 'getSearchableColumns')) {
            $searchableColumns = $model->getSearchableColumns();
        } else {
            // Fallback to fillable columns (excluding sensitive fields)
            $searchableColumns = $this->getDefaultSearchableColumns($model);
        }

        return $builder->where(function ($query) use ($searchableColumns, $searchTerm) {
            foreach ($searchableColumns as $column) {
                if (str_contains($column, '.')) {
                    $parts = explode('.', $column);
                    $columnName = array_pop($parts);
                    $relationName = implode('.', $parts);

                    $query->orWhereHas($relationName, function ($relQuery) use ($columnName, $searchTerm) {
                        $relQuery->where($columnName, 'LIKE', "%{$searchTerm}%");
                    });
                } else {
                    $query->orWhere($column, 'LIKE', "%{$searchTerm}%");
                }
            }
        });
    }

    /**
     * Get default searchable columns from fillable
     *
     * @param Model $model
     * @return array
     */
    protected function getDefaultSearchableColumns(Model $model): array
    {
        // Get excluded columns from model if method exists
        $excludedColumns = method_exists($model, 'getSearchExcludedColumns')
            ? $model->getSearchExcludedColumns()
            : $this->getDefaultExcludedColumns();

        return array_filter($model->getFillable(), function ($column) use ($excludedColumns) {
            return !in_array($column, $excludedColumns);
        });
    }

    /**
     * Get default excluded columns (common sensitive fields)
     *
     * @return array
     */
    protected function getDefaultExcludedColumns(): array
    {
        return ['password', 'remember_token', 'email_verified_at'];
    }
}
