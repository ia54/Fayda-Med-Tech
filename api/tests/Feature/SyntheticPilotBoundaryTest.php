<?php

namespace Tests\Feature;

use App\Providers\SyntheticPilotServiceProvider;
use App\Services\DocuSignService;
use Illuminate\Support\Facades\Http;
use LogicException;
use Tests\TestCase;

class SyntheticPilotBoundaryTest extends TestCase
{
    private function enablePilot(): void
    {
        config(['pilot.synthetic' => true, 'mail.default' => 'smtp', 'queue.default' => 'redis', 'broadcasting.default' => 'pusher']);
        (new SyntheticPilotServiceProvider($this->app))->boot();
    }

    public function test_pilot_overrides_external_delivery_settings(): void
    {
        $this->enablePilot();
        $this->assertSame('array', config('mail.default'));
        $this->assertInstanceOf(\Illuminate\Mail\Transport\ArrayTransport::class, \Illuminate\Support\Facades\Mail::mailer()->getSymfonyTransport());
        $this->assertSame('sync', config('queue.default'));
        $this->assertSame('null', config('broadcasting.default'));
    }

    public function test_http_transport_is_blocked_before_a_provider_or_fake_handler_can_run(): void
    {
        $this->enablePilot();
        $sent = 0;
        Http::fake(function () use (&$sent) { $sent++; return Http::response([]); });
        try {
            Http::post('https://provider.example.invalid/transaction', ['synthetic' => true]);
            $this->fail('Transport must be blocked.');
        } catch (LogicException $e) {
            $this->assertSame('External requests are disabled in the synthetic pilot.', $e->getMessage());
        }
        $this->assertSame(0, $sent);
    }

    public function test_signing_sdk_is_blocked_before_credentials_or_database_lookup(): void
    {
        $this->enablePilot();
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Signing is disabled in the synthetic pilot.');
        app(DocuSignService::class)->getAccessToken();
    }

    public function test_synthetic_mode_does_not_open_the_production_pharmacy_gate(): void
    {
        $this->enablePilot();
        $original = $this->app['env'];
        $this->app['env'] = 'production';
        try {
            $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
            (new \App\Http\Middleware\PharmacyPreviewOnly)->handle(\Illuminate\Http\Request::create('/api/pharmacy/prescriptions'), fn () => $this->fail('Clinical gate opened.'));
        } finally {
            $this->app['env'] = $original;
        }
    }

    public function test_default_mode_does_not_replace_existing_delivery_policy(): void
    {
        config(['pilot.synthetic' => false, 'mail.default' => 'smtp']);
        (new SyntheticPilotServiceProvider($this->app))->boot();
        $this->assertSame('smtp', config('mail.default'));
        Http::fake(['https://provider.example.invalid/*' => Http::response(['synthetic' => true])]);
        $this->assertTrue(Http::get('https://provider.example.invalid/read')->json('synthetic'));
    }

    public function test_hosted_pharmacy_preview_requires_server_verified_gateway_attribute(): void
    {
        $this->enablePilot();
        config(['pilot.pharmacy_preview'=>true, 'app.debug'=>false,
            'app.url'=>'https://pilot-api.faydamed.tech', 'database.default'=>'mysql',
            'database.connections.mysql.host'=>'localhost',
            'database.connections.mysql.database'=>'faydamed_pilot']);
        $original=$this->app['env'];
        $this->app['env']='production';
        try {
            $request=\Illuminate\Http\Request::create('/api/pharmacy/prescriptions');
            $request->attributes->set('fayda_pilot_gateway_verified', true);
            $response=(new \App\Http\Middleware\PharmacyPreviewOnly)->handle($request, fn () => response('synthetic preview'));
            $this->assertSame('synthetic preview', $response->getContent());

            // Client-controlled inputs cannot grant the server-side attribute.
            $spoofed=\Illuminate\Http\Request::create('/api/pharmacy/prescriptions', 'POST',
                ['fayda_pilot_gateway_verified'=>true], [], [], ['HTTP_FAYDA_PILOT_GATEWAY_VERIFIED'=>'true']);
            $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
            (new \App\Http\Middleware\PharmacyPreviewOnly)->handle($spoofed, fn () => $this->fail('Spoofed gateway accepted.'));
        } finally {
            $this->app['env']=$original;
        }
    }
}
