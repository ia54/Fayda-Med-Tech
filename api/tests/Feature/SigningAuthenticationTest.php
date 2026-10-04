<?php
namespace Tests\Feature;

use App\Services\DocuSignService;
use DocuSign\eSign\Client\{ApiClient, Auth\OAuth};
use DocuSign\eSign\Configuration;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class SigningAuthenticationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('migrate',['--force'=>true,'--no-interaction'=>true]);
        config(['services.docusign.account_id'=>'chosen-account','services.docusign.client_id'=>'synthetic-client','services.docusign.impersonated_user_id'=>'synthetic-user','services.docusign.private_key'=>'synthetic-key']);
    }
    private function service(string $base, array $accounts): array
    {
        config(['services.docusign.base_uri'=>$base]);
        $client = $this->createMock(ApiClient::class);
        $oauth = new OAuth(); $configuration = new Configuration();
        $client->method('getOAuth')->willReturn($oauth);
        $client->method('getConfig')->willReturn($configuration);
        $client->expects($this->once())->method('requestJWTUserToken')->with('synthetic-client','synthetic-user','synthetic-key','signature impersonation',60)->willReturn([new \DocuSign\eSign\Client\Auth\OAuthToken(['access_token'=>'synthetic-token'])]);
        $client->expects($this->once())->method('getUserInfo')->with('synthetic-token')->willReturn([new \DocuSign\eSign\Client\Auth\UserInfo(['accounts'=>array_map(fn ($a)=>new \DocuSign\eSign\Client\Auth\Account($a),$accounts)])]);
        $service = new class($client) extends DocuSignService {
            public function __construct(private ApiClient $fake) { parent::__construct(); }
            protected function createClient(Configuration $configuration): ApiClient { return $this->fake; }
        };
        return [$service,$oauth,$configuration];
    }
    public function test_production_uses_production_oauth_and_the_selected_accounts_discovered_region(): void
    {
        [$service,$oauth,$configuration] = $this->service('https://na2.docusign.net',[
            ['account_id'=>'other-account','base_uri'=>'https://na1.docusign.net'],
            ['account_id'=>'chosen-account','base_uri'=>'https://eu.docusign.net'],
        ]);
        $this->assertSame('synthetic-token',$service->getAccessToken(null));
        $this->assertSame('account.docusign.com',$oauth->getOAuthBasePath());
        $this->assertSame('https://eu.docusign.net',$service->getBaseUri());
        $this->assertSame('https://eu.docusign.net/restapi',$configuration->getHost());
    }
    public function test_explicit_document_tenant_credentials_override_system_credentials(): void
    {
        \Illuminate\Support\Facades\DB::table('organizations')->insert(['id'=>1,'org_name'=>'Synthetic','org_type'=>'law_firm','subscription_plan'=>'test','email'=>'org@example.invalid']);
        \App\Models\ApiCredential::create(['organization_id'=>1,'provider'=>'docusign','name'=>'CLIENT_ID','key'=>'synthetic-client','is_active'=>true]);
        config(['services.docusign.client_id'=>'wrong-system-client']);
        [$service] = $this->service('https://demo.docusign.net', [['account_id'=>'chosen-account','base_uri'=>'https://demo.docusign.net']]);
        $this->assertSame('synthetic-token',$service->getAccessToken(1));
    }
    public function test_demo_uses_demo_oauth(): void
    {
        [$service,$oauth] = $this->service('https://demo.docusign.net', [['account_id'=>'chosen-account','base_uri'=>'https://demo.docusign.net']]);
        $service->getAccessToken(null);
        $this->assertSame('account-d.docusign.com',$oauth->getOAuthBasePath());
    }
    public function test_unowned_account_or_mismatched_environment_fails_closed(): void
    {
        foreach ([['account_id'=>'other','base_uri'=>'https://eu.docusign.net'],['account_id'=>'chosen-account','base_uri'=>'https://demo.docusign.net'],['account_id'=>'chosen-account','base_uri'=>'https://private.example.invalid']] as $account) {
            [$service] = $this->service('https://eu.docusign.net',[$account]);
            try { $service->getAccessToken(null); $this->fail('Invalid account accepted'); }
            catch (\Exception $exception) { $this->assertSame('Signing authentication failed. Ask your administrator to verify the account and consent.',$exception->getMessage()); }
        }
    }
    public function test_arbitrary_hosts_paths_credentials_and_ports_are_rejected(): void
    {
        foreach (['http://demo.docusign.net','https://demo.docusign.net.evil.test','https://user@demo.docusign.net','https://demo.docusign.net:443','https://demo.docusign.net/restapi','https://127.0.0.1','https://demo.docusign.net?x=1'] as $uri) {
            try { DocuSignService::validateBaseUri($uri); $this->fail('Invalid origin accepted'); }
            catch (\Exception $exception) { $this->assertSame('Invalid signing API origin.',$exception->getMessage()); }
        }
    }
}
