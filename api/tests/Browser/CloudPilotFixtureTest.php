<?php
namespace Tests\Browser;

use App\Models\{Invoice, ClaimAppeal};

class CloudPilotFixtureTest extends CloudSharedRoleFixtureTest
{
    protected function prepareSharedBrowserEvidence(array $evidence): array
    {
        $invoice = Invoice::withoutEvents(fn () => Invoice::create(['organization_id' => 1,
            'case_id' => $evidence['case_id'], 'invoice_number' => 'SYN-PILOT-DENIED',
            'amount' => '100.25', 'status' => 'denied']));
        for ($i = 0; $i < 26; $i++) {
            ClaimAppeal::create(['organization_id' => 1, 'invoice_id' => $invoice->id,
                'appeal_number' => sprintf('SYN-PILOT-%02d', $i), 'reason_category' => 'synthetic',
                'content' => 'SYNTHETIC local draft, no provider call or payer submission.',
                'status' => $i === 0 ? 'sent' : 'draft']);
        }
        for ($i = 0; $i < 26; $i++) {
            \App\Models\Eob::create(['organization_id'=>1, 'uploaded_by'=>$evidence['sessions']['biller']['user']['id'], 'patient_name'=>sprintf('SYN-PAGING-%02d',$i), 'payer_name'=>'SYNTHETIC payer', 'provider_name'=>'SYNTHETIC provider', 'billed_amount'=>'1.00', 'paid_amount'=>'0.00', 'status'=>$i===0?'rejected':'pending']);
        }
        $foreign = \App\Models\User::create(['first_name'=>'Synthetic','last_name'=>'Foreign client','email'=>'foreign-client@example.invalid','password'=>'synthetic-only','role'=>'client','organization_id'=>2,'status'=>'active']);
        $evidence['sessions'] += $this->browserSessions(['foreign_client' => $foreign]);
        $evidence['denied_invoice_id'] = $invoice->id;
        return $evidence;
    }
}
