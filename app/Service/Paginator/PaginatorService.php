<?php

namespace App\Service\Paginator;

use Illuminate\Database\Eloquent\Builder;

class PaginatorService
{
    /**
     * Paginate any Eloquent query with a clean JSON format.
     *
     * @param  Builder  $query
     * @param  int      $pageNumber
     * @param  int      $pageSize
     * @return array
     */
    public function paginate(Builder $query, int $pageNumber = 1, int $pageSize = 10): array
    {
        $totalItems = $query->count();
        $totalPages = (int) ceil($totalItems / $pageSize);

        $items = $query
            ->skip(($pageNumber - 1) * $pageSize)
            ->take($pageSize)
            ->get();

        return [
            'pageNumber' => $pageNumber,
            'pageSize'   => $pageSize,
            'totalItems' => $totalItems,
            'totalPages' => $totalPages,
            'items'      => $items,
        ];
    }
}
