<?php

namespace App\Http\Controllers;

use App\Models\VendorPayment;
use App\Service\Paginator\PaginatorService;
use App\Service\VendorPayment\VendorPaymentService;
use Illuminate\Http\Request;

class VendorPaymentController extends Controller
{
    public function __construct(
        private VendorPaymentService $paymentVendorService,
        private PaginatorService $paginator
    ) {}

    /**
     * Paginate vendor payments
     */
    public function paginate(int $pageNumber = 1, int $pageSize = 15): array
    {
        $query = VendorPayment::query()
            ->with('vendor')        // optional: eager load vendor relation
            ->with('purchaseReport'); // optional: eager load purchase report

        return $this->paginator->paginate($query, $pageNumber, $pageSize);
    }

    public function index(Request $request)
    {
        $pageNumber = (int) $request->query('pageNumber', 1);
        $pageSize = (int) $request->query('pageSize', 15);

        // 🔹 Correct query param
        $search = $request->query('searchTerm');

        $paginated = $this->paymentVendorService->paginate($pageNumber, $pageSize, $search);

        return response()->json($paginated);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'vendor_id' => 'required|integer|exists:vendors,id',
            'prs_id' => 'required|integer',
            'po_number' => 'required|string|max:255',
            'inv_number' => 'nullable|string|max:255',
            'amount' => 'required|numeric',
            'cheque_number' => 'nullable|string|max:255',
            'date_served' => 'nullable|date',
            'cheque_status' => 'nullable|string|max:50',
        ]);

        $vendorPayment = VendorPayment::create($data);

        return response()->json([
            'message' => 'Vendor Payment created successfully',
            'data' => $vendorPayment,
        ], 201);
    }

    public function update(Request $request, int $id)
    {
        $data = $request->validate([
            'vendor_id' => 'required|integer|exists:vendors,id',
            'prs_id' => 'required|integer',
            'po_number' => 'required|string|max:255',
            'inv_number' => 'nullable|string|max:255',
            'amount' => 'required|numeric',
            'cheque_number' => 'nullable|string|max:255',
            'date_served' => 'nullable|date',
            'cheque_status' => 'nullable|string|max:50',
        ]);

        return $this->paymentVendorService->update($id, $data);
    }

    public function destroy(int $id)
    {
        $this->paymentVendorService->delete($id);

        return response()->json([
            'message' => 'Vendor Payment deleted successfully',
        ], 200); // <-- explicitly return 200 instead of 204
    }

    public function serve(Request $request, int $id)
    {
        // Optional: allow passing a custom date via request, default to today
        $dateServed = $request->input('date_served');

        $vendorPayment = $this->paymentVendorService->serveDate($id, $dateServed);

        return response()->json([
            'message' => 'Vendor Payment served successfully',
            'data' => $vendorPayment,
        ]);
    }
}
