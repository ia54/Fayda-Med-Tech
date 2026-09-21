<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class SolAlertCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:sol-alert';
    protected $description = 'Scan for cases approaching Statute of Limitations and send alerts';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $intervals = [30, 7, 1];
        
        foreach ($intervals as $days) {
            $targetDate = now()->addDays($days)->toDateString();
            
            $cases = \App\Models\CaseModel::where('sol_date', $targetDate)
                ->where('status', '!=', 'Closed')
                ->with(['parties', 'organizationRelation.users'])
                ->get();
                
            foreach ($cases as $case) {
                $message = "Case #{$case->case_number} ({$case->title}) is approaching its Statute of Limitations in {$days} days.";
                $title = "SOL Alert: {$days} Days Remaining";
                
                // Notify assigned attorneys
                $attorneys = $case->parties()->where('role_in_case', 'attorney')->get();
                foreach ($attorneys as $party) {
                    if ($party->user_id) {
                        $user = \App\Models\User::find($party->user_id);
                        $user->notify(new \App\Notifications\SystemNotification($title, $message, 'warning', "/dashboard/cases/{$case->id}"));
                    }
                }
                
                // Notify firm admins
                $admins = \App\Models\User::where('organization_id', $case->organization_id)
                    ->where('role', 'firm_admin')
                    ->get();
                    
                foreach ($admins as $admin) {
                    $admin->notify(new \App\Notifications\SystemNotification($title, $message, 'warning', "/dashboard/cases/{$case->id}"));
                }
            }
        }
        
        $this->info('SOL alerts processed successfully.');
    }
}
