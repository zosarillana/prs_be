<?php

namespace App\Service\PurchaseReport;

use App\Models\PurchaseReport;
use Illuminate\Database\Eloquent\ModelNotFoundException;
class ApprovalPrService
{
    // ... your existing methods

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
public function updateItemStatus(int $id, int $index, string $status, string $remark): PurchaseReport
{
    $report = PurchaseReport::findOrFail($id);

    $itemStatus = $report->item_status ?? [];
    $remarks = $report->remarks ?? [];

    // Ensure arrays are properly initialized
    if (!is_array($itemStatus)) {
        $itemStatus = [];
    }
    if (!is_array($remarks)) {
        $remarks = [];
    }

    // Update only the given index if it exists
    if (array_key_exists($index, $report->item_description)) {
        $itemStatus[$index] = $status;
        $remarks[$index] = $remark;
    }

    // Determine PR status based on item_status
    $hasPending    = in_array('pending', $itemStatus, true);
    $hasPendingTr  = in_array('pending_tr', $itemStatus, true);

    if ($hasPending) {
        $prStatus = 'on_hold'; // covers both pending & (pending+pending_tr)
    } elseif ($hasPendingTr) {
        $prStatus = 'on_hold_tr';
    } else {
        $prStatus = 'for_approval';
    }

    // Save back into the report
    $report->update([
        'item_status' => $itemStatus,
        'remarks' => $remarks,
        'pr_status' => $prStatus,
    ]);

    return $report->fresh();
}


}