<?php

namespace App\Service\PurchaseOrder;

use App\Events\Global\GlobalPurchaseReportApprovalUpdated;
use App\Models\PurchaseReport;
use App\Models\PurchaseReportItemPo;
use App\Service\PurchaseReport\PurchaseReportNotificationService;
use Carbon\Carbon;

class PurchaseOrderService
{
    protected PurchaseReportNotificationService $notify;

    public function __construct(PurchaseReportNotificationService $notify)
    {
        $this->notify = $notify;
    }

    /**
     * ✅ Per-item PO
     */
    public function createOrUpdateItemPo(
        PurchaseReport $report,
        int $itemIndex,
        string $poNumber,
        int $purchaserId
    ): PurchaseReportItemPo {
        $itemPo = PurchaseReportItemPo::updateOrCreate(
            [
                'purchase_report_id' => $report->id,
                'item_index' => $itemIndex,
            ],
            [
                'po_number' => $poNumber,
                'purchaser_id' => $purchaserId,
                'status' => 'created',
                'po_created_at' => now(),
            ]
        );

        $this->syncReportPoSummary($report);

        $report->update([
            'po_status' => 'For_approval',
        ]);

        return $itemPo;
    }

    /**
     * ✅ Same PO for ALL items
     */
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

        $this->syncReportPoSummary($report);

        $report->update([
            'po_status' => 'For_approval',
        ]);
    }

    public function approveDocumentPoDate(
        PurchaseReport $report,
        string $status,
        Carbon $approvedDate,
        int $purchaserId
    ): PurchaseReport {

        // ✅ Update all item-level PO rows
        $report->itemPos()->update([
            'status' => $status,
            'po_approved_at' => $approvedDate,
            'purchaser_id' => $purchaserId,
        ]);

        // ✅ Update document-level summary fields (legacy support)
        $report->update([
            'po_status' => $status,
            'po_approved_date' => $approvedDate,
            'purchaser_id' => $purchaserId,
        ]);

        // 🔁 Recalculate derived status in case of mixed states later
        $this->syncApprovalSummary($report);

        // 🔔 Same side effects as your old service
        // $this->clearSummaryCaches();
        $this->notify->notifyPoApproved($report);
        event(new GlobalPurchaseReportApprovalUpdated($report, 'po_approved'));

        return $report->fresh(['itemPos']);
    }

    public function approveItemPoDate(
        PurchaseReport $report,
        int $itemIndex,
        string $status,
        Carbon $approvedDate,
        int $purchaserId
    ): PurchaseReportItemPo {

        $itemPo = PurchaseReportItemPo::where([
            'purchase_report_id' => $report->id,
            'item_index' => $itemIndex,
        ])->firstOrFail();

        $itemPo->update([
            'status' => $status,
            'po_approved_at' => $approvedDate,
            'purchaser_id' => $purchaserId,
        ]);

        // 🔁 recalc document summary
        $this->syncApprovalSummary($report);

        // 🔔 side effects (same as old)
        // $this->clearSummaryCaches();
        $this->notify->notifyPoApproved($report);
        event(new GlobalPurchaseReportApprovalUpdated($report, 'po_item_approved'));

        return $itemPo;
    }

    protected function syncApprovalSummary(PurchaseReport $report): void
    {
        $itemCount = count($report->item_description ?? []);

        $approvedCount = $report->itemPos()
            ->where('status', 'approved')
            ->count();

        if ($approvedCount === 0) {

            $report->update([
                'po_status' => 'For_approval',
            ]);

        } elseif ($approvedCount < $itemCount) {

            $report->update([
                'po_status' => 'partial_approved',
            ]);

        } else {

            $report->update([
                'po_status' => 'approved',
            ]);

        }
    }

    /**
     * 🔁 Keep purchase_reports.po_no in sync (legacy support)
     */
    protected function syncReportPoSummary(PurchaseReport $report): void
    {
        $poNumbers = $report->itemPos()
            ->pluck('po_number')
            ->unique()
            ->implode(' ');

        $report->update([
            'po_no' => $poNumbers,
        ]);
    }
}
