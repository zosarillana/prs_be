<?php

namespace App\Service\PurchaseReport;

use App\Models\PurchaseReport;

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

        $itemStatus = $report->item_status ?? [];
        $remarks = $report->remarks ?? [];

        if (! is_array($itemStatus)) {
            $itemStatus = [];
        }
        if (! is_array($remarks)) {
            $remarks = [];
        }

        if (array_key_exists($index, $report->item_description)) {
            $itemStatus[$index] = $status;
            $remarks[$index] = $remark;
        }

        // ✅ Determine PR status
        $hasPending = in_array('pending', $itemStatus, true);
        $hasPendingTr = in_array('pending_tr', $itemStatus, true);

        if ($hasPending) {
            $prStatus = 'on_hold';
        } elseif ($hasPendingTr) {
            $prStatus = 'on_hold_tr';
        } else {
            $prStatus = 'for_approval';
        }

        // ✅ Prepare update data
        $updateData = [
            'item_status' => $itemStatus,
            'remarks' => $remarks,
            'pr_status' => $prStatus,
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

        // ✅ Trigger notifications based on new PR status
        match ($report->pr_status) {
            'for_approval' => $this->notify->notifyPurchasingForApproval($report),
            'on_hold_tr' => $this->notify->notifyTechnicalReviewOnHold($report),
            default => null, // 'on_hold' might not need a notification
        };

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

    public function updatePoNo($id, $poNo)
    {
        $report = PurchaseReport::findOrFail($id);
        $report->po_no = $poNo;
        $report->pr_status = 'Closed';
        $report->po_status = 'For_approval';
        $report->po_created_date = now();
        $report->save();

        // ✅ Just call the notification service
        $this->notify->notifyPoCreated($report);

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
        $report->save();

        $this->notify->notifyPoCreated($report);

        return $report;
    }

    public function poApproveDate($id)
    {
        $report = PurchaseReport::findOrFail($id);
        $report->po_status = 'Approved';
        $report->po_approved_date = now();
        $report->save();

        // ✅ Just call the notification service
        $this->notify->notifyPoApproved($report);

        return $report;
    }
}
