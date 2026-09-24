<?php

namespace Tests\Feature;

use App\Models\{User, ReportGeneration};
use Illuminate\Support\Facades\{Artisan, DB};
use Laravel\Passport\Token;
use Tests\TestCase;

class ReportHistoryAccessTest extends TestCase
{
    private User $actor;
    private array $reports = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\App\Http\Middleware\TwoFactorMiddleware::class);
        Artisan::call('migrate', ['--force' => true]);
        foreach ([1, 2] as $id) DB::table('organizations')->insert(['id'=>$id, 'org_name'=>'Synthetic '.$id, 'org_type'=>'law_firm', 'subscription_plan'=>'test', 'email'=>"reports$id@example.invalid"]);
        $this->actor = User::create(['first_name'=>'Synthetic', 'last_name'=>'Reader', 'email'=>'report-reader@example.invalid', 'password'=>'synthetic-only', 'role'=>'firm_admin', 'organization_id'=>1, 'status'=>'active']);
        $other = User::create(['first_name'=>'Synthetic', 'last_name'=>'Colleague', 'email'=>'report-colleague@example.invalid', 'password'=>'synthetic-only', 'role'=>'firm_admin', 'organization_id'=>1, 'status'=>'active']);
        foreach ([['own',1,$this->actor->id,'revenue'], ['colleague',1,$other->id,'settlement'], ['prior_role',1,$this->actor->id,'settlement'], ['foreign',2,$this->actor->id,'revenue'], ['unknown',1,$this->actor->id,'legacy_private'], ['platform',1,$other->id,'revenue'], ['legacy',1,$other->id,'revenue']] as [$key,$org,$user,$type]) {
            $this->reports[$key] = ReportGeneration::create(['organization_id'=>$org, 'generated_by'=>$user, 'report_name'=>'Synthetic '.$key, 'report_type'=>$type, 'format'=>'json', 'status'=>'completed', 'parameters'=>$key === 'legacy' ? [] : ['_generated_role'=>$key === 'platform' ? 'admin' : 'firm_admin'], 'result_summary'=>['marker'=>$key]]);
        }
        $this->actor->withAccessToken(new Token(['expires_at'=>now()->addHour()]));
        $this->actingAs($this->actor, 'api');
    }

    public function test_staff_history_cannot_bypass_generation_roles_or_read_colleague_snapshots(): void
    {
        foreach (['medical_biller', 'provider_staff'] as $role) {
            $this->actor->role = $role;
            $this->reports['own']->update(['parameters'=>['_generated_role'=>$role]]);
            $this->getJson('/api/reports/settlement-summary')->assertForbidden();
            $this->getJson('/api/reports/history')->assertOk()->assertJsonPath('data.total', 1)->assertJsonPath('data.data.0.id', $this->reports['own']->id);
            $this->getJson('/api/reports/'.$this->reports['own']->id)->assertOk();
            foreach (['colleague','prior_role','foreign','unknown','platform','legacy'] as $key) $this->getJson('/api/reports/'.$this->reports[$key]->id)->assertNotFound();
        }
        $this->actor->role = 'attorney';
        $fresh = $this->getJson('/api/reports/settlement-summary?_generated_role=firm_admin')->assertOk()->assertJsonPath('data.parameters._generated_role', 'attorney')->json('data.id');
        $this->getJson('/api/reports/history')->assertOk()->assertJsonPath('data.total', 1);
        $this->getJson('/api/reports/'.$fresh)->assertOk();
        $this->getJson('/api/reports/'.$this->reports['own']->id)->assertNotFound();
        $this->getJson('/api/reports/'.$this->reports['colleague']->id)->assertNotFound();
        $this->getJson('/api/reports/'.$this->reports['prior_role']->id)->assertNotFound();
    }

    public function test_administrators_are_explicitly_scoped_even_when_global_tenant_scope_is_bypassed(): void
    {
        foreach (['firm_admin','admin'] as $role) {
            $this->actor->role = $role;
            $this->getJson('/api/reports/history')->assertOk()->assertJsonPath('data.total', $role === 'admin' ? 6 : 4);
            if ($role === 'firm_admin') {
                foreach (['platform','legacy'] as $key) $this->getJson('/api/reports/'.$this->reports[$key]->id)->assertNotFound();
            }
            $this->getJson('/api/reports/'.$this->reports['colleague']->id)->assertOk();
            $this->getJson('/api/reports/'.$this->reports['foreign']->id)->assertNotFound();
        }
        $this->actor->organization_id = null;
        $this->getJson('/api/reports/history')->assertForbidden();
        $this->getJson('/api/reports/'.$this->reports['own']->id)->assertForbidden();
    }

    public function test_patients_cannot_open_report_history_or_snapshots(): void
    {
        $this->actor->role = 'client';
        $this->getJson('/api/reports/history')->assertForbidden();
        $this->getJson('/api/reports/'.$this->reports['own']->id)->assertForbidden();
    }
}
