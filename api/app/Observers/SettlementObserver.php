<?php

namespace App\Observers;

use App\Models\CaseSettlement;
use App\Models\CaseModel;
use App\Models\User;
use App\Notifications\SystemNotification;

use App\Traits\LogsTimeline;

class SettlementObserver
{
    use LogsTimeline;
    public function created(CaseSettlement $settlement): void
    {
        $this->notifyParties($settlement, "New Settlement Recorded");
        
        if ($settlement->case_id) {
            $this->logTimeline(
                $settlement->case_id,
                'legal',
                'Settlement Recorded',
                "A settlement of \${$settlement->settlement_amount} has been recorded with status '{$settlement->status}'.",
                ['settlement_id' => $settlement->id, 'amount' => $settlement->settlement_amount]
            );
        }
    }

    /**
     * Handle the CaseSettlement "updated" event.
     */
    public function updated(CaseSettlement $settlement): void
    {
        if ($settlement->isDirty('status') || $settlement->isDirty('settlement_amount')) {
            $this->notifyParties($settlement, "Settlement Updated");
            
            if ($settlement->case_id) {
                $this->logTimeline(
                    $settlement->case_id,
                    'legal',
                    'Settlement Updated',
                    "Settlement status changed to '{$settlement->status}' with amount \${$settlement->settlement_amount}.",
                    ['settlement_id' => $settlement->id, 'status' => $settlement->status]
                );
            }
        }
    }

    private function notifyParties(CaseSettlement $settlement, string $title): void
    {
        $case = $settlement->case;
        if ($case) {
            $message = "Settlement for Case #{$case->case_number} has been updated to \${$settlement->settlement_amount} with status '{$settlement->status}'.";
            $url = "/dashboard/legal/settlements";

            // Notify Assigned Attorneys
            $attorneys = $case->parties()->where('role_in_case', 'attorney')->get();
            foreach ($attorneys as $party) {
                if ($party->user_id) {
                    $user = User::find($party->user_id);
                    if ($user) {
                        $user->notify(new SystemNotification($title, $message, 'success', $url));
                    }
                }
            }

            // Notify Firm Admins
            $admins = User::where('organization_id', $case->organization_id)
                ->where('role', 'firm_admin')
                ->get();
            foreach ($admins as $admin) {
                $admin->notify(new SystemNotification($title, $message, 'success', $url));
            }
        }
    }
}
