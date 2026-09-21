<?php
/*
 * Model Observer — PDF Section 8 & 14
 * Triggers notifications for medical record request events
 */

namespace App\Observers;

use App\Models\MedicalRecordRequest;
use App\Models\User;
use App\Notifications\SystemNotification;
use Illuminate\Support\Facades\Notification;

use App\Traits\LogsTimeline;

class MedicalRecordRequestObserver
{
    use LogsTimeline;
    /**
     * Handle the MedicalRecordRequest "updated" event.
     */
    public function updated(MedicalRecordRequest $request): void
    {
        // Notify attorney if records are received
        if ($request->isDirty('status') && in_array($request->status, ['received', 'partially_received'])) {
            $this->notifyAttorneys($request);

            if ($request->case_id) {
                $this->logTimeline(
                    $request->case_id,
                    'document',
                    'Medical Records Status',
                    "Medical records from '{$request->provider_name}' have been updated to '{$request->status}'.",
                    ['request_id' => $request->id, 'status' => $request->status]
                );
            }
        }
    }

    /**
     * Notify attorneys assigned to the case
     */
    private function notifyAttorneys(MedicalRecordRequest $request): void
    {
        if (!$request->case_id) return;

        $attorneys = User::where('role', 'attorney')
            ->whereHas('caseParties', function ($query) use ($request) {
                $query->where('case_id', $request->case_id)
                      ->where('role_in_case', 'attorney');
            })->get();

        if ($attorneys->isNotEmpty()) {
            Notification::send($attorneys, new SystemNotification(
                'Medical Records Received',
                "Medical records from '{$request->provider_name}' have been marked as '{$request->status}' for Case #{$request->case_id}.",
                'medical_record',
                $request->case_id
            ));
        }
    }
}
