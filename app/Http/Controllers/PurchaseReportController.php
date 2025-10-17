<?php

namespace App\Http\Controllers;

use App\Helpers\MapPurchaseReport;
use App\Helpers\PrRoleFilters;
use App\Helpers\QueryHelper;
use App\Http\Requests\PurchaseReport\StorePurchaseReportRequest;
use App\Http\Requests\PurchaseReport\UpdatePurchaseReportRequest;
use App\Models\PurchaseReport;
use App\Models\User;
use App\Notifications\NewMessageNotification;
use App\Service\DateFilter\DateFilterService;
use App\Service\Paginator\PaginatorService;
use App\Service\PurchaseReport\ApprovalPrService;
use App\Service\PurchaseReport\PurchaseReportNotificationService;
use App\Service\PurchaseReport\PurchaseReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;

class PurchaseReportController extends Controller
{
    protected $purchaseReportService;

    protected $approvalPrService;

    protected $notificationService; // ✅

    public function __construct(
        PurchaseReportService $purchaseReportService,
        ApprovalPrService $approvalPrService,
        PurchaseReportNotificationService $notificationService // ✅
    ) {
        $this->purchaseReportService = $purchaseReportService;
        $this->approvalPrService = $approvalPrService;
        $this->notificationService = $notificationService; // ✅
    }

    /**
     * Display a listing of purchase reports.
     */
    public function index(Request $request, PaginatorService $paginator, PurchaseReportService $purchaseReportService)
    {
        $result = QueryHelper::buildAndPaginate($request, $purchaseReportService, $paginator);

        $result['items'] = collect($result['items'])
            ->map(fn($report) => MapPurchaseReport::map($report))
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
                'completedTr'
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

        // ✅ Replace DateFilterService with simple whereDate
        if (!empty($queryParams['fromDate'])) {
            $filtered->whereRaw('DATE(created_at) >= ?', [$queryParams['fromDate']]);
        }

        if (!empty($queryParams['toDate'])) {
            $filtered->whereRaw('DATE(created_at) <= ?', [$queryParams['toDate']]);
        }

        $filtered = $filtered->reorder('id', 'desc');

        $result = $paginator->paginate(
            $filtered,
            $request->input('pageNumber', 1),
            $request->input('pageSize', 10)
        );

        $result['items'] = collect($result['items'])
            ->map(fn($r) => MapPurchaseReport::mapTable($r))
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
                'submittedFrom',
                'submittedTo',
                'neededFrom',
                'neededTo',
                'fromDate',
                'toDate',
                'sortBy',
                'sortOrder',
                'completedTr'
            ])
        );

        if (empty($queryParams['sortBy'])) {
            $queryParams['sortBy'] = 'id';
            $queryParams['sortOrder'] = 'desc';
        }

        // 🚫 No role filters applied
        $filtered = $purchaseReportService->getQuery($queryParams);

        // ✅ Simple date filtering
        if (!empty($queryParams['fromDate'])) {
            $filtered->whereRaw('DATE(created_at) >= ?', [$queryParams['fromDate']]);
        }

        if (!empty($queryParams['toDate'])) {
            $filtered->whereRaw('DATE(created_at) <= ?', [$queryParams['toDate']]);
        }

        $filtered = $filtered->reorder('id', 'desc');

        $result = $paginator->paginate(
            $filtered,
            $request->input('pageNumber', 1),
            $request->input('pageSize', 10)
        );

        $result['items'] = collect($result['items'])
            ->map(fn($r) => MapPurchaseReport::mapTable($r))
            ->toArray();

        return response()->json($result);
    }

    /**
     * Store a newly created purchase report.
     */
    public function store(StorePurchaseReportRequest $request)
    {
        $report = $this->purchaseReportService->store($request->validated());

        // ✅ Send all creation-related notifications
        $this->notificationService->notifyOnCreated($report);

        return response()->json($report, 201);
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
    public function update(UpdatePurchaseReportRequest $request, $id)
    {
        $report = $this->purchaseReportService->update($id, $request->validated());

        return response()->json($report);
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
            'status' => 'required|string|in:approved,rejected,pending_tr',
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
        $departments = collect($user->department ?? []);
        $counts = [];

        // 🧩 Admin: show everything
        if (in_array('admin', $roles)) {
            $counts = [
                'on_hold' => PurchaseReport::where('pr_status', 'on_hold')->count(),
                'for_approval' => PurchaseReport::where('pr_status', 'for_approval')->count(),
                'on_hold_tr' => PurchaseReport::where('pr_status', 'on_hold_tr')->count(),
                'closed_pr' => PurchaseReport::where('pr_status', 'closed')->count(),
                'for_ceo_approval' => PurchaseReport::where('po_status', 'for_approval')->count(),
                'approved_po' => PurchaseReport::where('po_status', 'approved')->count(),
                'completed_hod_review' => PurchaseReport::whereNotNull('hod_user_id')->count(),
                'completed_tr_review' => PurchaseReport::whereNotNull('tr_user_id')->count(),
                'own_created' => PurchaseReport::count(),
                'department_total' => PurchaseReport::count(),
                'total_prs' => PurchaseReport::count(),
                'completed_tr' => PurchaseReport::whereNotNull('tr_user_id')->count(),
            ];
            return response()->json($counts);
        }

        // 🧠 NEW CONDITION:
        // If user has both HOD and Purchasing, treat them as Admin (global view)
        $isHodAndPurchasing = in_array('hod', $roles) && in_array('purchasing', $roles);
        if ($isHodAndPurchasing) {
            $counts = [
                'on_hold' => PurchaseReport::where('pr_status', 'on_hold')->count(),
                'for_approval' => PurchaseReport::where('pr_status', 'for_approval')->count(),
                'on_hold_tr' => PurchaseReport::where('pr_status', 'on_hold_tr')->count(),
                'closed_pr' => PurchaseReport::where('pr_status', 'closed')->count(),
                'for_ceo_approval' => PurchaseReport::where('po_status', 'for_approval')->count(),
                'approved_po' => PurchaseReport::where('po_status', 'approved')->count(),
                'completed_hod_review' => PurchaseReport::whereNotNull('hod_user_id')->count(),
                'completed_tr_review' => PurchaseReport::whereNotNull('tr_user_id')->count(),
                'own_created' => PurchaseReport::count(),
                'department_total' => PurchaseReport::count(),
                'total_prs' => PurchaseReport::count(),
                'completed_tr' => PurchaseReport::whereNotNull('tr_user_id')->count(),
            ];
            return response()->json($counts);
        }

        // 🔧 Helper: merge counts (for remaining role types)
        $mergeCounts = function (&$counts, array $newCounts) {
            foreach ($newCounts as $key => $value) {
                $counts[$key] = ($counts[$key] ?? 0) + $value;
            }
        };

        // 🔹 HOD role
        if (in_array('hod', $roles)) {
            foreach ($departments as $dept) {
                $mergeCounts($counts, [
                    'on_hold' => PurchaseReport::where('department', $dept)->where('pr_status', 'on_hold')->count(),
                    'for_approval' => PurchaseReport::where('department', $dept)->where('pr_status', 'for_approval')->count(),
                    'on_hold_tr' => PurchaseReport::where('department', $dept)->where('pr_status', 'on_hold_tr')->count(),
                    'completed_hod_review' => PurchaseReport::where('department', $dept)->whereNotNull('hod_user_id')->count(),
                    'department_total' => PurchaseReport::where('department', $dept)->count(),
                ]);
            }
        }

        // 🔹 USER role
        if (in_array('user', $roles)) {
            foreach ($departments as $dept) {
                $mergeCounts($counts, [
                    'own_created' => PurchaseReport::where('user_id', $user->id)->count(),
                    'department_total' => PurchaseReport::where('department', $dept)->count(),
                    'for_approval' => PurchaseReport::where('department', $dept)->where('pr_status', 'for_approval')->count(),
                    'on_hold' => PurchaseReport::where('department', $dept)->where('pr_status', 'on_hold')->count(),
                    'on_hold_tr' => PurchaseReport::where('department', $dept)->where('pr_status', 'on_hold_tr')->count(),
                ]);
            }
        }

        // 🔹 TR role
        if (in_array('technical_reviewer', $roles)) {
            foreach ($departments as $dept) {
                $mergeCounts($counts, [
                    'on_hold_tr' => PurchaseReport::where('pr_status', 'on_hold_tr')
                        ->whereRaw("JSON_CONTAINS(JSON_EXTRACT(tag, '$[*].department'), JSON_QUOTE(?))", [$dept])
                        ->count(),
                    'completed_tr' => PurchaseReport::whereNotNull('tr_user_id')
                        ->whereRaw("JSON_CONTAINS(JSON_EXTRACT(tag, '$[*].department'), JSON_QUOTE(?))", [$dept])
                        ->count(),
                    'for_approval' => PurchaseReport::where('pr_status', 'for_approval')->count(),
                ]);
            }
        }

        // 🔹 Purchasing role
        if (in_array('purchasing', $roles)) {
            $mergeCounts($counts, [
                'closed_pr' => PurchaseReport::where('pr_status', 'closed')->count(),
                'for_approval' => PurchaseReport::where('pr_status', 'for_approval')->count(),
                'closed' => PurchaseReport::where('pr_status', 'closed')->count(),
                'approved_po' => PurchaseReport::where('po_status', 'approved')->count(),
                'for_ceo_approval' => PurchaseReport::where('po_status', 'for_approval')->count(),
            ]);
        }

        return response()->json($counts);
    }


    public function updatePoNo(Request $request, $id)
    {
        $validated = $request->validate([
            'po_no' => 'required|integer',
        ]);

        // ✅ Pass the logged-in user's ID to the service
        $purchaserId = $request->user()->id;

        $report = $this->approvalPrService->updatePoNo(
            $id,
            $validated['po_no'],
            $purchaserId
        );

        // ✅ Send notifications via queue (async)
        $recipients = User::query()
            ->whereJsonContains('role', 'admin')
            ->orWhereJsonContains('role', 'purchasing')
            ->orWhereJsonContains('role', 'hod')
            ->get();

        Notification::send($recipients, new NewMessageNotification([
            'title' => 'New PO Created',
            'report_id' => $report->id,
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


    public function cancelPoNo(Request $request, $id)
    {
        $report = $this->approvalPrService->cancelPoNo($id);

        $recipients = User::query()
            ->whereJsonContains('role', 'admin')
            ->orWhereJsonContains('role', 'purchasing')
            ->orWhereJsonContains('role', 'hod')
            ->get();

        foreach ($recipients as $user) {
            $user->notify(new NewMessageNotification([
                'title' => 'PO Cancelled',
                'report_id' => $report->id,
                'po_no' => $report->po_no, // will be null
                'created_by' => $report->user->name ?? 'Unknown',
                'pr_status' => $report->pr_status,
                'po_status' => $report->po_status,
                'user_id' => $user->id,
                'role' => $user->role,
            ]));
        }

        return response()->json([
            'message' => 'PO number cancelled successfully',
            'report' => $report,
        ], 200);
    }

    public function poApproveDate(Request $request, $id)
    {
        $validated = $request->validate([
            'date' => 'required|date',
            'status' => 'required|string|in:approved,rejected,pending_tr,canceled',
        ]);

        $report = PurchaseReport::findOrFail($id);

        // Parse both dates and normalize to start of day (00:00:00)
        $poCreatedDate = \Carbon\Carbon::parse($report->po_created_date)->startOfDay();
        $poApprovedDate = \Carbon\Carbon::parse($validated['date'])->startOfDay();

        // ✅ Now compares only dates, not timestamps
        if ($poApprovedDate->lt($poCreatedDate)) {
            return response()->json([
                'error' => 'Invalid date: PO Approved date cannot be earlier than PO Created date.'
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
}
