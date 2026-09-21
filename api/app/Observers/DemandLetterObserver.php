<?php

namespace App\Observers;

use App\Models\DemandLetter;
use App\Models\User;
use App\Notifications\SystemNotification;

use App\Traits\LogsTimeline;

class DemandLetterObserver
{
    use LogsTimeline;
    public function created(DemandLetter $demandLetter): void
    {
        $this->notifyParties($demandLetter, "New Demand Letter Generated");
        
        if ($demandLetter->case_id) {
            $this->logTimeline(
                $demandLetter->case_id,
                'milestone',
                'Demand Letter Generated',
                "A new demand letter has been generated with status '{$demandLetter->status}' for the amount of ${$demandLetter->demand_amount}.",
                ['demand_id' => $demandLetter->id, 'amount' => $demandLetter->demand_amount]
            );
        }
    }

    /**
     * Handle the DemandLetter "updated" event.
     */
    public function updated(DemandLetter $demandLetter): void
    {
        if ($demandLetter->isDirty('status')) {
            $this->notifyParties($demandLetter, "Demand Letter Status Updated");
            
            if ($demandLetter->case_id) {
                $this->logTimeline(
                    $demandLetter->case_id,
                    'milestone',
                    'Demand Status Updated',
                    "Demand letter status changed to '{$demandLetter->status}'.",
                    ['demand_id' => $demandLetter->id, 'status' => $demandLetter->status]
                );
            }
        }
    }

    private function notifyParties(DemandLetter $demandLetter, string $title): void
    {
        $case = $demandLetter->case;
        if ($case) {
            $message = "Demand Letter for Case #{$case->case_number} has been updated to status '{$demandLetter->status}'. Amount: ${$demandLetter->demand_amount}.";
            $url = "/dashboard/legal/demand-letters";

            // Notify Assigned Attorneys
            $attorneys = $case->parties()->where('role_in_case', 'attorney')->get();
            foreach ($attorneys as $party) {
                if ($party->user_id) {
                    $user = User::find($party->user_id);
                    if ($user) {
                        $user->notify(new SystemNotification($title, $message, 'info', $url));
                    }
                }
            }

            // Notify Firm Admins
            $admins = User::where('organization_id', $case->organization_id)
                ->where('role', 'firm_admin')
                ->get();
            foreach ($admins as $admin) {
                $admin->notify(new SystemNotification($title, $message, 'info', $url));
            }
        }
    }
}
