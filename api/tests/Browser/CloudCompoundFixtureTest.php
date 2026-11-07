<?php

namespace Tests\Browser;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\Feature\PharmacyWorkflowTest;

class CloudCompoundFixtureTest extends PharmacyWorkflowTest
{
    use CloudBrowserFixture;

    private ?\DateTimeInterface $fixtureClock = null;

    protected function beyondUseFixtureClock(): \DateTimeInterface
    {
        return $this->fixtureClock ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    public function test_wall_clock_fixture_handles_the_first_hour_of_the_day(): void
    {
        $this->fixtureClock = (new \DateTimeImmutable('today', new \DateTimeZone('UTC')))->modify('+15 minutes');
        [$execution, , , $body] = (new \ReflectionMethod(PharmacyWorkflowTest::class, 'beyondUseFixture'))->invoke($this);
        $this->assertSame('00:00', (new \DateTimeImmutable($body['proposal']['prepared_at']))->format('H:i'));
        $this->getJson("/api/pharmacy/executions/{$execution->id}/beyond-use-context")->assertOk();
    }

    public function test_seed_cloud_compound_browser_fixture(): void
    {
        $directory = $this->browserDirectory('compound.sqlite');
        $actor = (new \ReflectionProperty(PharmacyWorkflowTest::class, 'actor'))->getValue($this);
        $patient = (new \ReflectionProperty(PharmacyWorkflowTest::class, 'patient'))->getValue($this);
        $cases = []; $users = ['author' => $actor];
        foreach (['main'] as $name) {
            [$execution, $lot, $reviewer, $proof] = (new \ReflectionMethod(PharmacyWorkflowTest::class, 'compoundRehearsalFixture'))->invoke($this);
            $this->getJson("/api/pharmacy/executions/{$execution->id}/handover-rehearsal-context")->assertOk()->assertJsonPath('release_enabled', false);
            $cases[$name] = ['execution_id' => $execution->id, 'batch_id' => $execution->batch_id,
                'ingredient_lot_id' => $lot, 'container' => $proof->container_identifier, 'label_code' => $proof->barcode_code];
            $users['reviewer'] = $reviewer;
        }
        $this->travelBack();
        $location = (new \ReflectionProperty(PharmacyWorkflowTest::class, 'location'))->getValue($this);
        foreach (['technician' => ['pharmacy_technician', 1], 'outsider' => ['pharmacist', 2]] as $name => [$role, $org]) {
            $users[$name] = User::create(['first_name' => 'Synthetic', 'last_name' => $name, 'email' => $name.'@example.invalid',
                'password' => 'synthetic-only', 'role' => $role, 'organization_id' => $org, 'status' => 'active']);
            if ($org === 1) DB::table('pharmacy_staff_assignments')->insert(['location_id' => $location,
                'user_id' => $users[$name]->id, 'active' => true, 'valid_until' => now()->addYear()->toDateString()]);
        }
        $this->exportBrowserDatabase($directory, 'compound.sqlite', ['cases' => $cases, 'patient_id' => $patient->id,
            'sessions' => $this->browserSessions($users)]);
    }
}
