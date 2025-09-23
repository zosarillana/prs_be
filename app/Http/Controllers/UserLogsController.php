<?php

namespace App\Http\Controllers;

use App\Models\UserLoginHistory;
use Illuminate\Http\Request;
use App\Service\Paginator\PaginatorService;

class UserLogsController extends Controller
{
    /**
     * Display a paginated list of user login/logout logs.
     */
    public function index(Request $request, PaginatorService $paginator)
    {
        $query = UserLoginHistory::with('user:id,name,email');

        // 🔎 Search across user name/email
        if ($search = $request->input('searchTerm')) {
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Filter by user_id if provided
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // ✅ Sorting
        $sortBy    = $request->input('sortBy', 'id');
        $sortOrder = $request->input('sortOrder', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // ✅ Paginate results
        $result = $paginator->paginate(
            $query,
            $request->input('pageNumber', 1),
            $request->input('pageSize', 10)
        );

        // Optional transformation (flat table data)
        $result['items'] = collect($result['items'])->map(function ($log) {
            return [
                'id'           => $log->id,
                'user_name'    => $log->user?->name,
                'user_email'   => $log->user?->email,
                'ip_address'   => $log->ip_address,
                'user_agent'   => $log->user_agent,
                'logged_in_at' => $log->logged_in_at,
                'logged_out_at'=> $log->logged_out_at,
            ];
        })->toArray();

        return response()->json($result);
    }
}
