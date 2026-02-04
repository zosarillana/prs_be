<?php

namespace App\Http\Controllers;

use App\Models\PurchaseReport;
use App\Models\PurchaseReportItemPo;
use App\Models\User;
use App\Notifications\NewMessageNotification;
use App\Service\PurchaseOrder\PurchaseOrderService;
use App\Service\PurchaseReport\ApprovalPrService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Notification;

class PurchaseOrderController extends Controller
{
    protected PurchaseOrderService $poService;

    protected ApprovalPrService $approvalPrService;

    public function __construct(PurchaseOrderService $poService, ApprovalPrService $approvalPrService)
    {
        $this->poService = $poService;
        $this->approvalPrService = $approvalPrService;
    }

    /**
     * ✅ Create / update PO for a SINGLE ITEM
     */
    public function perItemPoNo(Request $request, $reportId)
    {
        $validated = $request->validate([
            'po_no' => 'required|string',
            'item_index' => 'required|integer',
        ]);

        $report = PurchaseReport::findOrFail($reportId);
        $purchaserId = $request->user()->id;

        // 1️⃣ Update or create the item-level PO
        $itemPo = $this->poService->createOrUpdateItemPo(
            $report,
            $validated['item_index'],
            $validated['po_no'],
            $purchaserId
        );

        // 2️⃣ Count total items and how many have PO numbers
        $itemCount = count($report->item_description ?? []);
        $poCount = PurchaseReportItemPo::where('purchase_report_id', $report->id)
            ->whereNotNull('po_number')
            ->count();

        // 3️⃣ Update document-level statuses based on PO coverage
        if ($poCount === 0) {
            $report->po_status = 'for_approval';
            $report->pr_status = 'for_approval';
        } elseif ($poCount < $itemCount) {
            $report->po_status = 'partial_po';
            $report->pr_status = 'for_approval';
        } else {
            $report->po_status = 'partial_po';
            $report->pr_status = 'closed';
        }

        // 4️⃣ Set PO number and purchaser for the document
        $report->po_no = $report->po_no ?? $validated['po_no'];

        // ✅ Set po_created_date to match po_approved_date
        $latestApprovedDate = PurchaseReportItemPo::where('purchase_report_id', $report->id)
            ->whereNotNull('po_approved_at')
            ->latest('po_approved_at')
            ->value('po_approved_at');

        $report->po_created_date = $latestApprovedDate ? Carbon::parse($latestApprovedDate) : now();
        $report->purchaser_id = $purchaserId;
        $report->save();

        // 5️⃣ Notify relevant users
        $this->notifyPoCreated($report, $itemPo->po_number);

        // 6️⃣ Refresh the report to get all relationships
        $report->load(['itemPos', 'user', 'trUser', 'hodUser', 'purchaser']);

        // Return the FULL updated report
        return response()->json($report, 200);
    }

    /**
     * ✅ Create / update PO for ALL ITEMS (same PO number)
     */
    public function updateDocumentPoNo(Request $request, $reportId)
    {
        // 1️⃣ Validate input
        $validated = $request->validate([
            'po_no' => 'required|string',
        ]);

        // 2️⃣ Fetch the PurchaseReport
        $report = PurchaseReport::findOrFail($reportId);

        $purchaserId = $request->user()->id;

        // 3️⃣ Update all item-level POs via the service
        $this->poService->createOrUpdateDocumentPo(
            $report,
            $validated['po_no'],
            $purchaserId
        );

        // 4️⃣ Update document-level fields (po_no, po_status, po_created_date, purchaser_id)
        //    You can reuse your approvalPrService here
        $this->approvalPrService->updatePoNo(
            $report->id,
            $validated['po_no'],
            $purchaserId
        );

        // 5️⃣ Send notifications
        $this->notifyPoCreated($report, $validated['po_no']);

        return response()->json([
            'message' => 'PO number applied to all items and document updated',
        ], 200);
    }

    /**
     * 🔔 Shared notification logic
     */
    protected function notifyPoCreated(PurchaseReport $report, string $poNo): void
    {
        $recipients = User::query()
            ->whereJsonContains('role', 'admin')
            ->orWhereJsonContains('role', 'purchasing')
            ->orWhereJsonContains('role', 'hod')
            ->get()
            ->unique('id');

        Notification::send($recipients, new NewMessageNotification([
            'title' => 'New PO Created',
            'report_id' => $report->id,
            'series_no' => $report->series_no,
            'po_no' => $poNo,
            'created_by' => $report->user->name ?? 'Unknown',
            'pr_status' => $report->pr_status,
            'po_status' => $report->po_status,
        ]));
    }

    public function createOrUpdateDocumentPo(
        PurchaseReport $report,
        string $poNumber,
        int $purchaserId
    ): void {
        $itemCount = count($report->item_description ?? []);

        for ($i = 0; $i < $itemCount; $i++) {
            PurchaseReportItemPo::updateOrCreate(
                [
                    'purchase_report_id' => $report->id,
                    'item_index' => $i,
                ],
                [
                    'po_number' => $poNumber,
                    'purchaser_id' => $purchaserId,
                    'status' => 'created',
                    'po_created_at' => now(),
                ]
            );
        }

        // ✅ Use your existing service to update document-level PO
        $this->approvalPrService->updatePoNo($report->id, $poNumber, $purchaserId);
    }

    public function cancelPoNo($id)
    {
        // Cancel the PO number via the service
        $report = $this->approvalPrService->cancelPoNo($id);

        // ✅ Update all itemPos to cancelled if they exist
        if ($report->itemPos && $report->itemPos->isNotEmpty()) {
            foreach ($report->itemPos as $itemPo) {
                $itemPo->update([
                    'status' => 'cancelled',
                    'po_approved_at' => null, // clear approval date if any
                ]);
            }
        }

        // ✅ Update document-level PO status
        $report->po_status = 'Cancelled';
        $report->save();

        // Collect admin, purchasing, hod users for notification
        $recipients = User::query()
            ->whereJsonContains('role', 'admin')
            ->orWhereJsonContains('role', 'purchasing')
            ->orWhereJsonContains('role', 'hod')
            ->get()
            ->unique('id');

        // Send notification
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

    // public function poApproveDate(Request $request, $id)
    // {
    //     $validated = $request->validate([
    //         'date' => 'required|date',
    //         'status' => 'required|string|in:approved,rejected,pending_tr,canceled,return',
    //     ]);

    //     $report = PurchaseReport::findOrFail($id);

    //     // Parse both dates and normalize to start of day (00:00:00)
    //     $poCreatedDate = \Carbon\Carbon::parse($report->po_created_date)->startOfDay();
    //     $poApprovedDate = \Carbon\Carbon::parse($validated['date'])->startOfDay();

    //     // ✅ Now compares only dates, not timestamps
    //     if ($poApprovedDate->lt($poCreatedDate)) {
    //         return response()->json([
    //             'error' => 'Invalid date: PO Approved date cannot be earlier than PO Created date.',
    //         ], 422);
    //     }

    //     // Service handles all the business logic
    //     $report = $this->approvalPrService->poApproveDate(
    //         $id,
    //         $validated['status'],
    //         $poApprovedDate,
    //         auth()->id()
    //     );

    //     return response()->json([
    //         'message' => 'PO approved successfully',
    //         'report' => $report,
    //     ], 200);
    // }

    // public function perItemPoApproveDate(Request $request, $reportId)
    // {
    //     $validated = $request->validate([
    //         'item_index' => 'required|integer',
    //         'date' => 'required|date',
    //         'status' => 'required|string|in:approved,cancelled',
    //     ]);

    //     $report = PurchaseReport::findOrFail($reportId);

    //     $approvedDate = Carbon::parse($validated['date'])->startOfDay();

    //     // Guard vs PO created date
    //     if ($report->po_created_date) {
    //         $created = Carbon::parse($report->po_created_date)->startOfDay();

    //         if ($approvedDate->lt($created)) {
    //             return response()->json([
    //                 'error' => 'Approved date cannot be earlier than PO created date',
    //             ], 422);
    //         }
    //     }

    //     // Fetch the item PO
    //     $itemPo = PurchaseReportItemPo::where('purchase_report_id', $reportId)
    //         ->where('item_index', $validated['item_index'])
    //         ->first();

    //     if (! $itemPo) {
    //         return response()->json([
    //             'error' => 'Item PO not found for this index',
    //         ], 404);
    //     }

    //     // Update the existing item PO
    //     $itemPo->update([
    //         'po_approved_at' => $approvedDate,
    //         'status' => $validated['status'],
    //     ]);

    //     // Reload all item POs
    //     $report->load('itemPos');

    //     // Check if all item POs are approved
    //     $allApproved = $report->itemPos->every(fn ($po) => $po->status === 'approved');

    //     if ($allApproved) {
    //         $report->po_status = 'approved';
    //         $report->save();
    //     }

    //     return response()->json([
    //         'message' => 'Item PO updated',
    //         'item_po' => $itemPo,
    //         'report_po_status' => $report->po_status,
    //     ], 200);
    // }

    public function perItemPoApproveDate(Request $request, $reportId)
    {
        $validated = $request->validate([
            'item_index' => 'required|integer',
            'date' => 'required|date',
            'status' => 'required|string|in:approved,cancelled',
        ]);

        $report = PurchaseReport::findOrFail($reportId);

        $approvedDate = Carbon::parse($validated['date'])->startOfDay();

        /*
        |----------------------------------------------------------------------
        | Guard — approved date cannot be before PO created date
        |----------------------------------------------------------------------
        */
        if ($report->po_created_date) {
            $created = Carbon::parse($report->po_created_date)->startOfDay();

            if ($approvedDate->lt($created)) {
                return response()->json([
                    'error' => 'Approved date cannot be earlier than PO created date',
                ], 422);
            }
        }

        $itemPo = PurchaseReportItemPo::where('purchase_report_id', $reportId)
            ->where('item_index', $validated['item_index'])
            ->first();

        if (! $itemPo) {
            return response()->json([
                'error' => 'Item PO not found for this index',
            ], 404);
        }

        $itemPo->update([
            'po_approved_at' => $approvedDate,
            'status' => $validated['status'],
        ]);

        // total PR line items (true count of document rows)
        $totalItems = is_array($report->item_description)
            ? count($report->item_description)
            : 0;

        // how many item_po rows exist
        $itemPoCount = PurchaseReportItemPo::where('purchase_report_id', $reportId)
            ->count();

        // how many are approved
        $approvedCount = PurchaseReportItemPo::where('purchase_report_id', $reportId)
            ->where('status', 'approved')
            ->count();

        if ($itemPoCount === $totalItems && $approvedCount === $totalItems && $totalItems > 0) {
            // all items exist + all approved
            $report->po_status = 'approved';
        } elseif ($itemPoCount === $totalItems && $approvedCount > 0) {
            // all rows exist but mixed approved/cancelled
            $report->po_status = 'partial_po';
        } else {
            // missing item_po rows OR none approved
            $report->po_status = 'partial_po';
        }

        /*
        |----------------------------------------------------------------------
        | Update main PR po_approved_date to latest approved item date
        |----------------------------------------------------------------------
        */
        $latestApprovedDate = PurchaseReportItemPo::where('purchase_report_id', $reportId)
            ->whereNotNull('po_approved_at')
            ->latest('po_approved_at')
            ->value('po_approved_at');

        $report->po_approved_date = $latestApprovedDate;

        $report->save();

        /*
        |----------------------------------------------------------------------
        | Response
        |----------------------------------------------------------------------
        */
        return response()->json([
            'message' => 'Item PO updated successfully',
            'item_po' => $itemPo,
            'counts' => [
                'total_pr_items' => $totalItems,
                'item_po_rows' => $itemPoCount,
                'approved_rows' => $approvedCount,
            ],
            'report_po_status' => $report->po_status,
            'report_po_approved_date' => $report->po_approved_date,
        ], 200);
    }

    // public function perItemPoApproveDate(Request $request, $id)
    // {
    //     $request->validate([
    //         'item_index' => 'required|integer',
    //         'date' => 'required|date',
    //         'status' => 'required|string|in:approved,cancelled',
    //     ]);

    //     $itemIndex = $request->input('item_index');

    //     $itemPo = PurchaseReportItemPo::updateOrCreate(
    //         [
    //             'purchase_report_id' => $id,
    //             'item_index' => $itemIndex,
    //         ],
    //         [
    //             'po_approved_at' => $request->date,
    //             'po_status' => $request->status,
    //         ]
    //     );

    //     return response()->json($itemPo);
    // }

    public function documentPoApproveDate(Request $request, $reportId)
    {
        $validated = $request->validate([
            'date' => 'required|date',
            'status' => 'required|string|in:approved,cancelled',
        ]);

        $report = PurchaseReport::findOrFail($reportId);

        $approvedDate = Carbon::parse($validated['date'])->startOfDay();

        // ✅ guard vs created date
        if ($report->po_created_date) {
            $created = Carbon::parse($report->po_created_date)->startOfDay();

            if ($approvedDate->lt($created)) {
                return response()->json([
                    'error' => 'Approved date cannot be earlier than PO created date',
                ], 422);
            }
        }

        $report = $this->poService->approveDocumentPoDate(
            $report,
            $validated['status'],
            $approvedDate,
            auth()->id()
        );

        $report->load(['itemPos', 'user', 'purchaser']);

        return response()->json([
            'message' => 'Document PO updated',
            'report' => $report,
        ], 200);
    }
}
