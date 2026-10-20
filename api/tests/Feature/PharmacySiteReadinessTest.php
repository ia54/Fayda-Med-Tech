<?php
namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\{Artisan,DB};
use Laravel\Passport\Token;
use Tests\TestCase;

class PharmacySiteReadinessTest extends TestCase
{
    private User $actor;
    private int $site;
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\App\Http\Middleware\TwoFactorMiddleware::class);
        Artisan::call('migrate',['--force'=>true]);
        foreach([1,2] as $org) DB::table('organizations')->insert(['id'=>$org,'org_name'=>'Synthetic '.$org,'org_type'=>'provider','subscription_plan'=>'test','email'=>"site$org@example.invalid"]);
        $this->actor=$this->user('pharmacist',1);
        $this->site=DB::table('pharmacy_locations')->insertGetId(['organization_id'=>1,'name'=>'Synthetic site','address'=>'Synthetic only','license_reference'=>'NOT VALID']);
        $this->assign($this->actor);
        $this->actingAs($this->actor,'api');
    }
    private function user(string $role,int $org): User
    {
        static $n=0;
        $u=User::create(['first_name'=>'Synthetic','last_name'=>'Staff','email'=>'site-user-'.(++$n).'@example.invalid','password'=>'synthetic-only','role'=>$role,'organization_id'=>$org,'status'=>'active']);
        return $u->withAccessToken(new Token(['expires_at'=>now()->addHour()]));
    }
    private function assign(User $u,?string $until=null): void
    {
        DB::table('pharmacy_staff_assignments')->insert(['location_id'=>$this->site,'user_id'=>$u->id,'active'=>true,'valid_until'=>$until??now()->toDateString()]);
    }
    public function test_report_is_documentary_and_never_grants_release_or_changes_operational_records(): void
    {
        $this->assign($this->user('pharmacy_technician',1));
        $this->assign($this->user('pharmacist',2)); // Inconsistent legacy assignment must not count.
        $this->assign($this->user('pharmacist',1),now()->subDay()->toDateString());
        $inactive=$this->user('pharmacist',1);$inactive->update(['status'=>'inactive']);$this->assign($inactive);
        $tables=['pharmacy_locations','pharmacy_staff_assignments','pharmacy_stock_lots','pharmacy_fills','invoices','payments'];
        $before=[];foreach($tables as $t)$before[$t]=DB::table($t)->get()->toJson();
        $this->getJson('/api/pharmacy/locations/'.$this->site.'/readiness')->assertOk()
            ->assertJsonPath('data.recorded.facility_license_reference_present',true)
            ->assertJsonPath('data.recorded.active_pharmacist_assignments',1)
            ->assertJsonPath('data.recorded.active_technician_assignments',1)
            ->assertJsonPath('data.requirements.1.status','reference_only')
            ->assertJsonPath('data.operational_release_enabled',false)
            ->assertJsonPath('data.synthetic_preview_only',true)->assertJsonCount(4,'data.workflows');
        foreach($this->getJson('/api/pharmacy/locations/'.$this->site.'/readiness')->json('data.workflows') as $workflow){$this->assertFalse($workflow['operational_release_enabled']);$this->assertFalse($workflow['clinical_acceptance_verified']);}
        foreach($tables as $t)$this->assertSame($before[$t],DB::table($t)->get()->toJson(),$t);
    }
    public function test_roles_organization_assignment_expiry_and_inactive_site_are_scoped(): void
    {
        $url='/api/pharmacy/locations/'.$this->site.'/readiness';
        foreach(['client','attorney','firm_admin','provider_staff','medical_biller'] as $role){$this->actor->role=$role;$this->getJson($url)->assertForbidden();}
        $this->actor->role='pharmacy_technician';$this->getJson($url)->assertOk();
        DB::table('pharmacy_staff_assignments')->where('user_id',$this->actor->id)->update(['valid_until'=>now()->subDay()->toDateString()]);$this->getJson($url)->assertNotFound();
        $this->actor->role='admin';$this->actor->organization_id=2;$this->getJson($url)->assertNotFound();
        $this->actor->organization_id=null;$this->getJson($url)->assertForbidden();
        $this->actor->organization_id=1;DB::table('pharmacy_locations')->where('id',$this->site)->update(['active'=>false]);
        $this->getJson($url)->assertOk()->assertJsonPath('data.site.active',false)->assertJsonPath('data.operational_release_enabled',false);
        $this->actor->role='pharmacist';$this->getJson($url)->assertNotFound();
    }
    public function test_production_preview_gate_remains_closed(): void
    {
        $original=$this->app['env'];$this->app['env']='production';
        try{$this->getJson('/api/pharmacy/locations/'.$this->site.'/readiness')->assertStatus(503);}finally{$this->app['env']=$original;}
    }
}
