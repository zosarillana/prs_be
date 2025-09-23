<?php

namespace App\Http\Controllers;

use App\Service\Notification\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\DB;

class NotificationController extends Controller
{
    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Get all notifications
     */
    public function index(Request $request)
    {
        $user = $request->user();

        if (in_array('admin', $user->role ?? [])) {
            // ✅ Only THIS admin’s own notifications where role is admin
            $notifications = $user->notifications()
                ->whereJsonContains('data->role', 'admin')
                ->orderBy('created_at', 'desc')
                ->get();

        } elseif (in_array('hod', $user->role ?? [])) {
            // ✅ Only THIS HOD’s own notifications where role is hod + department matches
            $department = is_array($user->department) ? $user->department[0] : $user->department;
            $notifications = $user->notifications()
                ->whereJsonContains('data->role', 'hod')
                ->whereJsonContains('data->department', $department)
                ->orderBy('created_at', 'desc')
                ->get();

        } else {
            // ✅ Regular user gets all their personal notifications
            $notifications = $this->notificationService->getAllForUser($user);
        }

        return response()->json($notifications);
    }

    public function counts(Request $request)
    {
        $user = $request->user();

        if (in_array('admin', $user->role ?? [])) {
            // 🔹 SAME query as index() for admins
            $notifications = $user->notifications()
                ->whereJsonContains('data->role', 'admin')
                ->orderBy('created_at', 'desc')
                ->get();

            $total = $notifications->count();
            $unread = $notifications->whereNull('read_at')->count();
            $read = $total - $unread;

            $counts = [
                'total' => $total,
                'unread' => $unread,
                'read' => $read,
            ];

        } elseif (in_array('hod', $user->role ?? [])) {
            // 🔹 SAME query as index() for HODs
            $department = is_array($user->department) ? $user->department[0] : $user->department;

            $notifications = $user->notifications()
                ->whereJsonContains('data->role', 'hod')
                ->whereJsonContains('data->department', $department)
                ->orderBy('created_at', 'desc')
                ->get();

            $total = $notifications->count();
            $unread = $notifications->whereNull('read_at')->count();
            $read = $total - $unread;

            $counts = [
                'total' => $total,
                'unread' => $unread,
                'read' => $read,
            ];

        } else {
            $counts = $this->notificationService->getCountsForUser($user);
        }

        return response()->json($counts);
    }

    /**
     * Get notification counts (total, unread, read)
     */
    public function getCountsForDepartment(string|array $departments)
    {
        $query = DB::table('notifications');

        if (is_array($departments)) {
            foreach ($departments as $dept) {
                $query->orWhereJsonContains('data->department', $dept);
            }
        } else {
            $query->whereJsonContains('data->department', $departments);
        }

        $total = $query->count();
        $unread = (clone $query)->whereNull('read_at')->count();
        $read = $total - $unread;

        return [
            'total' => $total,
            'unread' => $unread,
            'read' => $read,
        ];
    }

    /**
     * Get role/department/status summary
     */
    public function summary(Request $request)
    {
        $role = $request->query('role');
        $department = $request->query('department');
        $prStatus = $request->query('pr_status');
        $poStatus = $request->query('po_status');

        $summary = $this->notificationService->getSummary($role, $department, $prStatus, $poStatus);

        return response()->json($summary);
    }

    /**
     * Mark a single notification as read
     */
    public function markAsRead(Request $request, $id)
    {
        $notification = DatabaseNotification::findOrFail($id);

        // ✅ Only allow marking own notifications
        if ($notification->notifiable_id !== $request->user()->id) {
            return response()->json(['error' => 'Not allowed'], 403);
        }

        $updated = $this->notificationService->markAsRead($notification);

        return response()->json($updated);
    }

    /**
     * Mark all notifications as read
     */
    public function markAllAsRead(Request $request)
    {
        $user = $request->user();
        $this->notificationService->markAllAsRead($user);

        return response()->json(['message' => 'All notifications marked as read']);
    }
}
