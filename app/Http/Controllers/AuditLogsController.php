<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use App\Service\Paginator\PaginatorService;

class AuditLogsController extends Controller
{
    /**
     * Display a paginated list of audit logs (search + sort + filter).
     */
    public function index(Request $request, PaginatorService $paginator)
    {
        // Build query with optional filters and search
        $query = AuditLog::query()->with('user:id,name,email');

        // 🔎 Search across action / model_type / user name / user email
        if ($search = $request->input('searchTerm')) {
            $query->where(function ($q) use ($search) {
                $q->where('action', 'like', "%{$search}%")
                  ->orWhere('model_type', 'like', "%{$search}%")
                  ->orWhereHas('user', function ($userQuery) use ($search) {
                      $userQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                  });
            });
        }

        // Filter by user_id if provided
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // Filter by user email if provided
        if ($request->filled('email')) {
            $query->whereHas('user', function ($userQuery) use ($request) {
                $userQuery->where('email', 'like', "%{$request->email}%");
            });
        }

        // Filter by user name if provided
        if ($request->filled('username')) {
            $query->whereHas('user', function ($userQuery) use ($request) {
                $userQuery->where('name', 'like', "%{$request->username}%");
            });
        }

        // Filter by model_type if provided
        if ($request->filled('model_type')) {
            $query->where('model_type', $request->model_type);
        }

        // ✅ Sorting
        $sortBy    = $request->input('sortBy', 'id');
        $sortOrder = $request->input('sortOrder', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // ✅ Paginate using the shared paginator service
        $result = $paginator->paginate(
            $query,
            $request->input('pageNumber', 1),
            $request->input('pageSize', 10)
        );

        // If you want to transform each row (similar to MapUsers)
        $result['items'] = collect($result['items'])->map(function ($log) {
            return [
                'id'          => $log->id,
                'user_name'   => $log->user?->name,
                'user_email'  => $log->user?->email,
                'action'      => $log->action,
                'model_type'  => $log->model_type,
                'model_id'    => $log->model_id,
                'old_values'  => $log->old_values,
                'new_values'  => $log->new_values,
                'ip_address'  => $log->ip_address,
                'user_agent'  => $log->user_agent,
                'created_at'  => $log->created_at,
            ];
        })->toArray();

        return response()->json($result);
    }
}
