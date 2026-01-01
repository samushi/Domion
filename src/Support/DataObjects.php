<?php

declare(strict_types=1);

namespace Samushi\Domion\Support;

use Closure;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use ReflectionParameter;

abstract class DataObjects
{
    protected static bool $isSnakeCase = true;

    /**
     * Without snake case
     * @return self
     * @throws ReflectionException
     */
    public static function noSnakeCase(): static
    {
        static::$isSnakeCase = false;
        $reflectionClass = new ReflectionClass(static::class);
        return $reflectionClass->newInstanceWithoutConstructor();
    }

    /**
     * Get validated all data and initialize with props
     *
     * @param FormRequest $request
     * @return DataObjects
     *
     * @throws ReflectionException
     */
    public static function fromRequest(FormRequest $request): static
    {
        return static::makeInstanceArgs($request->validated());
    }

    /**
     * Get from an array list and initialize with props
     *
     * @throws ReflectionException
     */
    public static function fromArray(array $data): static
    {
        return static::makeInstanceArgs($data);
    }

    /**
     * From a collection or list array
     */
    public static function fromList(array $data): Collection
    {
        return Collection::make($data)->transform(fn ($item) => static::makeInstanceArgs($item)->toArray());
    }

    /**
     * Transform array list
     */
    public static function fromListTransform(array $data, ?Closure $transform = null): Collection
    {
        return self::fromList($data)->collect()->transform($transform);
    }

    /**
     * Paginate the collection into a simple paginator
     */
    public static function paginate(array $data, ?Closure $transform = null, int $perPage = 15, string $pageName = 'page', ?int $page = null, array $options = []): Paginator
    {
        // Resolve Current Page
        $page = $page ?: Paginator::resolveCurrentPage($pageName);

        // Transform to the current required data
        $results = Collection::make($data)
            ->transform(fn ($item) => static::makeInstanceArgs($item)->toArray());

        // Transform from secondary data object
        $results = $transform ? $results->transform($transform) : $results;

        // Slice the results
        $results = $results->slice(($page - 1) * $perPage)
            ->take($perPage + 1);

        // Create simple options for paginator
        $options += [
            'path' => Paginator::resolveCurrentPath(),
            'pageName' => $pageName,
        ];

        return new Paginator($results, $perPage, $page, $options);
    }

    /**
     * Make object to array
     */
    public function toArray(array $excepts = []): array
    {
        return $this->excepts($excepts, $this->arrayKeysFromCamelToSnake());
    }

    /**
     * Make Array to the Collections
     */
    public function toCollection(array $excepts = []): Collection
    {
        return Collection::make($this->toArray($excepts));
    }

    /**
     * Get if is snake case
     * @return bool
     */
    private static function getIsSnakeCase(): bool
    {
        return static::$isSnakeCase;
    }

    /**
     * Make instance from array list
     *
     * @return $this
     *
     * @throws ReflectionException
     */
    private static function makeInstanceArgs(array $data): static
    {
        $parameters = static::getClassProperties();
        $args = array_map(function (ReflectionParameter $param) use ($data) {
            $key = self::isStringSnaked($param->getName(), static::getIsSnakeCase());
            
            if (array_key_exists($key, $data)) {
                return $data[$key];
            }

            if ($param->isDefaultValueAvailable()) {
                return $param->getDefaultValue();
            }

            return null;
        }, $parameters);

        return static::getReflectionClass()->newInstanceArgs($args);
    }

    /**
     * Get Reflection class
     */
    private static function getReflectionClass(): ReflectionClass
    {
        return new ReflectionClass(static::class);
    }

    /**
     * Get Reflection class
     */
    private static function getStaticContruction(): ReflectionMethod
    {
        return static::getReflectionClass()->getConstructor();
    }

    /**
     * Get Class parameters
     *
     * @return ReflectionParameter[]
     */
    private static function getClassProperties(): array
    {
        return static::getStaticContruction()->getParameters();
    }

    /**
     * Check if is snaked
     * @param string $key
     * @param bool $isSnaked
     * @return string
     */
    private static function isStringSnaked(string $key, bool $isSnaked = true): string
    {
        return $isSnaked ? Str::snake($key) : $key;
    }

    /**
     * Convert all camel properties to the snake
     */
    private function arrayKeysFromCamelToSnake(): array
    {
        $props = get_object_vars($this);
        $newKeys = array_map(fn ($key) => self::isStringSnaked($key, static::$isSnakeCase), array_keys($props));
        return array_combine($newKeys, $props);
    }

    /**
     * Excepts from data
     */
    private function excepts(array $excepts = [], array $props = []): ?array
    {
        foreach ($excepts as $except) {
            Arr::pull($props, $except);
        }

        return $props;
    }
}
