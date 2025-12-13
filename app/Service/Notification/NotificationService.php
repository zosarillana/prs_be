<?php

namespace App\Service\Notification;

use Illuminate\Notifications\DatabaseNotification;
use App\Models\User;
use App\Notifications\NewMessageNotification;
use Illuminate\Support\Facades\DB;

class NotificationService
{
    /**
     * Send notification to selected roles or departments
     */
    public function sendNotification(array $data)
    {
        $roles = $data['roles'] ?? [];
        $departments = $data['departments'] ?? [];

        // Query users who match any allowed role OR department
        $users = User::query()
            ->when(!empty($roles), function ($q) use ($roles) {
                foreach ($roles as $role) {
                    $q->orWhereJsonContains('role', $role);
                }
            })
            ->when(!empty($departments), function ($q) use ($departments) {
                foreach ($departments as $dept) {
                    $q->orWhereJsonContains('department', $dept);
                }
            })
            ->get();

        // Send the notification to each matching user
        foreach ($users as $user) {
            $user->notify(new NewMessageNotification($data));
        }

        return $users->count(); // return how many users were notified
    }

    /**
     * Get all notifications for a user
     */
    public function getAllForUser(User $user)
    {
        return $user->notifications()
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get counts (total, unread, read) for a user
     */
    public function getCountsForUser(User $user)
    {
        $total = $user->notifications()->count();
        $unread = $user->unreadNotifications()->count();
        $read = $total - $unread;

        return [
            'total' => $total,
            'unread' => $unread,
            'read' => $read,
        ];
    }

    /**
     * Get all notifications tagged for Admins only
     */
    public function getAllForAdmins()
    {
        return DB::table('notifications')
            ->whereJsonContains('data->role', 'admin')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get counts for Admin
     */
    public function getCountsForAdmins()
    {
        $query = DB::table('notifications')
            ->whereJsonContains('data->role', 'admin');

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
     * Get notifications for a department
     */
    public function getAllForDepartment(string|array $departments)
    {
        $query = DB::table('notifications');

        if (is_array($departments)) {
            foreach ($departments as $dept) {
                $query->orWhereJsonContains('data->department', $dept);
            }
        } else {
            $query->whereJsonContains('data->department', $departments);
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    /**
     * Counts for department
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
     * Summary filters
     */
    public function getSummary(
        ?string $role = null,
        ?string $department = null,
        ?string $prStatus = null,
        ?string $poStatus = null
    ) {
        $query = DB::table('notifications');

        if ($role) {
            $query->whereJsonContains('data->role', $role);
        }

        if ($department) {
            $query->whereJsonContains('data->department', $department);
        }

        if ($prStatus) {
            $query->where('data->pr_status', $prStatus);
        }

        if ($poStatus) {
            $query->where('data->po_status', $poStatus);
        }

        $total = $query->count();
        $unread = (clone $query)->whereNull('read_at')->count();
        $read = $total - $unread;

        return [
            'total' => $total,
            'unread' => $unread,
            'read' => $read,
            'filters' => [
                'role' => $role,
                'department' => $department,
                'pr_status' => $prStatus,
                'po_status' => $poStatus,
            ]
        ];
    }

    /**
     * Mark a single notification as read
     */
    public function markAsRead(DatabaseNotification $notification)
    {
        $notification->markAsRead();
        return $notification;
    }

    /**
     * Mark all notifications as read
     */
    public function markAllAsRead(User $user)
    {
        $user->unreadNotifications->markAsRead();
    }
}
