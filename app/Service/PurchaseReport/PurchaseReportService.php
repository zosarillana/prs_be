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

        if (! empty($filters['searchTerm'])) {
            $this->applyGlobalSearch($query, $filters['searchTerm']);
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

    protected function applyDynamicFilters(Builder $query, array $filters): void
    {
        foreach ($this->filterMap as $field => $type) {
            if (! isset($filters[$field]) || $filters[$field] === '') {
                continue;
            }

            $this->applyFilterByType($query, $field, $filters[$field], $type);
        }
    }

    protected function applyFilterByType(
        Builder $query,
        string $field,
        mixed $value,
        string $type
    ): void {
        match ($type) {

            // 🔍 string search
            'like' => $query->where($field, 'LIKE', "%{$value}%"),

            // 🎯 exact match
            'equals' => $query->where($field, $value),

            // 📌 multiple values
            'in' => $query->whereIn(
                $field,
                is_array($value) ? $value : explode(',', $value)
            ),

            // 📅 date range
            'date' => $this->applyDateFilter($query, $field, $value),

            // 🧩 JSON contains value
            'json_like' => $query->whereRaw(
                "JSON_SEARCH($field, 'one', ?) IS NOT NULL",
                ["%{$value}%"]
            ),

            // 🧩 JSON array contains enum
            'json_in' => $this->applyJsonInFilter($query, $field, $value),

            default => null,
        };
    }

    protected function applyDateFilter(Builder $query, string $field, mixed $value): void
    {
        if (is_array($value) && isset($value['from'], $value['to'])) {
            $query->whereBetween($field, [$value['from'], $value['to']]);
        }
    }

    protected function applyJsonInFilter(
        Builder $query,
        string $field,
        mixed $value
    ): void {
        $values = is_array($value) ? $value : explode(',', $value);

        $query->where(function ($q) use ($field, $values) {
            foreach ($values as $v) {
                $q->orWhereRaw(
                    "JSON_CONTAINS($field, JSON_QUOTE(?))",
                    [$v]
                );
            }
        });
    }

    protected function applyGlobalSearch(Builder $query, string $search): void
    {
        $query->where(function ($q) use ($search) {

            /** --------------------
             *  Normal columns
             * -------------------- */
            $q->orWhere('series_no', 'like', "%{$search}%")
                ->orWhereRaw('CAST(sap_id AS CHAR) LIKE ?', ["%{$search}%"])
                ->orWhereRaw('CAST(po_no AS CHAR) LIKE ?', ["%{$search}%"])

                ->orWhere('po_status', 'like', "%{$search}%")
                ->orWhere('pr_status', 'like', "%{$search}%")
                ->orWhere('delivery_status', 'like', "%{$search}%")
                ->orWhere('pr_purpose', 'like', "%{$search}%")
                ->orWhere('department', 'like', "%{$search}%");

            /** --------------------
             *  Date columns (string match)
             * -------------------- */
            $q->orWhereRaw('DATE(created_at) LIKE ?', ["%{$search}%"])
                ->orWhereRaw('DATE(date_submitted) LIKE ?', ["%{$search}%"])
                ->orWhereRaw('DATE(date_needed) LIKE ?', ["%{$search}%"])
                ->orWhereRaw('DATE(po_created_date) LIKE ?', ["%{$search}%"])
                ->orWhereRaw('DATE(po_approved_date) LIKE ?', ["%{$search}%"]);

            /** --------------------
             *  Relationships
             * -------------------- */
            $q->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
            );

            $q->orWhereHas('trUser', fn ($u) => $u->where('name', 'like', "%{$search}%")
            );

            $q->orWhereHas('hodUser', fn ($u) => $u->where('name', 'like', "%{$search}%")
            );

            $q->orWhereHas('purchaserUser', fn ($u) => $u->where('name', 'like', "%{$search}%")
            );

            /** --------------------
             *  JSON / array columns
             * -------------------- */
            $jsonColumns = [
                'quantity',
                'unit',
                'item_description',
                'tag',
                'item_status',
                'remarks',
            ];

            foreach ($jsonColumns as $column) {
                $q->orWhereRaw(
                    "JSON_SEARCH($column, 'all', ?) IS NOT NULL",
                    ["%{$search}%"]
                );
            }
        });
    }

    protected array $filterMap = [
        // normal columns
        'series_no' => 'like',
        'po_no' => 'like',
        'sap_id' => 'like',
        'department' => 'like',
        'po_status' => 'in',
        'pr_status' => 'in',
        'delivery_status' => 'equals',

        // date columns
        'created_at' => 'date',
        'date_submitted' => 'date',
        'date_needed' => 'date',
        'po_created_date' => 'date',
        'po_approved_date' => 'date',

        // JSON columns
        'item_status' => 'json_in',
        'tag' => 'json_like',
        'remarks' => 'json_like',
    ];

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

    /** -----------------------------
     *  For Item Row Processing
     * ---------------------------- */
    public function approveEdit(int $prId, int $index, array $itemData): PurchaseReport
    {
        $report = PurchaseReport::findOrFail($prId);

        // Validate index exists in all arrays
        $statuses = $report->item_status ?? [];
        if (! array_key_exists($index, $statuses)) {
            throw new \Exception('Invalid item index');
        }

        // ✅ Update all fields at the specified index
        $quantities = $report->quantity ?? [];
        $units = $report->unit ?? [];
        $descriptions = $report->item_description ?? [];
        $tags = $report->tag ?? [];
        $remarks = $report->remarks ?? [];

        // Update the specific index with new data
        $quantities[$index] = $itemData['quantity'] ?? $quantities[$index];
        $units[$index] = $itemData['unit'] ?? $units[$index];
        $descriptions[$index] = $itemData['item_description'] ?? $descriptions[$index];
        $tags[$index] = $itemData['tag'] ?? $tags[$index];
        $remarks[$index] = $itemData['remarks'] ?? $remarks[$index];
        $statuses[$index] = 'pending'; // Change status to pending

        // Save all updates
        $report->quantity = $quantities;
        $report->unit = $units;
        $report->item_description = $descriptions;
        $report->tag = $tags;
        $report->remarks = $remarks;
        $report->item_status = $statuses;

        // ✅ Only set pr_status to on_hold if there are NO other 'return' or 'returned' items
        $hasReturnItems = collect($statuses)->contains(function ($status) {
            return in_array($status, ['return', 'returned']);
        });

        if (! $hasReturnItems) {
            $report->pr_status = 'on_hold';
        }

        $report->save();

        return $report->fresh();
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

    public function addItem(int $id): PurchaseReport
    {
        $report = PurchaseReport::findOrFail($id);

        // ✅ Use proper structure for tag (array with id and description)
        $newRow = [
            'quantity' => '1',
            'unit' => '',
            'item_description' => 'none',
            'tag' => ['id' => null, 'description' => ''], // ✅ Proper structure instead of empty string
            'remarks' => 'none',
            'item_status' => 'return',
        ];

        $appendToArray = function (?array $array, $value) {
            $array = $array ?? [];
            $array[] = $value;

            return $array;
        };
        $report->pr_status = 'on_hold_return';
        $report->quantity = $appendToArray($report->quantity, $newRow['quantity']);
        $report->unit = $appendToArray($report->unit, $newRow['unit']);
        $report->item_description = $appendToArray($report->item_description, $newRow['item_description']);
        $report->tag = $appendToArray($report->tag, $newRow['tag']); // ✅ Now adds proper structure
        $report->remarks = $appendToArray($report->remarks, $newRow['remarks']);
        $report->item_status = $appendToArray($report->item_status, $newRow['item_status']);

        $report->save();

        // 🔍 Debug: Check value BEFORE save
        \Log::info('Before save:', ['item_status' => $report->item_status]);

        $report->save();

        // 🔍 Debug: Check value AFTER save
        \Log::info('After save:', ['item_status' => $report->item_status]);

        $fresh = $report->fresh();

        // 🔍 Debug: Check value AFTER fresh
        \Log::info('After fresh:', ['item_status' => $fresh->item_status]);

        return $fresh;
    }

    public function removeItemRows(int $id, array $rowIndices): PurchaseReport
    {
        $report = PurchaseReport::findOrFail($id);

        $removeIndices = function (?array $array) use ($rowIndices) {
            if (! is_array($array)) {
                return $array;
            }

            return array_values(array_filter($array, fn ($v, $k) => ! in_array($k, $rowIndices), ARRAY_FILTER_USE_BOTH));
        };

        $report->quantity = $removeIndices($report->quantity);
        $report->unit = $removeIndices($report->unit);
        $report->item_description = $removeIndices($report->item_description);
        $report->tag = $removeIndices($report->tag);
        $report->remarks = $removeIndices($report->remarks);
        $report->item_status = $removeIndices($report->item_status);

        // Recalculate PR status
        $statuses = $report->item_status ?? [];
        $totalItems = count($statuses);

        if ($totalItems === 0) {
            $report->pr_status = 'drafted';
        } elseif (count(array_filter($statuses, fn ($s) => $s === 'rejected')) === $totalItems) {
            $report->pr_status = 'rejected';
        } elseif (in_array('pending_tr', $statuses, true)) {
            $report->pr_status = 'on_hold_tr';
        } elseif (in_array('pending', $statuses, true)) {
            $report->pr_status = 'on_hold';
        } else {
            $report->pr_status = 'for_approval';
        }

        $report->save();

        return $report->fresh();
    }

    public function approveEdits(int $prId, array $itemsData): PurchaseReport
    {
        $report = PurchaseReport::findOrFail($prId);

        $quantities = $report->quantity ?? [];
        $units = $report->unit ?? [];
        $descriptions = $report->item_description ?? [];
        $tags = $report->tag ?? [];
        $remarks = $report->remarks ?? [];
        $statuses = $report->item_status ?? [];

        foreach ($itemsData as $index => $itemData) {
            if (! array_key_exists($index, $statuses)) {
                throw new \Exception("Invalid item index: $index");
            }
            $quantities[$index] = $itemData['quantity'] ?? $quantities[$index];
            $units[$index] = $itemData['unit'] ?? $units[$index];
            $descriptions[$index] = $itemData['item_description'] ?? $descriptions[$index];
            $tags[$index] = $itemData['tag'] ?? $tags[$index];
            $remarks[$index] = $itemData['remarks'] ?? $remarks[$index];
            $statuses[$index] = 'pending';
        }

        $report->quantity = $quantities;
        $report->unit = $units;
        $report->item_description = $descriptions;
        $report->tag = $tags;
        $report->remarks = $remarks;
        $report->item_status = $statuses;

        $hasReturnItems = collect($statuses)->contains(fn ($status) => in_array($status, ['return', 'returned']));
        if (! $hasReturnItems) {
            $report->pr_status = 'on_hold';
        }

        $report->save();

        return $report->fresh();
    }
}
