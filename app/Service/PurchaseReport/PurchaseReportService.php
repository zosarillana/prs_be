<?php

namespace App\Service\PurchaseReport;

use App\Contracts\TableQueryService;
use App\Events\Global\GlobalPurchaseReportApprovalUpdated;
use App\Models\PurchaseReport;
use App\Models\Tags;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PurchaseReportService implements TableQueryService
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
        $query = PurchaseReport::with(['user', 'trUser', 'hodUser', 'purchaserUser']);

        // 🔎 Search by fields including dates
        // 🔎 Search by fields including dates and PO number
        if (! empty($filters['searchTerm'])) {
            $search = $filters['searchTerm'];

            $query->where(function ($q) use ($search) {
                $q->where('series_no', 'like', "%{$search}%")
                    ->orWhere('po_no', 'like', "%{$search}%")          // ✅ ADD THIS
                    ->orWhere('pr_purpose', 'like', "%{$search}%")
                    ->orWhere('department', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    })
                    // ✅ Date search
                    ->orWhereRaw(
                        'DATE_FORMAT(created_at, "%Y-%m-%d") LIKE ?',
                        ["%{$search}%"]
                    );
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

        // ✅ Filter for own department
        $ownDepartment = filter_var($filters['ownDepartment'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if ($ownDepartment) {
            $user = Auth::user();
            $userDepartment = $user->department;
            $query->where('department', $userDepartment);
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
                $filters['submittedTo'],
            ]);
        }

        if (! empty($filters['neededFrom']) && ! empty($filters['neededTo'])) {
            $query->whereBetween('date_needed', [
                $filters['neededFrom'],
                $filters['neededTo'],
            ]);
        }

        // ❌ REMOVE THIS BLOCK - it's causing the duplicate filter
        // The whereDate in the controller handles this correctly
        /*
    if (! empty($filters['fromDate']) && ! empty($filters['toDate'])) {
        $query->whereBetween('created_at', [
            $filters['fromDate'],
            $filters['toDate']
        ]);
    }
    */

        // Sorting
        if (! empty($filters['sortBy'])) {
            $sortOrder = $filters['sortOrder'] ?? 'asc';
            $query->orderBy($filters['sortBy'], $sortOrder);
        }

        return $query;
    }

    /**
     * Generate new Series No.
     */
    private function generateSeriesNo(): int
    {
        $startingSeries = 12000;

        // Lock table so parallel requests WAIT
        DB::statement('LOCK TABLES purchase_reports WRITE');

        try {
            // Get all existing series numbers
            $existingSeries = PurchaseReport::pluck('series_no')->toArray();

            // If no records exist, start from the beginning
            if (empty($existingSeries)) {
                return $startingSeries;
            }

            // Get the maximum series number
            $maxSeries = max($existingSeries);

            // Check for gaps in the series starting from the base number
            for ($i = $startingSeries; $i <= $maxSeries; $i++) {
                if (! in_array($i, $existingSeries)) {
                    // Found a gap - reuse this number
                    return $i;
                }
            }

            // No gaps found, increment from the maximum
            return $maxSeries + 1;
        } finally {
            // Always unlock tables, even if an error occurs
            DB::statement('UNLOCK TABLES');
        }
    }

    public function storeOrUpdate(array $data): PurchaseReport
    {
        // ✅ Explicitly cast is_draft to boolean
        $isDraft = isset($data['is_draft']) && filter_var($data['is_draft'], FILTER_VALIDATE_BOOLEAN);

        \Log::info('🔍 [1] RECEIVED DATA', [
            'is_draft' => $isDraft,
            'incoming_tag_count' => count($data['tag'] ?? []),
            'incoming_tags' => $data['tag'] ?? [],
            'quantity_count' => count($data['quantity'] ?? []),
            'description_count' => count($data['item_description'] ?? []),
        ]);

        // ✅ IMPORTANT: Get item count BEFORE processing tags
        $itemCount = count($data['tag'] ?? []);

        \Log::info('🔢 [2] ITEM COUNT', [
            'item_count' => $itemCount,
        ]);

        // Process tags - convert IDs to objects
        if (isset($data['tag']) && is_array($data['tag'])) {
            $tagIds = collect($data['tag'])
                ->map(fn ($tag) => is_numeric($tag) ? (int) $tag : ($tag['id'] ?? null))
                ->filter()
                ->toArray();

            \Log::info('🏷️ [3] TAG IDs EXTRACTED', [
                'tag_ids' => $tagIds,
                'tag_ids_count' => count($tagIds),
            ]);

            $tags = Tags::with('department')
                ->whereIn('id', $tagIds)
                ->get();

            $data['tag'] = collect($tagIds)->map(function ($tagId) use ($tags) {
                $tag = $tags->firstWhere('id', $tagId);

                return [
                    'id' => $tag?->id,
                    'description' => $tag?->description,
                    'department' => $tag?->department?->name,
                ];
            })->toArray();

            \Log::info('🏷️ [4] PROCESSED TAGS', [
                'processed_tag_count' => count($data['tag']),
                'processed_tags' => $data['tag'],
            ]);
        }

        // ✅ CRITICAL: Use the item count from BEFORE tag processing
        // Because tag processing might filter out nulls or invalid tags

        // ✅ Generate item_status - always pending when submitted (not drafted)
        if ($isDraft) {
            $data['item_status'] = array_fill(0, $itemCount, 'drafted');
        } else {
            // All items become pending when submitted
            $data['item_status'] = array_fill(0, $itemCount, 'pending');
        }

        \Log::info('📊 [5] GENERATED item_status', [
            'item_count' => $itemCount,
            'item_status_array' => $data['item_status'],
            'item_status_count' => count($data['item_status']),
        ]);

        // ✅ Handle remarks - preserve existing or create new
        if (! empty($data['id'])) {
            $existing = PurchaseReport::find($data['id']);
            if ($existing && isset($existing->remarks)) {
                $existingRemarks = $existing->remarks;
                $newRemarks = $data['remarks'] ?? [];

                $mergedRemarks = [];
                for ($i = 0; $i < $itemCount; $i++) {
                    $mergedRemarks[$i] = $newRemarks[$i] ?? $existingRemarks[$i] ?? '';
                }
                $data['remarks'] = $mergedRemarks;
            }
        }

        // Ensure remarks array matches item count
        if (! isset($data['remarks']) || count($data['remarks']) !== $itemCount) {
            $data['remarks'] = array_pad($data['remarks'] ?? [], $itemCount, '');
        }

        // ✅ Set PR status - always on_hold when submitted (not drafted)
        $data['pr_status'] = $isDraft ? 'drafted' : 'on_hold';

        \Log::info('📦 [6] FINAL DATA BEFORE SAVE', [
            'pr_status' => $data['pr_status'],
            'item_status' => $data['item_status'],
            'item_status_count' => count($data['item_status']),
            'tag_count' => count($data['tag']),
            'quantity_count' => count($data['quantity'] ?? []),
            'remarks_count' => count($data['remarks']),
        ]);

        // Update existing record
        if (! empty($data['id'])) {
            $existing = PurchaseReport::find($data['id']);
            if ($existing) {
                $existing->update($data);
                $fresh = $existing->fresh();

                \Log::info('✅ [7] AFTER UPDATE & FRESH', [
                    'item_status' => $fresh->item_status,
                    'item_status_count' => count($fresh->item_status ?? []),
                    'pr_status' => $fresh->pr_status,
                    'tag_count' => count($fresh->tag ?? []),
                ]);

                return $fresh;
            }
        }

        // Create new record with generated series no
        $data['series_no'] = $this->generateSeriesNo();

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

        \Log::info('🔧 [UPDATE] Start', [
            'id' => $id,
            'incoming_tag_count' => count($data['tag'] ?? []),
            'incoming_is_draft' => $data['is_draft'] ?? 'not set',
            'existing_pr_status' => $report->pr_status,
            'existing_item_status_count' => count($report->item_status ?? []),
        ]);

        // ✅ If 'tag' contains IDs (not objects yet), fetch their descriptions + departments
        if (isset($data['tag']) && is_array($data['tag']) && isset($data['tag'][0]) && ! is_array($data['tag'][0])) {
            $tags = Tags::with('department')
                ->whereIn('id', $data['tag'])
                ->get();

            $data['tag'] = collect($data['tag'])->map(function ($tagId) use ($tags) {
                $tag = $tags->firstWhere('id', $tagId);

                return [
                    'id' => $tag?->id,
                    'description' => $tag?->description,
                    'department' => $tag?->department?->name,
                ];
            })->toArray();

            \Log::info('🔧 [UPDATE] Processed tags', [
                'tags' => $data['tag'],
            ]);
        }

        // ✅ NEW: Get the new item count
        $newItemCount = count($data['tag'] ?? []);
        $oldItemCount = count($report->item_status ?? []);

        \Log::info('🔧 [UPDATE] Item counts', [
            'new_item_count' => $newItemCount,
            'old_item_count' => $oldItemCount,
        ]);

        // ✅ Determine if this submission is a draft
        $isDraft = isset($data['is_draft']) && filter_var($data['is_draft'], FILTER_VALIDATE_BOOLEAN);

        \Log::info('🔧 [UPDATE] Draft status', [
            'is_draft' => $isDraft,
            'incoming_is_draft_raw' => $data['is_draft'] ?? 'not set',
        ]);

        // ✅ ADD THIS - Log the incoming item_status
        \Log::info('🔧 [UPDATE] Incoming item_status', [
            'incoming_item_status' => $data['item_status'] ?? 'not set',
            'incoming_count' => isset($data['item_status']) ? count($data['item_status']) : 0,
        ]);

        // ✅ Handle item_status updates
        if ($newItemCount !== $oldItemCount || ! isset($data['item_status'])) {
            // Item count changed OR no item_status provided - regenerate

            if ($isDraft) {
                // Saving as draft - keep all as drafted
                $data['item_status'] = array_fill(0, $newItemCount, 'drafted');
            } else {
                // Submitting (not a draft) - all items become pending
                $data['item_status'] = array_fill(0, $newItemCount, 'pending');
            }
        } else {
            // Item count same AND item_status provided

            if (! $isDraft && $report->pr_status === 'drafted') {
                // Submitting a previously drafted PR - all items become pending
                $data['item_status'] = array_fill(0, $newItemCount, 'pending');
            } else {
                // ✅ Normal update: Use the incoming item_status from the request
                $incomingStatuses = $data['item_status'];

                $newStatuses = [];
                foreach ($incomingStatuses as $idx => $status) {
                    if (in_array($status, ['rejected', 'rejected_tr', 'return'], true)) {
                        $newStatuses[$idx] = 'pending';
                    } else {
                        $newStatuses[$idx] = $status;
                    }
                }
                $data['item_status'] = $newStatuses;
            }
        }

        \Log::info('🔧 [UPDATE] Final item_status', [
            'item_status' => $data['item_status'],
            'item_status_count' => count($data['item_status']),
        ]);

        // ✅ Update remarks count if necessary
        if (isset($data['remarks']) && count($data['remarks']) < $newItemCount) {
            $missing = $newItemCount - count($data['remarks']);
            $data['remarks'] = array_merge($data['remarks'], array_fill(0, $missing, ''));
        }

        // ✅ Determine PR status - always on_hold when submitted (not drafted)
        if ($isDraft) {
            $data['pr_status'] = 'drafted';
        } else {
            $data['pr_status'] = 'on_hold';
        }

        \Log::info('🔧 [UPDATE] Final pr_status', [
            'pr_status' => $data['pr_status'],
        ]);

        // ✅ Perform the update
        $report->fill($data);
        $report->save();

        return $report->fresh();
    }

    /**
     * Update delivery status
     *
     * @throws ModelNotFoundException
     */
    public function updateDeliveryStatus(int $id, string $status): PurchaseReport
    {
        $report = PurchaseReport::findOrFail($id);

        // ✅ Only allow specific statuses
        if (! in_array($status, ['pending', 'delivered', 'partial'])) {
            throw new \InvalidArgumentException("Invalid delivery status: {$status}");
        }

        $report->delivery_status = $status;
        $report->save();

        // ✅ Trigger audit log
        auditLog('updated', $report, ['delivery_status' => $report->getOriginal('delivery_status')], ['delivery_status' => $status]);

        // ✅ Notify about delivery status change
        $this->notify->notifyDeliveryStatusUpdated($report);

        // ✅ Broadcast the global event
        event(new GlobalPurchaseReportApprovalUpdated($report, 'delivery_status_updated'));

        return $report->fresh();
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

    public function removeItemRow(int $id, int $rowIndex): PurchaseReport
    {
        $report = PurchaseReport::findOrFail($id);

        $removeAtIndex = function (?array $array) use ($rowIndex) {
            if (! is_array($array)) {
                return $array;
            }

            if (! array_key_exists($rowIndex, $array)) {
                return array_values($array);
            }

            unset($array[$rowIndex]);

            return array_values($array); // reindex
        };

        // Remove item data
        $report->quantity = $removeAtIndex($report->quantity);
        $report->unit = $removeAtIndex($report->unit);
        $report->item_description = $removeAtIndex($report->item_description);
        $report->tag = $removeAtIndex($report->tag);
        $report->remarks = $removeAtIndex($report->remarks);
        $report->item_status = $removeAtIndex($report->item_status);

        /**
         * ✅ Recalculate PR Status AFTER removal
         */
        $statuses = $report->item_status ?? [];
        $totalItems = count($statuses);

        if ($totalItems === 0) {
            // No items left
            $report->pr_status = 'drafted';

        } elseif (count(array_filter($statuses, fn ($s) => $s === 'rejected')) === $totalItems) {
            // All rejected
            $report->pr_status = 'rejected';

        } elseif (in_array('pending_tr', $statuses, true)) {
            // Any pending TR
            $report->pr_status = 'on_hold_tr';

        } elseif (in_array('pending', $statuses, true)) {
            // Any pending
            $report->pr_status = 'on_hold';

        } else {
            // All approved
            $report->pr_status = 'for_approval';
        }

        $report->save();

        return $report->fresh();
    }
}
