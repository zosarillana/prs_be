<?php

namespace App\Http\Controllers;

use App\Service\PurchaseReport\PurchaseReportProgressService;
use Illuminate\Http\Request;

class PurchaseReportProgressController extends Controller
{
    protected $service;


    public function __construct(PurchaseReportProgressService $service)
    {
        $this->service = $service;
    }

    // GET /purchase-reports/{id}/progresses
    public function index($reportId)
    {
        $report = $this->service->listByReport($reportId);
        return response()->json($report);
    }

    // POST /purchase-reports/{id}/progresses
    public function store(Request $request, $reportId)
    {
        $validated = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'title' => 'required|string|max:255',
            'remarks' => 'nullable|string',
        ]);

        $progress = $this->service->addProgress($reportId, $validated);

        return response()->json([
            'message' => 'Progress added successfully.',
            'data' => $progress
        ], 201);
    }

    // PUT /purchase-reports/progresses/{id}
    public function update(Request $request, $progressId)
    {
        $validated = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'title' => 'required|string|max:255',
            'remarks' => 'nullable|string',
        ]);

        $progress = $this->service->updateProgress($progressId, $validated);

        return response()->json([
            'message' => 'Progress updated successfully.',
            'data' => $progress
        ], 200);
    }

    // DELETE /purchase-reports/progresses/{id}
    public function destroy($progressId)
    {
        $this->service->deleteProgress($progressId);

        return response()->json(['message' => 'Progress deleted successfully.']);
    }
}
