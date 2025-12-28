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

/**
 * Base Repository class for DDD pattern
 *
 * Repositories handle all data access for a domain.
 * They abstract the persistence layer from business logic.
 *
 * @example
 * ```php
 * class UserRepository extends Repositories
 * {
 *     protected function getModel(): Model
 *     {
 *         return new User();
 *     }
 * }
 * ```
 */
abstract class Repositories
{
    /**
     * Get the model instance for this repository
     */
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
    public function with(array $relations = []): Builder
    {
        return $this->query()->with($relations);
    }

    /**
     * Get all records (with filters support)
     */
    public function all(array $columns = ['*']): Collection
    {
        return $this->buildQuery()->get($columns);
    }

    /**
     * Paginate records (with filters support)
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
     * Create a new record
     */
    public function create(array $data): Model
    {
        return $this->query()->create($data);
    }

    /**
     * Update a record by ID
     */
    public function update(array $data, string $id, string $attribute = 'id'): bool
    {
        $model = $this->query()
            ->where($attribute, '=', $id)
            ->first();

        if (!$model) {
            return false;
        }

        return $model->fill($data)->save();
    }

    /**
     * Delete a record by ID
     */
    public function delete(string $id): bool
    {
        return $this->getModel()->destroy($id) > 0;
    }

    /**
     * Find by ID
     */
    public function find(string|int $id, array $columns = ['*']): ?Model
    {
        return $this->query()->find($id, $columns);
    }

    /**
     * Find by ID or throw exception
     */
    public function findOrFail(string|int $id, array $columns = ['*']): Model
    {
        return $this->query()->findOrFail($id, $columns);
    }

    /**
     * Find by a specific column
     */
    public function findBy(string $column, mixed $value, array $columns = ['*']): ?Model
    {
        return $this->query()->where($column, $value)->first($columns);
    }

    /**
     * Find by email (common use case)
     */
    public function findByEmail(string $email, array $columns = ['*']): ?Model
    {
        return $this->findBy('email', $email, $columns);
    }

    /**
     * Find by UUID
     */
    public function findByUuid(string $uuid, array $columns = ['*']): ?Model
    {
        return $this->findBy('uuid', $uuid, $columns);
    }

    /**
     * Find records matching conditions
     */
    public function findWhere(array $conditions, array $columns = ['*']): Collection
    {
        return $this->query()->where($conditions)->get($columns);
    }

    /**
     * Find first record matching conditions
     */
    public function findWhereFirst(array $conditions, array $columns = ['*']): ?Model
    {
        return $this->query()->where($conditions)->first($columns);
    }

    /**
     * Find records where column is in array
     */
    public function findWhereIn(string $column, array $values, array $columns = ['*']): Collection
    {
        return $this->query()->whereIn($column, $values)->get($columns);
    }

    /**
     * Check if record exists
     */
    public function exists(string|int $id): bool
    {
        return $this->query()->where('id', $id)->exists();
    }

    /**
     * Check if record exists by conditions
     */
    public function existsWhere(array $conditions): bool
    {
        return $this->query()->where($conditions)->exists();
    }

    /**
     * Count records
     */
    public function count(): int
    {
        return $this->query()->count();
    }

    /**
     * Count records with conditions
     */
    public function countWhere(array $conditions): int
    {
        return $this->query()->where($conditions)->count();
    }

    /**
     * Get first record
     */
    public function first(array $columns = ['*']): ?Model
    {
        return $this->query()->first($columns);
    }

    /**
     * Get latest record
     */
    public function latest(string $column = 'created_at'): ?Model
    {
        return $this->query()->latest($column)->first();
    }

    /**
     * Get records between dates
     */
    public function whereBetweenDates(string $start, string $end, string $column = 'created_at'): Collection
    {
        return $this->query()->whereBetween($column, [$start, $end])->get();
    }

    /**
     * Get base query builder
     */
    protected function query(): Builder
    {
        return $this->getModel()->newQuery();
    }

    /**
     * Upsert records
     */
    public function upsert(array $values, array|string $uniqueBy, ?array $update = null): int
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
     * Increment a column value
     */
    public function increment(string $id, string $column, int $amount = 1): void
    {
        $this->query()->where('id', $id)->increment($column, $amount);
    }

    /**
     * Decrement a column value
     */
    public function decrement(string $id, string $column, int $amount = 1): void
    {
        $this->query()->where('id', $id)->decrement($column, $amount);
    }

    /**
     * Soft delete a record (if model uses SoftDeletes)
     */
    public function softDelete(string $id): bool
    {
        $model = $this->find($id);
        return $model ? $model->delete() : false;
    }

    /**
     * Restore a soft-deleted record
     */
    public function restore(string $id): bool
    {
        $model = $this->query()->withTrashed()->find($id);
        return $model ? $model->restore() : false;
    }

    /**
     * Force delete a record
     */
    public function forceDelete(string $id): bool
    {
        $model = $this->query()->withTrashed()->find($id);
        return $model ? $model->forceDelete() : false;
    }
}
