<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class StagingCheck extends Command
{
    protected $signature = 'staging:check';
    protected $description = 'Read-only configuration checks before synthetic staging acceptance';

    public function handle(): int
    {
        $frontend = config('app.frontend_url');
        $database = config('database.connections.mysql', []);
        $checks = [
            'Staging environment and debug disabled' => config('app.env') === 'staging' && config('app.debug') === false,
            'Explicit HTTPS staging API and frontend origins' => $this->stagingUrl(config('app.url')) && $this->stagingUrl($frontend) && config('app.url') !== $frontend,
            'CORS restricted to the staging frontend' => config('cors.allowed_origins') === [$frontend] && config('cors.allowed_origins_patterns') === [],
            'Dedicated named MySQL database and non-root account configured' => config('database.default') === 'mysql' && empty($database['url']) && str_ends_with($database['database'] ?? '', '_staging') && str_ends_with($database['username'] ?? '', '_staging') && !empty($database['password']),
            'Mail captured without delivery and initial queues synchronous' => config('mail.default') === 'array' && config('queue.default') === 'sync',
            'Local storage and private document disk' => config('filesystems.default') === 'local' && config('filesystems.disks.documents.driver') === 'local' && config('filesystems.disks.documents.root') === storage_path('app/private/documents'),
            'Secure staging-specific session cookie' => config('session.secure') === true && config('session.cookie') === 'faydamed_staging_session',
            'Application encryption key configured' => is_string(config('app.key')) && strlen(config('app.key')) >= 32,
        ];
        foreach ($checks as $label => $passed) {
            $this->line(($passed ? 'PASS: ' : 'FAIL: ').$label);
        }
        $this->warn('Configuration only: database grants, empty data, private vhosts/storage, TLS, egress controls, and restore must be verified on the host. No data was changed.');
        return in_array(false, $checks, true) ? self::FAILURE : self::SUCCESS;
    }

    private function stagingUrl(?string $url): bool
    {
        $parts = parse_url($url ?? '');
        $host = strtolower($parts['host'] ?? '');
        return ($parts['scheme'] ?? '') === 'https'
            && preg_match('/^staging[-.]/', $host) === 1
            && !str_ends_with($host, '.invalid')
            && !isset($parts['user']) && !isset($parts['pass'])
            && !isset($parts['query']) && !isset($parts['fragment'])
            && empty($parts['path']);
    }
}
