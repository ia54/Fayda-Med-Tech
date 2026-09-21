<?php

namespace App\Observers;

use App\Models\Document;
use App\Models\CaseModel;
use App\Models\User;
use App\Notifications\SystemNotification;

class DocumentObserver
{
    /**
     * Handle the Document "created" event.
     */
    public function created(Document $document): void
    {
        $caseId = $document->metadata['case_id'] ?? null;
        
        if ($caseId) {
            $case = CaseModel::find($caseId);
            if ($case) {
                $title = "New Document Uploaded";
                $message = "Document '{$document->title}' was uploaded to Case #{$case->case_number}.";
                $url = "/dashboard/legal/cases/{$case->id}";

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
}
