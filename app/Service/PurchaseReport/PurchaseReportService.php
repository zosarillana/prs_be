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
        $query = PurchaseReport::with('user'); // eager load user

        // Search by fields
        if (!empty($filters['searchTerm'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('series_no', 'like', '%' . $filters['searchTerm'] . '%')
                    ->orWhere('pr_purpose', 'like', '%' . $filters['searchTerm'] . '%')
                    ->orWhere('department', 'like', '%' . $filters['searchTerm'] . '%')
                    ->orWhereHas('user', function ($q) use ($filters) {
                        $q->where('name', 'like', '%' . $filters['searchTerm'] . '%')
                            ->orWhere('email', 'like', '%' . $filters['searchTerm'] . '%');
                    });
            });
        }

        // Date filters
        if (!empty($filters['submittedFrom']) && !empty($filters['submittedTo'])) {
            $query->whereBetween('date_submitted', [$filters['submittedFrom'], $filters['submittedTo']]);
        }

        if (!empty($filters['neededFrom']) && !empty($filters['neededTo'])) {
            $query->whereBetween('date_needed', [$filters['neededFrom'], $filters['neededTo']]);
        }

        if (!empty($filters['fromDate']) && !empty($filters['toDate'])) {
            $query->whereBetween('created_at', [$filters['fromDate'], $filters['toDate']]);
        }

        // Sorting
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
        // Ensure item_status is generated if not provided
        if (!isset($data['item_status']) && isset($data['tag']) && is_array($data['tag'])) {
            $data['item_status'] = array_fill(0, count($data['tag']), 'pending');
        }

        // Ensure remarks is generated if not provided
        if (!isset($data['remarks']) && isset($data['tag']) && is_array($data['tag'])) {
            $data['remarks'] = array_fill(0, count($data['tag']), '');
        }

        // Always set pr_status to "on_hold" on creation
        $data['pr_status'] = 'on_hold';

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
        return PurchaseReport::with('user')->findOrFail($id);
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
