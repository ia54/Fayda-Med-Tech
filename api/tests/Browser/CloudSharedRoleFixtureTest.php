<?php

namespace Tests\Browser;

use App\Models\CaseModel;
use App\Models\CaseParty;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\Feature\PharmacyWorkflowTest;

class CloudSharedRoleFixtureTest extends PharmacyWorkflowTest
{
    use CloudBrowserFixture;

    public function test_seed_cloud_shared_role_browser_fixture(): void
    {
        $directory = $this->browserDirectory('shared.sqlite');
        $actor = (new \ReflectionProperty(PharmacyWorkflowTest::class, 'actor'))->getValue($this);
        $patient = (new \ReflectionProperty(PharmacyWorkflowTest::class, 'patient'))->getValue($this);
        $case = (new \ReflectionProperty(PharmacyWorkflowTest::class, 'case'))->getValue($this);
        $case->forceFill(['case_number' => 'SYN-CLOUD-OWN', 'title' => 'SYNTHETIC cloud assigned case'])->saveQuietly();
        $users = ['client' => $patient, 'pharmacist' => $actor];
        foreach (['platform_admin' => 'admin', 'firm_admin' => 'firm_admin', 'attorney' => 'attorney',
            'biller' => 'medical_biller', 'provider' => 'provider_staff', 'technician' => 'pharmacy_technician'] as $name => $role) {
            $users[$name] = User::create(['first_name' => 'Synthetic', 'last_name' => $name, 'email' => $name.'@example.invalid',
                'password' => 'synthetic-only', 'role' => $role, 'organization_id' => $role === 'admin' ? null : 1, 'status' => 'active']);
        }
        CaseParty::withoutEvents(fn () => CaseParty::create(['case_id' => $case->id, 'user_id' => $users['attorney']->id, 'role_in_case' => 'attorney']));
        $location = (new \ReflectionProperty(PharmacyWorkflowTest::class, 'location'))->getValue($this);
        DB::table('pharmacy_staff_assignments')->insert(['location_id' => $location, 'user_id' => $users['technician']->id,
            'active' => true, 'valid_until' => now()->addYear()->toDateString()]);
        $hidden = [];
        foreach (['unassigned' => 1, 'foreign' => 2] as $name => $org) {
            $other = CaseModel::withoutEvents(fn () => CaseModel::create(['organization_id' => $org,
                'case_number' => 'SYN-CLOUD-'.strtoupper($name), 'title' => 'SYNTHETIC '.$name.' case', 'created_by' => $actor->id,
                'accident_date' => now()->subDays(30)->toDateString()]));
            $invoice = Invoice::withoutEvents(fn () => Invoice::create(['organization_id' => $org, 'case_id' => $other->id,
                'invoice_number' => 'SYN-'.strtoupper($name).'-INV', 'amount' => '73.25', 'status' => 'sent',
                'metadata' => ['patient_name' => 'SYNTHETIC '.$name.' patient']]));
            $hidden[$name] = ['case_id' => $other->id, 'invoice_id' => $invoice->id, 'invoice_number' => $invoice->invoice_number];
        }
        // Financial baseline has no payments. The UI records synthetic receipts only.
        $this->assertSame(0, DB::table('payments')->count());
        $this->exportBrowserDatabase($directory, 'shared.sqlite', ['case_id' => $case->id,
            'case_number' => $case->case_number, 'hidden' => $hidden, 'sessions' => $this->browserSessions($users)]);
    }
}
