<?php

namespace App\Http\Controllers;


use App\Helpers\MapPurchaseReport;
use App\Http\Requests\PurchaseReport\StorePurchaseReportRequest;
use App\Http\Requests\PurchaseReport\UpdatePurchaseReportRequest;
use App\Events\PurchaseReportEvents\PurchaseReportCreated;
use App\Service\Paginator\PaginatorService;
use App\Service\PurchaseReport\PurchaseReportService;
use App\Service\PurchaseReport\ApprovalPrService;
use App\Models\PurchaseReport;
use Illuminate\Http\Request;

class PurchaseReportController extends Controller
{
    protected $purchaseReportService;
    protected $approvalPrService;

    public function __construct(
        PurchaseReportService $purchaseReportService,
        ApprovalPrService $approvalPrService
    ) {
        $this->purchaseReportService = $purchaseReportService;
        $this->approvalPrService = $approvalPrService;
    }
    /**
     * Display a listing of purchase reports.
     */

    public function index(
        Request $request,
        PaginatorService $paginator,
        PurchaseReportService $purchaseReportService
    ) {
        // Build query via service
        $query = $purchaseReportService->getQuery([
            'searchTerm' => $request->input('searchTerm'),
            'fromDate' => $request->input('fromDate'),
            'toDate' => $request->input('toDate'),
            'sortBy' => $request->input('sortBy', 'id'),
            'sortOrder' => $request->input('sortOrder', 'asc'),
        ]);

        // Paginate results
        $result = $paginator->paginate(
            $query,
            $request->input('pageNumber', 1),
            $request->input('pageSize', 10)
        );

        // Transform each item using the helper
        $result['items'] = collect($result['items'])
            ->map(fn($report) => MapPurchaseReport::map($report))
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
        $user = $request->user(); // Authenticated user

        $query = $purchaseReportService->getQuery([
            'searchTerm' => $request->input('searchTerm'),
            'fromDate' => $request->input('fromDate'),
            'toDate' => $request->input('toDate'),
            'sortBy' => $request->input('sortBy', 'id'),
            'sortOrder' => $request->input('sortOrder', 'asc'),
        ]);

        $departments = $user->department ?? [];
        $roles = $user->role ?? [];

        // If user is purchasing → see ALL departments but only for_approval
        // If user is purchasing → see ALL departments but only for_approval OR closed
        if (in_array('purchasing', $roles)) {
            $query->whereIn('pr_status', ['for_approval', 'closed']);
        } else {
            // Default: filter by user department
            $query->whereIn('department', $departments);
        }


        // If user is a technical reviewer
        if (in_array('technical_reviewer', $roles)) {
            $query->where(function ($q) {
                $q->where(function ($sub) {
                    // pr_status = for_approval AND tag ends with "_tr"
                    $sub->where('pr_status', 'for_approval')
                        ->where(function ($tagSub) {
                            // if tags are stored as JSON array
                            $tagSub->whereRaw("JSON_SEARCH(JSON_EXTRACT(tag, '$'), 'one', '%_tr') IS NOT NULL");
                        });
                })
                    ->orWhere('pr_status', 'on_hold_tr');
            });
        }

        $result = $paginator->paginate(
            $query,
            $request->input('pageNumber', 1),
            $request->input('pageSize', 10)
        );

        $result['items'] = collect($result['items'])
            ->map(fn($report) => MapPurchaseReport::mapTable($report))
            ->toArray();

        return response()->json($result);
    }


    /**
     * Store a newly created purchase report.
     */
    public function store(StorePurchaseReportRequest $request)
    {
        $report = $this->purchaseReportService->store($request->validated());

        // Broadcast event
        event(new PurchaseReportCreated($report));

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
            'status' => 'required|string|in:approved,rejected',
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
        $report->save();

        return response()->json($report);
    }

    public function updatePoNo(Request $request, $id)
    {
        $validated = $request->validate([
            'po_no' => 'required|integer',
            
        ]);

        $report = $this->purchaseReportService->updatePoNo($id, $validated['po_no']);

        return response()->json([
            'message' => 'PO number updated and status set to Closed',
            'report' => $report,
        ], 200);
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
            $counts['completed_hod_review'] = PurchaseReport::whereNotNull('hod_user_id')->count();
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
        }

        // 🔹 Normal USER role
        if (in_array('user', $roles)) {
            $counts['own_created'] = PurchaseReport::where('user_id', $user->id)->count();
            $counts['department_total'] = PurchaseReport::where('department', $department)->count();
        }

        // 🔹 TR role
        if (in_array('technical_reviewer', $roles)) {
            $counts['on_hold_tr'] = PurchaseReport::where('department', $department)
                ->where('pr_status', 'on_hold_tr')
                ->count();

            $counts['completed_tr'] = PurchaseReport::where('department', $department)
                ->whereNotNull('tr_user_id')
                ->count();
        }

        return response()->json($counts);
    }


}