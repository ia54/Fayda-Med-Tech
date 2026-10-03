<?php

namespace App\Console\Commands;

use App\Models\Document;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PrivatizeDocuments extends Command
{
    protected $signature = 'documents:privatize {--apply : Copy and verify legacy files, then update records} {--remove-public : Remove verified public copies after backup and host access controls are confirmed}';
    protected $description = 'Inventory or migrate legacy document files to private storage; dry run by default';

    public function handle(): int
    {
        if ($this->option('remove-public') && !$this->option('apply')) {
            $this->error('--remove-public requires --apply.'); return self::FAILURE;
        }
        $failed = false;
        Document::withoutGlobalScopes()->where('path', 'like', 'documents/uploads/%')->orderBy('id')->chunkById(100, function ($documents) use (&$failed) {
            foreach ($documents as $document) {
                try {
                    $public = Storage::disk('public'); $private = Storage::disk('documents');
                    if (!$public->exists($document->path)) {
                        if ($document->storage_disk === 'public') { throw new \RuntimeException('Missing legacy file'); }
                        continue;
                    }
                    if (!$this->option('apply')) { $this->line('Pending document '.$document->id); continue; }
                    $bytes = $public->get($document->path);
                    if (!$private->exists($document->path)) { $private->put($document->path, $bytes); }
                    if (!hash_equals(hash('sha256', $bytes), hash('sha256', $private->get($document->path)))) {
                        throw new \RuntimeException('Checksum mismatch; original retained');
                    }
                    $document->update(['storage_disk' => 'documents', 'url' => null]);
                    if ($this->option('remove-public') && !$public->delete($document->path)) {
                        throw new \RuntimeException('Unable to remove public copy');
                    }
                    $this->info('Verified document '.$document->id);
                } catch (\Throwable $exception) {
                    $failed = true; $this->error('Document '.$document->id.': '.$exception->getMessage());
                }
            }
        });
        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
