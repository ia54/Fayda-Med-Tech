<?php

namespace App\Observers;

use App\Models\CaseParty;
use App\Models\User;
use App\Notifications\SystemNotification;

class CasePartyObserver
{
    /**
     * Handle the CaseParty "created" event.
     */
    public function created(CaseParty $party): void
    {
        // If an attorney is added to a case, notify them
        if ($party->role_in_case === 'attorney' && $party->user_id) {
            $user = User::find($party->user_id);
            if ($user) {
                $case = $party->case;
                $user->notify(new SystemNotification(
                    "New Case Assigned",
                    "You have been assigned to Case #{$case->case_number}: {$case->title}.",
                    'info',
                    "/dashboard/legal/cases/{$case->id}"
                ));
            }
        }
    }
}
