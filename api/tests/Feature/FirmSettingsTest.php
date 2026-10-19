<?php
namespace Tests\Feature;
use App\Models\User;
use Illuminate\Support\Facades\{Artisan, DB, Storage};
use Illuminate\Http\UploadedFile;
use Laravel\Passport\Token;
use Tests\TestCase;
class FirmSettingsTest extends TestCase
{
    public function test_profile_update_cannot_change_subscription_type_or_other_organization(): void
    {
        $this->withoutMiddleware(\App\Http\Middleware\TwoFactorMiddleware::class);
        Artisan::call('migrate',['--force'=>true]);
        Storage::fake('public');
        foreach ([1,2] as $id) DB::table('organizations')->insert(['id'=>$id,'org_name'=>'Synthetic '.$id,'org_type'=>'law_firm','subscription_plan'=>'test','email'=>"firm$id@example.invalid"]);
        $actor=User::create(['first_name'=>'Synthetic','last_name'=>'Admin','email'=>'firmadmin@example.invalid','password'=>'synthetic-only','role'=>'firm_admin','organization_id'=>1,'status'=>'active']);
        $actor->withAccessToken(new Token(['expires_at'=>now()->addHour()]));
        $this->actingAs($actor,'api');
        $this->postJson('/api/firm/organization',['org_name'=>'Updated synthetic firm','organization_id'=>2,'org_type'=>'provider','subscription_plan'=>'premium'])->assertOk();
        $this->assertDatabaseHas('organizations',['id'=>1,'org_name'=>'Updated synthetic firm','org_type'=>'law_firm','subscription_plan'=>'test']);
        $this->assertDatabaseHas('organizations',['id'=>2,'org_name'=>'Synthetic 2']);
        $this->getJson('/api/firm/organization')->assertOk()->assertJsonPath('data.org_name','Updated synthetic firm');
        $this->postJson('/api/firm/organization',['primary_color'=>'not-a-color'])->assertUnprocessable();
        $this->postJson('/api/firm/organization',['support_documents'=>[UploadedFile::fake()->create('private.pdf',1,'application/pdf')]])->assertUnprocessable();
        $this->assertSame([],Storage::disk('public')->allFiles());
        $actor->role='attorney';
        $this->postJson('/api/firm/organization',['org_name'=>'Denied'])->assertForbidden();
    }
}
