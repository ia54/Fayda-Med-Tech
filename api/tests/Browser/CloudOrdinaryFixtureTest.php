<?php

namespace Tests\Browser;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\Feature\PharmacyWorkflowTest;

/** Explicitly opted-in disposable fixture; never part of the default suite. */
class CloudOrdinaryFixtureTest extends PharmacyWorkflowTest
{
    use CloudBrowserFixture;

    public function test_seed_cloud_ordinary_browser_fixture(): void
    {
        Http::preventStrayRequests();
        $directory = $this->browserDirectory('ordinary.sqlite');
        $actor = (new \ReflectionProperty(PharmacyWorkflowTest::class, 'actor'))->getValue($this);
        $location = (new \ReflectionProperty(PharmacyWorkflowTest::class, 'location'))->getValue($this);
        $patient = $this->postJson('/api/pharmacy/patients', [
            'request_id' => (string) Str::uuid(), 'record_number' => 'SYN-CLOUD-ORDINARY',
            'location_id' => $location, 'first_name' => 'Synthetic', 'last_name' => 'Ordinary',
            'date_of_birth' => '1980-01-01', 'identity_reference' => 'SYNTHETIC identity only',
        ])->assertCreated()->json('data');
        $lot = (new \ReflectionMethod(PharmacyWorkflowTest::class, 'lot'))->invoke($this, [], false);
        $prescriptions = [];
        foreach (['collection', 'delivery'] as $journey) {
            $prescriptions[$journey] = (new \ReflectionMethod(PharmacyWorkflowTest::class, 'rx'))->invoke($this, [
                'patient_id' => null, 'pharmacy_patient_id' => $patient['id'],
                'rx_number' => 'SYN-CLOUD-'.strtoupper($journey),
            ]);
        }
        $users = ['pharmacist' => $actor];
        foreach (['technician' => ['pharmacy_technician', 1], 'biller' => ['medical_biller', 1],
                  'outsider' => ['pharmacist', 2], 'unassigned' => ['pharmacist', 1]] as $name => [$role, $org]) {
            $users[$name] = User::create(['first_name' => 'Synthetic', 'last_name' => $name,
                'email' => $name.'@example.invalid', 'password' => 'synthetic-only',
                'role' => $role, 'organization_id' => $org, 'status' => 'active']);
            if (in_array($name, ['technician', 'biller'], true)) DB::table('pharmacy_staff_assignments')->insert([
                'location_id' => $location, 'user_id' => $users[$name]->id,
                'active' => true, 'valid_until' => now()->addYear()->toDateString(),
            ]);
        }
        $this->assertSame(0, DB::table('pharmacy_fills')->count());
        $this->assertSame(0, DB::table('payments')->count());
        $this->exportBrowserDatabase($directory, 'ordinary.sqlite', [
            'patient_id' => $patient['id'], 'lot_id' => $lot, 'prescriptions' => $prescriptions,
            'sessions' => $this->browserSessions($users),
        ]);
        Http::assertNothingSent();
    }
}
