<?php

declare(strict_types=1);

namespace Samushi\Domion\Helpers;

use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class PathResolver
{
    /**
     * Base path cache for performance optimization.
     */
    protected array $basePathCache = [];

    /**
     * Resolve path to Domain directory.
     */
    public function domain(string $path = ''): string
    {
        if (empty($path)) {
            return $this->getCachedBasePath('domain', 'app/Domain');
        }

        return $this->resolvePath('domain', 'app/Domain', $path);
    }

    /**
     * Resolve path to Support directory.
     */
    public function support(string $path = ''): string
    {
        if (empty($path)) {
            return $this->getCachedBasePath('support', 'app/Support');
        }

        return $this->resolvePath('support', 'app/Support', $path);
    }

    /**
     * Check if a resolved path exists in the filesystem.
     */
    public function exists(string $domain, string $path): bool
    {
        $resolvedPath = match ($domain) {
            'domain' => $this->domain($path),
            'support' => $this->support($path),
            default => throw new InvalidArgumentException("Invalid domain: {$domain}")
        };

        return file_exists($resolvedPath);
    }

    /**
     * Require a PHP file from the specified domain.
     */
    public function require(string $domain, string $path): mixed
    {
        $resolvedPath = match ($domain) {
            'domain' => $this->domain($path),
            'support' => $this->support($path),
            default => throw new InvalidArgumentException("Invalid domain: {$domain}")
        };

        if (!file_exists($resolvedPath)) {
            throw new RuntimeException("File not found: {$resolvedPath}");
        }

        return require $resolvedPath;
    }

    /**
     * Get directory contents for a domain path.
     */
    public function scan(string $domain, string $path = '', bool $onlyDirectories = false): array
    {
        $resolvedPath = match ($domain) {
            'domain' => $this->domain($path),
            'support' => $this->support($path),
            default => throw new InvalidArgumentException("Invalid domain: {$domain}")
        };

        if (!is_dir($resolvedPath)) {
            return [];
        }

        $pattern = $resolvedPath . DIRECTORY_SEPARATOR . '*';
        $flags = $onlyDirectories ? GLOB_ONLYDIR : 0;

        return glob($pattern, $flags) ?: [];
    }

    /**
     * Get cached base path or compute and cache if not exists.
     */
    protected function getCachedBasePath(string $cacheKey, string $relativeBase): string
    {
        if (!isset($this->basePathCache[$cacheKey])) {
            $this->basePathCache[$cacheKey] = base_path($relativeBase);
        }

        return $this->basePathCache[$cacheKey];
    }

    /**
     * Core path resolution logic with caching, normalization, and dot notation support.
     */
    protected function resolvePath(string $cacheKey, string $relativeBase, string $path): string
    {
        $basePath = $this->getCachedBasePath($cacheKey, $relativeBase);

        if (empty($path) || $path === '/' || $path === '\\') {
            return $basePath;
        }

        $normalizedPath = $this->normalizePath($path);

        return $basePath . DIRECTORY_SEPARATOR . $normalizedPath;
    }

    /**
     * Normalize path separators and handle dot notation.
     */
    protected function normalizePath(string $path): string
    {
        $hasDotNotation = str_contains($path, '.') &&
            !str_contains($path, '/') &&
            !str_contains($path, '\\');

        if ($hasDotNotation) {
            $parts = explode('.', $path);
            $parts = array_map(fn ($part) => Str::studly($part), $parts);
            $normalized = implode(DIRECTORY_SEPARATOR, $parts);
        } else {
            $normalized = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
        }

        return trim($normalized, DIRECTORY_SEPARATOR);
    }
}
