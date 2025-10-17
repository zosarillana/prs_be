<?php

namespace App\Service\PurchaseReport;

use App\Models\PurchaseReport;
use App\Models\Tags;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class PurchaseReportService
{
    /**
     * Build the query for PurchaseReports with optional filters.
     *
     * @param  array  $filterssearchTerm
     * @return Builder
     */
    protected PurchaseReportNotificationService $notify;

    public function __construct(PurchaseReportNotificationService $notify)
    {
        $this->notify = $notify;
    }

    public function getQuery(array $filters = []): Builder
    {
        // Eager load all user relationships that the mapper expects
        $query = PurchaseReport::with(['user', 'trUser', 'hodUser']);

        // 🔎 Search by fields
        if (! empty($filters['searchTerm'])) {
            $search = $filters['searchTerm'];
            $query->where(function ($q) use ($search) {
                $q->where('series_no', 'like', "%{$search}%")
                    ->orWhere('pr_purpose', 'like', "%{$search}%")
                    ->orWhere('department', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        // ✅ PO Status filter (case-insensitive)
        if (! empty($filters['statusTerm'])) {
            $statuses = $filters['statusTerm'];
            if (! is_array($statuses)) {
                $statuses = explode(',', $statuses);
            }
            $statuses = array_map('strtolower', $statuses);
            $query->where(function ($q) use ($statuses) {
                foreach ($statuses as $status) {
                    $q->orWhereRaw('LOWER(po_status) = ?', [$status]);
                }
            });
        }

        // ✅ PR Status filter (case-insensitive)
        if (! empty($filters['prStatusTerm'])) {
            $prStatuses = $filters['prStatusTerm'];
            if (! is_array($prStatuses)) {
                $prStatuses = explode(',', $prStatuses);
            }
            $prStatuses = array_map('strtolower', $prStatuses);
            $query->where(function ($q) use ($prStatuses) {
                foreach ($prStatuses as $status) {
                    $q->orWhereRaw('LOWER(pr_status) = ?', [$status]);
                }
            });
        }

        // ✅ Filter for completed TR
        $completedTr = filter_var($filters['completedTr'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if ($completedTr) {
            $query->whereNotNull('tr_user_id');
        }

        // Date filters
        if (! empty($filters['submittedFrom']) && ! empty($filters['submittedTo'])) {
            $query->whereBetween('date_submitted', [
                $filters['submittedFrom'],
                $filters['submittedTo']
            ]);
        }

        if (! empty($filters['neededFrom']) && ! empty($filters['neededTo'])) {
            $query->whereBetween('date_needed', [
                $filters['neededFrom'],
                $filters['neededTo']
            ]);
        }

        if (! empty($filters['fromDate']) && ! empty($filters['toDate'])) {
            $query->whereBetween('created_at', [
                $filters['fromDate'],
                $filters['toDate']
            ]);
        }

        // Sorting
        if (! empty($filters['sortBy'])) {
            $sortOrder = $filters['sortOrder'] ?? 'asc';
            $query->orderBy($filters['sortBy'], $sortOrder);
        }

        return $query;
    }


    /**
     * Store a new Purchase Report.
     */

    public function store(array $data): PurchaseReport
    {
        // ✅ If 'tag' contains IDs, fetch their descriptions + departments
        if (isset($data['tag']) && is_array($data['tag'])) {
            // Fetch all unique tag models first
            $tags = Tags::with('department')
                ->whereIn('id', $data['tag'])
                ->get();

            // ✅ Map each tag ID from payload to its corresponding Tag model (preserving order and duplicates)
            $data['tag'] = collect($data['tag'])->map(function ($tagId) use ($tags) {
                $tag = $tags->firstWhere('id', $tagId);
                return [
                    'id' => $tag?->id,
                    'description' => $tag?->description,
                    'department' => $tag?->department?->name,
                ];
            })->toArray();
        }

        // ✅ Generate item_status — same length as tags
        if (!isset($data['item_status']) && isset($data['tag']) && is_array($data['tag'])) {
            $data['item_status'] = array_map(function ($tag) {
                $desc = $tag['description'] ?? '';
                return str_ends_with($desc, '_tr') ? 'pending' : 'pending';
            }, $data['tag']);
        }

        // ✅ Generate remarks — same length as tags
        if (!isset($data['remarks']) && isset($data['tag']) && is_array($data['tag'])) {
            $data['remarks'] = array_fill(0, count($data['tag']), '');
        }

        // ✅ Determine PR status
        if (isset($data['item_status']) && is_array($data['item_status'])) {
            if (in_array('pending', $data['item_status'])) {
                $data['pr_status'] = 'on_hold';
            } else {
                $data['pr_status'] = 'on_hold_tr';
            }
        } else {
            $data['pr_status'] = 'on_hold';
        }

        // ✅ Create the PurchaseReport record
        return PurchaseReport::create($data);
    }

    /**
     * Get a single Purchase Report by ID.
     *
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
     *
     * @throws ModelNotFoundException
     */
    public function update(int $id, array $data): PurchaseReport
    {
        $report = PurchaseReport::findOrFail($id);

        // ✅ If 'tag' contains IDs (not objects yet), fetch their descriptions + departments
        if (isset($data['tag']) && is_array($data['tag']) && isset($data['tag'][0]) && !is_array($data['tag'][0])) {
            // Fetch all unique tag models first
            $tags = Tags::with('department')
                ->whereIn('id', $data['tag'])
                ->get();

            // ✅ Map each tag ID from payload to its corresponding Tag model (preserving order and duplicates)
            $data['tag'] = collect($data['tag'])->map(function ($tagId) use ($tags) {
                $tag = $tags->firstWhere('id', $tagId);
                return [
                    'id' => $tag?->id,
                    'description' => $tag?->description,
                    'department' => $tag?->department?->name,
                ];
            })->toArray();
        }

        // ✅ Handle item_status updates (keep existing unless changed)
        $currentStatuses = $report->item_status ?? [];
        $tags = $data['tag'] ?? $report->tag ?? [];

        $newStatuses = [];
        foreach ($currentStatuses as $idx => $status) {
            $tag = $tags[$idx] ?? null;
            $desc = is_array($tag) ? ($tag['description'] ?? '') : '';

            // If rejected → reset to pending / pending_tr
            if (in_array($status, ['rejected', 'rejected_tr'], true)) {
                $newStatuses[$idx] = str_ends_with($desc, '_tr') ? 'pending_tr' : 'pending';
            } else {
                $newStatuses[$idx] = $status;
            }
        }

        // ✅ Merge the computed statuses into the update payload
        $data['item_status'] = $newStatuses;

        // ✅ Update remarks count if necessary
        if (isset($data['remarks']) && count($data['remarks']) < count($tags)) {
            $missing = count($tags) - count($data['remarks']);
            $data['remarks'] = array_merge($data['remarks'], array_fill(0, $missing, ''));
        }

        // ✅ Determine PR status again
        if (isset($data['item_status']) && is_array($data['item_status'])) {
            if (in_array('pending', $data['item_status'])) {
                $data['pr_status'] = 'on_hold';
            } else {
                $data['pr_status'] = 'on_hold_tr';
            }
        } else {
            $data['pr_status'] = 'on_hold';
        }

        // ✅ Perform the update
        $report->fill($data);
        $report->save();

        return $report->fresh(); // always return the fresh DB state
    }


    /**
     * Delete a Purchase Report by ID.
     *
     *
     * @throws ModelNotFoundException
     */
    public function delete(int $id): ?bool
    {
        $report = PurchaseReport::findOrFail($id);

        return $report->delete();
    }
}
