<?php

namespace App\Service\PurchaseReport;

use App\Events\Global\GlobalPurchaseReportCreated;
use App\Events\PurchaseReportEvents\PurchaseReportCreated;
use App\Models\PurchaseReport;
use App\Models\User;
use App\Notifications\NewMessageNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class PurchaseReportNotificationService
{
    /** -----------------------------
     *  NEW HELPERS (CENTRALIZED)
     * ---------------------------- */

    /**
     * Get users by roles, optionally restricted to department.
     */
    protected function getUsersByRoles(array $roles, ?string $department = null): Collection
    {
        $query = User::query();

        $query->where(function ($q) use ($roles) {
            foreach ($roles as $role) {
                $q->orWhereJsonContains('role', $role);
            }
        });

        // Only restrict dept for NON-admin roles
        if ($department && !in_array('admin', $roles)) {
            $query->whereJsonContains('department', $department);
        }

        return $query->get();
    }

    /**
     * Centralized notification sender (with duplicate prevention)
     */
    protected function notifyMany(
        Collection $users,
        PurchaseReport $report,
        string $title,
        ?array $overrideRole = null
    ): void {
        $users
            ->unique('id')
            ->each(function (User $user) use ($report, $title, $overrideRole) {

                // Prevent duplicate notifications
                $exists = $user->notifications()
                    ->where('data->report_id', $report->id)
                    ->where('data->title', $title)
                    ->exists();

                if ($exists) return;

                $user->notify(new NewMessageNotification([
                    'title'      => $title,
                    'report_id'  => $report->id,
                    'series_no'  => $report->series_no ?? null,
                    'po_no'      => $report->po_no ?? null,
                    'created_by' => $report->user->name ?? 'Unknown',
                    'pr_status'  => $report->pr_status,
                    'po_status'  => $report->po_status,
                    'user_id'    => $user->id,
                    'department' => $user->department,
                    'role'       => $overrideRole ?? $user->role,
                ]));
            });
    }

    /** -----------------------------
     *  CREATION EVENTS
     * ---------------------------- */

    public function notifyOnCreated(PurchaseReport $report): void
    {
        // Admin + Purchasing get all reports
        $admins      = $this->getUsersByRoles(['admin']);
        $purchasing  = $this->getUsersByRoles(['purchasing']);
        $hodTrUsers  = $this->getUsersByRoles(['hod', 'technical_reviewer'], $report->department);

        $recipients = $admins->merge($purchasing)->merge($hodTrUsers);

        $this->notifyMany($recipients, $report, 'New Purchase Request Created');

        // Broadcast for real-time updates
        $this->broadcastCreationEvents($report);
    }

    protected function broadcastCreationEvents(PurchaseReport $report): void
    {
        event(new PurchaseReportCreated($report));
        event(new GlobalPurchaseReportCreated($report));

        Log::info("📢 Events dispatched for report {$report->id}");
    }

    /** -----------------------------
     *  PURCHASE APPROVAL
     * ---------------------------- */

    public function notifyPurchasingForApproval(PurchaseReport $report): void
    {
        // Notify Admins (global)
        $this->notifyMany(
            $this->getUsersByRoles(['admin']),
            $report,
            'Purchase Request Ready for Approval',
            ['admin']
        );

        // Notify Purchasing (global)
        $this->notifyMany(
            $this->getUsersByRoles(['purchasing']),
            $report,
            'Purchase Request Ready for Approval',
            ['purchasing']
        );

        // Notify HOD (department-specific)
        $this->notifyMany(
            $this->getUsersByRoles(['hod'], $report->department),
            $report,
            'Purchase Request Ready for Approval',
            ['hod']
        );

        // Notify Technical Reviewer (department-specific)
        $this->notifyMany(
            $this->getUsersByRoles(['technical_reviewer'], $report->department),
            $report,
            'Purchase Request Ready for Approval',
            ['technical_reviewer']
        );

        // Notify User (department-specific)
        $this->notifyMany(
            $this->getUsersByRoles(['user'], $report->department),
            $report,
            'Purchase Request Ready for Approval',
            ['user']
        );
    }

    /** -----------------------------
     *  PO CREATED/RETURNED/APPROVED
     * ---------------------------- */

    public function notifyPoCreated(PurchaseReport $report): void
    {
        $this->notifyAdminPurchasingHod($report, 'New PO Created');

        $this->notifyMany(
            $this->getUsersByRoles(['user'], $report->department),
            $report,
            'New PO Created'
        );
    }

    public function notifyPoReturned(PurchaseReport $report): void
    {
        $this->notifyAdminPurchasingHod($report, 'PO Returned');

        $this->notifyMany(
            $this->getUsersByRoles(['user'], $report->department),
            $report,
            'PO Returned'
        );
    }

    public function notifyPoApproved(PurchaseReport $report): void
    {
        $this->notifyAdminPurchasingHod($report, 'PO Approved');

        $this->notifyMany(
            $this->getUsersByRoles(['user'], $report->department),
            $report,
            'PO Approved'
        );

        $this->notifyMany(
            $this->getUsersByRoles(['technical_reviewer'], $report->department),
            $report,
            'PO Approved'
        );
    }

    /** -----------------------------
     *  TECHNICAL REVIEW
     * ---------------------------- */

    public function notifyTechnicalOnHold(PurchaseReport $report): void
    {
        $this->notifyMany(
            $this->getUsersByRoles(['technical_reviewer'], $report->department),
            $report,
            'Purchase Request For TR Approval (Technical Review)'
        );

        $this->notifyMany(
            $this->getUsersByRoles(['admin']),
            $report,
            'Purchase Request For TR Approval (Admin Copy)'
        );

        $this->notifyMany(
            $this->getUsersByRoles(['user'], $report->department),
            $report,
            'Purchase Request For TR Approval (User Copy)'
        );
    }

    public function notifyTechnicalReviewOnHold(PurchaseReport $report): void
    {
        $this->notifyMany(
            $this->getUsersByRoles(['technical_reviewer'], $report->department),
            $report,
            'Purchase Request On Hold (Technical Review)'
        );

        $this->notifyMany(
            $this->getUsersByRoles(['user'], $report->department),
            $report,
            'Purchase Request On Hold (Technical Review)'
        );
    }

    /** -----------------------------
     *  REJECTED
     * ---------------------------- */

    public function notifyRejected(PurchaseReport $report): void
    {
        $title = 'Purchase Request Rejected';

        $this->notifyAdminPurchasingHod($report, $title);

        if ($report->user) {
            $this->notifyMany(collect([$report->user]), $report, $title);
        }

        $this->notifyMany(
            $this->getUsersByRoles(['technical_reviewer'], $report->department),
            $report,
            $title
        );
    }

    /** -----------------------------
     *  DELIVERY STATUS CHANGED
     * ---------------------------- */

    public function notifyDeliveryStatusUpdated(PurchaseReport $report): void
    {
        $title = match ($report->delivery_status) {
            'delivered' => 'Purchase Order Delivered',
            'partial'   => 'Purchase Order Partially Delivered',
            'pending'   => 'Delivery Status Changed to Pending',
            default     => 'Delivery Status Updated',
        };

        $admin      = $this->getUsersByRoles(['admin']);
        $purchasing = $this->getUsersByRoles(['purchasing']);
        $hod        = $this->getUsersByRoles(['hod'], $report->department);

        $this->notifyMany($admin->merge($purchasing)->merge($hod), $report, $title);

        $this->notifyMany(
            $this->getUsersByRoles(['user'], $report->department),
            $report,
            $title
        );
    }

    /** -----------------------------
     *  HELPER – ADMINS + PURCHASING + HOD
     * ---------------------------- */

    protected function notifyAdminPurchasingHod(PurchaseReport $report, string $title): void
    {
        $recipients = collect()
            ->merge($this->getUsersByRoles(['admin']))
            ->merge($this->getUsersByRoles(['purchasing']))
            ->merge($this->getUsersByRoles(['hod'], $report->department));

        if ($report->hodUser) {
            $recipients->push($report->hodUser);
        }

        $this->notifyMany($recipients, $report, $title);
    }
}
