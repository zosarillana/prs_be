<?php

namespace App\Service\PurchaseReport;

use App\Models\PurchaseReport;
use App\Models\User;
use App\Notifications\NewMessageNotification;
use App\Events\PurchaseReportEvents\PurchaseReportCreated;
use App\Events\Global\GlobalPurchaseReportCreated;
use Illuminate\Support\Collection;

class PurchaseReportNotificationService
{
    /** 🔹 When a report is newly created */
    public function notifyOnCreated(PurchaseReport $report): void
    {
        // Fire events only ONCE
        event(new PurchaseReportCreated($report));
        event(new GlobalPurchaseReportCreated($report));

        // ✅ Notify HODs in same department
        $hods = User::query()
            ->whereJsonContains('role', 'hod')
            ->whereJsonContains('department', $report->department)
            ->get();

        $this->notifyUnique($hods, $report, 'New Purchase Report Created');

        // ✅ Notify Admins in same department
        $admins = User::query()
            ->whereJsonContains('role', 'admin')
            ->whereJsonContains('department', $report->department)
            ->get();

        $this->notifyUnique($admins, $report, 'New Purchase Report Created', ['admin']);
    }

    /** 🔹 When a PO is created */
    public function notifyPoCreated(PurchaseReport $report): void
    {
        // ⚠️ DO NOT dispatch GlobalPurchaseReportCreated again if already dispatched elsewhere
        $this->notifyAdminPurchasingHod($report, 'New PO Created');
    }

    /** 🔹 When a PO is approved */
    public function notifyPoApproved(PurchaseReport $report): void
    {
        $this->notifyAdminPurchasingHod($report, 'PO Approved');
    }

    /** 🔹 When item status triggers on_hold_tr (technical hold) */
    public function notifyTechnicalOnHold(PurchaseReport $report): void
    {
        $this->notifyByRole($report, 'technical_reviewer', 'Purchase Report On Hold (Technical Review)');
        $this->notifyByRole($report, 'admin', 'Purchase Report On Hold (Admin Copy)', ['admin']);
    }

    /** 🔹 When PR is ready for approval (purchasing) */
    public function notifyPurchasingForApproval(PurchaseReport $report): void
    {
        // ✅ Notify Purchasing (all, no department restriction unless needed)
        $this->notifyByRole($report, 'purchasing', 'Purchase Report Ready for Approval');

        // ✅ Notify Admins in same department
        $this->notifyByRole($report, 'admin', 'Purchase Report Ready for Approval', ['admin']);
    }

    /** 🔹 When PR is on hold for technical review */
    public function notifyTechnicalReviewOnHold(PurchaseReport $report): void
    {
        $this->notifyByRole($report, 'technical_reviewer', 'Purchase Report On Hold (Technical Review)');
    }

    /**
     * 🔹 Generic: notify users by a single JSON role
     * Automatically filters by department for HODs/Admins
     */
    protected function notifyByRole(
        PurchaseReport $report,
        string $role,
        string $title,
        ?array $overrideRole = null
    ): void {
        $query = User::query()->whereJsonContains('role', $role);

        // ✅ Department restriction for admins & hods
        if (in_array($role, ['admin', 'hod'])) {
            $query->whereJsonContains('department', $report->department);
        }

        $users = $query->get();
        $this->notifyUnique($users, $report, $title, $overrideRole);
    }

    /**
     * 🔹 Generic: notify admins + purchasing + HOD of this report
     */
    protected function notifyAdminPurchasingHod(PurchaseReport $report, string $title): void
    {
        $recipients = User::query()
            ->where(function ($q) use ($report) {
                $q->whereJsonContains('role', 'purchasing')
                  ->orWhere(function ($q2) use ($report) {
                      // ✅ Admins restricted to same department
                      $q2->whereJsonContains('role', 'admin')
                         ->whereJsonContains('department', $report->department);
                  });
            })
            ->get();

        // Add the HOD of the same department if available
        if ($report->hodUser) {
            $recipients->push($report->hodUser);
        }

        $this->notifyUnique($recipients, $report, $title);
    }

    /**
     * 🔹 Ensure unique notifications for each user
     */
    protected function notifyUnique(Collection $users, PurchaseReport $report, string $title, ?array $overrideRole = null): void
    {
        $users->unique('id')->each(function (User $user) use ($report, $title, $overrideRole) {
            // ✅ Prevent duplicates in DB
            if (! $user->notifications()
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
    private function notify(User $user, PurchaseReport $report, string $title, ?array $overrideRole = null): void
    {
        $user->notify(new NewMessageNotification([
            'title'       => $title,
            'report_id'   => $report->id,
            'po_no'       => $report->po_no ?? null,
            'created_by'  => $report->user->name ?? 'Unknown',
            'pr_status'   => $report->pr_status,
            'po_status'   => $report->po_status,
            'user_id'     => $user->id,
            'department'  => $user->department,
            'role'        => $overrideRole ?? $user->role,
        ]));
    }
}
