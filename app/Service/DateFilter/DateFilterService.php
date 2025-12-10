<?php

namespace App\Service\DateFilter;

use Illuminate\Database\Eloquent\Builder;

class DateFilterService
{
    public static function apply(
        Builder $query,
        ?string $fromDate,
        ?string $toDate,
        string $column = 'created_at'
    ): Builder {
        // Handle empty dates
        if (empty($fromDate) && empty($toDate)) {
            return $query;
        }

        // For date columns, use simple date comparison
        if ($fromDate) {
            $query->whereDate($column, '>=', $fromDate);
        }

        if ($toDate) {
            $query->whereDate($column, '<=', $toDate);
        }

        return $query;
    }
}
