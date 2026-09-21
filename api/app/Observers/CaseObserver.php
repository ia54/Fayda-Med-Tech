<?php

namespace App\Observers;

use App\Models\CaseModel;

class CaseObserver
{
    /**
     * Handle the CaseModel "created" event.
     */
    public function created(CaseModel $case): void
    {
        $admins = \App\Models\User::where('organization_id', $case->organization_id)
            ->where('role', 'firm_admin')
            ->get();
            
        // Notify Admins
        foreach ($admins as $admin) {
            $admin->notify(new \App\Notifications\SystemNotification(
                "New Case Created",
                "Case #{$case->case_number} has been initialized.",
                'info',
                "/dashboard/legal/cases/{$case->id}"
            ));
        }
    }

    /**
     * Handle the CaseModel "updated" event.
     */
    public function updated(CaseModel $case): void
    {
        if ($case->isDirty('status')) {
            $oldStatus = $case->getOriginal('status');
            $newStatus = $case->status;
            
            // Get Firm Admins
            $admins = \App\Models\User::where('organization_id', $case->organization_id)
                ->where('role', 'firm_admin')
                ->get();
                
            // Get Assigned Attorneys
            $attorneys = $case->parties()->where('role_in_case', 'attorney')->get();
                
            $title = "Case Status Updated";
            $message = "Case #{$case->case_number} moved from {$oldStatus} to {$newStatus}.";
            $url = "/dashboard/legal/cases/{$case->id}";

            foreach ($admins as $admin) {
                $admin->notify(new \App\Notifications\SystemNotification($title, $message, 'success', $url));
            }

            foreach ($attorneys as $party) {
                if ($party->user_id) {
                    $user = \App\Models\User::find($party->user_id);
                    if ($user) {
                        $user->notify(new \App\Notifications\SystemNotification($title, $message, 'success', $url));
                    }
                }
            }
        }
    }

    /**
     * Handle the CaseModel "deleted" event.
     */
    public function deleted(CaseModel $caseModel): void
    {
        //
    }

    /**
     * Handle the CaseModel "restored" event.
     */
    public function restored(CaseModel $caseModel): void
    {
        //
    }

    /**
     * Handle the CaseModel "force deleted" event.
     */
    public function forceDeleted(CaseModel $caseModel): void
    {
        //
    }
}
