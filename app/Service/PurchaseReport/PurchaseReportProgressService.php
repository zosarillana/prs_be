<?php

namespace App\Service\PurchaseReport;

use App\Models\PurchaseReport;
use App\Models\PurchaseReportProgress;
use Illuminate\Support\Facades\Auth;

class PurchaseReportProgressService
{
    public function listByReport($reportId)
    {
        return PurchaseReport::with('progresses')->findOrFail($reportId);
    }

    public function addProgress($reportId, array $data)
    {
        $report = PurchaseReport::findOrFail($reportId);

        return $report->progresses()->create([
            'start_date' => $data['start_date'] ?? null,
            'end_date' => $data['end_date'] ?? null,
            'title' => $data['title'],
            'remarks' => $data['remarks'] ?? null,
            'created_by' => Auth::id(),
        ]);
    }

    public function updateProgress($progressId, array $data)
    {
        $progress = PurchaseReportProgress::findOrFail($progressId);

        $progress->update([
            'start_date' => $data['start_date'] ?? $progress->start_date,
            'end_date' => $data['end_date'] ?? $progress->end_date,
            'title' => $data['title'] ?? $progress->title,
            'remarks' => $data['remarks'] ?? $progress->remarks,
        ]);

        return $progress;
    }

    public function deleteProgress($progressId)
    {
        $progress = PurchaseReportProgress::findOrFail($progressId);
        return $progress->delete();
    }
}
