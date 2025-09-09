<?php

namespace App\Service\Notification;

use Illuminate\Notifications\DatabaseNotification;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class NotificationService
{
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
     * Get counts for Admin (only notifications tagged as admin)
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
     * Get all notifications for a specific department (HOD)
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
     * Get counts for a department (HOD)
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
     * Get summary counts filtered by role, department, or status
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
