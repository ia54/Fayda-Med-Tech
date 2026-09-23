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
        if ($settlement->isDirty(['status', 'settlement_amount', 'attorney_fees', 'costs', 'other_deductions', 'notes', 'settlement_date'])) {
            $this->notifyParties($settlement, "Settlement Updated");
            
            if ($settlement->case_id) {
                $this->logTimeline(
                    $settlement->case_id,
                    'legal',
                    'Settlement Updated',
                    "Settlement details updated with status '{$settlement->status}' with amount \${$settlement->settlement_amount}.",
                    ['settlement_id' => $settlement->id, 'status' => $settlement->status, 'allocations' => $settlement->only(['settlement_amount', 'attorney_fees', 'costs', 'other_deductions']), 'details' => $settlement->only(['notes', 'settlement_date']), 'previous_details' => array_intersect_key($settlement->getOriginal(), array_flip(['notes', 'settlement_date'])), 'previous_allocations' => array_intersect_key($settlement->getOriginal(), array_flip(['settlement_amount', 'attorney_fees', 'costs', 'other_deductions']))]
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
