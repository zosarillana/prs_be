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
            // 🔹 Admin sees notifications meant for all admins under 'personal'
            $notifications =
                $this->notificationService->getAllForAdmins();

        } elseif (in_array('hod', $user->role ?? [])) {
            $department = is_array($user->department) ? $user->department[0] : $user->department;
            $notifications = $this->notificationService->getAllForDepartment($department);
        } else {
            // 🔹 Normal user only sees their own
            $notifications = $this->notificationService->getAllForUser($user);
        }

        return response()->json($notifications);
    }

    public function counts(Request $request)
    {
        $user = $request->user();

        if (in_array('admin', $user->role ?? [])) {
            // Admin only sees admin-tagged notifications
            $counts = $this->notificationService->getCountsForAdmins();
        } elseif (in_array('hod', $user->role ?? [])) {
            // HOD may have one or multiple departments
            $departments = $user->department;
            $counts = $this->notificationService->getCountsForDepartment($departments);
        } else {
            // Normal user only sees their own
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
