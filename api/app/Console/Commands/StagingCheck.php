<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class StagingCheck extends Command
{
    protected $signature = 'staging:check {--loopback : Validate HTTP origins for an SSH-only loopback deployment}';
    protected $description = 'Read-only configuration checks before synthetic staging acceptance';

    public function handle(): int
    {
        $frontend = config('app.frontend_url');
        $database = config('database.connections.mysql', []);
        $loopback = (bool) $this->option('loopback');
        $origins = array_values(array_filter([$frontend, config('app.public_url')]));
        $allowed = config('cors.allowed_origins', []);
        sort($origins);
        sort($allowed);
        $validOrigin = fn ($url) => $loopback ? $this->loopbackUrl($url) : $this->stagingUrl($url);
        $validUrls = $validOrigin(config('app.url')) && $validOrigin($frontend)
            && config('app.url') !== $frontend
            && (!config('app.public_url') || ($validOrigin(config('app.public_url'))
                && !in_array(config('app.public_url'), [config('app.url'), $frontend], true)));
        $checks = [
            'Staging environment and debug disabled' => config('app.env') === 'staging' && config('app.debug') === false,
            ($loopback ? 'Explicit loopback staging origins' : 'Explicit HTTPS staging API and frontend origins') => $validUrls,
            'CORS restricted to the declared staging frontends' => $allowed === $origins && config('cors.allowed_origins_patterns') === [],
            'Dedicated named MySQL database and non-root account configured' => config('database.default') === 'mysql' && empty($database['url']) && str_ends_with($database['database'] ?? '', '_staging') && str_ends_with($database['username'] ?? '', '_staging') && !empty($database['password']),
            'Mail captured without delivery and initial queues synchronous' => config('mail.default') === 'array' && config('queue.default') === 'sync',
            'Local storage and private document disk' => config('filesystems.default') === 'local' && config('filesystems.disks.documents.driver') === 'local' && config('filesystems.disks.documents.root') === storage_path('app/private/documents'),
            'Session cookie restricted to the selected staging mode' => config('session.secure') === !$loopback && config('session.cookie') === 'faydamed_staging_session',
            'Application encryption key configured' => is_string(config('app.key')) && strlen(config('app.key')) >= 32,
        ];
        foreach ($checks as $label => $passed) {
            $this->line(($passed ? 'PASS: ' : 'FAIL: ').$label);
        }
        $this->warn('Configuration only: database grants, empty data, private vhosts/storage, TLS, egress controls, and restore must be verified on the host. No data was changed.');
        return in_array(false, $checks, true) ? self::FAILURE : self::SUCCESS;
    }

    private function loopbackUrl(?string $url): bool
    {
        $parts = parse_url($url ?? '');
        return ($parts['scheme'] ?? '') === 'http'
            && ($parts['host'] ?? '') === '127.0.0.1'
            && ($parts['port'] ?? 0) >= 1024 && ($parts['port'] ?? 0) <= 65535
            && !isset($parts['user']) && !isset($parts['pass'])
            && !isset($parts['query']) && !isset($parts['fragment']) && empty($parts['path']);
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
