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
use App\Service\Paginator\PaginatorService;
use App\Service\PurchaseReport\ApprovalPrService;
use App\Service\PurchaseReport\PurchaseReportNotificationService;
use App\Service\PurchaseReport\PurchaseReportService;
use Illuminate\Http\Request;

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
            ->map(fn ($report) => MapPurchaseReport::map($report))
            ->toArray();

        return response()->json($result);
    }

    /**
     * Display a listing of purchase reports table.
     */
    public function table(
        Request $request,
        PaginatorService $paginator,
        PurchaseReportService $purchaseReportService
    ) {
        $user = $request->user();

        // ✅ If you still need QueryHelper for pagination params, keep it,
        // but also merge in all request filters so statusTerm is preserved.
        $result = QueryHelper::buildAndPaginate(
            $request,
            $purchaseReportService,
            $paginator
        );

        // ✅ Merge in the raw request filters to be sure statusTerm passes through
        $queryParams = array_merge(
            $result['query_params'] ?? [],
            $request->only([
                'searchTerm',
                'statusTerm',
                'submittedFrom',
                'submittedTo',
                'neededFrom',
                'neededTo',
                'fromDate',
                'toDate',
                'sortBy',
                'sortOrder',
            ])
        );

        $filtered = PrRoleFilters::applyRoleFilters(
            $purchaseReportService->getQuery($queryParams),
            $user
        );

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
        $department = $user->department;

        $counts = [];

        // 🔹 Admin: show everything
        if (in_array('admin', $roles)) {
            $counts['on_hold'] = PurchaseReport::where('pr_status', 'on_hold')->count();
            $counts['for_approval'] = PurchaseReport::where('pr_status', 'for_approval')->count();
            $counts['on_hold_tr'] = PurchaseReport::where('pr_status', 'on_hold_tr')->count();
            $counts['closed_pr'] = PurchaseReport::where('pr_status', 'closed')->count();
            $counts['for_ceo_approval'] = PurchaseReport::where('po_status', 'for_approval')->count();
            $counts['approved_po'] = PurchaseReport::where('po_status', 'approved')->count();
            $counts['completed_hod_review'] = PurchaseReport::whereNotNull('hod_user_id')->count();
            $counts['completed_tr_review'] = PurchaseReport::whereNotNull('tr_user_id')->count();
            $counts['own_created'] = PurchaseReport::count(); // all created PRs
            $counts['department_total'] = PurchaseReport::count(); // all departments
            $counts['completed_tr'] = PurchaseReport::whereNotNull('tr_user_id')->count();

            return response()->json($counts);
        }

        // 🔹 HOD role
        if (in_array('hod', $roles)) {
            $counts['on_hold'] = PurchaseReport::where('department', $department)
                ->where('pr_status', 'on_hold')
                ->count();

            $counts['for_approval'] = PurchaseReport::where('department', $department)
                ->where('pr_status', 'for_approval')
                ->count();

            $counts['on_hold_tr'] = PurchaseReport::where('department', $department)
                ->where('pr_status', 'on_hold_tr')
                ->count();

            $counts['completed_hod_review'] = PurchaseReport::where('department', $department)
                ->whereNotNull('hod_user_id')
                ->count();

            $counts['department_total'] = PurchaseReport::count(); // all departments
        }

        // 🔹 Normal USER role
        if (in_array('user', $roles)) {
            $counts['own_created'] = PurchaseReport::where('user_id', $user->id)->count();
            $counts['department_total'] = PurchaseReport::where('department', $department)->count();
            $counts['for_approval'] = PurchaseReport::where('pr_status', 'for_approval')->count();
            $counts['on_hold'] = PurchaseReport::where('pr_status', 'on_hold')->count();
        }

        // 🔹 TR role
        if (in_array('technical_reviewer', $roles)) {
            $counts['on_hold_tr'] = PurchaseReport::where('department', $department)
                ->where('pr_status', 'on_hold_tr')
                ->count();

            $counts['completed_tr'] = PurchaseReport::where('department', $department)
                ->whereNotNull('tr_user_id')
                ->count();
            $counts['for_approval'] = PurchaseReport::where('pr_status', 'for_approval')->count();
        }

        // 🔹 Purchasing role
        if (in_array('purchasing', $roles)) {
            $counts['closed_pr'] = PurchaseReport::where('pr_status', 'closed')->count();
            $counts['for_approval'] = PurchaseReport::where('pr_status', 'for_approval')->count();
            $counts['closed'] = PurchaseReport::where('pr_status', 'closed')->count();
            $counts['approved_po'] = PurchaseReport::where('po_status', 'approved')->count();
            $counts['for_ceo_approval'] = PurchaseReport::where('po_status', 'for_approval')->count();
        }

        return response()->json($counts);
    }

    public function updatePoNo(Request $request, $id)
    {
        $validated = $request->validate([
            'po_no' => 'required|integer',
        ]);

        $report = $this->approvalPrService->updatePoNo($id, $validated['po_no']);

        // 🔹 Notify admins + purchasing + HODs (works for JSON or string roles)
        $recipients = User::query()
            ->whereJsonContains('role', 'admin')
            ->orWhereJsonContains('role', 'purchasing')
            ->orWhereJsonContains('role', 'hod')
            ->get();

        foreach ($recipients as $user) {
            $user->notify(new NewMessageNotification([
                'title' => 'New PO Created',
                'report_id' => $report->id,
                'po_no' => $report->po_no,
                'created_by' => $report->user->name ?? 'Unknown',
                'pr_status' => $report->pr_status,
                'po_status' => $report->po_status,
                'user_id' => $user->id,
                'role' => $user->role,
            ]));
        }

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

    // public function poApproveDate($id)
    // {
    //     // ✅ Approve the PO using your service
    //     $report = $this->approvalPrService->poApproveDate($id);

    //     // ✅ Set purchaser_id as the logged-in user's ID
    //     $report->purchaser_id = auth()->id(); // or request()->user()->id
    //     $report->save();

    //     // ✅ Notify Admin + Purchasing + HOD
    //     $this->notificationService->notifyPoApproved($report);

    //     return response()->json([
    //         'message' => 'PO approved successfully',
    //         'report' => $report,
    //     ], 200);
    // }

    public function poApproveDate(Request $request, $id)
    {
        $validated = $request->validate([
            'date' => 'required|date',
            'status' => 'required|string|in:approved,rejected,pending_tr,canceled',
        ]);

        // Approve the PO using your service
        $report = $this->approvalPrService->poApproveDate($id);

        // Set purchaser_id as the logged-in user's ID
        $report->purchaser_id = auth()->id();
        $report->po_approved_date = $validated['date']; // store the date
        $report->po_status = $validated['status'];      // store the status
        $report->save();

        // Notify Admin + Purchasing + HOD
        $this->notificationService->notifyPoApproved($report);

        return response()->json([
            'message' => 'PO approved successfully',
            'report' => $report,
        ], 200);
    }
}
