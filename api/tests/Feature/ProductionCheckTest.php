<?php
namespace Tests\Feature;
use Tests\TestCase;

class ProductionCheckTest extends TestCase
{
    private function productionConfig(): void
    {
        config(['app.env'=>'production','app.debug'=>false,'app.url'=>'https://api.faydamed.tech','app.frontend_url'=>'https://admin.faydamed.tech','app.public_url'=>'https://faydamed.tech',
            'cors.allowed_origins'=>['https://admin.faydamed.tech','https://faydamed.tech'],'cors.allowed_origins_patterns'=>[],
            'database.default'=>'mysql','database.connections.mysql.url'=>null,'database.connections.mysql.host'=>'unreachable.example.invalid','database.connections.mysql.database'=>'synthetic','database.connections.mysql.username'=>'synthetic','database.connections.mysql.password'=>'synthetic-not-a-deployment-secret',
            'session.secure'=>true,'session.http_only'=>true,'session.same_site'=>'lax',
            'app.key'=>'base64:'.base64_encode(str_repeat('test',8))]);
    }
    public function test_configuration_check_is_read_only_and_does_not_connect(): void
    {
        $this->productionConfig();
        $this->artisan('production:check')->assertSuccessful();
    }
    public function test_unsafe_origins_debug_and_root_database_are_rejected(): void
    {
        $this->productionConfig();
        config(['app.debug'=>true,'app.frontend_url'=>'http://localhost:3000','database.connections.mysql.username'=>'root','cors.allowed_origins'=>['*']]);
        $this->artisan('production:check')->expectsOutput('FAIL: Production mode with debug disabled')->expectsOutput('FAIL: Exact approved HTTPS origins')->expectsOutput('FAIL: CORS restricted to the two approved frontends')->expectsOutput('FAIL: Named MySQL database and non-root credential without URL override')->assertFailed();
    }
    public function test_public_documents_cookie_and_placeholder_key_are_rejected(): void
    {
        $this->productionConfig();
        config(['filesystems.disks.documents.root'=>public_path('documents'),'session.secure'=>false,'app.key'=>'base64:'.base64_encode(str_repeat("\0",32))]);
        $this->artisan('production:check')->expectsOutput('FAIL: Secure HTTP-only session cookie')->expectsOutput('FAIL: Private local document storage configured')->expectsOutput('FAIL: Supported application encryption key configured')->assertFailed();
    }
}
