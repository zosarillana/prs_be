<?php

namespace App\Service\PurchaseReport;

use App\Models\PurchaseReport;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class ApprovalPrService
{
    /**
     * Update a specific item's status and remark inside a Purchase Report.
     *
     * @param  int    $id       The PurchaseReport ID
     * @param  int    $index    The index of the item to update
     * @param  string $status   The new status (e.g., 'approved', 'rejected')
     * @param  string $remark   The remark to set for this item
     * @return PurchaseReport
     *
     * @throws ModelNotFoundException
     */
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

        if (!is_array($itemStatus)) {
            $itemStatus = [];
        }
        if (!is_array($remarks)) {
            $remarks = [];
        }

        if (array_key_exists($index, $report->item_description)) {
            $itemStatus[$index] = $status;
            $remarks[$index] = $remark;
        }

        // Determine PR status
        $hasPending = in_array('pending', $itemStatus, true);
        $hasPendingTr = in_array('pending_tr', $itemStatus, true);

        if ($hasPending) {
            $prStatus = 'on_hold';
        } elseif ($hasPendingTr) {
            $prStatus = 'on_hold_tr';
        } else {
            $prStatus = 'for_approval';
        }

        // Prepare update data
        $updateData = [
            'item_status' => $itemStatus,
            'remarks' => $remarks,
            'pr_status' => $prStatus,
        ];

        // Attach approver info only if asRole is provided
        if ($asRole === 'technical_reviewer' || $asRole === 'both') {
            $updateData['tr_user_id'] = $loggedUserId;
            $updateData['tr_signed_at'] = now();
        }

        if ($asRole === 'hod' || $asRole === 'both') {
            $updateData['hod_user_id'] = $loggedUserId;
            $updateData['hod_signed_at'] = now();
        }

        $report->update($updateData);

        return $report->fresh();
    }


}
