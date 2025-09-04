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
        // Eager load all user relationships that the mapper expects
        $query = PurchaseReport::with(['user', 'trUser', 'hodUser']);

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
            $data['item_status'] = array_map(function ($tag) {
                return str_ends_with($tag, '_tr') ? 'pending_tr' : 'pending';
            }, $data['tag']);
        }

        // Ensure remarks is generated if not provided
        if (!isset($data['remarks']) && isset($data['tag']) && is_array($data['tag'])) {
            $data['remarks'] = array_fill(0, count($data['tag']), '');
        }

        // Set pr_status depending on item_status
        if (isset($data['item_status']) && is_array($data['item_status'])) {
            if (in_array('pending', $data['item_status'])) {
                $data['pr_status'] = 'on_hold'; // at least one "pending"
            } else {
                $data['pr_status'] = 'on_hold_tr'; // only "pending_tr"
            }
        } else {
            $data['pr_status'] = 'on_hold'; // default fallback
        }

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
        return PurchaseReport::with(['user', 'trUser', 'hodUser'])->findOrFail($id);
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