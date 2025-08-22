<?php

namespace App\Service\PurchaseReport;

use App\Models\PurchaseReport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class PurchaseReportService
{
    /**
     * Build the query for PurchaseReports with optional filters.
     *
     * @param  array  $filters
     * @return Builder
     */
    public function getQuery(array $filters = []): Builder
    {
        $query = PurchaseReport::query();

        // Optional search filter
        if (!empty($filters['searchTerm'])) {
            $query->where('title', 'like', '%' . $filters['searchTerm'] . '%');
        }

        // Example: optional date filter
        if (!empty($filters['fromDate']) && !empty($filters['toDate'])) {
            $query->whereBetween('created_at', [$filters['fromDate'], $filters['toDate']]);
        }

        // Example: optional sorting
        if (!empty($filters['sortBy'])) {
            $sortOrder = $filters['sortOrder'] ?? 'asc';
            $query->orderBy($filters['sortBy'], $sortOrder);
        }

        return $query;
    }

    /**
     * Store a new Purchase Report.
     *
     * @param  array  $data
     * @return PurchaseReport
     */
    public function store(array $data): PurchaseReport
    {
        return PurchaseReport::create($data);
    }

    /**
     * Get a single Purchase Report by ID.
     *
     * @param  int  $id
     * @return PurchaseReport
     *
     * @throws ModelNotFoundException
     */
    public function show(int $id): PurchaseReport
    {
        return PurchaseReport::findOrFail($id);
    }

    /**
     * Update a Purchase Report by ID.
     *
     * @param  int   $id
     * @param  array $data
     * @return PurchaseReport
     *
     * @throws ModelNotFoundException
     */
    public function update(int $id, array $data): PurchaseReport
    {
        $report = PurchaseReport::findOrFail($id);
        $report->update($data);

        return $report;
    }

    /**
     * Delete a Purchase Report by ID.
     *
     * @param  int  $id
     * @return bool|null
     *
     * @throws ModelNotFoundException
     */
    public function delete(int $id): ?bool
    {
        $report = PurchaseReport::findOrFail($id);
        return $report->delete();
    }
}
