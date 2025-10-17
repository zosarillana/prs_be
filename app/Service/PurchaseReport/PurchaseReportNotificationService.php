<?php

namespace App\Service\PurchaseReport;

use App\Events\Global\GlobalPurchaseReportCreated;
use App\Events\PurchaseReportEvents\PurchaseReportCreated;
use App\Models\PurchaseReport;
use App\Models\User;
use App\Notifications\NewMessageNotification;
use Illuminate\Support\Collection;

class PurchaseReportNotificationService
{
    /** 🔹 When a report is newly created */
    public function notifyOnCreated(PurchaseReport $report): void
    {
        event(new PurchaseReportCreated($report));
        event(new GlobalPurchaseReportCreated($report));

        $this->notifyDepartmentRole($report, 'hod', 'New Purchase Report Created');
        $this->notifyDepartmentRole($report, 'admin', 'New Purchase Report Created', ['admin']);
        $this->notifyDepartmentRole($report, 'user', 'New Purchase Report Created', ['user']);
    }

    /** 🔹 When PR is ready for purchasing approval */
    public function notifyPurchasingForApproval(PurchaseReport $report): void
    {
        $this->notifyByRole($report, 'purchasing', 'Purchase Report Ready for Approval');

        // Also notify admins + HODs of the same department
        $this->notifyDepartmentRole($report, 'admin', 'Purchase Report Ready for Approval', ['admin']);
        $this->notifyDepartmentRole($report, 'hod', 'Purchase Report Ready for Approval', ['hod']);
    }

    /** 🔹 When a PO is created */
    public function notifyPoCreated(PurchaseReport $report): void
    {
        $this->notifyAdminPurchasingHod($report, 'New PO Created');
        $this->notifyDepartmentRole($report, 'user', 'New PO Created', ['user']);
    }

    /** 🔹 When a PO is approved */
    public function notifyPoApproved(PurchaseReport $report): void
    {
        $this->notifyAdminPurchasingHod($report, 'PO Approved');
        $this->notifyDepartmentRole($report, 'user', 'PO Approved', ['user']);
    }

    /** 🔹 When item status triggers on_hold_tr (technical hold) */
    public function notifyTechnicalOnHold(PurchaseReport $report): void
    {
        $this->notifyByRole($report, 'technical_reviewer', 'Purchase Report For TR Approval (Technical Review)');
        $this->notifyDepartmentRole($report, 'admin', 'Purchase Report For TR Approval (Admin Copy)', ['admin']);
        $this->notifyDepartmentRole($report, 'user', 'Purchase Report For TR Approval (User Copy)', ['user']);
    }

    /** 🔹 When PR is on hold for technical review */
    public function notifyTechnicalReviewOnHold(PurchaseReport $report): void
    {
        $this->notifyByRole($report, 'technical_reviewer', 'Purchase Report On Hold (Technical Review)');
        $this->notifyDepartmentRole($report, 'user', 'Purchase Report On Hold (Technical Review)', ['user']);
    }

    /**
     * 🔹 Notify users of a specific role but restricted to the report’s department
     */
    protected function notifyDepartmentRole(
        PurchaseReport $report,
        string $role,
        string $title,
        ?array $overrideRole = null
    ): void {
        $users = User::query()
            ->whereJsonContains('role', $role)
            ->whereJsonContains('department', $report->department)
            ->get();

        $this->notifyUnique($users, $report, $title, $overrideRole);
    }

    /**
     * 🔹 Notify users by role (admins/hods limited to same dept)
     */
    protected function notifyByRole(
        PurchaseReport $report,
        string $role,
        string $title,
        ?array $overrideRole = null
    ): void {
        $query = User::query()->whereJsonContains('role', $role);

        if (in_array($role, ['admin', 'hod'])) {
            $query->whereJsonContains('department', $report->department);
        }

        $users = $query->get();
        $this->notifyUnique($users, $report, $title, $overrideRole);
    }

    /**
     * 🔹 Notify admins + purchasing + HOD
     */
    protected function notifyAdminPurchasingHod(PurchaseReport $report, string $title): void
    {
        $recipients = User::query()
            ->where(function ($q) use ($report) {
                $q->whereJsonContains('role', 'purchasing')
                  ->orWhere(function ($q2) use ($report) {
                      $q2->whereJsonContains('role', 'admin')
                         ->whereJsonContains('department', $report->department);
                  });
            })
            ->get();

        if ($report->hodUser) {
            $recipients->push($report->hodUser);
        }

        $this->notifyUnique($recipients, $report, $title);
    }

    /**
     * 🔹 Prevent duplicate notifications
     */
    protected function notifyUnique(
        Collection $users,
        PurchaseReport $report,
        string $title,
        ?array $overrideRole = null
    ): void {
        $users->unique('id')->each(function (User $user) use ($report, $title, $overrideRole) {
            if (!$user->notifications()
                ->where('data->report_id', $report->id)
                ->where('data->title', $title)
                ->exists()) {
                $this->notify($user, $report, $title, $overrideRole);
            }
        });
    }

    /**
     * 🔹 Send the actual notification
     */
    private function notify(
        User $user,
        PurchaseReport $report,
        string $title,
        ?array $overrideRole = null
    ): void {
        $user->notify(new NewMessageNotification([
            'title'      => $title,
            'report_id'  => $report->id,
            'po_no'      => $report->po_no ?? null,
            'created_by' => $report->user->name ?? 'Unknown',
            'pr_status'  => $report->pr_status,
            'po_status'  => $report->po_status,
            'user_id'    => $user->id,
            'department' => $user->department,
            'role'       => $overrideRole ?? $user->role,
        ]));
    }
}
