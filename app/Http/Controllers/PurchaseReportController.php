<?php

namespace App\Http\Controllers;

use App\Helpers\MapPurchaseReport;
use App\Helpers\PrRoleFilters;
use App\Helpers\QueryHelper;
use App\Http\Requests\PurchaseReport\StorePurchaseReportRequest;
use App\Models\PurchaseReport;
use App\Models\User;
use App\Notifications\NewMessageNotification;
use App\Service\Paginator\PaginatorService;
use App\Service\PurchaseReport\ApprovalPrService;
use App\Service\PurchaseReport\PurchaseReportNotificationService;
use App\Service\PurchaseReport\PurchaseReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

class PurchaseReportController extends Controller
{
    protected $purchaseReportService;

    protected $approvalPrService;

    protected $notificationService;

    public function __construct(
        PurchaseReportService $purchaseReportService,
        ApprovalPrService $approvalPrService,
        PurchaseReportNotificationService $notificationService
    ) {
        $this->purchaseReportService = $purchaseReportService;
        $this->approvalPrService = $approvalPrService;
        $this->notificationService = $notificationService;
    }

    /**
     * Display a listing of purchase reports.
     */
    public function index(Request $request, PaginatorService $paginator, PurchaseReportService $purchaseReportService)
    {
        $result = QueryHelper::buildAndPaginate($request, $purchaseReportService, $paginator);

        $result['items'] = collect($result['items'])
            ->map(fn ($report) => MapPurchaseReport::map($report))
            ->toArray();

        return response()->json($result);
    }

    /**
     * Display a listing of purchase reports table.
     */
    public function table(Request $request, PaginatorService $paginator, PurchaseReportService $purchaseReportService)
    {
        $user = $request->user();

        $result = QueryHelper::buildAndPaginate($request, $purchaseReportService, $paginator);

        $queryParams = array_merge(
            $result['query_params'] ?? [],
            $request->only([
                'searchTerm',
                'statusTerm',
                'prStatusTerm',
                'submittedFrom',
                'submittedTo',
                'neededFrom',
                'neededTo',
                'fromDate',
                'toDate',
                'sortBy',
                'sortOrder',
                'completedTr',
                'ownDepartment',
                'tagDescription',
                'purchaserName',
            ])
        );

        if (empty($queryParams['sortBy'])) {
            $queryParams['sortBy'] = 'id';
            $queryParams['sortOrder'] = 'desc';
        }

        $filtered = PrRoleFilters::applyRoleFilters(
            $purchaseReportService->getQuery($queryParams),
            $user
        );

        // Date filters
        if (! empty($queryParams['fromDate'])) {
            $filtered->whereDate('created_at', '>=', $queryParams['fromDate']);
        }

        if (! empty($queryParams['toDate'])) {
            $filtered->whereDate('created_at', '<=', $queryParams['toDate']);
        }

        // ✅ TAG FILTER
        if (! empty($queryParams['tagDescription'])) {
            $filtered->whereRaw(
                "JSON_SEARCH(tag, 'one', ?) IS NOT NULL",
                [$queryParams['tagDescription']]
            );
        }

        // ✅ PURCHASER NAME FILTER (MariaDB compatible)
        if (! empty($queryParams['purchaserName'])) {
            $name = $queryParams['purchaserName'];
            $searchTerm = '%'.strtolower($name).'%';

            $filtered->where(function ($q) use ($searchTerm) {
                // Search direct purchaser_id
                $q->whereHas('purchaserUser', function ($subQ) use ($searchTerm) {
                    $subQ->whereRaw('LOWER(name) LIKE ?', [$searchTerm]);
                })
                // OR search via tag-based UserPrivileges resolution (MariaDB compatible)
                    ->orWhereExists(function ($subQ) use ($searchTerm) {
                        $subQ->select(\DB::raw(1))
                            ->from('user_privileges')
                            ->join('users', 'users.id', '=', 'user_privileges.user_id')
                            ->whereRaw('LOWER(users.name) LIKE ?', [$searchTerm])
                            ->whereJsonLength('users.role', 1)
                            ->whereJsonContains('users.role', 'purchasing')
                            ->whereRaw("
                        EXISTS (
                            SELECT 1
                            FROM (
                                SELECT 0 as idx UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL 
                                SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL 
                                SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL 
                                SELECT 9 UNION ALL SELECT 10 UNION ALL SELECT 11 UNION ALL 
                                SELECT 12 UNION ALL SELECT 13 UNION ALL SELECT 14 UNION ALL SELECT 15
                            ) indices
                            WHERE JSON_EXTRACT(purchase_reports.tag, CONCAT('$[', indices.idx, '].id')) IS NOT NULL
                            AND JSON_CONTAINS(
                                user_privileges.tag_ids,
                                JSON_EXTRACT(purchase_reports.tag, CONCAT('$[', indices.idx, '].id'))
                            )
                        )
                    ");
                    });
            });
        }

        // ✅ SEARCH TERM FILTER (MariaDB compatible)
        if (! empty($queryParams['searchTerm'])) {
            $term = '%'.strtolower($queryParams['searchTerm']).'%';

            $filtered->where(function ($q) use ($term) {
                $q->whereRaw('LOWER(series_no) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(pr_purpose) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(department) LIKE ?', [$term])
                    // Search direct purchaser
                    ->orWhereHas('purchaserUser', function ($subQ) use ($term) {
                        $subQ->whereRaw('LOWER(name) LIKE ?', [$term]);
                    })
                    // Search resolved purchaser via tags (MariaDB compatible)
                    ->orWhereExists(function ($subQ) use ($term) {
                        $subQ->select(\DB::raw(1))
                            ->from('user_privileges')
                            ->join('users', 'users.id', '=', 'user_privileges.user_id')
                            ->whereRaw('LOWER(users.name) LIKE ?', [$term])
                            ->whereJsonLength('users.role', 1)
                            ->whereJsonContains('users.role', 'purchasing')
                            ->whereRaw("
                            EXISTS (
                                SELECT 1
                                FROM (
                                    SELECT 0 as idx UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL 
                                    SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL 
                                    SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL 
                                    SELECT 9 UNION ALL SELECT 10 UNION ALL SELECT 11 UNION ALL 
                                    SELECT 12 UNION ALL SELECT 13 UNION ALL SELECT 14 UNION ALL SELECT 15
                                ) indices
                                WHERE JSON_EXTRACT(purchase_reports.tag, CONCAT('$[', indices.idx, '].id')) IS NOT NULL
                                AND JSON_CONTAINS(
                                    user_privileges.tag_ids,
                                    JSON_EXTRACT(purchase_reports.tag, CONCAT('$[', indices.idx, '].id'))
                                )
                            )
                        ");
                    });
            });
        }

        // Sorting
        $filtered->reorder('id', 'desc');

        // Debug
        \Log::info($filtered->toSql());
        \Log::info($filtered->getBindings());

        // Pagination
        $result = $paginator->paginate(
            $filtered,
            $request->input('pageNumber', 1),
            $request->input('pageSize', 10)
        );

        // Mapping
        $result['items'] = collect($result['items'])
            ->map(fn ($r) => MapPurchaseReport::mapTable($r))
            ->toArray();

        return response()->json($result);
    }

    public function tableReports(Request $request, PaginatorService $paginator, PurchaseReportService $purchaseReportService)
    {
        $result = QueryHelper::buildAndPaginate($request, $purchaseReportService, $paginator);

        $queryParams = array_merge(
            $result['query_params'] ?? [],
            $request->only([
                'searchTerm',
                'statusTerm',
                'prStatusTerm',
                'po_no',
                'submittedFrom',
                'submittedTo',
                'neededFrom',
                'neededTo',
                'fromDate',
                'toDate',
                'sortBy',
                'sortOrder',
                'completedTr',
                'ownDepartment',
            ])
        );

        if (empty($queryParams['sortBy'])) {
            $queryParams['sortBy'] = 'id';
            $queryParams['sortOrder'] = 'desc';
        }

        // 🚫 No role filters applied
        $filtered = $purchaseReportService->getQuery($queryParams);

        // ✅ Simple date filtering
        if (! empty($queryParams['fromDate'])) {
            $filtered->whereRaw('DATE(created_at) >= ?', [$queryParams['fromDate']]);
        }

        if (! empty($queryParams['toDate'])) {
            $filtered->whereRaw('DATE(created_at) <= ?', [$queryParams['toDate']]);
        }

        $filtered = $filtered->reorder('id', 'desc');

        $result = $paginator->paginate(
            $filtered,
            $request->input('pageNumber', 1),
            $request->input('pageSize', 10)
        );

        $result['items'] = collect($result['items'])
            ->map(fn ($r) => MapPurchaseReport::mapTable($r))
            ->toArray();

        return response()->json($result);
    }

    /**
     * Store a newly created purchase report.
     */
    public function store(StorePurchaseReportRequest $request)
    {
        $report = $this->purchaseReportService->storeOrUpdate($request->validated());

        // ✅ Send all creation-related notifications
        $this->notificationService->notifyOnCreated($report);

        return response()->json($report, 201);
    }

    /**
     * Get Recent Series No..
     */
    public function getNextSeriesNo()
    {
        $startingSeries = 12000;

        // Get all existing series numbers
        $existingSeries = PurchaseReport::pluck('series_no')->toArray();

        // If no records exist, start from the beginning
        if (empty($existingSeries)) {
            return response()->json([
                'next_series_no' => $startingSeries,
            ]);
        }

        // Get the maximum series number
        $maxSeries = max($existingSeries);

        // Check for gaps in the series starting from the base number
        for ($i = $startingSeries; $i <= $maxSeries; $i++) {
            if (! in_array($i, $existingSeries)) {
                // Found a gap - reuse this number
                return response()->json([
                    'next_series_no' => $i,
                ]);
            }
        }

        // No gaps found, increment from the maximum
        return response()->json([
            'next_series_no' => $maxSeries + 1,
        ]);
    }

    /**
     * Display a single purchase report.
     */
    public function show($id)
    {
        $report = $this->purchaseReportService->show((int) $id);

        $mappedReport = MapPurchaseReport::map($report);

        return response()->json($mappedReport);
    }

    /**
     * Update a purchase report.
     */
    public function update(Request $request, int $id)
    {
        \Log::info('🔍 [CONTROLLER] Raw request data', [
            'all' => $request->all(),
            'has_item_status' => $request->has('item_status'),
            'item_status' => $request->input('item_status'),
        ]);

        $data = $request->all(); // ✅ Changed from validated() to all()

        \Log::info('🔍 [CONTROLLER] Passing to service', [
            'data' => $data,
            'has_item_status' => isset($data['item_status']),
        ]);

        return $this->purchaseReportService->update($id, $data);
    }

    /**
     * Delete a purchase report.
     */
    public function destroy($id)
    {
        $this->purchaseReportService->delete($id);

        return response()->json(['message' => 'Deleted successfully']);
    }

    /**
     * Approve or reject a specific item inside purchase report (via ApprovalPrService).
     */
    public function approveItem(Request $request, $id)
    {
        $validated = $request->validate([
            'index' => 'required|integer|min:0',
            'status' => 'required|string|in:approved,rejected,pending_tr,return',
            'remark' => 'nullable|string',
            'as_role' => 'nullable|string|in:technical_reviewer,hod,both',
            'logged_user_id' => 'required|integer|exists:users,id', // ✅ add this
        ]);

        $report = $this->approvalPrService->updateItemStatus(
            $id,
            $validated['index'],
            $validated['status'],
            $validated['remark'] ?? null,
            $validated['as_role'] ?? null,
            $validated['logged_user_id'] ?? null, // ✅ pass it along
        );

        return response()->json($report);
    }

    public function updateItemStatusOnly(Request $request, $id)
    {
        $validated = $request->validate([
            'index' => 'required|integer|min:0',
            'status' => 'required|string',
        ]);

        $report = PurchaseReport::findOrFail($id);
        $itemStatus = $report->item_status;

        $itemStatus[$validated['index']] = $validated['status'];
        $report->item_status = $itemStatus;

        // Determine if all TR tags are pending
        $tags = $report->tag;
        $allTrOnHold = true;

        foreach ($tags as $i => $tag) {
            if (str_ends_with($tag, '_tr')) {
                if (($itemStatus[$i] ?? null) !== 'pending_tr') {
                    $allTrOnHold = false;
                    break;
                }
            }
        }

        if ($allTrOnHold) {
            $report->pr_status = 'on_hold_tr';
        }

        $report->save();
        $report->refresh();

        // ✅ Trigger notification if needed
        if ($report->pr_status === 'on_hold_tr') {
            $this->notificationService->notifyTechnicalOnHold($report);
        }

        return response()->json($report);
    }

    public function summaryCounts(Request $request)
    {
        $user = $request->user();
        $roles = $user->role ?? [];
        $departments = $user->department ?? [];

        $cacheKey = "summary_counts_{$user->id}_".md5(json_encode($roles).json_encode($departments));

        return Cache::remember($cacheKey, 60, function () use ($user, $roles) {

            // Use fast counting method for non-admin users
            if (in_array('admin', $roles) || in_array('purchasing', $roles)) {
                $query = PurchaseReport::query(); // No filters for admin
            } else {
                $query = PrRoleFilters::applyRoleFilters(PurchaseReport::query(), $user);
            }

            // Get main counts
            $result = $query->selectRaw("
            COUNT(*) as total,
            COUNT(CASE WHEN pr_status = 'on_hold' THEN 1 END) as on_hold,
            COUNT(CASE WHEN pr_status = 'for_approval' THEN 1 END) as for_approval,
            COUNT(CASE WHEN pr_status = 'on_hold_tr' THEN 1 END) as on_hold_tr,
            COUNT(CASE WHEN pr_status = 'closed' THEN 1 END) as closed_pr,
            COUNT(CASE WHEN pr_status = 'returned' THEN 1 END) as returned,
            COUNT(CASE WHEN pr_status = 'rejected' THEN 1 END) as rejected,
            COUNT(CASE WHEN po_status = 'for_approval' THEN 1 END) as for_ceo_approval,
            COUNT(CASE WHEN po_status = 'approved' THEN 1 END) as approved_po,
            COUNT(CASE WHEN hod_user_id IS NOT NULL THEN 1 END) as completed_hod_review,
            COUNT(CASE WHEN tr_user_id IS NOT NULL THEN 1 END) as completed_tr_review,
            COUNT(CASE WHEN user_id = ? THEN 1 END) as own_created
        ", [$user->id])->first();

            $response = [
                'on_hold' => $result->on_hold,
                'for_approval' => $result->for_approval,
                'on_hold_tr' => $result->on_hold_tr,
                'closed_pr' => $result->closed_pr,
                'for_ceo_approval' => $result->for_ceo_approval,
                'approved_po' => $result->approved_po,
                'returned' => $result->returned,
                'rejected' => $result->rejected,
                'completed_hod_review' => $result->completed_hod_review,
                'completed_tr_review' => $result->completed_tr_review,
                'own_created' => $result->own_created,
                'total_prs' => $result->total,
                'completed_tr' => $result->completed_tr_review,
            ];

            // Add department total for ALL users with departments
            if (! empty($user->department)) {
                $deptValues = collect($user->department)->flatMap(function ($dept) {
                    $slug = preg_replace('/[^A-Za-z0-9_.-]/', '_', $dept);

                    return [$dept, $slug];
                })->all();

                // For admins, count all PRs in their departments (column + tags)
                if (in_array('admin', $roles) || in_array('purchasing', $roles)) {
                    $response['department_total'] = PurchaseReport::whereIn('department', $deptValues)->count();
                } else {
                    // For non-admins, use the SAME filter as the main query
                    // This ensures department_total matches the same filtering logic
                    $deptQuery = PrRoleFilters::applyRoleFilters(PurchaseReport::query(), $user);
                    $response['department_total'] = $deptQuery->count();
                }
            }

            return $response;
        });
    }

    public function updatePoNo(Request $request, $id)
    {
        // Validate PO number as a string (keep formatting)
        $validated = $request->validate([
            'po_no' => 'required|string',
        ]);

        $purchaserId = $request->user()->id;

        // Update PO number and status via service
        $report = $this->approvalPrService->updatePoNo(
            $id,
            $validated['po_no'],
            $purchaserId
        );

        // Collect admin, purchasing, hod users
        $recipients = User::query()
            ->whereJsonContains('role', 'admin')
            ->orWhereJsonContains('role', 'purchasing')
            ->orWhereJsonContains('role', 'hod')
            ->get()
            ->unique('id');

        // Send notifications
        Notification::send($recipients, new NewMessageNotification([
            'title' => 'New PO Created',
            'report_id' => $report->id,
            'series_no' => $report->series_no,
            'po_no' => $report->po_no,
            'created_by' => $report->user->name ?? 'Unknown',
            'pr_status' => $report->pr_status,
            'po_status' => $report->po_status,
        ]));

        return response()->json([
            'message' => 'PO number updated and status set to Closed',
            'report' => $report,
        ], 200);
    }

    public function cancelPoNo($id)
    {
        $report = $this->approvalPrService->cancelPoNo($id);

        // Collect admin, purchasing, hod
        $recipients = User::query()
            ->whereJsonContains('role', 'admin')
            ->orWhereJsonContains('role', 'purchasing')
            ->orWhereJsonContains('role', 'hod')
            ->get()
            ->unique('id');

        Notification::send($recipients, new NewMessageNotification([
            'title' => 'PO Cancelled',
            'report_id' => $report->id,
            'series_no' => $report->series_no,
            'po_no' => $report->po_no,
            'created_by' => $report->user->name ?? 'Unknown',
            'pr_status' => $report->pr_status,
            'po_status' => $report->po_status,
        ]));

        return response()->json([
            'message' => 'PO number cancelled successfully',
            'report' => $report,
        ], 200);
    }

    public function returnPoNo(Request $request, $id)
    {
        $report = $this->approvalPrService->returnPoNo($id);

        // Collect admin, purchasing, hod
        $recipients = User::query()
            ->whereJsonContains('role', 'admin')
            ->orWhereJsonContains('role', 'purchasing')
            ->orWhereJsonContains('role', 'hod')
            ->get()
            ->unique('id');

        Notification::send($recipients, new NewMessageNotification([
            'title' => 'PO Returned',
            'report_id' => $report->id,
            'series_no' => $report->series_no, // important
            'po_no' => $report->po_no,
            'created_by' => $report->user->name ?? 'Unknown',
            'pr_status' => $report->pr_status,
            'po_status' => $report->po_status,
        ]));

        return response()->json([
            'message' => 'PO number returned successfully',
            'report' => $report,
        ], 200);
    }

    public function poApproveDate(Request $request, $id)
    {
        $validated = $request->validate([
            'date' => 'required|date',
            'status' => 'required|string|in:approved,rejected,pending_tr,canceled,return',
        ]);

        $report = PurchaseReport::findOrFail($id);

        // Parse both dates and normalize to start of day (00:00:00)
        $poCreatedDate = \Carbon\Carbon::parse($report->po_created_date)->startOfDay();
        $poApprovedDate = \Carbon\Carbon::parse($validated['date'])->startOfDay();

        // ✅ Now compares only dates, not timestamps
        if ($poApprovedDate->lt($poCreatedDate)) {
            return response()->json([
                'error' => 'Invalid date: PO Approved date cannot be earlier than PO Created date.',
            ], 422);
        }

        // Service handles all the business logic
        $report = $this->approvalPrService->poApproveDate(
            $id,
            $validated['status'],
            $poApprovedDate,
            auth()->id()
        );

        return response()->json([
            'message' => 'PO approved successfully',
            'report' => $report,
        ], 200);
    }

    /**
     * Update delivery status of a purchase report.
     */
    public function updateDeliveryStatus(Request $request, $id)
    {
        $validated = $request->validate([
            'delivery_status' => 'required|string|in:pending,delivered,partial',
        ]);

        $report = $this->purchaseReportService->updateDeliveryStatus($id, $validated['delivery_status']);

        return response()->json([
            'message' => 'Delivery status updated successfully.',
            'report' => $report,
        ], 200);
    }

    public function removeRow(Request $request, $id)
    {
        $request->validate([
            'row_index' => 'required|integer|min:0',
        ]);

        $report = $this->purchaseReportService->removeItemRow(
            $id,
            $request->row_index
        );

        return response()->json($report);
    }
}
