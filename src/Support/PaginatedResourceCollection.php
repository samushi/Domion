<?php

namespace Samushi\Domion\Support;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

abstract class PaginatedResourceCollection extends ResourceCollection
{
    /**
     * Pagination status
     */
    protected bool $withPagination = true;

    /**
     * Transform the resource collection into an array.
     */
    public function toArray(Request $request): array
    {
        $data = $this->transformData($request);

        if ($this->shouldIncludePagination()) {
            return array_merge($data, $this->paginationData());
        }

        return $data;
    }

    /**
     * Transform data
     */
    abstract protected function transformData(Request $request): array;

    /**
     * Check if pagination should be included
     */
    protected function shouldIncludePagination(): bool
    {
        return $this->withPagination
            && method_exists($this->resource, 'hasPages')
            && $this->resource->hasPages();
    }

    /**
     * Get pagination data
     */
    protected function paginationData(): array
    {
        return [
            'links' => [
                'first' => $this->url(1),
                'last' => $this->url($this->lastPage()),
                'prev' => $this->previousPageUrl(),
                'next' => $this->nextPageUrl(),
            ],
            'meta' => [
                'current_page' => $this->currentPage(),
                'from' => $this->firstItem(),
                'last_page' => $this->lastPage(),
                'path' => $this->path(),
                'per_page' => $this->perPage(),
                'to' => $this->lastItem(),
                'total' => $this->total(),
            ]
        ];
    }

    /**
     * Without pagination
     */
    public function withoutPagination(): static
    {
        $this->withPagination = false;
        return $this;
    }

    /**
     * With pagination
     */
    public function withPagination(): static
    {
        $this->withPagination = true;
        return $this;
    }
}
