<?php

declare(strict_types=1);

namespace Samushi\Domion\Support\Filters;

use Samushi\Domion\Support\Enums\OrderQuery;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Samushi\QueryFilter\Filter;

class Order extends Filter
{
    /**
     * Handle the filter execution
     *
     * @param Builder $builder
     * @param Closure $next
     * @return Builder
     */
    public function handle(Builder $builder, Closure $next): Builder
    {
        $hasDynamicOrder = false;
        foreach (request()->all() as $key => $value) {
            if (str_starts_with($key, 'order__') && $value !== "") {
                $hasDynamicOrder = true;
                break;
            }
        }

        if ($hasDynamicOrder || (request()->has($this->filterName()) && request()->input($this->filterName()) !== "")) {
            return $next($this->applyFilter($builder));
        }

        return $next($builder);
    }

    /**
     * Apply the order filter
     *
     * @param Builder $builder
     * @return Builder
     */
    protected function applyFilter(Builder $builder): Builder
    {
        $params = request()->all();
        $orderApplied = false;
        $model = $builder->getModel();

        $allowedColumns = method_exists($model, 'getSortableColumns')
            ? $model->getSortableColumns()
            : null;

        foreach ($params as $key => $value) {
            if (str_starts_with($key, 'order__')) {
                $column = str_replace('order__', '', $key);

                if ($allowedColumns && !in_array($column, $allowedColumns)) {
                    continue;
                }

                $direction = OrderQuery::tryFrom(strtolower((string) $value)) ?? OrderQuery::ASC;

                $builder->orderBy($column, $direction->value);
                $orderApplied = true;
            }
        }

        if (!$orderApplied) {
            $value = $this->getValue();
            $direction = OrderQuery::tryFrom(strtolower((string) ($value ?? ''))) ?? OrderQuery::ASC;

            return $builder->orderBy($builder->getModel()->getTable() . '.created_at', $direction->value);
        }

        return $builder;
    }
}
