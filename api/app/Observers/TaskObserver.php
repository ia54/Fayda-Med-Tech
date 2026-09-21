<?php

namespace App\Observers;

use App\Models\CaseTask;
use App\Models\User;
use App\Notifications\SystemNotification;

class TaskObserver
{
    /**
     * Handle the CaseTask "created" event.
     */
    public function created(CaseTask $task): void
    {
        if ($task->assigned_to) {
            $user = User::find($task->assigned_to);
            if ($user) {
                $case = $task->case;
                $user->notify(new SystemNotification(
                    "New Task Assigned",
                    "You have a new task '{$task->title}' for Case #{$case->case_number}.",
                    'info',
                    "/dashboard/legal/cases/{$case->id}"
                ));
            }
        }
    }

    /**
     * Handle the CaseTask "updated" event.
     */
    public function updated(CaseTask $task): void
    {
        if ($task->isDirty('status') && $task->status === 'completed') {
            // Notify the case attorney if task is completed
            $case = $task->case;
            if ($case) {
                $title = "Task Completed";
                $message = "Task '{$task->title}' has been completed.";
                $url = "/dashboard/legal/cases/{$case->id}";

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
            }
        }
    }
}
