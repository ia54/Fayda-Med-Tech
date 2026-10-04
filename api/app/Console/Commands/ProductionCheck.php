<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;

class ProductionCheck extends Command
{
    protected $signature = 'production:check';
    protected $description = 'Read-only production configuration gate; does not certify deployment readiness';

    public function handle(): int
    {
        $origins = config('cors.allowed_origins', []);
        sort($origins);
        $expected = ['https://admin.faydamed.tech', 'https://faydamed.tech'];
        sort($expected);
        $db = config('database.connections.mysql', []);
        $key = config('app.key', '');
        $decoded = is_string($key) && str_starts_with($key, 'base64:') ? base64_decode(substr($key, 7), true) : $key;
        $checks = [
            'Production mode with debug disabled' => config('app.env') === 'production' && config('app.debug') === false,
            'Exact approved HTTPS origins' => config('app.url') === 'https://api.faydamed.tech' && config('app.frontend_url') === 'https://admin.faydamed.tech' && config('app.public_url') === 'https://faydamed.tech',
            'CORS restricted to the two approved frontends' => $origins === $expected && config('cors.allowed_origins_patterns') === [],
            'Named MySQL database and non-root credential without URL override' => config('database.default') === 'mysql' && empty($db['url']) && !empty($db['database']) && !empty($db['username']) && strtolower($db['username']) !== 'root' && !empty($db['password']),
            'Secure HTTP-only session cookie' => config('session.secure') === true && config('session.http_only') === true && in_array(config('session.same_site'), ['lax','strict'], true),
            'Private local document storage configured' => config('filesystems.disks.documents.driver') === 'local' && config('filesystems.disks.documents.root') === storage_path('app/private/documents') && config('filesystems.disks.documents.visibility') === 'private',
            'Supported application encryption key configured' => is_string($decoded) && \Illuminate\Encryption\Encrypter::supported($decoded, config('app.cipher')) && $decoded !== str_repeat("\0", strlen($decoded)),
        ];
        foreach ($checks as $label=>$passed) $this->line(($passed ? 'PASS: ' : 'FAIL: ').$label);
        $this->warn('Configuration only. No database connection, data change, secret output, mail or external request performed.');
        $this->warn('Launch also requires host isolation/grants, signing keys, migration rehearsal, private-file access checks, recoverable backups, delivery configuration and role acceptance.');
        return in_array(false, $checks, true) ? self::FAILURE : self::SUCCESS;
    }
}
