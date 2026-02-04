<?php

namespace App\Http\Controllers;

use App\Helpers\MapPurchaseReport;
use App\Helpers\MapPurchaseReportClean;
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
use Illuminate\Http\JsonResponse;
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

    public function showClean(int $id)
    {
        $report = PurchaseReport::with([
            'user',
            'purchaserUser',
            'trUser',
            'hodUser',
            'progresses',
        ])->findOrFail($id);

        return response()->json(
            MapPurchaseReportClean::map($report)
        );
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
            $purchaseReportService->getQuery($queryParams)
                ->with(['itemPos.purchaser']), // 👈 ADD THIS
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

    public function tableReports(
        Request $request,
        PaginatorService $paginator,
        PurchaseReportService $purchaseReportService
    ) {
        QueryHelper::buildAndPaginate(
            $request,
            $purchaseReportService,
            $paginator
        );

        // ✅ ONE query, with eager loading
        $query = $purchaseReportService->getQuery($request->all())
            ->with(['itemPos.purchaser']);

        // ✅ Paginate
        $result = $paginator->paginate(
            $query,
            $request->input('pageNumber', 1),
            $request->input('pageSize', 10)
        );

        // ✅ Map results
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
     *Update item level.
     */
    public function approveEdit(Request $request, PurchaseReport $pr, int $index)
    {
        // ✅ Validate the incoming data
        $validated = $request->validate([
            'quantity' => 'nullable',
            'unit' => 'nullable|string',
            'item_description' => 'nullable|string',
            'tag' => 'nullable',
            'remarks' => 'nullable|string',
        ]);

        $report = $this->purchaseReportService->approveEdit(
            $pr->id,
            $index,
            $validated  // ✅ Pass the validated data
        );

        return MapPurchaseReport::map($report);
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
            COUNT(CASE WHEN pr_status = 'drafted' THEN 1 END) as drafted,
            COUNT(CASE WHEN pr_status = 'on_hold' THEN 1 END) as on_hold,
            COUNT(CASE WHEN pr_status = 'on_hold_return' THEN 1 END) as on_hold_return,
            COUNT(CASE WHEN pr_status = 'for_approval' THEN 1 END) as for_approval,
            COUNT(CASE WHEN pr_status = 'on_hold_tr' THEN 1 END) as on_hold_tr,
            COUNT(CASE WHEN pr_status = 'closed' THEN 1 END) as closed_pr,
            COUNT(CASE WHEN pr_status = 'returned' THEN 1 END) as returned,
            COUNT(CASE WHEN pr_status = 'rejected' THEN 1 END) as rejected,
            COUNT(CASE WHEN po_status = 'for_approval' THEN 1 END) as for_ceo_approval,
            COUNT(CASE WHEN po_status = 'partial_po' THEN 1 END) as partial_po,
            COUNT(CASE WHEN po_status = 'approved' THEN 1 END) as approved_po,
            COUNT(CASE WHEN hod_user_id IS NOT NULL THEN 1 END) as completed_hod_review,
            COUNT(CASE WHEN tr_user_id IS NOT NULL THEN 1 END) as completed_tr_review,
            COUNT(CASE WHEN user_id = ? THEN 1 END) as own_created
        ", [$user->id])->first();

            $response = [
                'drafted' => $result->drafted,
                'on_hold' => $result->on_hold,
                'on_hold_return' => $result->on_hold_return,
                'for_approval' => $result->for_approval,
                'partial_po' => $result->partial_po,
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

    public function updateSapId(Request $request, $id)
    {
        // Validate SAP ID as a string
        $validated = $request->validate([
            'sap_id' => 'required|string',
        ]);

        $purchaserId = $request->user()->id;

        // Update SAP ID via service
        $report = $this->approvalPrService->updateSapId(
            $id,
            $validated['sap_id'],
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
            'title' => 'SAP ID Assigned',
            'report_id' => $report->id,
            'series_no' => $report->series_no,
            'sap_id' => $report->sap_id,
            'created_by' => $report->user->name ?? 'Unknown',
            'pr_status' => $report->pr_status,
            'po_status' => $report->po_status,
        ]));

        return response()->json([
            'message' => 'SAP ID updated successfully',
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

    public function addItem($id)
    {
        // Call service to add a new empty row
        $report = $this->purchaseReportService->addItem($id);

        return response()->json($report);
    }

    /** -----------------------------
     *  For Item Row Controllers
     * ---------------------------- */

    /**
     * Bulk remove item rows.
     */
    public function removeRows(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'row_indices' => 'required|array',
            'row_indices.*' => 'integer|min:0',
        ]);

        $report = $this->purchaseReportService->removeItemRows(
            $id,
            $request->row_indices
        );

        return response()->json($report);
    }

    /**
     * Bulk approve/edit items.
     */
    public function approveEdits(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'items_data' => 'required|array',
            'items_data.*.index' => 'required|integer|min:0',
            'items_data.*.quantity' => 'nullable',
            'items_data.*.unit' => 'nullable',
            'items_data.*.item_description' => 'nullable',
            'items_data.*.tag' => 'nullable',
            'items_data.*.remarks' => 'nullable',
        ]);

        $report = $this->purchaseReportService->approveEdits(
            $id,
            $request->items_data
        );

        return response()->json($report);
    }
}
