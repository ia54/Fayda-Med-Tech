<?php
/*
 * Model Observer — PDF Section 5 & 14
 * Triggers notifications for HIPAA authorization events
 */

namespace App\Observers;

use App\Models\HipaaAuthorization;
use App\Models\User;
use App\Notifications\SystemNotification;
use Illuminate\Support\Facades\Notification;

use App\Traits\LogsTimeline;

class HipaaAuthorizationObserver
{
    use LogsTimeline;
    /**
     * Handle the HipaaAuthorization "updated" event.
     */
    public function updated(HipaaAuthorization $auth): void
    {
        // Notify firm admin if authorization is signed
        if ($auth->isDirty('status') && $auth->status === 'signed') {
            $this->notifyFirmAdmins($auth);

            if ($auth->case_id) {
                $this->logTimeline(
                    $auth->case_id,
                    'milestone',
                    'HIPAA Signed',
                    "HIPAA authorization of type '{$auth->authorization_type}' has been signed by the client.",
                    ['auth_id' => $auth->id, 'type' => $auth->authorization_type]
                );
            }
        }
    }

    /**
     * Notify firm admins of the organization
     */
    private function notifyFirmAdmins(HipaaAuthorization $auth): void
    {
        $admins = User::where('role', 'firm_admin')
            ->where('organization_id', $auth->organization_id)
            ->get();

        if ($admins->isNotEmpty()) {
            Notification::send($admins, new SystemNotification(
                'HIPAA Authorization Signed',
                "A HIPAA authorization of type '{$auth->authorization_type}' has been signed by the client for Case #{$auth->case_id}.",
                'compliance',
                $auth->case_id
            ));
        }
    }
}
