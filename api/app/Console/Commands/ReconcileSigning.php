<?php
namespace App\Console\Commands;

use App\Exceptions\SigningRecoveryException;
use App\Models\{Document, User};
use App\Services\{DocuSignService, SigningRecovery};
use Illuminate\Console\Command;
use Throwable;

class ReconcileSigning extends Command
{
    protected $signature = 'signing:reconcile {document : Local document ID} {--apply : Link a verified existing envelope; never send or clear a guard} {--envelope= : Exact envelope ID from the read-only check} {--actor= : Active authorized administrator user ID} {--reason= : Short audit reason without private details}';
    protected $description = 'Inspect an uncertain signing dispatch or explicitly link a verified provider envelope';

    public function handle(SigningRecovery $recovery, DocuSignService $provider): int
    {
        if (!ctype_digit((string)$this->argument('document'))) { $this->error('A numeric document ID is required.'); return self::FAILURE; }
        $document=Document::withoutGlobalScopes()->whereNull('deleted_at')->find($this->argument('document'));
        if (!$document) { $this->error('Document not found.'); return self::FAILURE; }
        $actor=null;
        if ($this->option('apply')) {
            $actor=ctype_digit((string)$this->option('actor')) ? User::withoutGlobalScopes()->find($this->option('actor')) : null;
            if (!$actor || $actor->status!=='active' || !in_array($actor->role,['admin','firm_admin'],true) || ($actor->role!=='admin' && $actor->organization_id!==$document->organization_id)
                || !$this->option('envelope') || !is_string($this->option('reason')) || trim($this->option('reason'))==='' || strlen($this->option('reason'))>300) {
                $this->error('Apply requires an authorized active administrator, exact envelope ID and a short audit reason.'); return self::FAILURE;
            }
        }
        if ($document->docusign_envelope_id) {
            if ($this->option('apply') && $this->option('envelope')!==$document->docusign_envelope_id) { $this->error('The document is linked to a different envelope.'); return self::FAILURE; }
            $this->info('Document already linked; no changes made.'); return self::SUCCESS;
        }
        try {
            $candidate=$recovery->inspect($document,$provider);
            $this->line('Document '.$document->id.'; verified envelope '.$candidate['envelope_id'].'; provider status '.$candidate['status'].'.');
            if (!$this->option('apply')) { $this->info('Read-only check complete. No local records changed and no signature request sent.'); return self::SUCCESS; }
            $recovery->apply($document,$candidate,$actor,$this->option('envelope'),$this->option('reason'));
            $this->info('Verified envelope linked and recovery audit saved. Send guard retained.'); return self::SUCCESS;
        } catch (SigningRecoveryException $exception) {
            $this->error($exception->getMessage()); return self::FAILURE;
        } catch (Throwable $exception) {
            $this->error('Recovery could not be verified. Check account configuration and provider availability. The send guard remains.'); return self::FAILURE;
        }
    }
}
