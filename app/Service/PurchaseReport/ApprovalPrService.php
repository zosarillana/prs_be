<?php

namespace App\Service\PurchaseReport;

use App\Models\PurchaseReport;
use App\Events\Global\GlobalPurchaseReportApprovalUpdated;
use Illuminate\Support\Facades\Cache;

class ApprovalPrService
{
    protected PurchaseReportNotificationService $notify;

    public function __construct(PurchaseReportNotificationService $notify)
    {
        $this->notify = $notify;
    }

    public function updateItemStatus(
        int $id,
        int $index,
        string $status,
        ?string $remark = null,
        ?string $asRole = null,
        ?int $loggedUserId = null
    ): PurchaseReport {
        $report = PurchaseReport::findOrFail($id);
        $oldStatus = $report->pr_status;
        $itemStatus = $report->item_status ?? [];
        $remarks = $report->remarks ?? [];

        if (!is_array($itemStatus)) {
            $itemStatus = [];
        }
        if (!is_array($remarks)) {
            $remarks = [];
        }

        // ✅ Update only the targeted item
        if (array_key_exists($index, $report->item_description)) {
            $itemStatus[$index] = $status;
            $remarks[$index] = $remark;
        }

        /**
         * ✅ Determine PR Status
         * Logic:
         * - If ANY item is rejected → pr_status = 'rejected'
         * - Else if any item pending_tr → 'on_hold_tr'
         * - Else if any item pending → 'on_hold'
         * - Else → 'for_approval'
         */
        if (in_array('rejected', $itemStatus, true)) {
            $prStatus = 'rejected';
            $poStatus = null; // ✅ Also set PO status rejected
        } elseif (in_array('pending_tr', $itemStatus, true)) {
            $prStatus = 'on_hold_tr';
            $poStatus = $report->po_status ?? null;
        } elseif (in_array('pending', $itemStatus, true)) {
            $prStatus = 'on_hold';
            $poStatus = $report->po_status ?? null;
        } else {
            $prStatus = 'for_approval';
            $poStatus = $report->po_status ?? null;
        }

        // ✅ Prepare update data
        $updateData = [
            'item_status' => $itemStatus,
            'remarks' => $remarks,
            'pr_status' => $prStatus,
            'po_status' => $poStatus, // ✅ Added
        ];

        // ✅ Attach approver info
        if ($asRole === 'technical_reviewer' || $asRole === 'both') {
            $updateData['tr_user_id'] = $loggedUserId;
            $updateData['tr_signed_at'] = now();
        }

        if ($asRole === 'hod' || $asRole === 'both') {
            $updateData['hod_user_id'] = $loggedUserId;
            $updateData['hod_signed_at'] = now();
        }

        $report->update($updateData);
        $report->refresh();

        // ✅ Trigger notifications based on PR status
        match ($report->pr_status) {
            'for_approval' => $this->notify->notifyPurchasingForApproval($report),
            'on_hold_tr'   => $this->notify->notifyTechnicalReviewOnHold($report),
            'rejected'     => $this->notify->notifyRejected($report),
            default        => null,
        };

        // ✅ Clear cache BEFORE notifications
        $this->clearSummaryCaches();
        event(new GlobalPurchaseReportApprovalUpdated($report, 'item_approved', $oldStatus)); // ✅ Pass old status

        return $report;
    }


    /**
     * Send notifications based on PR status
     */
    protected function notifyByStatus(PurchaseReport $report): void
    {
        match ($report->pr_status) {
            'for_approval' => $this->notify->notifyPurchasingForApproval($report),
            'on_hold_tr' => $this->notify->notifyTechnicalReviewOnHold($report),
            default => null,
        };
    }

    public function updatePoNo($id, $poNo, $purchaserId)
    {
        $report = PurchaseReport::findOrFail($id);

        $report->po_no = $poNo;
        $report->pr_status = 'Closed';
        $report->po_status = 'For_approval';
        $report->po_created_date = now();
        $report->purchaser_id = $purchaserId; // ✅ set purchaser_id

        $report->save();
        // ✅ Clear cache
        $this->clearSummaryCaches();
        // ✅ Notify and fire events
        $this->notify->notifyPoCreated($report);
        event(new GlobalPurchaseReportApprovalUpdated($report, 'po_created'));

        return $report;
    }

    // In ApprovalPrService.php
    public function cancelPoNo($id)
    {
        $report = PurchaseReport::findOrFail($id);

        $report->po_no = null;
        $report->pr_status = 'Cancelled';
        $report->po_status = 'Cancelled';
        $report->po_created_date = now();

        // ✅ Update all item statuses to 'cancelled'
        if (is_array($report->item_status)) {
            $report->item_status = array_map(fn() => 'cancelled', $report->item_status);
        }

        $report->save();

        // ✅ Clear cache
        $this->clearSummaryCaches();
        $this->notify->notifyPoCreated($report);
        event(new GlobalPurchaseReportApprovalUpdated($report, 'po_cancelled'));

        return $report;
    }

    public function returnPoNo($id)
    {
        $report = PurchaseReport::findOrFail($id);

        // ✅ Reset PO-related fields
        $report->po_no = null;
        $report->po_created_date = null;
        $report->po_approved_date = null;
        $report->po_status = null; // ← keep this null
        // $report->po_created_date = now();

        // ✅ Mark PR as returned
        $report->pr_status = 'returned';

        // ✅ Update all item statuses to 'returned'
        if (is_array($report->item_status)) {
            $report->item_status = array_map(fn() => 'returned', $report->item_status);
        }

        // ✅ Clear TR / HOD approvals and signatures
        $report->tr_user_id = null;
        $report->hod_user_id = null;
        $report->tr_signed_at = null;
        $report->hod_signed_at = null;

        // // ✅ Optionally clear remarks (optional)
        // if (is_array($report->remarks)) {
        //     $report->remarks = array_fill(0, count($report->remarks), '');
        // }

        // ✅ Optionally set delivery_status to returned (if your workflow uses it)
        // $report->delivery_status = 'returned';

        $report->save();
        // ✅ Clear cache
        $this->clearSummaryCaches();
        // ✅ Notify and broadcast
        $this->notify->notifyPoReturned($report);
        event(new GlobalPurchaseReportApprovalUpdated($report, 'po_returned'));

        return $report;
    }


    public function poApproveDate($id, $status, $approvedDate, $purchaserId)
    {
        $report = PurchaseReport::findOrFail($id);

        $report->po_status = $status;
        $report->po_approved_date = $approvedDate;
        $report->purchaser_id = $purchaserId;
        $report->save();
        // ✅ Clear cache
        $this->clearSummaryCaches();
        $this->notify->notifyPoApproved($report);
        event(new GlobalPurchaseReportApprovalUpdated($report, 'po_approved'));

        return $report;
    }

    protected function clearSummaryCaches(): void
    {
        // Clear all summary caches (since multiple users might be affected)
        Cache::flush(); // Or use a more targeted approach if needed

        // Or if you want to be more specific:
        // $pattern = 'summary_counts_*';
        // Cache::tags(['summary'])->flush();
    }
}
