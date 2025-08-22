<?php

namespace App\Http\Controllers;


use App\Http\Requests\PurchaseReport\StorePurchaseReportRequest;
use App\Http\Requests\PurchaseReport\UpdatePurchaseReportRequest;
use App\Service\Paginator\PaginatorService;
use App\Service\PurchaseReport\PurchaseReportService;
use Illuminate\Http\Request;

class PurchaseReportController extends Controller
{
    protected $purchaseReportService;

    public function __construct(PurchaseReportService $purchaseReportService)
    {
        $this->purchaseReportService = $purchaseReportService;
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

        // Paginate with reusable paginator
        $result = $paginator->paginate(
            $query,
            $request->input('pageNumber', 1),
            $request->input('pageSize', 10)
        );

        return response()->json($result);
    }


    /**
     * Store a newly created purchase report.
     */
     public function store(StorePurchaseReportRequest $request)
    {
        $report = $this->purchaseReportService->store($request->validated());
        return response()->json($report, 201);
    }

    /**
     * Display a single purchase report.
     */
    public function show($id)
    {
        $report = $this->purchaseReportService->show($id);
        return response()->json($report);
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
}