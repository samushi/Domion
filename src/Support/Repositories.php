<?php

declare(strict_types=1);

namespace Samushi\Domion\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Samushi\QueryFilter\Facade\QueryFilter;
use Samushi\Domion\Support\Filters\Search;
use Samushi\Domion\Support\Filters\Order;

abstract class Repositories
{
    abstract protected function getModel(): Model;

    /**
     * Get filters for this repository
     */
    protected function getFilters(): array
    {
        return [];
    }

    /**
     * Eager loading
     */
    public function with(array $array = []): Builder
    {
        return $this->query()->with($array);
    }

    /**
     * All columns list (with filters' support)
     */
    public function all(array $columns = ['*']): Model|Collection|array
    {
        return $this->buildQuery()->get($columns);
    }

    /**
     * Paginate (with filters' support)
     */
    public function paginate(int $perPage = 15, array $columns = ['*']): LengthAwarePaginator
    {
        return $this->buildQuery()->paginate($perPage, $columns);
    }

    /**
     * Build query with filters applied
     */
    protected function buildQuery(?array $filters = null): Builder
    {
        $filters = $filters ?? $this->getFilters();

        $allFilters = array_merge([
            new Search(),
            new Order(),
        ], $filters);

        return QueryFilter::query($this->query(), $allFilters);
    }

    /**
     * Create a new model
     */
    public function create(array $data): Model
    {
        return $this->query()->create($data);
    }

    /**
     * Update the model
     */
    public function update(array $data, string $id, string $attribute = 'id'): int
    {
        $model = $this->query()
            ->where($attribute, '=', $id)
            ->first();

        $model->fill($data);
        $model->save();

        return 1;
    }

    /**
     * Delete model
     */
    public function delete(string $id): int
    {
        return $this->getModel()->destroy($id);
    }

    /**
     * Find by id
     */
    public function find($id, array $columns = ['*']): ?Model
    {
        return $this->query()->find($id, $columns);
    }

    /**
     * Find by id or fail
     */
    public function findOrFail($id, array $columns = ['*']): Model
    {
        return $this->query()->findOrFail($id, $columns);
    }

    /**
     * Where Between date
     */
    protected function betweenDate(string $start, string $end, string $column = 'event_date'): Builder
    {
        return $this->query()->whereBetween($column, [$start, $end]);
    }

    /**
     * Query builder
     */
    protected function query(): Builder
    {
        return $this->getModel()->newQuery();
    }

    /**
     * Upsert records
     */
    public function upsert(array $values, array|string $uniqueBy, array|null $update = null): int
    {
         return $this->query()->upsert($values, $uniqueBy, $update);
    }

    /**
     * Insert or ignore records
     */
    public function insertOrIgnore(array $values): int
    {
         return $this->query()->insertOrIgnore($values);
    }

    /**
     * Increment a column
     */
    public function increment(string $id, string $column, int $amount = 1): void
    {
        $this->query()->where('id', $id)->increment($column, $amount);
    }
}
