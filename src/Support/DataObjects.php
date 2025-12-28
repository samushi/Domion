<?php

declare(strict_types=1);

namespace Samushi\Domion\Support;

use Closure;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use JsonSerializable;
use ReflectionClass;
use ReflectionParameter;

/**
 * Base Data Transfer Object (DTO) class
 *
 * DTOs are used to transfer data between layers in DDD.
 * They are immutable and provide type safety.
 *
 * @example
 * ```php
 * class LoginDto extends DataObjects
 * {
 *     public function __construct(
 *         public readonly string $email,
 *         public readonly string $password,
 *     ) {}
 * }
 *
 * // Usage:
 * $dto = LoginDto::fromRequest($request);
 * $dto = LoginDto::fromArray(['email' => '...', 'password' => '...']);
 * ```
 */
abstract class DataObjects implements Arrayable, JsonSerializable
{
    /**
     * Whether to convert property names to snake_case
     */
    protected static bool $isSnakeCase = true;

    /**
     * Create DTO without snake_case conversion
     */
    public static function noSnakeCase(): static
    {
        static::$isSnakeCase = false;
        $reflectionClass = new ReflectionClass(static::class);
        return $reflectionClass->newInstanceWithoutConstructor();
    }

    /**
     * Create DTO from a validated FormRequest
     */
    public static function fromRequest(FormRequest $request): static
    {
        return static::makeInstanceArgs($request->validated());
    }

    /**
     * Create DTO from an array
     */
    public static function fromArray(array $data): static
    {
        return static::makeInstanceArgs($data);
    }

    /**
     * Create a collection of DTOs from an array of arrays
     */
    public static function fromList(array $data): Collection
    {
        return Collection::make($data)->map(
            fn (array $item) => static::makeInstanceArgs($item)
        );
    }

    /**
     * Create and transform a collection of DTOs
     */
    public static function fromListTransform(array $data, ?Closure $transform = null): Collection
    {
        $collection = static::fromList($data);
        return $transform ? $collection->map($transform) : $collection;
    }

    /**
     * Create a paginated collection of DTOs
     */
    public static function paginate(
        array $data,
        ?Closure $transform = null,
        int $perPage = 15,
        string $pageName = 'page',
        ?int $page = null,
        array $options = []
    ): Paginator {
        $page = $page ?: Paginator::resolveCurrentPage($pageName);

        $results = Collection::make($data)
            ->map(fn (array $item) => static::makeInstanceArgs($item)->toArray());

        if ($transform) {
            $results = $results->map($transform);
        }

        $results = $results->slice(($page - 1) * $perPage)->take($perPage + 1);

        $options += [
            'path' => Paginator::resolveCurrentPath(),
            'pageName' => $pageName,
        ];

        return new Paginator($results, $perPage, $page, $options);
    }

    /**
     * Create a length-aware paginated collection of DTOs
     */
    public static function paginateWithTotal(
        array $data,
        int $total,
        int $perPage = 15,
        ?int $currentPage = null,
        array $options = []
    ): LengthAwarePaginator {
        $currentPage = $currentPage ?: Paginator::resolveCurrentPage();

        $results = Collection::make($data)
            ->map(fn (array $item) => static::makeInstanceArgs($item)->toArray());

        $options += ['path' => Paginator::resolveCurrentPath()];

        return new LengthAwarePaginator($results, $total, $perPage, $currentPage, $options);
    }

    /**
     * Convert DTO to array
     */
    public function toArray(array $excepts = []): array
    {
        return $this->excepts($excepts, $this->arrayKeysFromCamelToSnake());
    }

    /**
     * Convert DTO to Collection
     */
    public function toCollection(array $excepts = []): Collection
    {
        return Collection::make($this->toArray($excepts));
    }

    /**
     * Convert DTO to JSON
     */
    public function toJson(int $options = 0): string
    {
        return json_encode($this->toArray(), $options);
    }

    /**
     * Get a specific property value
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->{$key} ?? $default;
    }

    /**
     * Check if a property exists and is not null
     */
    public function has(string $key): bool
    {
        return isset($this->{$key});
    }

    /**
     * Get only specific keys from the DTO
     */
    public function only(array $keys): array
    {
        return Arr::only($this->toArray(), $keys);
    }

    /**
     * Get all except specific keys from the DTO
     */
    public function except(array $keys): array
    {
        return Arr::except($this->toArray(), $keys);
    }

    /**
     * Implement JsonSerializable
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Get if is snake case
     */
    private static function getIsSnakeCase(): bool
    {
        return static::$isSnakeCase;
    }

    /**
     * Make instance from array
     */
    private static function makeInstanceArgs(array $data): static
    {
        $reflection = new ReflectionClass(static::class);
        $constructor = $reflection->getConstructor();

        if (!$constructor) {
            return $reflection->newInstance();
        }

        $args = array_map(
            function (ReflectionParameter $param) use ($data) {
                $snakeName = Str::snake($param->getName());
                $camelName = $param->getName();

                // Try snake_case first, then camelCase
                if (array_key_exists($snakeName, $data)) {
                    return $data[$snakeName];
                }
                if (array_key_exists($camelName, $data)) {
                    return $data[$camelName];
                }

                // Return default value if available
                if ($param->isDefaultValueAvailable()) {
                    return $param->getDefaultValue();
                }

                // Return null if nullable
                if ($param->allowsNull()) {
                    return null;
                }

                return null;
            },
            $constructor->getParameters()
        );

        return $reflection->newInstanceArgs($args);
    }

    /**
     * Check if is snaked
     */
    private static function isStringSnaked(string $key, bool $isSnaked = true): string
    {
        return $isSnaked ? Str::snake($key) : $key;
    }

    /**
     * Convert all camel properties to snake_case
     */
    private function arrayKeysFromCamelToSnake(): array
    {
        $props = get_object_vars($this);
        $newKeys = array_map(
            fn (string $key) => self::isStringSnaked($key, static::$isSnakeCase),
            array_keys($props)
        );
        return array_combine($newKeys, $props);
    }

    /**
     * Exclude keys from props
     */
    private function excepts(array $excepts, array $props): array
    {
        foreach ($excepts as $except) {
            Arr::pull($props, $except);
        }
        return $props;
    }
}
