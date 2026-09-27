<?php

namespace Tests\Feature;

use App\Http\Middleware\TwoFactorMiddleware;
use App\Models\CaseModel;
use App\Models\CaseParty;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Passport\Token;
use Tests\TestCase;

class PharmacyWorkflowTest extends TestCase
{
    private User $actor;

    private User $patient;

    private CaseModel $case;

    private int $location;

    private int $otherLocation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(TwoFactorMiddleware::class);
        Artisan::call('migrate', ['--force' => true]);
        foreach ([1, 2] as $id) {
            DB::table('organizations')->insert(['id' => $id, 'org_name' => 'Synthetic pharmacy '.$id, 'org_type' => 'provider', 'subscription_plan' => 'test', 'email' => "pharm$id@example.invalid"]);
        }
        $this->actor = User::create(['first_name' => 'Synthetic', 'last_name' => 'Pharmacist', 'email' => 'pharmacist@example.invalid', 'password' => 'synthetic-only', 'role' => 'pharmacist', 'organization_id' => 1, 'status' => 'active']);
        $this->actor->withAccessToken(new Token(['expires_at' => now()->addHour()]));
        $this->actingAs($this->actor, 'api');
        $this->patient = User::create(['first_name' => 'Synthetic', 'last_name' => 'Patient', 'email' => 'patient@example.invalid', 'password' => 'synthetic-only', 'role' => 'client', 'organization_id' => 1, 'status' => 'active']);
        $this->case = CaseModel::withoutEvents(fn () => CaseModel::create(['organization_id' => 1, 'case_number' => 'PHARM-SYN', 'title' => 'Synthetic pharmacy case', 'accident_date' => now()->subDays(30)->toDateString(), 'created_by' => $this->actor->id]));
        CaseParty::create(['case_id' => $this->case->id, 'user_id' => $this->patient->id, 'role_in_case' => 'client']);
        $this->location = DB::table('pharmacy_locations')->insertGetId(['organization_id' => 1, 'name' => 'Synthetic location A', 'address' => 'Synthetic only', 'license_reference' => 'NOT VALID']);
        $this->otherLocation = DB::table('pharmacy_locations')->insertGetId(['organization_id' => 1, 'name' => 'Synthetic location B', 'address' => 'Synthetic only', 'license_reference' => 'NOT VALID']);
        foreach ([$this->location, $this->otherLocation] as $id) {
            DB::table('pharmacy_staff_assignments')->insert(['location_id' => $id, 'user_id' => $this->actor->id, 'active' => true, 'valid_until' => now()->addYear()->toDateString()]);
        }
    }

    public function test_original_source_files_are_private_immutable_and_location_scoped(): void
    {
        \Illuminate\Support\Facades\Storage::fake('documents');
        $rx = $this->rx();
        $url = "/api/pharmacy/prescriptions/$rx/sources";
        $bytes = "%PDF-1.4\n% SYNTHETIC ONLY\n%%EOF";
        $body = ['request_id' => (string) Str::uuid(), 'reference' => 'Synthetic received prescription'];
        $send = fn ($text = null) => $this->post($url, $body + ['file' => \Illuminate\Http\UploadedFile::fake()->createWithContent('synthetic.pdf', $text ?? $bytes)], ['Accept' => 'application/json']);
        $id = $send()->assertCreated()->json('data.id');
        $send()->assertOk()->assertJsonPath('data.id', $id);
        $send($bytes.'changed')->assertStatus(409);
        $this->assertSame(1, DB::table('pharmacy_source_documents')->count());
        $this->assertSame(1, DB::table('pharmacy_events')->where('action', 'source_document_added')->count());
        $record = DB::table('pharmacy_source_documents')->first();
        $this->getJson($url)->assertOk()->assertJsonPath('data.0.sha256', hash('sha256', $bytes))->assertJsonMissingPath('data.0.path');
        $response = $this->get("$url/$id/file")->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertSame($bytes, $response->streamedContent());
        $this->getJson("$url/999999/file")->assertNotFound();
        $otherRx = $this->rx();
        $this->getJson("/api/pharmacy/prescriptions/$otherRx/sources/$id/file")->assertNotFound();
        $this->deleteJson("$url/$id")->assertNotFound();
        $this->putJson("$url/$id", ['reference' => 'overwrite'])->assertNotFound();
        $this->actor->role = 'medical_biller'; $this->actor->save();
        $this->getJson($url)->assertForbidden();
        $this->getJson("$url/$id/file")->assertForbidden();
        $send()->assertForbidden();
        $this->actor->role = 'pharmacy_technician'; $this->actor->save();
        $this->getJson($url)->assertOk();
        $send()->assertOk();
        DB::table('pharmacy_staff_assignments')->where('location_id', $this->location)->update(['active' => false]);
        $this->getJson($url)->assertNotFound();
        $this->getJson("$url/$id/file")->assertNotFound();
        $send()->assertNotFound();
        DB::table('pharmacy_staff_assignments')->where('location_id', $this->location)->update(['active' => true]);
        $this->actor->organization_id = 2; $this->actor->save();
        $this->getJson($url)->assertNotFound();
        $this->actor->organization_id = 1; $this->actor->save();
        \Illuminate\Support\Facades\Storage::disk('documents')->put($record->path, 'corrupted');
        $this->getJson("$url/$id/file")->assertStatus(409);
        \Illuminate\Support\Facades\Storage::disk('documents')->delete($record->path);
        $this->getJson("$url/$id/file")->assertNotFound();
    }

    public function test_source_file_validation_and_transaction_cleanup(): void
    {
        \Illuminate\Support\Facades\Storage::fake('documents');
        $rx = $this->rx();
        $url = "/api/pharmacy/prescriptions/$rx/sources";
        $body = ['request_id' => (string) Str::uuid(), 'reference' => 'Synthetic'];
        $disguised = \Illuminate\Http\UploadedFile::fake()->createWithContent('fake.pdf', '<script>bad</script>');
        $this->post($url, $body + ['file' => new \Illuminate\Http\UploadedFile($disguised->getRealPath(), 'fake.pdf', null, null, true)], ['Accept' => 'application/json'])->assertStatus(422);
        $this->post($url, $body + ['file' => \Illuminate\Http\UploadedFile::fake()->create('large.pdf', 10241, 'application/pdf')], ['Accept' => 'application/json'])->assertStatus(422);
        $this->postJson($url, $body)->assertStatus(422);
        $this->assertSame([], \Illuminate\Support\Facades\Storage::disk('documents')->allFiles());
        // A failure after storing bytes must roll back both the row and the private file.
        DB::connection()->beforeExecuting(function ($query) {
            if (str_starts_with(strtolower($query), 'insert into') && str_contains($query, 'pharmacy_events')) {
                throw new \RuntimeException('Synthetic event write failure');
            }
        });
        $this->post($url, $body + ['file' => \Illuminate\Http\UploadedFile::fake()->createWithContent('synthetic.pdf', "%PDF-1.4\n%%EOF")], ['Accept' => 'application/json'])->assertStatus(500);
        $this->assertSame(0, DB::table('pharmacy_source_documents')->count());
        $this->assertSame([], \Illuminate\Support\Facades\Storage::disk('documents')->allFiles());
    }

    public function test_new_source_evidence_requires_fresh_review_before_preparation_or_handover(): void
    {
        \Illuminate\Support\Facades\Storage::fake('documents');
        $rx = $this->rx(); $lot = $this->lot();
        $f = $this->act($rx, $this->fill($rx, $lot), 'approve', $this->checks());
        $upload = fn () => $this->post("/api/pharmacy/prescriptions/$rx/sources", ['request_id' => (string) Str::uuid(), 'reference' => 'Synthetic evidence', 'file' => \Illuminate\Http\UploadedFile::fake()->createWithContent('synthetic.pdf', "%PDF-1.4\n%%EOF")], ['Accept' => 'application/json'])->assertCreated();
        $upload();
        $this->act($rx, $f, 'ready', ['checks' => ['label' => true]], 422);
        $f = $this->act($rx, $f, 'approve', $this->checks());
        $f = $this->act($rx, $f, 'ready', ['checks' => ['label' => true]]);
        $upload();
        $this->act($rx, $f, 'collected', ['occurred_on' => now()->toDateString(), 'reference' => 'Synthetic handover', 'counseling' => 'provided'], 422);
        $this->assertEquals(50, DB::table('pharmacy_stock_lots')->where('id', $lot)->value('on_hand'));
        $this->act($rx, $f, 'cancel');
        $this->assertEquals(0, DB::table('pharmacy_stock_lots')->where('id', $lot)->value('reserved'));
    }

    private function receivedDiscrepancy(User $receiver, string $received = '9.125'): array
    {
        $this->actingAs($this->actor, 'api'); $source = $this->lot();
        $id = $this->postJson('/api/pharmacy/stock-transfers', $this->transferBody($source))->assertCreated()->json('data.id');
        $this->postJson("/api/pharmacy/stock-transfers/$id/actions", $this->transferAction($id, 'dispatch'))->assertOk();
        $this->actingAs($receiver, 'api');
        $destination = $this->postJson("/api/pharmacy/stock-transfers/$id/actions", $this->transferAction($id, 'receive', ['received_quantity' => $received]))->assertOk()->json('data.destination_lot_id');
        return [$id, $source, $destination];
    }

    private function correctedReceiptCount(int $lot, User $receiver): int
    {
        $this->actingAs($receiver, 'api');
        $count = $this->postJson("/api/pharmacy/stock/$lot/counts", $this->stockCountBody($lot, ['counted_quantity' => '10.125']))->assertCreated()->json('data.counts.data.0.id');
        $this->actingAs($this->actor, 'api');
        $this->postJson("/api/pharmacy/stock/$lot/counts/$count/review", ['decision' => 'apply', 'evidence' => 'SYNTHETIC independently verified physical quantity'])->assertOk();
        return $count;
    }

    private function correctionBody(int $transfer, int $count): array
    {
        return ['request_id' => (string) Str::uuid(), 'version' => (int) DB::table('pharmacy_stock_transfers')->where('id', $transfer)->value('version'), 'stock_count_id' => $count, 'evidence' => 'SYNTHETIC receipt-entry error, physical count matches original dispatch; no new stock arrived'];
    }

    public function test_receipt_correction_retains_originals_requires_source_review_and_keeps_quarantine(): void
    {
        $receiver = $this->receivingPharmacist();
        foreach (['0', '9.125', '11.125'] as $received) {
            [$id, $source, $dest] = $this->receivedDiscrepancy($receiver, $received);
            $this->getJson("/api/pharmacy/stock/$dest")->assertOk()->assertJsonPath('data.custody_hold', 'Stock custody or receipt discrepancy requires reconciliation before release or use.');
            $count = $this->correctedReceiptCount($dest, $receiver);
            $this->actingAs($receiver, 'api'); $body = $this->correctionBody($id, $count); $url = "/api/pharmacy/stock-transfers/$id/corrections";
            $c = $this->postJson($url, $body)->assertCreated()->assertJsonPath('data.correction_pending', true)->json('data.corrections.data.0.id');
            $this->postJson($url, $body)->assertOk();
            $this->postJson($url, array_replace($body, ['evidence' => 'Changed']))->assertStatus(409);
            $this->postJson($url, $this->correctionBody($id, $count))->assertStatus(409);
            $review = ['request_id' => (string) Str::uuid(), 'version' => 4, 'decision' => 'apply', 'evidence' => 'SYNTHETIC sending pharmacist confirms original dispatch and verified correction'];
            $this->postJson("$url/$c/review", $review)->assertStatus(422);
            $this->actingAs($this->actor, 'api');
            $response = $this->postJson("$url/$c/review", $review)->assertOk()->assertJsonPath('data.status', 'received_corrected')->assertJsonPath('data.receipt_correction_id', $c);
            $this->assertSame(\App\Services\PharmacyStock::milli($received), \App\Services\PharmacyStock::milli($response->json('data.received_quantity')));
            $this->postJson("$url/$c/review", $review)->assertOk();
            $this->postJson("$url/$c/review", array_replace($review, ['decision' => 'reject']))->assertStatus(409);
            $this->getJson("/api/pharmacy/stock/$dest")->assertOk()->assertJsonPath('data.custody_hold', null)->assertJsonPath('data.status', 'quarantined');
            $this->assertEquals(39.875, DB::table('pharmacy_stock_lots')->where('id', $source)->value('on_hand'));
            $this->assertEquals(10.125, DB::table('pharmacy_stock_lots')->where('id', $dest)->value('on_hand'));
            $this->assertSame(1, DB::table('pharmacy_stock_events')->where('stock_lot_id', $dest)->where('action', 'transfer_receipt_corrected')->count());
            $this->putJson("/api/pharmacy/stock/$dest/status", ['version' => 3, 'status' => 'available', 'note' => 'SYNTHETIC separate release'])->assertOk();
            $rx = $this->rx(['location_id' => $this->otherLocation]); $f = $this->fill($rx, $dest);
            $this->recallStock($source)->assertOk();
            $this->getJson("/api/pharmacy/prescriptions/$rx")->assertOk()->assertJsonPath('data.fills.0.stock_custody_hold', 'Stock custody or recall history requires reconciliation before release or use.');
            $this->act($rx, $f, 'cancel');
        }
    }

    public function test_receipt_correction_rejects_unverified_counts_changed_stock_and_recall(): void
    {
        $receiver = $this->receivingPharmacist(); [$id, $source, $dest] = $this->receivedDiscrepancy($receiver);
        $url = "/api/pharmacy/stock-transfers/$id/corrections";
        $this->postJson($url, $this->correctionBody($id, 99999))->assertStatus(409);
        $count = $this->postJson("/api/pharmacy/stock/$dest/counts", $this->stockCountBody($dest, ['counted_quantity' => '9']))->assertCreated()->json('data.counts.data.0.id');
        $this->postJson($url, $this->correctionBody($id, $count))->assertStatus(409);
        $this->actingAs($this->actor, 'api');
        $this->postJson("/api/pharmacy/stock/$dest/counts/$count/review", ['decision' => 'apply', 'evidence' => 'SYNTHETIC shortage remains'])->assertOk();
        $this->postJson($url, $this->correctionBody($id, $count))->assertStatus(422);
        $count = $this->correctedReceiptCount($dest, $receiver);
        $this->actingAs($receiver, 'api');
        $c = $this->postJson($url, $this->correctionBody($id, $count))->assertCreated()->json('data.corrections.data.0.id');
        $this->actingAs($this->actor, 'api');
        $this->putJson("/api/pharmacy/stock/$dest/status", ['version' => 5, 'status' => 'quarantined', 'note' => 'SYNTHETIC changed stock state'])->assertOk();
        $review = ['request_id' => (string) Str::uuid(), 'version' => 4, 'decision' => 'apply', 'evidence' => 'SYNTHETIC'];
        $this->postJson("$url/$c/review", $review)->assertStatus(409);
        $this->postJson("$url/$c/review", array_replace($review, ['decision' => 'reject']))->assertOk()->assertJsonPath('data.status', 'received_discrepancy');
        $this->recallStock($source)->assertOk();
        // Even a fresh count cannot clear a source recall through receipt correction.
        $count = $this->postJson("/api/pharmacy/stock/$dest/counts", $this->stockCountBody($dest, ['counted_quantity' => '9']))->assertCreated()->json('data.counts.data.0.id');
        $this->actingAs($receiver, 'api');
        $this->postJson("/api/pharmacy/stock/$dest/counts/$count/review", ['decision' => 'apply', 'evidence' => 'SYNTHETIC'])->assertOk();
        $count = $this->correctedReceiptCount($dest, $receiver);
        $this->postJson($url, $this->correctionBody($id, $count))->assertStatus(422);
    }

    public function test_receipt_correction_permissions_and_audit_rollback(): void
    {
        $receiver = $this->receivingPharmacist(); [$id, $source, $dest] = $this->receivedDiscrepancy($receiver); $count = $this->correctedReceiptCount($dest, $receiver);
        $url = "/api/pharmacy/stock-transfers/$id/corrections"; $body = $this->correctionBody($id, $count);
        DB::table('pharmacy_staff_assignments')->where('user_id', $this->actor->id)->where('location_id', $this->otherLocation)->update(['active' => false]);
        $this->postJson($url, $body)->assertNotFound();
        $this->actingAs($receiver, 'api'); $receiver->role = 'pharmacy_technician'; $receiver->save();
        $this->postJson($url, $body)->assertForbidden(); $receiver->role = 'pharmacist'; $receiver->save();
        $fail = true;
        DB::connection()->beforeExecuting(function ($query) use (&$fail) {
            if ($fail && str_starts_with(strtolower($query), 'insert into') && str_contains($query, 'pharmacy_stock_transfer_events')) { throw new \RuntimeException('Synthetic correction audit failure'); }
        });
        $this->postJson($url, $body)->assertStatus(500);
        $this->assertSame(0, DB::table('pharmacy_transfer_corrections')->count());
        $this->assertEquals(3, DB::table('pharmacy_stock_transfers')->where('id', $id)->value('version'));
        $fail = false; $c = $this->postJson($url, $body)->assertCreated()->json('data.corrections.data.0.id');
        $review = ['request_id' => (string) Str::uuid(), 'version' => 4, 'decision' => 'apply', 'evidence' => 'SYNTHETIC'];
        DB::table('pharmacy_staff_assignments')->where('user_id', $receiver->id)->where('location_id', $this->location)->update(['active' => false]);
        $this->postJson("$url/$c/review", $review)->assertNotFound();
        $this->actingAs($this->actor, 'api'); $fail = true;
        $this->postJson("$url/$c/review", $review)->assertStatus(500);
        $this->assertSame('pending', DB::table('pharmacy_transfer_corrections')->value('status'));
        $this->assertSame('received_discrepancy', DB::table('pharmacy_stock_transfers')->value('status'));
        $this->assertSame(0, DB::table('pharmacy_stock_events')->where('action', 'transfer_receipt_corrected')->count());
        $fail = false; $this->postJson("$url/$c/review", $review)->assertOk();
        $this->actor->organization_id = 2; $this->actor->save();
        $this->getJson("/api/pharmacy/stock-transfers/$id")->assertNotFound();
        $this->postJson("$url/$c/review", $review)->assertNotFound();
    }

    private function transferBody(int $lot, array $changes = []): array
    {
        return array_replace(['request_id' => (string) Str::uuid(), 'source_lot_id' => $lot, 'destination_location_id' => $this->otherLocation, 'version' => (int) DB::table('pharmacy_stock_lots')->where('id', $lot)->value('version'), 'quantity' => '10.125', 'reference' => 'SYNTHETIC transfer'], $changes);
    }

    private function transferAction(int $id, string $action, array $changes = []): array
    {
        return array_replace(['request_id' => (string) Str::uuid(), 'version' => (int) DB::table('pharmacy_stock_transfers')->where('id', $id)->value('version'), 'action' => $action, 'evidence' => 'SYNTHETIC custody evidence'], $changes);
    }

    private function receivingPharmacist(): User
    {
        $reviewer = $this->independentReviewer();
        DB::table('pharmacy_staff_assignments')->insert(['location_id' => $this->otherLocation, 'user_id' => $reviewer->id, 'active' => true, 'valid_until' => now()->addYear()->toDateString()]);
        return $reviewer;
    }

    public function test_stock_transfer_custody_balances_independent_receipt_and_retries(): void
    {
        $lot = $this->lot(); $body = $this->transferBody($lot);
        $id = $this->postJson('/api/pharmacy/stock-transfers', $body)->assertCreated()->assertJsonPath('data.status', 'planned')->json('data.id');
        $this->postJson('/api/pharmacy/stock-transfers', $body)->assertOk()->assertJsonPath('data.id', $id);
        $this->postJson('/api/pharmacy/stock-transfers', array_replace($body, ['quantity' => '11']))->assertStatus(409);
        $this->assertEquals(50, DB::table('pharmacy_stock_lots')->where('id', $lot)->value('on_hand'));
        $this->assertEquals(10.125, DB::table('pharmacy_stock_lots')->where('id', $lot)->value('reserved'));
        $this->getJson('/api/pharmacy/stock-transfers')->assertOk()->assertJsonPath('data.data.0.product.medication', 'Synthetic medication');
        $url = "/api/pharmacy/stock-transfers/$id/actions";
        $this->postJson($url, $this->transferAction($id, 'receive', ['received_quantity' => '10.125']))->assertStatus(422);
        $dispatch = $this->transferAction($id, 'dispatch');
        $this->postJson($url, $dispatch)->assertOk()->assertJsonPath('data.status', 'dispatched');
        $this->postJson($url, $dispatch)->assertOk();
        $this->assertEquals(39.875, DB::table('pharmacy_stock_lots')->where('id', $lot)->value('on_hand'));
        $this->assertEquals(0, DB::table('pharmacy_stock_lots')->where('id', $lot)->value('reserved'));
        $this->assertSame(1, DB::table('pharmacy_stock_lots')->count());
        $this->postJson($url, $this->transferAction($id, 'cancel'))->assertStatus(422);
        $receipt = $this->transferAction($id, 'receive', ['received_quantity' => '10.125']);
        $this->postJson($url, $receipt)->assertStatus(422);
        $this->actingAs($this->receivingPharmacist(), 'api');
        $destination = $this->postJson($url, $receipt)->assertOk()->assertJsonPath('data.status', 'received')->json('data.destination_lot_id');
        $this->postJson($url, $receipt)->assertOk()->assertJsonPath('data.destination_lot_id', $destination);
        $this->postJson($url, array_replace($receipt, ['received_quantity' => '10']))->assertStatus(409);
        $this->assertSame(2, DB::table('pharmacy_stock_lots')->count());
        $this->assertSame(3, DB::table('pharmacy_stock_transfer_events')->count());
        $this->getJson("/api/pharmacy/stock/$destination")->assertOk()->assertJsonPath('data.status', 'quarantined');
        $this->putJson("/api/pharmacy/stock/$destination/status", ['version' => 1, 'status' => 'available', 'note' => 'SYNTHETIC independent review'])->assertOk();
        $this->assertEquals(50, DB::table('pharmacy_stock_lots')->sum('on_hand'));
    }

    public function test_stock_transfer_receipt_variances_remain_held_even_after_count_adjustment(): void
    {
        $reviewer = $this->receivingPharmacist();
        foreach (['0', '9.125', '11.125'] as $received) {
            $this->actingAs($this->actor, 'api');
            $lot = $this->lot();
            $id = $this->postJson('/api/pharmacy/stock-transfers', $this->transferBody($lot))->assertCreated()->json('data.id');
            $url = "/api/pharmacy/stock-transfers/$id/actions";
            $this->postJson($url, $this->transferAction($id, 'dispatch'))->assertOk();
            $this->actingAs($reviewer, 'api');
            $dest = $this->postJson($url, $this->transferAction($id, 'receive', ['received_quantity' => $received]))->assertOk()->assertJsonPath('data.status', 'received_discrepancy')->json('data.destination_lot_id');
            $this->assertSame(\App\Services\PharmacyStock::milli($received), \App\Services\PharmacyStock::milli(DB::table('pharmacy_stock_lots')->where('id', $dest)->value('on_hand')));
            $this->putJson("/api/pharmacy/stock/$dest/status", ['version' => 1, 'status' => 'available', 'note' => 'Synthetic'])->assertStatus(422);
            $count = $this->postJson("/api/pharmacy/stock/$dest/counts", $this->stockCountBody($dest, ['counted_quantity' => '10.125']))->assertCreated()->json('data.counts.data.0.id');
            $this->actingAs($this->actor, 'api');
            $this->postJson("/api/pharmacy/stock/$dest/counts/$count/review", ['decision' => 'apply', 'evidence' => 'SYNTHETIC'])->assertOk();
            $this->putJson("/api/pharmacy/stock/$dest/status", ['version' => 3, 'status' => 'available', 'note' => 'Synthetic'])->assertStatus(422);
            $this->postJson('/api/pharmacy/stock-transfers', $this->transferBody($dest, ['destination_location_id' => $this->location]))->assertStatus(422);
        }
    }

    public function test_stock_transfer_source_recalls_block_descendant_release_and_use(): void
    {
        $lot = $this->lot(); $reviewer = $this->receivingPharmacist();
        $id = $this->postJson('/api/pharmacy/stock-transfers', $this->transferBody($lot))->assertCreated()->json('data.id');
        $this->postJson("/api/pharmacy/stock-transfers/$id/actions", $this->transferAction($id, 'dispatch'))->assertOk();
        $this->actingAs($reviewer, 'api');
        $dest = $this->postJson("/api/pharmacy/stock-transfers/$id/actions", $this->transferAction($id, 'receive', ['received_quantity' => '10.125']))->assertOk()->json('data.destination_lot_id');
        $this->putJson("/api/pharmacy/stock/$dest/status", ['version' => 1, 'status' => 'available', 'note' => 'Synthetic'])->assertOk();
        $rx = $this->rx(['location_id' => $this->otherLocation]);
        $f = $this->act($rx, $this->fill($rx, $dest), 'approve', $this->checks());
        $this->actingAs($this->actor, 'api');
        $this->recallStock($lot)->assertOk();
        $this->act($rx, $f, 'ready', ['checks' => ['label' => true]], 422);
        $this->postJson("/api/pharmacy/prescriptions/$rx/fills", $this->fillBody($dest))->assertStatus(422);
        $this->putJson("/api/pharmacy/stock/$dest/status", ['version' => 3, 'status' => 'available', 'note' => 'Synthetic'])->assertStatus(422);
        $this->postJson('/api/pharmacy/stock-transfers', $this->transferBody($dest, ['destination_location_id' => $this->location, 'quantity' => '0.125']))->assertStatus(422);
        $this->act($rx, $f, 'cancel');
        $this->assertEquals(10.125, DB::table('pharmacy_stock_lots')->where('id', $dest)->value('on_hand'));
    }

    public function test_stock_transfer_scope_reservations_and_cancellation(): void
    {
        $lot = $this->lot(); $url = '/api/pharmacy/stock-transfers';
        $this->postJson($url, $this->transferBody($lot, ['destination_location_id' => (string) $this->location]))->assertStatus(422);
        $this->postJson($url, $this->transferBody($lot, ['quantity' => '50.001']))->assertStatus(422);
        $this->postJson($url, $this->transferBody($lot, ['quantity' => '1.0001']))->assertStatus(422);
        $this->postJson($url, $this->transferBody($lot, ['destination_location_id' => 99999]))->assertNotFound();
        $id = $this->postJson($url, $this->transferBody($lot))->assertCreated()->json('data.id');
        $this->postJson($url, $this->transferBody($lot, ['version' => 1]))->assertStatus(409);
        $this->postJson($url, $this->transferBody($lot, ['quantity' => '40']))->assertStatus(422);
        $this->actor->role = 'pharmacy_technician'; $this->actor->save();
        $this->getJson("$url/$id")->assertOk();
        $this->postJson("$url/$id/actions", $this->transferAction($id, 'dispatch'))->assertForbidden();
        $this->postJson($url, $this->transferBody($lot))->assertForbidden();
        $this->actor->role = 'pharmacist'; $this->actor->save();
        DB::table('pharmacy_staff_assignments')->where('user_id', $this->actor->id)->where('location_id', $this->location)->update(['active' => false]);
        $this->getJson("$url/$id")->assertOk(); // destination visibility does not grant source mutation
        $this->postJson("$url/$id/actions", $this->transferAction($id, 'dispatch'))->assertNotFound();
        DB::table('pharmacy_staff_assignments')->where('user_id', $this->actor->id)->update(['active' => false]);
        $this->getJson("$url/$id")->assertNotFound();
        $this->getJson($url)->assertOk()->assertJsonPath('data.total', 0);
        DB::table('pharmacy_staff_assignments')->where('user_id', $this->actor->id)->update(['active' => true]);
        $this->actor->organization_id = 2; $this->actor->save();
        $this->getJson("$url/$id")->assertNotFound();
        $this->actor->organization_id = 1; $this->actor->save();
        $this->recallStock($lot)->assertOk();
        $this->postJson("$url/$id/actions", $this->transferAction($id, 'dispatch'))->assertStatus(422);
        $cancel = $this->transferAction($id, 'cancel');
        $this->postJson("$url/$id/actions", $cancel)->assertOk()->assertJsonPath('data.status', 'cancelled');
        $this->postJson("$url/$id/actions", $cancel)->assertOk();
        $this->assertEquals(50, DB::table('pharmacy_stock_lots')->where('id', $lot)->value('on_hand'));
        $this->assertEquals(0, DB::table('pharmacy_stock_lots')->where('id', $lot)->value('reserved'));
    }

    public function test_stock_transfer_failed_audit_rolls_back_custody_and_receipt(): void
    {
        $lot = $this->lot();
        $id = $this->postJson('/api/pharmacy/stock-transfers', $this->transferBody($lot))->assertCreated()->json('data.id');
        $fail = true;
        DB::connection()->beforeExecuting(function ($query) use (&$fail) {
            if ($fail && str_starts_with(strtolower($query), 'insert into') && str_contains($query, 'pharmacy_stock_transfer_events')) { throw new \RuntimeException('Synthetic audit failure'); }
        });
        $url = "/api/pharmacy/stock-transfers/$id/actions";
        $dispatch = $this->transferAction($id, 'dispatch');
        $this->postJson($url, $dispatch)->assertStatus(500);
        $this->assertEquals(50, DB::table('pharmacy_stock_lots')->where('id', $lot)->value('on_hand'));
        $this->assertSame('planned', DB::table('pharmacy_stock_transfers')->value('status'));
        $fail = false; $this->postJson($url, $dispatch)->assertOk();
        $this->actingAs($this->receivingPharmacist(), 'api'); $fail = true;
        $receipt = $this->transferAction($id, 'receive', ['received_quantity' => '10.125']);
        $this->postJson($url, $receipt)->assertStatus(500);
        $this->assertSame(1, DB::table('pharmacy_stock_lots')->count());
        $this->assertSame('dispatched', DB::table('pharmacy_stock_transfers')->value('status'));
        $fail = false; $this->postJson($url, $receipt)->assertOk();
        $this->assertSame(2, DB::table('pharmacy_stock_lots')->count());
    }

    private function stockCountBody(int $lot, array $changes = []): array
    {
        return array_replace(['request_id' => (string) Str::uuid(), 'version' => (int) DB::table('pharmacy_stock_lots')->where('id', $lot)->value('version'), 'counted_quantity' => '49.875', 'reason' => 'physical_count', 'evidence' => 'SYNTHETIC count evidence'], $changes);
    }

    public function test_medication_count_quarantines_and_requires_independent_review(): void
    {
        $lot = $this->lot(); $url = "/api/pharmacy/stock/$lot/counts"; $body = $this->stockCountBody($lot);
        $result = $this->postJson($url, $body)->assertCreated()->assertJsonPath('data.count_pending', true)->assertJsonPath('data.status', 'quarantined');
        $id = $result->json('data.counts.data.0.id'); $this->assertEquals(50, $result->json('data.on_hand'));
        $this->postJson($url, $body)->assertOk()->assertJsonPath('data.counts.total', 1);
        $this->postJson($url, array_replace($body, ['counted_quantity' => '49']))->assertStatus(409);
        $this->postJson($url, $this->stockCountBody($lot))->assertStatus(409);
        foreach (['available', 'quarantined'] as $status) {
            $this->putJson("/api/pharmacy/stock/$lot/status", ['version' => 2, 'status' => $status, 'note' => 'Synthetic'])->assertStatus(422);
        }
        $rx = $this->rx(); $this->postJson("/api/pharmacy/prescriptions/$rx/fills", $this->fillBody($lot))->assertStatus(422);
        $review = ['decision' => 'apply', 'evidence' => 'SYNTHETIC independent count verification'];
        $this->postJson("$url/$id/review", $review)->assertStatus(422);
        $this->actingAs($this->independentReviewer(), 'api');
        $result = $this->postJson("$url/$id/review", $review)->assertOk()->assertJsonPath('data.count_pending', false)->assertJsonPath('data.counts.data.0.status', 'applied')->assertJsonPath('data.status', 'quarantined');
        $this->assertEquals(49.875, $result->json('data.on_hand'));
        $this->postJson("$url/$id/review", $review)->assertStatus(409);
        $this->assertEquals(-0.125, DB::table('pharmacy_stock_events')->where('action', 'count_adjustment_applied')->value('quantity'));
        $this->assertSame(1, DB::table('pharmacy_stock_events')->where('action', 'count_adjustment_applied')->count());
        $this->putJson("/api/pharmacy/stock/$lot/status", ['version' => 3, 'status' => 'available', 'note' => 'Synthetic separate release'])->assertOk();
    }

    public function test_medication_count_protects_reservations_and_requires_fresh_count_after_cancellation(): void
    {
        $lot = $this->lot(); $rx = $this->rx(); $fill = $this->fill($rx, $lot);
        $url = "/api/pharmacy/stock/$lot/counts";
        $id = $this->postJson($url, $this->stockCountBody($lot, ['counted_quantity' => '5']))->assertCreated()->json('data.counts.data.0.id');
        $reviewer = $this->independentReviewer(); $this->actingAs($reviewer, 'api');
        $review = ['decision' => 'apply', 'evidence' => 'Synthetic shortage review'];
        $this->postJson("$url/$id/review", $review)->assertStatus(422);
        $this->act($rx, $fill, 'cancel');
        $this->postJson("$url/$id/review", $review)->assertStatus(409);
        $this->postJson("$url/$id/review", array_replace($review, ['decision' => 'reject']))->assertOk()->assertJsonPath('data.counts.data.0.status', 'rejected');
        $this->assertEquals(50, DB::table('pharmacy_stock_lots')->where('id', $lot)->value('on_hand'));
        $fresh = $this->postJson($url, $this->stockCountBody($lot, ['counted_quantity' => '0']))->assertCreated()->json('data.counts.data.0.id');
        $this->actingAs($this->actor, 'api');
        $r = $this->postJson("$url/$fresh/review", $review)->assertOk()->assertJsonPath('data.counts.total', 2);
        $this->assertEquals(0, $r->json('data.on_hand')); $this->assertEquals(0, $r->json('data.reserved'));
    }

    public function test_medication_count_role_scope_and_validation(): void
    {
        $lot = $this->lot(); $url = "/api/pharmacy/stock/$lot/counts"; $body = $this->stockCountBody($lot);
        foreach (['-1', '1000000', '1.1234', '50'] as $quantity) {
            $this->postJson($url, array_replace($body, ['counted_quantity' => $quantity]))->assertStatus(422);
        }
        $this->postJson($url, array_replace($body, ['counted_quantity' => '51', 'reason' => 'observed_loss']))->assertStatus(422);
        $this->actor->role = 'medical_biller'; $this->actor->save(); $this->postJson($url, $body)->assertForbidden();
        $this->actor->role = 'admin'; $this->actor->save(); $this->postJson($url, $body)->assertForbidden();
        $this->actor->role = 'pharmacy_technician'; $this->actor->save();
        $id = $this->postJson($url, $body)->assertCreated()->json('data.counts.data.0.id');
        $review = ['decision' => 'apply', 'evidence' => 'Synthetic'];
        $this->postJson("$url/$id/review", $review)->assertForbidden();
        $this->actor->role = 'pharmacist'; $this->actor->save();
        DB::table('pharmacy_staff_assignments')->where('user_id', $this->actor->id)->update(['active' => false]);
        $this->postJson($url, $body)->assertNotFound(); $this->postJson("$url/$id/review", $review)->assertNotFound();
        $this->actor->organization_id = 2; $this->actor->save();
        $this->postJson($url, $body)->assertNotFound(); $this->postJson("$url/$id/review", $review)->assertNotFound();
    }

    public function test_medication_count_cannot_adjust_recalled_stock(): void
    {
        $lot = $this->lot(); $url = "/api/pharmacy/stock/$lot/counts";
        $id = $this->postJson($url, $this->stockCountBody($lot))->assertCreated()->json('data.counts.data.0.id');
        $this->recallStock($lot)->assertOk();
        $this->actingAs($this->independentReviewer(), 'api');
        $review = ['decision' => 'apply', 'evidence' => 'Synthetic'];
        $this->postJson("$url/$id/review", $review)->assertStatus(409);
        $this->postJson("$url/$id/review", array_replace($review, ['decision' => 'reject']))->assertOk()->assertJsonPath('data.status', 'recalled');
        $this->postJson($url, $this->stockCountBody($lot))->assertStatus(422);
        $this->assertEquals(50, DB::table('pharmacy_stock_lots')->where('id', $lot)->value('on_hand'));
    }

    public function test_medication_count_and_adjustment_roll_back_without_audit_event(): void
    {
        $lot = $this->lot(); $url = "/api/pharmacy/stock/$lot/counts"; $fail = true;
        DB::connection()->beforeExecuting(function ($query) use (&$fail) {
            if ($fail && str_starts_with(strtolower($query), 'insert into') && str_contains($query, 'pharmacy_stock_events')) { throw new \RuntimeException('Synthetic audit failure'); }
        });
        $this->postJson($url, $this->stockCountBody($lot))->assertStatus(500);
        $this->assertSame(0, DB::table('pharmacy_stock_counts')->count());
        $this->assertSame('available', DB::table('pharmacy_stock_lots')->where('id', $lot)->value('status'));
        $fail = false; $id = $this->postJson($url, $this->stockCountBody($lot))->assertCreated()->json('data.counts.data.0.id');
        $this->actingAs($this->independentReviewer(), 'api'); $fail = true;
        $this->postJson("$url/$id/review", ['decision' => 'apply', 'evidence' => 'Synthetic'])->assertStatus(500);
        $this->assertSame('pending', DB::table('pharmacy_stock_counts')->where('id', $id)->value('status'));
        $this->assertEquals(50, DB::table('pharmacy_stock_lots')->where('id', $lot)->value('on_hand'));
        $this->assertSame(0, DB::table('pharmacy_stock_events')->where('action', 'count_adjustment_applied')->count());
    }

    private function recallStock(int $lot, array $changes = [])
    {
        return $this->postJson("/api/pharmacy/stock/$lot/recall", array_replace(['request_id' => (string) Str::uuid(), 'version' => (int) DB::table('pharmacy_stock_lots')->where('id', $lot)->value('version'), 'reference' => 'SYNTHETIC recall notice', 'evidence' => 'Synthetic product and lot match'], $changes));
    }

    public function test_stock_recall_blocks_use_preserves_prior_handover_and_allows_explicit_release(): void
    {
        $lot = $this->lot(); $oldRx = $this->rx();
        $done = $this->complete($oldRx, $this->fill($oldRx, $lot));
        $rx = $this->rx(); $f = $this->act($rx, $this->fill($rx, $lot), 'approve', $this->checks());
        $f = $this->act($rx, $f, 'ready', ['checks' => ['label' => true]]);
        $key = (string) Str::uuid(); $version = (int) DB::table('pharmacy_stock_lots')->where('id', $lot)->value('version');
        $body = ['request_id' => $key, 'version' => $version];
        $result = $this->recallStock($lot, $body)->assertOk()->assertJsonPath('data.status', 'recalled')->assertJsonPath('data.trace.total', 2)->assertJsonMissingPath('data.recall_request_id');
        $this->assertEquals(40, $result->json('data.on_hand')); $this->assertEquals(10, $result->json('data.reserved'));
        $this->recallStock($lot, $body)->assertOk();
        $this->recallStock($lot, $body + ['evidence' => 'changed'])->assertStatus(409);
        $this->recallStock($lot)->assertStatus(409);
        $this->assertSame(1, DB::table('pharmacy_stock_events')->where('action', 'recall_hold_recorded')->count());
        $this->act($rx, $f, 'collected', ['occurred_on' => now()->toDateString(), 'reference' => 'Synthetic handover', 'counseling' => 'provided'], 422);
        $otherRx = $this->rx();
        $this->postJson("/api/pharmacy/prescriptions/$otherRx/fills", $this->fillBody($lot))->assertStatus(422);
        $this->putJson("/api/pharmacy/stock/$lot/status", ['version' => $version + 1, 'status' => 'available', 'note' => 'Synthetic release'])->assertStatus(422);
        $this->putJson("/api/pharmacy/stock/$lot/status", ['version' => $version + 1, 'status' => 'quarantined', 'note' => 'Synthetic downgrade'])->assertStatus(422);
        $this->act($rx, $f, 'cancel');
        $this->recallStock($lot, $body)->assertOk(); // exact retry survives later stock version changes
        $this->assertEquals(40, DB::table('pharmacy_stock_lots')->where('id', $lot)->value('on_hand'));
        $this->assertEquals(0, DB::table('pharmacy_stock_lots')->where('id', $lot)->value('reserved'));
        $this->assertSame('collected', DB::table('pharmacy_fills')->where('id', $done['id'])->value('fulfillment_status'));
        $this->getJson("/api/pharmacy/prescriptions/$rx")->assertOk()->assertJsonPath('data.fills.0.stock_recall_reference', 'SYNTHETIC recall notice')->assertJsonPath('data.fills.0.stock_status', 'recalled');
        $this->getJson("/api/pharmacy/stock/$lot")->assertOk()->assertJsonPath('data.trace.data.0.fulfillment_status', 'cancelled')->assertJsonPath('data.trace.data.1.fulfillment_status', 'collected');
        $this->getJson('/api/pharmacy/stock?status=recalled&search=SYNTHETIC%20recall')->assertOk()->assertJsonPath('data.total', 1)->assertJsonMissingPath('data.data.0.recall_evidence');
        $this->getJson('/api/pharmacy/stock?status=available')->assertOk()->assertJsonPath('data.total', 0);
    }

    public function test_stock_recall_trace_access_and_stale_versions_are_enforced(): void
    {
        $lot = $this->lot(); $rx = $this->rx(); $f = $this->fill($rx, $lot);
        $this->recallStock($lot, ['version' => 1])->assertStatus(409);
        $this->actor->role = 'pharmacy_technician'; $this->actor->save();
        $this->getJson("/api/pharmacy/stock/$lot")->assertOk();
        $this->recallStock($lot)->assertForbidden();
        foreach (['medical_biller', 'admin', 'client'] as $role) {
            $this->actor->role = $role; $this->actor->save();
            $this->getJson("/api/pharmacy/stock/$lot")->assertForbidden();
            $this->recallStock($lot)->assertForbidden();
        }
        $this->actor->role = 'pharmacist'; $this->actor->save();
        DB::table('pharmacy_staff_assignments')->where('location_id', $this->location)->update(['active' => false]);
        $this->getJson("/api/pharmacy/stock/$lot")->assertNotFound(); $this->recallStock($lot)->assertNotFound();
        $this->getJson('/api/pharmacy/stock')->assertOk()->assertJsonPath('data.total', 0);
        DB::table('pharmacy_staff_assignments')->where('location_id', $this->location)->update(['active' => true]);
        $this->actor->organization_id = 2; $this->actor->save();
        $this->getJson("/api/pharmacy/stock/$lot")->assertNotFound(); $this->recallStock($lot)->assertNotFound();
        $this->actor->organization_id = 1; $this->actor->save();
        $this->getJson('/api/pharmacy/stock?status=invalid')->assertStatus(422);
        $this->getJson("/api/pharmacy/stock/$lot?fill_page=0")->assertStatus(422);
        $this->recallStock($lot)->assertOk();
        $f = $this->act($rx, $f, 'approve', $this->checks());
        $this->act($rx, $f, 'ready', ['checks' => ['label' => true]], 422);
        $this->deleteJson("/api/pharmacy/stock/$lot/recall")->assertStatus(405);
    }

    public function test_stock_trace_paginates_history_and_filters_inconsistent_location_links(): void
    {
        $lot = $this->lot(); $rx = $this->rx(); $this->fill($rx, $lot);
        $fill = (array) DB::table('pharmacy_fills')->first(); unset($fill['id']);
        for ($i = 2; $i <= 31; $i++) {
            DB::table('pharmacy_fills')->insert(array_replace($fill, ['fill_number' => $i, 'request_id' => (string) Str::uuid(), 'fulfillment_status' => 'cancelled']));
        }
        $other = $this->rx(['location_id' => $this->otherLocation]);
        DB::table('pharmacy_fills')->insert(array_replace($fill, ['prescription_id' => $other, 'request_id' => (string) Str::uuid()]));
        $this->getJson("/api/pharmacy/stock/$lot")->assertOk()->assertJsonPath('data.trace.total', 31)->assertJsonCount(30, 'data.trace.data')->assertJsonMissingPath('data.request_hash');
        $this->getJson("/api/pharmacy/stock/$lot?fill_page=2")->assertOk()->assertJsonCount(1, 'data.trace.data')->assertJsonPath('data.trace.data.0.fill_number', (int) $fill['fill_number']);
        for ($i = 0; $i < 31; $i++) {
            DB::table('pharmacy_stock_events')->insert(['stock_lot_id' => $lot, 'actor_id' => $this->actor->id, 'action' => 'synthetic_history', 'quantity' => '0.000', 'details' => '{}', 'created_at' => now()]);
        }
        $this->getJson("/api/pharmacy/stock/$lot?event_page=2")->assertOk()->assertJsonPath('data.events.total', 33)->assertJsonCount(3, 'data.events.data');
        $this->recallStock($lot)->assertOk();
        // A retained hold independently blocks use even if legacy status is inconsistent.
        DB::table('pharmacy_stock_lots')->where('id', $lot)->update(['status' => 'available']);
        $newRx = $this->rx();
        $this->postJson("/api/pharmacy/prescriptions/$newRx/fills", $this->fillBody($lot))->assertStatus(422);
    }

    public function test_stock_recall_rolls_back_if_audit_write_fails(): void
    {
        $lot = $this->lot();
        DB::connection()->beforeExecuting(function ($query) {
            if (str_starts_with(strtolower($query), 'insert into') && str_contains($query, 'pharmacy_stock_events')) {
                throw new \RuntimeException('Synthetic recall audit failure');
            }
        });
        $this->recallStock($lot)->assertStatus(500);
        $row = DB::table('pharmacy_stock_lots')->where('id', $lot)->first();
        $this->assertSame('available', $row->status); $this->assertNull($row->recall_reference); $this->assertSame(1, (int) $row->version);
    }

    private function noticeBody(array $changes = []): array
    {
        return array_replace(['request_id' => (string) Str::uuid(), 'product_description' => 'Synthetic medication', 'reference' => 'SYNTHETIC notice', 'evidence' => 'Synthetic verified product and lot scope; not a real recall', 'all_lots' => false, 'lot_number' => 'syn-lot', 'ndcs' => ['00000000000', '00000-0000-00']], $changes);
    }

    public function test_shared_recall_holds_existing_and_future_receipts_without_changing_balances(): void
    {
        $a = $this->lot(); $b = $this->lot(['location_id' => $this->otherLocation]);
        $other = $this->lot(['lot_number' => 'OTHER']);
        $rx = $this->rx(); $f = $this->fill($rx, $a);
        $f = $this->act($rx, $f, 'approve', $this->checks());
        $f = $this->act($rx, $f, 'ready', ['checks' => ['label' => true]]);
        $before = DB::table('pharmacy_stock_lots')->orderBy('id')->get()->toJson();
        $body = $this->noticeBody();
        $id = $this->postJson('/api/pharmacy/recall-notices', $body)->assertCreated()->assertJsonPath('data.stock.total', 2)->assertJsonPath('data.fills.total', 1)->json('data.id');
        $this->assertSame($before, DB::table('pharmacy_stock_lots')->orderBy('id')->get()->toJson());
        $this->postJson('/api/pharmacy/recall-notices', $body)->assertOk()->assertJsonPath('data.id', $id);
        $this->postJson('/api/pharmacy/recall-notices', array_replace($body, ['evidence' => 'changed']))->assertStatus(409);
        $this->assertSame(1, DB::table('pharmacy_recall_codes')->count());
        $this->getJson("/api/pharmacy/stock/$a")->assertOk()->assertJsonPath('data.recall_notices.0.id', $id);
        $this->act($rx, $f, 'collected', ['occurred_on' => now()->toDateString(), 'reference' => 'Synthetic', 'counseling' => 'provided'], 422);
        $this->postJson('/api/pharmacy/prescriptions/'.$this->rx(['location_id' => $this->otherLocation]).'/fills', $this->fillBody($b))->assertStatus(422);
        $this->act($rx, $f, 'cancel');
        $this->assertEquals(50, DB::table('pharmacy_stock_lots')->where('id', $a)->value('on_hand'));
        $this->assertEquals(0, DB::table('pharmacy_stock_lots')->where('id', $a)->value('reserved'));
        $future = $this->lot(['lot_number' => ' SYN-LOT ', 'ndc' => '00000000000']);
        $this->getJson("/api/pharmacy/stock/$future")->assertOk()->assertJsonPath('data.status', 'quarantined');
        $event = DB::table('pharmacy_stock_events')->where('stock_lot_id', $future)->first();
        $this->assertTrue(json_decode($event->details, true)['organization_recall_hold']);
        $this->putJson("/api/pharmacy/stock/$future/status", ['version' => 1, 'status' => 'available', 'note' => 'Synthetic release attempt'])->assertStatus(422);
        $this->getJson("/api/pharmacy/stock/$other")->assertOk()->assertJsonPath('data.custody_hold', null);
        $this->getJson("/api/pharmacy/recall-notices/$id")->assertOk()->assertJsonPath('data.stock.total', 3)->assertJsonPath('data.fills.data.0.fulfillment_status', 'cancelled');
    }

    public function test_shared_recall_access_scopes_affected_records_and_preserves_org_isolation(): void
    {
        $a = $this->lot(); $b = $this->lot(['location_id' => $this->otherLocation]);
        $this->fill($this->rx(), $a); $this->fill($this->rx(['location_id' => $this->otherLocation]), $b);
        $body = $this->noticeBody();
        $id = $this->postJson('/api/pharmacy/recall-notices', $body)->assertCreated()->json('data.id');
        DB::table('pharmacy_staff_assignments')->where('location_id', $this->otherLocation)->update(['active' => false]);
        $this->getJson("/api/pharmacy/recall-notices/$id")->assertOk()->assertJsonPath('data.stock.total', 1)->assertJsonPath('data.stock.data.0.id', $a)->assertJsonPath('data.fills.total', 1)->assertJsonMissingPath('data.request_hash');
        $this->actor->role = 'pharmacy_technician'; $this->actor->save();
        $this->getJson('/api/pharmacy/recall-notices')->assertOk()->assertJsonPath('data.total', 1);
        $this->postJson('/api/pharmacy/recall-notices', $body)->assertForbidden();
        foreach (['admin', 'medical_biller', 'client'] as $role) {
            $this->actor->role = $role; $this->actor->save();
            $this->getJson("/api/pharmacy/recall-notices/$id")->assertForbidden();
        }
        $this->actor->role = 'pharmacist'; $this->actor->save();
        DB::table('pharmacy_staff_assignments')->update(['active' => false]);
        $this->getJson('/api/pharmacy/recall-notices')->assertForbidden();
        $this->actor->organization_id = 2; $this->actor->save();
        $foreign = DB::table('pharmacy_locations')->insertGetId(['organization_id' => 2, 'name' => 'Synthetic foreign site', 'address' => 'Synthetic', 'license_reference' => 'NOT VALID']);
        DB::table('pharmacy_staff_assignments')->insert(['location_id' => $foreign, 'user_id' => $this->actor->id, 'active' => true, 'valid_until' => now()->addYear()->toDateString()]);
        $this->getJson("/api/pharmacy/recall-notices/$id")->assertNotFound();
        $this->getJson('/api/pharmacy/recall-notices')->assertOk()->assertJsonPath('data.total', 0);
        $foreignLot = $this->lot(['location_id' => $foreign]);
        $this->getJson("/api/pharmacy/stock/$foreignLot")->assertOk()->assertJsonPath('data.custody_hold', null)->assertJsonPath('data.status', 'available');
    }

    public function test_shared_recall_requires_explicit_ndc_variants_and_all_lot_scope(): void
    {
        $ten = $this->lot(['ndc' => '1234-5678-90']);
        $eleven = $this->lot(['ndc' => '01234-5678-90']);
        $different = $this->lot(['ndc' => '99999-9999-99']);
        $body = $this->noticeBody(['ndcs' => ['1234567890'], 'all_lots' => true, 'lot_number' => null]);
        $id = $this->postJson('/api/pharmacy/recall-notices', $body)->assertCreated()->assertJsonPath('data.stock.total', 1)->assertJsonPath('data.stock.data.0.id', $ten)->json('data.id');
        $this->getJson("/api/pharmacy/stock/$eleven")->assertOk()->assertJsonPath('data.custody_hold', null);
        $this->lot(['ndc' => '1234-5678-90', 'lot_number' => 'OTHER']);
        $this->getJson("/api/pharmacy/recall-notices/$id")->assertOk()->assertJsonPath('data.stock.total', 2);
        $this->postJson('/api/pharmacy/recall-notices', $this->noticeBody(['ndcs' => ['1234567890', '01234567890']]))->assertCreated()->assertJsonPath('data.stock.total', 2);
        $this->getJson("/api/pharmacy/stock/$different")->assertOk()->assertJsonPath('data.custody_hold', null);
        foreach ([['all_lots' => true], ['lot_number' => '  '], ['ndcs' => ['123']], ['ndcs' => []]] as $bad) {
            $this->postJson('/api/pharmacy/recall-notices', $this->noticeBody($bad))->assertStatus(422);
        }
        $this->getJson("/api/pharmacy/recall-notices/$id?stock_page=0")->assertStatus(422);
        $this->deleteJson("/api/pharmacy/recall-notices/$id")->assertStatus(405);
    }

    public function test_shared_recall_stops_dispatch_and_counts_but_retains_in_transit_receipts(): void
    {
        $lot = $this->lot();
        $planned = $this->postJson('/api/pharmacy/stock-transfers', $this->transferBody($lot))->assertCreated()->json('data.id');
        $sent = $this->postJson('/api/pharmacy/stock-transfers', $this->transferBody($lot))->assertCreated()->json('data.id');
        $this->postJson("/api/pharmacy/stock-transfers/$sent/actions", $this->transferAction($sent, 'dispatch'))->assertOk();
        $countLot = $this->lot();
        $count = $this->postJson("/api/pharmacy/stock/$countLot/counts", $this->stockCountBody($countLot))->assertCreated()->json('data.counts.data.0.id');
        $body = $this->noticeBody();
        $notice = $this->postJson('/api/pharmacy/recall-notices', $body)->assertCreated()->json('data.id');
        $this->postJson("/api/pharmacy/stock-transfers/$planned/actions", $this->transferAction($planned, 'dispatch'))->assertStatus(422);
        $this->postJson("/api/pharmacy/stock-transfers/$planned/actions", $this->transferAction($planned, 'cancel'))->assertOk();
        $this->postJson("/api/pharmacy/stock/$lot/counts", $this->stockCountBody($lot))->assertStatus(422);
        $this->actingAs($this->receivingPharmacist(), 'api');
        $this->postJson('/api/pharmacy/recall-notices', $body)->assertStatus(409);
        $dest = $this->postJson("/api/pharmacy/stock-transfers/$sent/actions", $this->transferAction($sent, 'receive', ['received_quantity' => '10.125']))->assertOk()->json('data.destination_lot_id');
        $this->getJson("/api/pharmacy/stock/$dest")->assertOk()->assertJsonPath('data.status', 'quarantined')->assertJsonPath('data.recall_notices.0.id', $notice);
        $this->putJson("/api/pharmacy/stock/$dest/status", ['version' => 1, 'status' => 'available', 'note' => 'Synthetic'])->assertStatus(422);
        $this->postJson("/api/pharmacy/stock/$countLot/counts/$count/review", ['decision' => 'apply', 'evidence' => 'Synthetic'])->assertStatus(409);
        $this->postJson("/api/pharmacy/stock/$countLot/counts/$count/review", ['decision' => 'reject', 'evidence' => 'Held by recall'])->assertOk();
        $this->getJson("/api/pharmacy/recall-notices/$notice")->assertOk()->assertJsonPath('data.stock.total', 3);
        $this->assertEquals(100, DB::table('pharmacy_stock_lots')->sum('on_hand'));
        $this->assertEquals(0, DB::table('pharmacy_stock_lots')->sum('reserved'));
    }

    public function test_shared_recall_trace_paginates_without_duplicate_aliases_or_inconsistent_fills(): void
    {
        $lot = $this->lot(); $rx = $this->rx(); $this->fill($rx, $lot);
        $fill = (array) DB::table('pharmacy_fills')->first(); unset($fill['id']);
        for ($i = 2; $i <= 27; $i++) {
            DB::table('pharmacy_fills')->insert(array_replace($fill, ['fill_number' => $i, 'request_id' => (string) Str::uuid(), 'fulfillment_status' => 'cancelled']));
            $this->lot();
        }
        $other = $this->rx(['location_id' => $this->otherLocation]);
        DB::table('pharmacy_fills')->insert(array_replace($fill, ['prescription_id' => $other, 'request_id' => (string) Str::uuid()]));
        $id = $this->postJson('/api/pharmacy/recall-notices', $this->noticeBody())->assertCreated()->assertJsonPath('data.stock.total', 27)->assertJsonPath('data.fills.total', 27)->assertJsonCount(25, 'data.stock.data')->assertJsonCount(25, 'data.fills.data')->json('data.id');
        $this->getJson("/api/pharmacy/recall-notices/$id?stock_page=2&fill_page=2")->assertOk()->assertJsonCount(2, 'data.stock.data')->assertJsonCount(2, 'data.fills.data');
    }

    public function test_shared_recall_registration_rolls_back_if_code_write_fails(): void
    {
        DB::connection()->beforeExecuting(function ($query) {
            if (str_starts_with(strtolower($query), 'insert into') && str_contains($query, 'pharmacy_recall_codes')) {
                throw new \RuntimeException('Synthetic notice code failure');
            }
        });
        $this->postJson('/api/pharmacy/recall-notices', $this->noticeBody())->assertStatus(500);
        $this->assertSame(0, DB::table('pharmacy_recall_notices')->count());
        $this->assertSame(0, DB::table('pharmacy_recall_codes')->count());
    }

    private function followUpBody(array $changes = []): array
    {
        return array_replace(['request_id' => (string) Str::uuid(), 'version' => 0, 'action' => 'assessment', 'occurred_on' => now()->toDateString(), 'note' => 'Synthetic pharmacist follow-up evidence', 'evidence' => 'SYNTHETIC QA ONLY'], $changes);
    }

    public function test_recall_follow_up_retains_facts_and_requires_pharmacist_completion_after_reservations_resolve(): void
    {
        $lot = $this->lot(); $rx = $this->rx(); $f = $this->fill($rx, $lot);
        $notice = $this->postJson('/api/pharmacy/recall-notices', $this->noticeBody())->assertCreated()->json('data.id');
        $url = "/api/pharmacy/recall-notices/$notice/fills/{$f['id']}/follow-up";
        $this->getJson($url)->assertOk()->assertJsonPath('data.status', 'not_started')->assertJsonPath('data.version', 0);
        $body = $this->followUpBody();
        $this->postJson("$url/events", $body)->assertCreated()->assertJsonPath('data.status', 'open')->assertJsonPath('data.version', 1);
        $this->postJson("$url/events", $body)->assertOk()->assertJsonPath('data.events.total', 1);
        $this->postJson("$url/events", array_replace($body, ['note' => 'changed']))->assertStatus(409);
        $this->postJson("$url/events", $this->followUpBody())->assertStatus(409);
        $this->postJson("$url/events", $this->followUpBody(['version' => 1, 'action' => 'complete']))->assertStatus(422);
        $this->actor->role = 'pharmacy_technician'; $this->actor->save();
        $attempt = $this->followUpBody(['version' => 1, 'action' => 'contact_attempt', 'method' => 'phone', 'note' => 'Synthetic call attempt only; no actual communication']);
        $this->postJson("$url/events", $attempt)->assertCreated()->assertJsonPath('data.version', 2)->assertJsonPath('data.status', 'open');
        foreach (['assessment', 'complete', 'reopen'] as $action) {
            $this->postJson("$url/events", $this->followUpBody(['version' => 2, 'action' => $action]))->assertForbidden();
        }
        $this->actor->role = 'pharmacist'; $this->actor->save(); $this->act($rx, $f, 'cancel');
        $stock = DB::table('pharmacy_stock_lots')->get()->toJson(); $fills = DB::table('pharmacy_fills')->get()->toJson(); $events = DB::table('pharmacy_stock_events')->get()->toJson();
        $complete = $this->followUpBody(['version' => 2, 'action' => 'complete', 'note' => 'Synthetic cancelled fill reviewed. No handover occurred.']);
        $this->postJson("$url/events", $complete)->assertCreated()->assertJsonPath('data.status', 'completed');
        $this->postJson("$url/events", $this->followUpBody(['version' => 3, 'action' => 'follow_up']))->assertStatus(422);
        $this->postJson("$url/events", $this->followUpBody(['version' => 3, 'action' => 'reopen']))->assertCreated()->assertJsonPath('data.status', 'open')->assertJsonPath('data.events.total', 4);
        $this->postJson("$url/events", $complete)->assertOk()->assertJsonPath('data.status', 'open')->assertJsonPath('data.events.total', 4);
        $this->assertSame($stock, DB::table('pharmacy_stock_lots')->get()->toJson());
        $this->assertSame($fills, DB::table('pharmacy_fills')->get()->toJson());
        $this->assertSame($events, DB::table('pharmacy_stock_events')->get()->toJson());
        $this->getJson("/api/pharmacy/stock/$lot")->assertOk()->assertJsonPath('data.recall_notices.0.id', $notice);
        $this->deleteJson("$url/events/1")->assertNotFound();
    }

    public function test_recall_follow_up_worklist_filters_and_completed_handover_history_are_retained(): void
    {
        $lot = $this->lot(); $rx = $this->rx(); $f = $this->complete($rx, $this->fill($rx, $lot));
        $otherRx = $this->rx(); $other = $this->fill($otherRx, $lot);
        $notice = $this->postJson('/api/pharmacy/recall-notices', $this->noticeBody())->assertCreated()->json('data.id');
        $url = "/api/pharmacy/recall-notices/$notice/fills/{$f['id']}/follow-up";
        $before = DB::table('pharmacy_fills')->get()->toJson(); $stock = DB::table('pharmacy_stock_lots')->get()->toJson();
        $this->postJson("$url/events", $this->followUpBody(['action' => 'response', 'method' => 'secure_message']))->assertCreated()->assertJsonPath('data.status', 'open');
        $this->getJson("/api/pharmacy/recall-notices/$notice?follow_up=not_started")->assertOk()->assertJsonPath('data.fills.total', 1)->assertJsonPath('data.fills.data.0.id', $other['id']);
        $this->getJson("/api/pharmacy/recall-notices/$notice?follow_up=open")->assertOk()->assertJsonPath('data.fills.total', 1)->assertJsonPath('data.fills.data.0.id', $f['id']);
        $this->postJson("$url/events", $this->followUpBody(['version' => 1, 'action' => 'complete']))->assertCreated();
        $this->getJson("/api/pharmacy/recall-notices/$notice?follow_up=completed")->assertOk()->assertJsonPath('data.fills.data.0.follow_up_status', 'completed')->assertJsonPath('data.fills.total', 1);
        $this->assertSame($before, DB::table('pharmacy_fills')->get()->toJson()); $this->assertSame($stock, DB::table('pharmacy_stock_lots')->get()->toJson());
    }

    public function test_recall_follow_up_scope_validation_and_replay_actor_boundaries(): void
    {
        $lot = $this->lot(); $rx = $this->rx(); $f = $this->fill($rx, $lot);
        $notice = $this->postJson('/api/pharmacy/recall-notices', $this->noticeBody())->assertCreated()->json('data.id');
        $unmatched = $this->postJson('/api/pharmacy/recall-notices', $this->noticeBody(['lot_number' => 'OTHER']))->assertCreated()->json('data.id');
        $url = "/api/pharmacy/recall-notices/$notice/fills/{$f['id']}/follow-up";
        $this->getJson("/api/pharmacy/recall-notices/$unmatched/fills/{$f['id']}/follow-up")->assertNotFound();
        foreach ([['action' => 'contact_attempt'], ['occurred_on' => now()->addDay()->toDateString()], ['occurred_on' => '1900-01-01'], ['action' => 'reopen'], ['note' => ''], ['evidence' => '']] as $bad) {
            $this->postJson("$url/events", $this->followUpBody($bad))->assertStatus(422);
        }
        $body = $this->followUpBody(); $this->postJson("$url/events", $body)->assertCreated();
        $this->actingAs($this->independentReviewer(), 'api'); $this->postJson("$url/events", $body)->assertStatus(409);
        $this->actingAs($this->actor, 'api');
        foreach (['medical_biller', 'admin', 'client'] as $role) {
            $this->actor->role = $role; $this->actor->save(); $this->getJson($url)->assertForbidden(); $this->postJson("$url/events", $body)->assertForbidden();
        }
        $this->actor->role = 'pharmacist'; $this->actor->save();
        DB::table('pharmacy_staff_assignments')->where('user_id', $this->actor->id)->where('location_id', $this->location)->update(['active' => false]);
        $this->getJson($url)->assertNotFound(); $this->postJson("$url/events", $body)->assertNotFound();
        $this->getJson("/api/pharmacy/recall-notices/$notice?follow_up=open")->assertOk()->assertJsonPath('data.fills.total', 0);
        $this->actor->organization_id = 2; $this->actor->save(); $this->getJson($url)->assertNotFound(); $this->postJson("$url/events", $body)->assertNotFound();
    }

    public function test_recall_follow_up_history_is_paginated_and_failed_writes_roll_back(): void
    {
        $lot = $this->lot(); $rx = $this->rx(); $f = $this->fill($rx, $lot);
        $notice = $this->postJson('/api/pharmacy/recall-notices', $this->noticeBody())->assertCreated()->json('data.id');
        $url = "/api/pharmacy/recall-notices/$notice/fills/{$f['id']}/follow-up";
        for ($i=0; $i<27; $i++) { $this->postJson("$url/events", $this->followUpBody(['version' => $i, 'action' => 'follow_up']))->assertCreated(); }
        $this->getJson($url)->assertOk()->assertJsonPath('data.events.total', 27)->assertJsonCount(25, 'data.events.data')->assertJsonMissingPath('data.events.data.0.request_hash');
        $this->getJson("$url?page=2")->assertOk()->assertJsonCount(2, 'data.events.data')->assertJsonPath('data.events.data.1.version', 1);
        $this->getJson("$url?page=0")->assertStatus(422);
        $second = $this->postJson('/api/pharmacy/recall-notices', $this->noticeBody())->assertCreated()->json('data.id');
        DB::connection()->beforeExecuting(function ($query) { if (str_starts_with(strtolower($query), 'insert into') && str_contains($query, 'pharmacy_recall_follow_up_events')) { throw new \RuntimeException('Synthetic follow-up audit failure'); } });
        $this->postJson("$url/events", $this->followUpBody(['version' => 27]))->assertStatus(500);
        $this->getJson($url)->assertOk()->assertJsonPath('data.version', 27)->assertJsonPath('data.events.total', 27);
        $this->postJson("/api/pharmacy/recall-notices/$second/fills/{$f['id']}/follow-up/events", $this->followUpBody())->assertStatus(500);
        $this->assertSame(1, DB::table('pharmacy_recall_follow_ups')->count());
    }

    private function stopPrescription(int $rx, array $changes = [])
    {
        return $this->postJson("/api/pharmacy/prescriptions/$rx/discontinue", array_replace(['request_id' => (string) Str::uuid(), 'reason' => 'Synthetic discontinuation', 'reference' => 'SYNTHETIC authority record'], $changes));
    }

    public function test_discontinuation_blocks_fills_and_handover_preserves_stock_and_is_append_only(): void
    {
        $rx = $this->rx(); $lot = $this->lot();
        $f = $this->act($rx, $this->fill($rx, $lot), 'approve', $this->checks());
        $f = $this->act($rx, $f, 'ready', ['checks' => ['label' => true]]);
        $key = (string) Str::uuid();
        $this->stopPrescription($rx, ['request_id' => $key])->assertOk()->assertJsonPath('data.discontinuation_reason', 'Synthetic discontinuation');
        $this->stopPrescription($rx, ['request_id' => $key])->assertOk();
        $this->stopPrescription($rx, ['request_id' => $key, 'reason' => 'Changed'])->assertStatus(409);
        $this->stopPrescription($rx)->assertStatus(409);
        $this->assertSame(1, DB::table('pharmacy_events')->where('action', 'prescription_discontinued')->count());
        $this->assertEquals(10, DB::table('pharmacy_stock_lots')->where('id', $lot)->value('reserved'));
        $this->assertEquals(50, DB::table('pharmacy_stock_lots')->where('id', $lot)->value('on_hand'));
        foreach (['approve', 'ready', 'collected', 'delivered'] as $action) {
            $this->act($rx, $f, $action, $this->checks() + ['occurred_on' => now()->toDateString(), 'reference' => 'Synthetic', 'counseling' => 'provided'], 422);
        }
        $this->act($rx, $f, 'cancel');
        $this->assertEquals(0, DB::table('pharmacy_stock_lots')->where('id', $lot)->value('reserved'));
        $this->postJson("/api/pharmacy/prescriptions/$rx/fills", $this->fillBody($lot))->assertUnprocessable();
        $this->getJson('/api/pharmacy/prescriptions?status=discontinued')->assertOk()->assertJsonPath('data.total', 1);
        $this->getJson('/api/pharmacy/prescriptions?status=active')->assertOk()->assertJsonPath('data.total', 0);
        $this->getJson('/api/pharmacy/prescriptions?status=invalid')->assertUnprocessable();
        $this->assertEquals(50, DB::table('pharmacy_stock_lots')->where('id', $lot)->value('on_hand'));
    }

    public function test_discontinuation_requires_assigned_pharmacist_and_preserves_completed_fill_billing(): void
    {
        $rx = $this->rx(); $lot = $this->lot();
        $f = $this->complete($rx, $this->fill($rx, $lot));
        foreach (['pharmacy_technician', 'medical_biller', 'admin'] as $role) {
            $this->actor->role = $role; $this->actor->save();
            $this->stopPrescription($rx)->assertForbidden();
        }
        $this->actor->role = 'pharmacist'; $this->actor->save();
        DB::table('pharmacy_staff_assignments')->where('location_id', $this->location)->update(['active' => false]);
        $this->stopPrescription($rx)->assertNotFound();
        DB::table('pharmacy_staff_assignments')->where('location_id', $this->location)->update(['active' => true]);
        $this->actor->organization_id = 2; $this->actor->save();
        $this->stopPrescription($rx)->assertNotFound();
        $this->actor->organization_id = 1; $this->actor->save();
        $this->stopPrescription($rx, ['reference' => ''])->assertUnprocessable();
        $before = DB::table('pharmacy_fills')->where('id', $f['id'])->first();
        $this->stopPrescription($rx)->assertOk();
        $this->assertEquals($before, DB::table('pharmacy_fills')->where('id', $f['id'])->first());
        $this->actor->role = 'medical_biller'; $this->actor->save();
        $this->putJson("/api/pharmacy/prescriptions/$rx/coverage", ['version' => 1, 'status' => 'verified', 'payer' => 'Synthetic payer', 'claim_number' => 'SYN-PIP', 'coordination' => 'Synthetic coordination', 'evidence' => 'Synthetic verification', 'verified_on' => now()->toDateString()])->assertOk();
        $this->act($rx, $f, 'prepare_claim', ['amount' => '25.00', 'reference' => 'Synthetic pricing']);
        $this->assertEquals(40, DB::table('pharmacy_stock_lots')->where('id', $lot)->value('on_hand'));
    }

    public function test_discontinuation_blocks_compounding_but_allows_unused_reservations_to_release(): void
    {
        [$b, $lot] = $this->reservedWorksheet();
        $batch = DB::table('pharmacy_batch_worksheets')->where('id', $b)->first();
        $rx = $batch->prescription_id;
        $this->stopPrescription($rx)->assertOk();
        $this->postJson("/api/pharmacy/batch-worksheets/$b/execution", $this->executionBody())->assertUnprocessable();
        $this->assertSame(0, DB::table('pharmacy_batch_executions')->count());
        $this->assertEquals(5, DB::table('pharmacy_ingredient_lots')->where('id', $lot)->value('on_hand'));
        $this->assertEquals(2, DB::table('pharmacy_ingredient_lots')->where('id', $lot)->value('reserved'));
        $record = json_decode($batch->record, true);
        $this->postJson('/api/pharmacy/batch-worksheets', $record + ['request_id' => (string) Str::uuid(), 'prescription_id' => $rx, 'formulation_id' => $batch->formulation_id, 'batch_number' => 'STOPPED'])->assertUnprocessable();
        $this->postJson("/api/pharmacy/batch-worksheets/$b/allocation", ['version' => 3, 'action' => 'release', 'evidence' => 'Synthetic discontinuation'])->assertOk();
        $this->assertEquals(0, DB::table('pharmacy_ingredient_lots')->where('id', $lot)->value('reserved'));
        // Separate unallocated worksheet exercises the stop gate before any stock mutation.
        $b2 = $this->reviewedWorksheet();
        $rx2 = DB::table('pharmacy_batch_worksheets')->where('id', $b2)->value('prescription_id');
        $this->stopPrescription($rx2)->assertOk();
        $this->postJson("/api/pharmacy/batch-worksheets/$b2/allocation", ['version' => 2, 'action' => 'reserve', 'evidence' => 'Synthetic', 'lots' => [['key' => 'A', 'lot_id' => $lot]]])->assertUnprocessable();
        DB::table('pharmacy_batch_worksheets')->where('id', $b2)->update(['status' => 'draft']);
        $reviewer = $this->independentReviewer(); $this->actingAs($reviewer, 'api');
        $this->postJson("/api/pharmacy/batch-worksheets/$b2/review", ['version' => 2, 'action' => 'review', 'evidence' => 'Synthetic'])->assertUnprocessable();
        $this->postJson("/api/pharmacy/batch-worksheets/$b2/review", ['version' => 2, 'action' => 'reject', 'evidence' => 'Synthetic'])->assertOk();
    }

    public function test_replacement_links_preserve_both_prescriptions_and_audit_once(): void
    {
        $original = $this->rx();
        $replacement = $this->rx(['quantity' => '7.000', 'refills_authorized' => 0]);
        $url = "/api/pharmacy/prescriptions/$original/replacement";
        $body = ['request_id' => (string) Str::uuid(), 'replacement_id' => $replacement, 'reason' => 'Synthetic replacement', 'reference' => 'SYNTHETIC authority'];
        $this->postJson($url, $body)->assertUnprocessable();
        $this->stopPrescription($original)->assertOk();
        $before = DB::table('pharmacy_prescriptions')->orderBy('id')->get();
        $this->postJson($url, $body)->assertOk()->assertJsonPath('data.replacement.id', $replacement);
        $this->postJson($url, $body)->assertOk();
        $this->postJson($url, array_replace($body, ['reason' => 'Changed']))->assertStatus(409);
        $this->assertSame(1, DB::table('pharmacy_prescription_replacements')->count());
        $this->assertSame(1, DB::table('pharmacy_events')->where('action', 'replacement_linked')->count());
        $this->assertSame(1, DB::table('pharmacy_events')->where('action', 'original_prescription_linked')->count());
        $this->assertEquals($before, DB::table('pharmacy_prescriptions')->orderBy('id')->get());
        $this->getJson("/api/pharmacy/prescriptions/$replacement")->assertOk()->assertJsonPath('data.replaces.id', $original)->assertJsonPath('data.refills_authorized', 0);
        $this->deleteJson($url)->assertStatus(405);
        $this->putJson($url, $body)->assertStatus(405);
        $this->postJson("/api/pharmacy/prescriptions/$original/fills", $this->fillBody($this->lot()))->assertUnprocessable();
        $this->getJson("/api/pharmacy/prescriptions?replacement_for=$original")->assertOk()->assertJsonPath('data.total', 0);
    }

    public function test_replacement_link_access_patient_and_location_boundaries(): void
    {
        $original = $this->rx(); $this->stopPrescription($original)->assertOk();
        $replacement = $this->rx();
        $url = "/api/pharmacy/prescriptions/$original/replacement";
        $body = ['request_id' => (string) Str::uuid(), 'replacement_id' => $replacement, 'reason' => 'Synthetic', 'reference' => 'Synthetic'];
        foreach (['pharmacy_technician', 'medical_biller', 'admin'] as $role) {
            $this->actor->role = $role; $this->actor->save();
            $this->postJson($url, $body)->assertForbidden();
            $this->getJson("/api/pharmacy/prescriptions?replacement_for=$original")->assertForbidden();
        }
        $this->actor->role = 'pharmacist'; $this->actor->save();
        DB::table('pharmacy_staff_assignments')->where('location_id', $this->location)->update(['active' => false]);
        $this->postJson($url, $body)->assertNotFound();
        DB::table('pharmacy_staff_assignments')->where('location_id', $this->location)->update(['active' => true]);
        DB::table('pharmacy_prescriptions')->where('id', $replacement)->update(['organization_id' => 2]);
        $this->postJson($url, $body)->assertNotFound();
        DB::table('pharmacy_prescriptions')->where('id', $replacement)->update(['organization_id' => 1, 'location_id' => $this->otherLocation]);
        $this->postJson($url, $body)->assertUnprocessable();
        $otherPatient = User::create(['first_name' => 'Another', 'last_name' => 'Synthetic', 'email' => 'other-syn@example.invalid', 'password' => 'synthetic-only', 'role' => 'client', 'organization_id' => 1, 'status' => 'active']);
        CaseParty::create(['case_id' => $this->case->id, 'user_id' => $otherPatient->id, 'role_in_case' => 'client']);
        $otherRx = $this->rx(['patient_id' => $otherPatient->id]);
        $this->postJson($url, array_replace($body, ['replacement_id' => $otherRx]))->assertUnprocessable();
        $this->getJson("/api/pharmacy/prescriptions?replacement_for=$original")->assertOk()->assertJsonPath('data.total', 0);
        $this->assertSame(0, DB::table('pharmacy_prescription_replacements')->count());
    }

    public function test_replacement_links_reject_reuse_stopped_targets_and_backward_chains(): void
    {
        $first = $this->rx(); $second = $this->rx(); $third = $this->rx();
        foreach ([$first, $second] as $id) { $this->stopPrescription($id)->assertOk(); }
        $body = ['request_id' => (string) Str::uuid(), 'replacement_id' => $third, 'reason' => 'Synthetic', 'reference' => 'Synthetic'];
        $this->postJson("/api/pharmacy/prescriptions/$first/replacement", array_replace($body, ['replacement_id' => $second]))->assertUnprocessable();
        $this->postJson("/api/pharmacy/prescriptions/$first/replacement", array_replace($body, ['replacement_id' => $first]))->assertUnprocessable();
        $this->postJson("/api/pharmacy/prescriptions/$second/replacement", $body)->assertOk();
        $this->postJson("/api/pharmacy/prescriptions/$first/replacement", $body)->assertStatus(409);
        $this->stopPrescription($third)->assertOk();
        // A stopped successor remains part of the retained history and exact retries remain safe.
        $this->postJson("/api/pharmacy/prescriptions/$second/replacement", $body)->assertOk();
        $fourth = $this->rx();
        $this->postJson("/api/pharmacy/prescriptions/$third/replacement", array_replace($body, ['replacement_id' => $fourth]))->assertOk();
        $this->getJson("/api/pharmacy/prescriptions/$third")->assertOk()->assertJsonPath('data.replaces.id', $second)->assertJsonPath('data.replacement.id', $fourth);
        $this->assertSame(2, DB::table('pharmacy_prescription_replacements')->count());
    }

    public function test_discontinued_work_queue_clears_only_after_explicit_fill_cancellation(): void
    {
        $rx = $this->rx(); $lot = $this->lot(); $f = $this->fill($rx, $lot);
        $url = '/api/pharmacy/prescriptions?attention=discontinued_work';
        $this->getJson($url)->assertOk()->assertJsonPath('data.total', 0);
        $this->stopPrescription($rx)->assertOk();
        $this->getJson($url)->assertOk()->assertJsonPath('data.total', 1)->assertJsonPath('data.data.0.open_fill_count', 1)->assertJsonPath('data.data.0.reserved_batch_count', 0);
        $this->getJson($url.'&location_id='.$this->otherLocation)->assertOk()->assertJsonPath('data.total', 0);
        $this->getJson($url.'&status=active')->assertOk()->assertJsonPath('data.total', 0);
        DB::table('pharmacy_staff_assignments')->where('location_id', $this->location)->update(['active' => false]);
        $this->getJson($url)->assertOk()->assertJsonPath('data.total', 0);
        DB::table('pharmacy_staff_assignments')->where('location_id', $this->location)->update(['active' => true]);
        $this->act($rx, $f, 'cancel');
        $this->getJson($url)->assertOk()->assertJsonPath('data.total', 0);
        $this->assertEquals(50, DB::table('pharmacy_stock_lots')->where('id', $lot)->value('on_hand'));
        $this->getJson('/api/pharmacy/prescriptions?attention=invalid')->assertUnprocessable();
    }

    public function test_discontinued_work_queue_counts_distinct_reserved_batches_and_retains_history(): void
    {
        [$b, $lot] = $this->reservedWorksheet(true);
        $rx = DB::table('pharmacy_batch_worksheets')->where('id', $b)->value('prescription_id');
        $this->stopPrescription($rx)->assertOk();
        $url = '/api/pharmacy/prescriptions?attention=discontinued_work';
        $this->getJson($url)->assertOk()->assertJsonPath('data.total', 1)->assertJsonPath('data.data.0.open_fill_count', 0)->assertJsonPath('data.data.0.reserved_batch_count', 1);
        $this->getJson("/api/pharmacy/prescriptions/$rx")->assertOk()->assertJsonCount(1, 'data.reserved_batches')->assertJsonPath('data.reserved_batches.0.id', $b);
        $this->actor->organization_id = 2; $this->actor->save();
        $this->getJson($url)->assertOk()->assertJsonPath('data.total', 0);
        $this->actor->organization_id = 1; $this->actor->save();
        $this->postJson("/api/pharmacy/batch-worksheets/$b/allocation", ['version' => 3, 'action' => 'release', 'evidence' => 'Synthetic discontinuation resolution'])->assertOk();
        $this->getJson($url)->assertOk()->assertJsonPath('data.total', 0);
        $this->getJson("/api/pharmacy/prescriptions/$rx")->assertOk()->assertJsonCount(0, 'data.reserved_batches');
        $this->assertSame(2, DB::table('pharmacy_ingredient_allocations')->where('batch_id', $b)->where('status', 'released')->count());
        $this->assertEquals(5, DB::table('pharmacy_ingredient_lots')->where('id', $lot)->value('on_hand'));
    }

    private function body(array $overrides = []): array
    {
        if (! empty($overrides['compounded']) && ! array_key_exists('compound_type', $overrides)) {
            $overrides['compound_type'] = 'nonsterile';
        }

        return array_replace(['request_id' => (string) Str::uuid(), 'location_id' => $this->location, 'case_id' => $this->case->id, 'patient_id' => $this->patient->id, 'rx_number' => 'SYN-'.Str::random(10), 'medication' => 'Synthetic medication', 'strength' => 'Synthetic strength', 'dosage_form' => 'tablet', 'directions' => 'Synthetic fixture only', 'quantity' => '10.000', 'quantity_unit' => 'tablet', 'refills_authorized' => 1, 'written_on' => now()->subDay()->toDateString(), 'expires_on' => now()->addMonth()->toDateString(), 'prescriber_name' => 'Synthetic prescriber', 'prescriber_identifier' => 'NOT VALID', 'source_reference' => 'synthetic fixture', 'controlled' => false, 'compounded' => false], $overrides);
    }

    private function rx(array $overrides = []): int
    {
        return $this->postJson('/api/pharmacy/prescriptions', $this->body($overrides))->assertCreated()->json('data.id');
    }

    private function lot(array $overrides = []): int
    {
        return $this->postJson('/api/pharmacy/stock', array_replace(['request_id' => (string) Str::uuid(), 'location_id' => $this->location, 'ndc' => '00000-0000-00', 'medication' => 'Synthetic medication', 'lot_number' => 'SYN-LOT', 'quantity_unit' => 'tablet', 'expires_on' => now()->addMonth()->toDateString(), 'quantity' => '50.000', 'receipt_reference' => 'Synthetic receipt'], $overrides))->assertCreated()->json('data.id');
    }

    private function fillBody(int $lot, array $overrides = []): array
    {
        return array_replace(['request_id' => (string) Str::uuid(), 'stock_lot_id' => $lot, 'ndc' => '00000-0000-00', 'quantity' => '10.000', 'days_supply' => 10], $overrides);
    }

    private function fill(int $rx, int $lot): array
    {
        return $this->postJson("/api/pharmacy/prescriptions/$rx/fills", $this->fillBody($lot))->assertOk()->json('data.fills.0');
    }

    private function act(int $rx, array $f, string $a, array $extra = [], $status = 200): array
    {
        $result = $this->postJson("/api/pharmacy/prescriptions/$rx/fills/{$f['id']}/actions", array_replace(['version' => $f['version'], 'action' => $a, 'note' => 'Synthetic evidence'], $extra))->assertStatus($status);

        return $status === 200 ? collect($result->json('data.fills'))->firstWhere('id', $f['id']) : $f;
    }

    private function checks(): array
    {
        return ['checks' => ['identity' => true, 'prescriber' => true, 'therapy' => true, 'product' => true]];
    }

    private function complete(int $rx, array $f): array
    {
        $f = $this->act($rx, $f, 'approve', $this->checks());
        $f = $this->act($rx, $f, 'ready', ['checks' => ['label' => true]]);

        return $this->act($rx, $f, 'collected', ['occurred_on' => now()->toDateString(), 'reference' => 'Synthetic handover', 'counseling' => 'provided']);
    }

    public function test_full_workflow_records_stock_once_and_preserves_billing_link(): void
    {
        Http::preventStrayRequests();
        $rx = $this->rx();
        $lot = $this->lot();
        $f = $this->fill($rx, $lot);
        $this->assertEquals(10, DB::table('pharmacy_stock_lots')->where('id', $lot)->value('reserved'));
        $this->act($rx, $f, 'ready', ['checks' => ['label' => true]], 422);
        $this->act($rx, $f, 'approve', [], 422);
        foreach (['pharmacy_technician', 'medical_biller', 'admin'] as $role) {
            $this->actor->role = $role;
            $this->act($rx, $f, 'approve', $this->checks(), 403);
        }
        $this->actor->role = 'pharmacist';
        $f = $this->complete($rx, $f);
        $this->assertEquals(40, DB::table('pharmacy_stock_lots')->where('id', $lot)->value('on_hand'));
        $this->assertEquals(0, DB::table('pharmacy_stock_lots')->where('id', $lot)->value('reserved'));
        $this->act($rx, $f, 'collected', [], 422);
        $this->assertSame(1, DB::table('pharmacy_stock_events')->where('action', 'dispensed')->count());
        $this->actor->role = 'medical_biller';
        $this->act($rx, $f, 'prepare_claim', ['amount' => '123.45', 'reference' => 'Synthetic pricing basis'], 422);
        $coverage = ['version' => 1, 'status' => 'verified', 'payer' => 'Synthetic payer', 'claim_number' => 'SYN-PIP', 'coordination' => 'Synthetic coordination', 'evidence' => 'Synthetic verification', 'verified_on' => now()->toDateString()];
        $this->putJson("/api/pharmacy/prescriptions/$rx/coverage", $coverage)->assertOk();
        $this->putJson("/api/pharmacy/prescriptions/$rx/coverage", $coverage)->assertStatus(409);
        $f = $this->act($rx, $f, 'prepare_claim', ['amount' => '123.45', 'reference' => 'Synthetic pricing basis']);
        $invoice = $f['invoice_id'];
        $this->act($rx, $f, 'prepare_claim', ['amount' => '123.45', 'reference' => 'Synthetic pricing basis'], 422);
        $this->putJson("/api/invoices/$invoice", ['amount' => 1])->assertStatus(409);
        $this->deleteJson("/api/invoices/$invoice")->assertStatus(409);
        $this->act($rx, $f, 'record_submission', [], 422);
        $f = $this->act($rx, $f, 'record_submission', ['reference' => 'EXTERNAL-SYN', 'occurred_on' => now()->toDateString()]);
        $f = $this->act($rx, $f, 'record_denial', ['denial_type' => 'coverage', 'reference' => 'Synthetic determination', 'determination_on' => now()->toDateString()]);
        $this->assertSame('denied', $f['claim_status']);
        $this->getJson("/api/pharmacy/prescriptions/$rx/assistant")->assertOk()->assertJsonPath('data.ai_connected', false)->assertJsonPath('data.mode', 'rules_only');
        $this->assertSame(1, DB::table('invoices')->count());
        Http::assertNothingSent();
    }

    public function test_tenant_location_and_role_boundaries(): void
    {
        $rx = $this->rx();
        $lot = $this->lot(['location_id' => $this->otherLocation]);
        $body = $this->fillBody($lot);
        $this->postJson("/api/pharmacy/prescriptions/$rx/fills", $body)->assertNotFound();
        $this->actor->organization_id = 2;
        $this->getJson('/api/pharmacy/prescriptions')->assertOk()->assertJsonPath('data.total', 0);
        $this->getJson('/api/pharmacy/stock')->assertOk()->assertJsonPath('data.total', 0);
        $this->getJson("/api/pharmacy/prescriptions/$rx")->assertNotFound();
        $this->postJson("/api/pharmacy/prescriptions/$rx/fills", $body)->assertNotFound();
        $this->postJson('/api/pharmacy/prescriptions', $this->body())->assertNotFound();
        $this->actor->organization_id = 1;
        foreach (['client', 'provider_staff', 'attorney', 'firm_admin'] as $role) {
            $this->actor->role = $role;
            $this->getJson('/api/pharmacy/prescriptions')->assertForbidden();
        }
        foreach (['admin', 'medical_biller'] as $role) {
            $this->actor->role = $role;
            $this->postJson('/api/pharmacy/prescriptions', $this->body())->assertForbidden();
        }
        $this->actor->organization_id = null;
        $this->getJson('/api/pharmacy/prescriptions')->assertForbidden();
    }

    public function test_partial_quantities_share_an_allowance_and_cannot_exceed_it(): void
    {
        $rx = $this->rx(['quantity' => '0.300', 'refills_authorized' => 1]);
        $lot = $this->lot(['quantity' => '1.000']);
        $url = "/api/pharmacy/prescriptions/$rx/fills";
        $body = $this->fillBody($lot, ['quantity' => '0.100']);
        $this->postJson($url, $body)->assertUnprocessable();
        $body['partial_reason'] = 'Synthetic patient requested partial supply';
        $first = $this->postJson($url, $body)->assertOk()->assertJsonPath('data.quantity_balance.available_quantity', '0.200')->json('data.fills.0');
        $this->assertSame(1, $first['authorization_number']);
        $this->postJson($url, $body)->assertOk();
        $this->postJson($url, array_replace($body, ['partial_reason' => 'Changed']))->assertStatus(409);
        $this->postJson($url, $this->fillBody($lot, ['quantity' => '0.200']))->assertUnprocessable();
        $this->complete($rx, $first);
        $this->postJson($url, $this->fillBody($lot, ['quantity' => '0.201']))->assertUnprocessable();
        $second = $this->postJson($url, $this->fillBody($lot, ['quantity' => '0.200']))->assertOk()->json('data.fills.1');
        $this->assertSame(1, $second['authorization_number']);
        $this->act($rx, $second, 'cancel');
        $this->getJson("/api/pharmacy/prescriptions/$rx")->assertOk()->assertJsonPath('data.quantity_balance.available_quantity', '0.200');
        $second = $this->postJson($url, $this->fillBody($lot, ['quantity' => '0.200']))->assertOk()->json('data.fills.2');
        $this->complete($rx, $second);
        $this->getJson("/api/pharmacy/prescriptions/$rx")->assertOk()
            ->assertJsonPath('data.quantity_balance.next_authorization_number', 2)
            ->assertJsonPath('data.quantity_balance.allowances.0.handed_over', '0.300');
        $third = $this->postJson($url, $this->fillBody($lot, ['quantity' => '0.300']))->assertOk()->json('data.fills.3');
        $this->assertSame(2, $third['authorization_number']);
        $this->complete($rx, $third);
        $this->getJson("/api/pharmacy/prescriptions/$rx")->assertOk()->assertJsonPath('data.quantity_balance.next_authorization_number', null)->assertJsonPath('data.quantity_balance.available_quantity', '0.000');
        $this->postJson($url, $this->fillBody($lot, ['quantity' => '0.001']))->assertUnprocessable();
        $this->assertEquals(0.400, DB::table('pharmacy_stock_lots')->where('id', $lot)->value('on_hand'));
        $this->assertEquals(0, DB::table('pharmacy_stock_lots')->where('id', $lot)->value('reserved'));
        $this->assertSame(3, DB::table('pharmacy_stock_events')->where('action', 'dispensed')->count());
    }

    public function test_historical_partial_records_do_not_create_new_entitlement(): void
    {
        $rx = $this->rx(['refills_authorized' => 1]);
        $lot = $this->lot();
        $f = $this->postJson("/api/pharmacy/prescriptions/$rx/fills", $this->fillBody($lot, ['quantity' => '2.000', 'partial_reason' => 'Synthetic partial']))->assertOk()->json('data.fills.0');
        $this->complete($rx, $f);
        // Represents a retained pre-migration fill. Upgrade must not infer its remainder.
        \Illuminate\Support\Facades\Schema::table('pharmacy_fills', function ($table) {
            $table->dropIndex('pharmacy_fill_authorization_index');
            $table->dropColumn(['authorization_number', 'partial_reason']);
        });
        $before = (array) DB::table('pharmacy_fills')->where('id', $f['id'])->first();
        (require database_path('migrations/2026_09_27_000013_add_pharmacy_fill_quantity_accounting.php'))->up();
        $after = (array) DB::table('pharmacy_fills')->where('id', $f['id'])->first();
        foreach ($before as $key => $value) { $this->assertSame($value, $after[$key], $key); }
        $this->assertNull($after['authorization_number']);
        $this->assertNull($after['partial_reason']);
        $this->getJson("/api/pharmacy/prescriptions/$rx")->assertOk()->assertJsonPath('data.quantity_balance.legacy_allowances_used', 1)->assertJsonPath('data.quantity_balance.next_authorization_number', 2);
        $f = $this->postJson("/api/pharmacy/prescriptions/$rx/fills", $this->fillBody($lot))->assertOk()->json('data.fills.1');
        $this->assertSame(2, $f['authorization_number']);
        $this->complete($rx, $f);
        $this->postJson("/api/pharmacy/prescriptions/$rx/fills", $this->fillBody($lot, ['quantity' => '8.000', 'partial_reason' => 'Cannot reclaim old remainder']))->assertUnprocessable();
        $this->assertEquals(38, DB::table('pharmacy_stock_lots')->where('id', $lot)->value('on_hand'));
    }

    public function test_partial_remainder_retains_expiry_location_and_rollback_guards(): void
    {
        $rx = $this->rx(['refills_authorized' => 0]);
        $lot = $this->lot();
        $url = "/api/pharmacy/prescriptions/$rx/fills";
        $f = $this->postJson($url, $this->fillBody($lot, ['quantity' => '4.000', 'partial_reason' => 'Synthetic stock shortage']))->assertOk()->json('data.fills.0');
        $this->complete($rx, $f);
        DB::table('pharmacy_staff_assignments')->where('user_id', $this->actor->id)->where('location_id', $this->location)->update(['active' => false]);
        $this->postJson($url, $this->fillBody($lot, ['quantity' => '6.000']))->assertNotFound();
        DB::table('pharmacy_staff_assignments')->where('user_id', $this->actor->id)->where('location_id', $this->location)->update(['active' => true]);
        DB::table('pharmacy_prescriptions')->where('id', $rx)->update(['expires_on' => now()->subDay()->toDateString()]);
        $this->postJson($url, $this->fillBody($lot, ['quantity' => '6.000']))->assertUnprocessable();
        DB::table('pharmacy_prescriptions')->where('id', $rx)->update(['expires_on' => now()->addDay()->toDateString()]);
        $fail = true;
        DB::connection()->beforeExecuting(function ($query) use (&$fail) {
            if ($fail && str_starts_with(strtolower($query), 'insert into') && str_contains($query, 'pharmacy_events')) { throw new \RuntimeException('Synthetic quantity audit failure'); }
        });
        $this->postJson($url, $this->fillBody($lot, ['quantity' => '6.000']))->assertStatus(500);
        $fail = false;
        $this->assertSame(1, DB::table('pharmacy_fills')->where('prescription_id', $rx)->count());
        $this->assertEquals(0, DB::table('pharmacy_stock_lots')->where('id', $lot)->value('reserved'));
        $this->getJson("/api/pharmacy/prescriptions/$rx")->assertOk()->assertJsonPath('data.quantity_balance.available_quantity', '6.000');
    }

    public function test_retries_refills_stale_updates_and_cancel_release_stock(): void
    {
        $body = $this->body(['refills_authorized' => 0]);
        $rx = $this->postJson('/api/pharmacy/prescriptions', $body)->assertCreated()->json('data.id');
        $this->postJson('/api/pharmacy/prescriptions', $body)->assertCreated()->assertJsonPath('data.id', $rx);
        $this->postJson('/api/pharmacy/prescriptions', array_replace($body, ['medication' => 'Changed']))->assertStatus(409);
        $lot = $this->lot(['quantity' => '10.125']);
        $fill = $this->fillBody($lot);
        $this->postJson("/api/pharmacy/prescriptions/$rx/fills", array_replace($fill, ['quantity' => '11.000']))->assertUnprocessable();
        $f = $this->postJson("/api/pharmacy/prescriptions/$rx/fills", $fill)->assertOk()->json('data.fills.0');
        $this->postJson("/api/pharmacy/prescriptions/$rx/fills", $fill)->assertOk();
        $this->assertSame(1, DB::table('pharmacy_stock_events')->where('action', 'reserved')->count());
        $this->postJson("/api/pharmacy/prescriptions/$rx/fills", array_replace($fill, ['days_supply' => 9]))->assertStatus(409);
        $this->postJson("/api/pharmacy/prescriptions/$rx/fills", $this->fillBody($lot))->assertUnprocessable();
        $this->act($rx, $f, 'cancel');
        $this->assertEquals(0, DB::table('pharmacy_stock_lots')->where('id', $lot)->value('reserved'));
        $this->assertEquals(10.125, DB::table('pharmacy_stock_lots')->where('id', $lot)->value('on_hand'));
        $new = $this->postJson("/api/pharmacy/prescriptions/$rx/fills", $this->fillBody($lot))->assertOk()->json('data.fills.1');
        $approved = $this->act($rx, $new, 'approve', $this->checks());
        $this->act($rx, $new, 'hold', [], 409);
        $new = $this->act($rx, $approved, 'ready', ['checks' => ['label' => true]]);
        $this->act($rx, $new, 'collected', ['occurred_on' => now()->toDateString(), 'reference' => 'Synthetic', 'counseling' => 'provided']);
        $this->assertEquals(0.125, DB::table('pharmacy_stock_lots')->where('id', $lot)->value('on_hand'));
        $this->postJson("/api/pharmacy/prescriptions/$rx/fills", $this->fillBody($lot))->assertUnprocessable();
    }

    public function test_quarantine_and_expiry_are_rechecked_before_handover(): void
    {
        $rx = $this->rx();
        $lot = $this->lot();
        $f = $this->act($rx, $this->fill($rx, $lot), 'approve', $this->checks());
        $this->putJson("/api/pharmacy/stock/$lot/status", ['version' => 2, 'status' => 'quarantined', 'note' => 'Synthetic recall'])->assertOk();
        $this->act($rx, $f, 'ready', ['checks' => ['label' => true]], 422);
        $this->act($rx, $f, 'cancel');
        $this->assertEquals(0, DB::table('pharmacy_stock_lots')->where('id', $lot)->value('reserved'));
        $other = $this->rx();
        $otherLot = $this->lot();
        $f = $this->act($other, $this->fill($other, $otherLot), 'approve', $this->checks());
        $f = $this->act($other, $f, 'ready', ['checks' => ['label' => true]]);
        DB::table('pharmacy_stock_lots')->where('id', $otherLot)->update(['expires_on' => now()->subDay()->toDateString()]);
        $this->act($other, $f, 'collected', ['occurred_on' => now()->toDateString(), 'reference' => 'Synthetic', 'counseling' => 'provided'], 422);
        $this->assertEquals(50, DB::table('pharmacy_stock_lots')->where('id', $otherLot)->value('on_hand'));
    }

    public function test_controlled_and_compounded_fills_cannot_bypass_unimplemented_controls(): void
    {
        foreach ([['controlled' => true], ['compounded' => true], ['controlled' => true, 'compounded' => true]] as $flags) {
            $rx = $this->rx($flags);
            $f = $this->fill($rx, $this->lot());
            $this->act($rx, $f, 'approve', $this->checks(), 422);
            DB::table('pharmacy_fills')->where('id', $f['id'])->update(['review_status' => 'approved']);
            $this->act($rx, $f, 'ready', ['checks' => ['label' => true]], 422);
        }
        $this->assertEquals(0, DB::table('pharmacy_stock_events')->where('action', 'dispensed')->count());
    }

    public function test_shared_stock_cannot_be_over_reserved_and_units_must_match(): void
    {
        $lot = $this->lot(['quantity' => '15.000']);
        $first = $this->rx();
        $this->fill($first, $lot);
        $second = $this->rx();
        $this->postJson("/api/pharmacy/prescriptions/$second/fills", $this->fillBody($lot))->assertUnprocessable();
        $this->assertEquals(10, DB::table('pharmacy_stock_lots')->where('id', $lot)->value('reserved'));
        $this->assertSame(1, DB::table('pharmacy_fills')->count());
        $wrongUnit = $this->lot(['quantity_unit' => 'mL']);
        $this->postJson("/api/pharmacy/prescriptions/$second/fills", $this->fillBody($wrongUnit))->assertUnprocessable();
        $this->postJson("/api/pharmacy/prescriptions/$second/fills", $this->fillBody($lot, ['ndc' => '11111-1111-11']))->assertUnprocessable();
        $expired = $this->rx(['expires_on' => now()->subDay()->toDateString()]);
        $this->postJson("/api/pharmacy/prescriptions/$expired/fills", $this->fillBody($lot))->assertUnprocessable();
    }

    public function test_preview_is_unavailable_in_production_and_firm_admin_cannot_grant_pharmacy_role(): void
    {
        $this->actor->role = 'firm_admin';
        $this->postJson('/api/admin/users', ['first_name' => 'Synthetic', 'last_name' => 'Denied', 'email' => 'blocked@example.invalid', 'password' => 'Synthetic-password-123!', 'role' => 'pharmacist'])->assertForbidden();
        $this->assertDatabaseMissing('users', ['email' => 'blocked@example.invalid']);
        $this->actor->role = 'pharmacist';
        $this->app->instance('env', 'production');
        $this->getJson('/api/pharmacy/prescriptions')->assertStatus(503);
        $this->postJson('/api/pharmacy/prescriptions', $this->body())->assertStatus(503);
        $this->assertSame(0, DB::table('pharmacy_prescriptions')->count());
    }

    public function test_location_assignments_fail_closed_and_revocation_removes_existing_access(): void
    {
        $rx = $this->rx();
        $lot = $this->lot();
        DB::table('pharmacy_staff_assignments')->where('location_id', $this->location)->delete();
        $this->getJson('/api/pharmacy/locations')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/pharmacy/prescriptions')->assertOk()->assertJsonPath('data.total', 0);
        $this->getJson('/api/pharmacy/stock')->assertOk()->assertJsonPath('data.total', 0);
        $this->getJson("/api/pharmacy/prescriptions/$rx")->assertNotFound();
        $this->postJson('/api/pharmacy/prescriptions', $this->body())->assertNotFound();
        $this->putJson("/api/pharmacy/stock/$lot/status", ['version' => 1, 'status' => 'quarantined', 'note' => 'Denied'])->assertNotFound();
        $this->getJson('/api/pharmacy/staff')->assertForbidden();
        $grant = ['location_id' => $this->location, 'user_id' => $this->actor->id, 'active' => true, 'valid_until' => now()->addMonth()->toDateString(), 'version' => 0, 'reason' => 'Synthetic assignment'];
        $this->putJson('/api/pharmacy/staff', $grant)->assertForbidden();
        $this->actor->role = 'admin';
        $this->getJson('/api/pharmacy/staff')->assertOk();
        // Database role remains pharmacist; the admin actor is only the request principal in this fixture.
        $this->putJson('/api/pharmacy/staff', $grant)->assertOk();
        $this->putJson('/api/pharmacy/staff', $grant)->assertConflict();
        $this->actor->role = 'pharmacist';
        $this->getJson("/api/pharmacy/prescriptions/$rx")->assertOk();
        $this->actor->role = 'admin';
        $this->putJson('/api/pharmacy/staff', array_replace($grant, ['version' => 1, 'active' => false]))->assertOk();
        $this->actor->role = 'pharmacist';
        $this->getJson("/api/pharmacy/prescriptions/$rx/assistant")->assertNotFound();
        $this->assertSame(2, DB::table('pharmacy_access_events')->count());
        DB::table('pharmacy_staff_assignments')->where('user_id', $this->actor->id)->update(['active' => true, 'valid_until' => now()->subDay()->toDateString()]);
        $this->getJson('/api/pharmacy/locations')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/pharmacy/cases')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_independent_patient_chart_is_private_idempotent_and_invalidates_fill_review(): void
    {
        $body = ['request_id' => (string) Str::uuid(), 'record_number' => 'SYN-CHART-1', 'location_id' => $this->location,
            'first_name' => 'Synthetic', 'last_name' => 'No portal', 'date_of_birth' => '1980-01-01', 'identity_reference' => 'SYNTHETIC identity'];
        $usersBefore = DB::table('users')->count();
        $p = $this->postJson('/api/pharmacy/patients', $body)->assertCreated()->json('data');
        $this->postJson('/api/pharmacy/patients', $body)->assertOk()->assertJsonPath('data.id', $p['id']);
        $this->postJson('/api/pharmacy/patients', array_replace($body, ['first_name' => 'Changed']))->assertConflict();
        $this->assertSame($usersBefore, DB::table('users')->count());
        $this->assertSame('unknown', $p['clinical']['allergies_status']);
        $rx = $this->rx(['patient_id' => null, 'pharmacy_patient_id' => $p['id']]);
        $this->getJson('/api/pharmacy/prescriptions')->assertOk()->assertJsonPath('data.data.0.last_name', 'No portal');
        $f = $this->fill($rx, $this->lot());
        $this->act($rx, $f, 'approve', $this->checks(), 422);
        $review = ['version' => 1, 'allergies_status' => 'none_reported', 'medications_status' => 'none_reported', 'reviewed_on' => now()->toDateString(), 'source_reference' => 'Synthetic interview'];
        $this->actor->role = 'pharmacy_technician';
        $this->putJson("/api/pharmacy/patients/{$p['id']}/clinical", $review)->assertForbidden();
        $this->actor->role = 'medical_biller';
        $this->getJson("/api/pharmacy/patients/{$p['id']}")->assertForbidden();
        $this->actor->role = 'pharmacist';
        $this->putJson("/api/pharmacy/patients/{$p['id']}/clinical", $review)->assertOk();
        $this->putJson("/api/pharmacy/patients/{$p['id']}/clinical", $review)->assertConflict();
        $f = $this->act($rx, $f, 'approve', $this->checks());
        $f = $this->act($rx, $f, 'ready', ['checks' => ['label' => true]]);
        $this->putJson("/api/pharmacy/patients/{$p['id']}/clinical", array_replace($review, ['version' => 2, 'allergies_status' => 'documented', 'allergies' => 'Synthetic new allergy']))->assertOk();
        $this->act($rx, $f, 'collected', ['occurred_on' => now()->toDateString(), 'reference' => 'Synthetic', 'counseling' => 'provided'], 422);
        $this->assertEquals(0, DB::table('pharmacy_stock_events')->where('action', 'dispensed')->count());
        DB::table('pharmacy_staff_assignments')->where('location_id', $this->location)->update(['active' => false]);
        $this->getJson("/api/pharmacy/patients/{$p['id']}")->assertNotFound();
        $this->getJson('/api/pharmacy/patients')->assertOk()->assertJsonPath('data.total', 0);
        $this->assertSame(3, DB::table('pharmacy_patient_events')->count());
    }

    public function test_compounded_intake_requires_explicit_sterility_without_enabling_dispensing(): void
    {
        $this->postJson('/api/pharmacy/prescriptions', $this->body(['compounded' => true, 'compound_type' => null]))->assertUnprocessable();
        $this->postJson('/api/pharmacy/prescriptions', $this->body(['compounded' => false, 'compound_type' => 'sterile']))->assertUnprocessable();
        $this->postJson('/api/pharmacy/prescriptions', $this->body(['compounded' => true, 'compound_type' => 'unknown']))->assertUnprocessable();
        foreach (['sterile', 'nonsterile'] as $type) {
            $rx = $this->rx(['compounded' => true, 'compound_type' => $type]);
            $this->getJson("/api/pharmacy/prescriptions/$rx")->assertOk()->assertJsonPath('data.compound_type', $type);
            $f = $this->fill($rx, $this->lot());
            $this->act($rx, $f, 'approve', $this->checks(), 422);
        }
        $this->assertSame(2, DB::table('pharmacy_prescriptions')->count());
        $this->assertSame(0, DB::table('pharmacy_stock_events')->where('action', 'dispensed')->count());
    }

    private function formulationBody(array $overrides = []): array
    {
        return array_replace(['request_id' => (string) Str::uuid(), 'code' => 'SYN-NOT-FOR-USE', 'name' => 'Synthetic formulation NOT FOR USE',
            'preparation_type' => 'nonsterile', 'hazardous' => false, 'strength' => 'Synthetic only', 'dosage_form' => 'tablet',
            'output_quantity' => '10.000', 'output_unit' => 'tablet', 'source_reference' => 'SYNTHETIC', 'method' => 'Not a manufacturing instruction',
            'quality_checks' => 'Synthetic review', 'storage' => 'Synthetic only', 'bud_basis' => 'No clinical BUD assigned',
            'ingredients' => [['key' => 'A', 'name' => 'Synthetic ingredient', 'quantity' => '2.000', 'unit' => 'g', 'specification' => 'NOT FOR USE']]], $overrides);
    }

    private function independentReviewer(): User
    {
        $u = User::create(['first_name' => 'Synthetic', 'last_name' => 'Reviewer', 'email' => Str::uuid().'@example.invalid', 'password' => 'synthetic-only', 'role' => 'pharmacist', 'organization_id' => 1, 'status' => 'active']);
        $u->withAccessToken(new Token(['expires_at' => now()->addHour()]));
        DB::table('pharmacy_staff_assignments')->insert(['location_id' => $this->location, 'user_id' => $u->id, 'active' => true, 'valid_until' => now()->addYear()->toDateString()]);

        return $u;
    }

    public function test_compounding_records_require_independent_review_and_never_release_stock(): void
    {
        Http::preventStrayRequests();
        $body = $this->formulationBody();
        $f = $this->postJson('/api/pharmacy/formulations', $body)->assertCreated()->json('data.id');
        $this->postJson('/api/pharmacy/formulations', $body)->assertOk()->assertJsonPath('data.id', $f);
        $this->postJson('/api/pharmacy/formulations', array_replace($body, ['method' => 'Changed']))->assertStatus(409);
        $review = ['version' => 1, 'action' => 'review', 'evidence' => 'Synthetic independent review'];
        $this->postJson("/api/pharmacy/formulations/$f/review", $review)->assertStatus(422);
        $reviewer = $this->independentReviewer();
        $this->actingAs($reviewer, 'api');
        $this->postJson("/api/pharmacy/formulations/$f/review", $review)->assertOk()->assertJsonPath('data.status', 'reviewed');
        $this->postJson("/api/pharmacy/formulations/$f/review", $review)->assertStatus(409);
        $this->actingAs($this->actor, 'api');
        $rx = $this->rx(['compounded' => true]);
        $lot = $this->lot();
        $batch = ['request_id' => (string) Str::uuid(), 'prescription_id' => $rx, 'formulation_id' => $f, 'batch_number' => 'SYN-ONLY',
            'planned_on' => now()->toDateString(), 'calculation_reference' => 'Synthetic', 'prescription_match_reference' => 'Synthetic', 'site_process_reference' => 'Synthetic',
            'ingredients' => [['key' => 'A', 'supplier' => 'Synthetic', 'lot' => 'SYN', 'expires_on' => now()->addMonth()->toDateString(), 'quantity' => '2.000', 'unit' => 'g', 'certificate_reference' => 'NOT VALID']]];
        $b = $this->postJson('/api/pharmacy/batch-worksheets', $batch)->assertCreated()->assertJsonPath('data.production_release_enabled', false)->json('data.id');
        $this->postJson('/api/pharmacy/batch-worksheets', $batch)->assertOk()->assertJsonPath('data.id', $b);
        $this->postJson("/api/pharmacy/batch-worksheets/$b/review", $review)->assertStatus(422);
        $this->actingAs($reviewer, 'api');
        $this->postJson("/api/pharmacy/batch-worksheets/$b/review", $review)->assertOk()->assertJsonPath('data.status', 'reviewed')->assertJsonPath('data.production_release_enabled', false);
        $this->postJson("/api/pharmacy/batch-worksheets/$b/review", array_replace($review, ['version' => 2]))->assertStatus(422);
        $this->assertEquals(50, DB::table('pharmacy_stock_lots')->where('id', $lot)->value('on_hand'));
        $this->assertEquals(0, DB::table('pharmacy_stock_lots')->where('id', $lot)->value('reserved'));
        $this->assertSame(0, DB::table('pharmacy_fills')->count());
        $this->getJson("/api/pharmacy/formulations/$f")->assertOk()->assertJsonCount(2, 'data.events');
        DB::table('pharmacy_staff_assignments')->where('user_id', $reviewer->id)->update(['active' => false]);
        $this->getJson("/api/pharmacy/batch-worksheets/$b")->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_compounding_validation_and_scope_fail_closed(): void
    {
        $this->postJson('/api/pharmacy/formulations', $this->formulationBody(['preparation_type' => 'sterile']))->assertUnprocessable()->assertJsonValidationErrors('aseptic_process');
        $this->postJson('/api/pharmacy/formulations', $this->formulationBody(['hazardous' => true]))->assertUnprocessable()->assertJsonValidationErrors('hazard_controls');
        $this->actor->role = 'pharmacy_technician';
        $this->postJson('/api/pharmacy/formulations', $this->formulationBody())->assertForbidden();
        $this->actor->role = 'pharmacist';
        $f = $this->postJson('/api/pharmacy/formulations', $this->formulationBody())->assertCreated()->json('data.id');
        $rx = $this->rx(['compounded' => true]);
        $batch = ['request_id' => (string) Str::uuid(), 'prescription_id' => $rx, 'formulation_id' => $f, 'batch_number' => 'SYN-ONLY', 'planned_on' => now()->toDateString(),
            'calculation_reference' => 'Synthetic', 'prescription_match_reference' => 'Synthetic', 'site_process_reference' => 'Synthetic',
            'ingredients' => [['key' => 'A', 'supplier' => 'Synthetic', 'lot' => 'SYN', 'expires_on' => now()->addMonth()->toDateString(), 'quantity' => '2.000', 'unit' => 'g', 'certificate_reference' => 'NOT VALID']]];
        $this->postJson('/api/pharmacy/batch-worksheets', $batch)->assertUnprocessable();
        $reviewer = $this->independentReviewer();
        $this->actingAs($reviewer, 'api');
        $this->postJson("/api/pharmacy/formulations/$f/review", ['version' => 1, 'action' => 'review', 'evidence' => 'Synthetic'])->assertOk();
        $this->actingAs($this->actor, 'api');
        $wrong = $batch;
        $wrong['ingredients'][0]['quantity'] = '2.001';
        $this->postJson('/api/pharmacy/batch-worksheets', $wrong)->assertUnprocessable();
        $wrong = $batch;
        $wrong['ingredients'][0]['unit'] = 'mg';
        $this->postJson('/api/pharmacy/batch-worksheets', $wrong)->assertUnprocessable();
        $wrong = $batch;
        $wrong['ingredients'][0]['expires_on'] = now()->subDay()->toDateString();
        $this->postJson('/api/pharmacy/batch-worksheets', $wrong)->assertUnprocessable();
        $wrong = $batch;
        $wrong['prescription_id'] = $this->rx(['compounded' => true, 'compound_type' => 'sterile']);
        $this->postJson('/api/pharmacy/batch-worksheets', $wrong)->assertUnprocessable();
        $wrong = $batch;
        $wrong['prescription_id'] = $this->rx(['compounded' => true, 'quantity' => '11.000']);
        $this->postJson('/api/pharmacy/batch-worksheets', $wrong)->assertUnprocessable();
        $b = $this->postJson('/api/pharmacy/batch-worksheets', $batch)->assertCreated()->json('data.id');
        DB::table('pharmacy_staff_assignments')->where('user_id', $this->actor->id)->where('location_id', $this->location)->update(['active' => false]);
        $this->getJson("/api/pharmacy/batch-worksheets/$b")->assertNotFound();
        $this->postJson('/api/pharmacy/batch-worksheets', $batch)->assertNotFound();
        $this->getJson('/api/pharmacy/batch-worksheets')->assertOk()->assertJsonPath('data.total', 0);
        $this->actingAs($reviewer, 'api');
        $this->postJson("/api/pharmacy/formulations/$f/review", ['version' => 2, 'action' => 'retire', 'evidence' => 'Synthetic retirement'])->assertOk();
        $this->postJson("/api/pharmacy/batch-worksheets/$b/review", ['version' => 1, 'action' => 'review', 'evidence' => 'Synthetic'])->assertUnprocessable();
        $retry = $batch;
        $retry['request_id'] = (string) Str::uuid();
        $retry['batch_number'] = 'SYN-RETIRED';
        $this->postJson('/api/pharmacy/batch-worksheets', $retry)->assertUnprocessable();
        $revision = $this->postJson('/api/pharmacy/formulations', $this->formulationBody(['preparation_type' => 'sterile', 'aseptic_process' => 'SYNTHETIC NOT FOR USE', 'hazardous' => true, 'hazard_controls' => 'SYNTHETIC NOT FOR USE']))->assertCreated();
        $revision->assertJsonPath('data.revision', 2)->assertJsonPath('data.status', 'draft');
        $this->assertSame(1, DB::table('pharmacy_batch_worksheets')->count());
        $this->assertSame(1, DB::table('pharmacy_compounding_events')->where('batch_id', $b)->count());
        $reviewer->organization_id = 2;
        $this->getJson("/api/pharmacy/formulations/$f")->assertForbidden();
        $foreign = DB::table('pharmacy_locations')->insertGetId(['organization_id' => 2, 'name' => 'Other tenant synthetic', 'address' => 'NOT REAL', 'license_reference' => 'NOT VALID']);
        DB::table('pharmacy_staff_assignments')->insert(['location_id' => $foreign, 'user_id' => $reviewer->id, 'active' => true, 'valid_until' => now()->addYear()->toDateString()]);
        $this->getJson("/api/pharmacy/formulations/$f")->assertNotFound();
        $this->getJson("/api/pharmacy/batch-worksheets/$b")->assertNotFound();
        $this->getJson('/api/pharmacy/formulations')->assertOk()->assertJsonPath('data.total', 0);
    }

    private function ingredientReceipt(array $overrides = []): array
    {
        return array_replace(['request_id' => (string) Str::uuid(), 'location_id' => $this->location, 'ingredient_name' => 'Synthetic ingredient', 'supplier' => 'Synthetic', 'lot_number' => 'SYN',
            'quantity_unit' => 'g', 'quantity' => '5.000', 'expires_on' => now()->addMonth()->toDateString(), 'specification' => 'NOT FOR USE', 'certificate_reference' => 'NOT VALID', 'receipt_reference' => 'SYN RECEIPT'], $overrides);
    }

    private function reviewedWorksheet(bool $twoIngredients = false): int
    {
        $body = $this->formulationBody();
        if ($twoIngredients) {
            $body['ingredients'][] = array_replace($body['ingredients'][0], ['key' => 'B']);
        }
        $f = $this->postJson('/api/pharmacy/formulations', $body)->assertCreated()->json('data.id');
        $reviewer = $this->independentReviewer();
        $this->actingAs($reviewer, 'api');
        $this->postJson("/api/pharmacy/formulations/$f/review", ['version' => 1, 'action' => 'review', 'evidence' => 'Synthetic'])->assertOk();
        $this->actingAs($this->actor, 'api');
        $rx = $this->rx(['compounded' => true]);
        $line = ['key' => 'A', 'supplier' => 'Synthetic', 'lot' => 'SYN', 'expires_on' => now()->addMonth()->toDateString(), 'quantity' => '2.000', 'unit' => 'g', 'certificate_reference' => 'NOT VALID'];
        $b = $this->postJson('/api/pharmacy/batch-worksheets', ['request_id' => (string) Str::uuid(), 'prescription_id' => $rx, 'formulation_id' => $f, 'batch_number' => 'SYN-'.Str::uuid(),
            'planned_on' => now()->toDateString(), 'calculation_reference' => 'Synthetic', 'prescription_match_reference' => 'Synthetic', 'site_process_reference' => 'Synthetic',
            'ingredients' => $twoIngredients ? [$line, array_replace($line, ['key' => 'B'])] : [$line]])->assertCreated()->json('data.id');
        $this->actingAs($reviewer, 'api');
        $this->postJson("/api/pharmacy/batch-worksheets/$b/review", ['version' => 1, 'action' => 'review', 'evidence' => 'Synthetic'])->assertOk();
        $this->actingAs($this->actor, 'api');

        return $b;
    }

    public function test_ingredient_custody_reservations_and_release_are_exact_and_replay_safe(): void
    {
        $b = $this->reviewedWorksheet();
        $body = $this->ingredientReceipt();
        $lot = $this->postJson('/api/pharmacy/ingredient-lots', $body)->assertCreated()->assertJsonPath('data.status', 'quarantined')->json('data.id');
        $this->postJson('/api/pharmacy/ingredient-lots', $body)->assertOk()->assertJsonPath('data.id', $lot);
        $this->postJson('/api/pharmacy/ingredient-lots', array_replace($body, ['request_id' => (string) Str::uuid()]))->assertStatus(409);
        $this->postJson('/api/pharmacy/ingredient-lots', array_replace($body, ['quantity' => '6.000']))->assertStatus(409);
        $reserve = ['version' => 2, 'action' => 'reserve', 'evidence' => 'Synthetic reconciliation', 'lots' => [['key' => 'A', 'lot_id' => $lot]]];
        $this->postJson("/api/pharmacy/batch-worksheets/$b/allocation", $reserve)->assertUnprocessable();
        $status = ['version' => 1, 'status' => 'available', 'evidence' => 'Synthetic receiving review'];
        $this->actor->role = 'pharmacy_technician';
        $this->postJson("/api/pharmacy/ingredient-lots/$lot/status", $status)->assertForbidden();
        $this->postJson("/api/pharmacy/batch-worksheets/$b/allocation", $reserve)->assertForbidden();
        $this->actor->role = 'pharmacist';
        $this->postJson("/api/pharmacy/ingredient-lots/$lot/status", $status)->assertOk();
        $this->postJson("/api/pharmacy/ingredient-lots/$lot/status", $status)->assertStatus(409);
        $this->postJson("/api/pharmacy/batch-worksheets/$b/allocation", $reserve)->assertOk()->assertJsonPath('data.version', 3)->assertJsonPath('data.production_release_enabled', false);
        $this->postJson("/api/pharmacy/batch-worksheets/$b/allocation", $reserve)->assertStatus(409);
        $this->assertEquals(2, DB::table('pharmacy_ingredient_lots')->where('id', $lot)->value('reserved'));
        $this->assertEquals(5, DB::table('pharmacy_ingredient_lots')->where('id', $lot)->value('on_hand'));
        $this->postJson("/api/pharmacy/ingredient-lots/$lot/status", ['version' => 3, 'status' => 'quarantined', 'evidence' => 'Synthetic hold'])->assertOk();
        $this->getJson("/api/pharmacy/batch-worksheets/$b")->assertOk()->assertJsonPath('data.allocations.0.lot_status', 'quarantined');
        $this->postJson("/api/pharmacy/batch-worksheets/$b/allocation", ['version' => 3, 'action' => 'release', 'evidence' => 'Synthetic cancelled plan'])->assertOk()->assertJsonPath('data.allocations.0.status', 'released');
        $this->assertEquals(0, DB::table('pharmacy_ingredient_lots')->where('id', $lot)->value('reserved'));
        $this->assertEquals(5, DB::table('pharmacy_ingredient_lots')->where('id', $lot)->value('on_hand'));
        $this->postJson("/api/pharmacy/batch-worksheets/$b/allocation", array_replace($reserve, ['version' => 4]))->assertUnprocessable();
        $this->assertSame(1, DB::table('pharmacy_ingredient_events')->where('action', 'reserved')->count());
        $this->assertSame(1, DB::table('pharmacy_ingredient_events')->where('action', 'reservation_released')->count());
        $this->assertSame(0, DB::table('pharmacy_stock_lots')->count());
        $this->assertSame(0, DB::table('pharmacy_fills')->count());
    }

    public function test_ingredient_allocation_rolls_back_all_lines_on_shortage_and_checks_scope(): void
    {
        $b = $this->reviewedWorksheet(true);
        $lot = $this->postJson('/api/pharmacy/ingredient-lots', $this->ingredientReceipt(['quantity' => '3.999']))->assertCreated()->json('data.id');
        $this->postJson("/api/pharmacy/ingredient-lots/$lot/status", ['version' => 1, 'status' => 'available', 'evidence' => 'Synthetic'])->assertOk();
        $body = ['version' => 2, 'action' => 'reserve', 'evidence' => 'Synthetic', 'lots' => [['key' => 'A', 'lot_id' => $lot], ['key' => 'B', 'lot_id' => $lot]]];
        $this->postJson("/api/pharmacy/batch-worksheets/$b/allocation", $body)->assertUnprocessable();
        $this->assertEquals(0, DB::table('pharmacy_ingredient_lots')->where('id', $lot)->value('reserved'));
        $this->assertSame(0, DB::table('pharmacy_ingredient_allocations')->count());
        $this->assertSame(0, DB::table('pharmacy_ingredient_events')->where('action', 'reserved')->count());
        $this->assertEquals(2, DB::table('pharmacy_batch_worksheets')->where('id', $b)->value('version'));
        $foreign = $this->postJson('/api/pharmacy/ingredient-lots', $this->ingredientReceipt(['location_id' => $this->otherLocation]))->assertCreated()->json('data.id');
        $body['lots'][0]['lot_id'] = $foreign;
        $this->postJson("/api/pharmacy/batch-worksheets/$b/allocation", $body)->assertNotFound();
        DB::table('pharmacy_staff_assignments')->where('user_id', $this->actor->id)->where('location_id', $this->location)->update(['active' => false]);
        $this->getJson("/api/pharmacy/ingredient-lots/$lot")->assertNotFound();
        $this->getJson('/api/pharmacy/ingredient-lots')->assertOk()->assertJsonPath('data.total', 1);
        $this->postJson("/api/pharmacy/batch-worksheets/$b/allocation", $body)->assertNotFound();
    }

    public function test_ingredient_mismatch_and_expiration_cannot_be_reserved(): void
    {
        $b = $this->reviewedWorksheet();
        $lot = $this->postJson('/api/pharmacy/ingredient-lots', $this->ingredientReceipt(['specification' => 'Different grade']))->assertCreated()->json('data.id');
        $this->postJson("/api/pharmacy/ingredient-lots/$lot/status", ['version' => 1, 'status' => 'available', 'evidence' => 'Synthetic'])->assertOk();
        $body = ['version' => 2, 'action' => 'reserve', 'evidence' => 'Synthetic', 'lots' => [['key' => 'A', 'lot_id' => $lot]]];
        $this->postJson("/api/pharmacy/batch-worksheets/$b/allocation", $body)->assertUnprocessable();
        $good = $this->postJson('/api/pharmacy/ingredient-lots', $this->ingredientReceipt(['receipt_reference' => 'SYN SECOND']))->assertCreated()->json('data.id');
        DB::table('pharmacy_ingredient_lots')->where('id', $good)->update(['expires_on' => now()->subDay()->toDateString()]);
        $this->postJson("/api/pharmacy/ingredient-lots/$good/status", ['version' => 1, 'status' => 'available', 'evidence' => 'Synthetic'])->assertUnprocessable();
        $this->travel(2)->days();
        $this->actor->withAccessToken(new Token(['expires_at' => now()->addHour()]));
        $this->postJson("/api/pharmacy/batch-worksheets/$b/allocation", $body)->assertUnprocessable();
        $this->travelBack();
        $this->assertSame(0, DB::table('pharmacy_ingredient_allocations')->count());
    }

    public function test_ingredient_count_quarantines_and_applies_once_with_independent_review(): void
    {
        $lot = $this->postJson('/api/pharmacy/ingredient-lots', $this->ingredientReceipt())->assertCreated()->json('data.id');
        $this->postJson("/api/pharmacy/ingredient-lots/$lot/status", ['version' => 1, 'status' => 'available', 'evidence' => 'Synthetic'])->assertOk();
        $body = ['request_id' => (string) Str::uuid(), 'version' => 2, 'counted_quantity' => '4.875', 'reason' => 'observed_loss', 'evidence' => 'Synthetic physical count'];
        $url = "/api/pharmacy/ingredient-lots/$lot/counts";
        $r = $this->postJson($url, $body)->assertCreated()->assertJsonPath('data.status', 'quarantined');
        $count = $r->json('data.counts.0.id');
        $this->assertEquals(5, $r->json('data.on_hand'));
        $this->postJson($url, $body)->assertOk()->assertJsonCount(1, 'data.counts');
        $this->postJson($url, array_replace($body, ['counted_quantity' => '4']))->assertStatus(409);
        $this->postJson("/api/pharmacy/ingredient-lots/$lot/status", ['version' => 3, 'status' => 'available', 'evidence' => 'Synthetic'])->assertUnprocessable();
        $review = ['decision' => 'apply', 'evidence' => 'Synthetic independent count verification'];
        $this->postJson("$url/$count/review", $review)->assertUnprocessable();
        $this->actingAs($this->independentReviewer(), 'api');
        $this->postJson("$url/$count/review", $review)->assertOk()->assertJsonPath('data.counts.0.status', 'applied')->assertJsonPath('data.status', 'quarantined');
        $this->postJson("$url/$count/review", $review)->assertStatus(409);
        $this->assertEquals(4.875, DB::table('pharmacy_ingredient_lots')->where('id', $lot)->value('on_hand'));
        $this->assertEquals(-0.125, DB::table('pharmacy_ingredient_events')->where('action', 'count_adjustment_applied')->value('quantity'));
        $this->assertSame(1, DB::table('pharmacy_ingredient_events')->where('action', 'count_adjustment_applied')->count());
    }

    public function test_counts_protect_reservations_and_stale_counts_require_rejection(): void
    {
        [$b, $lot] = $this->reservedWorksheet();
        $version = DB::table('pharmacy_ingredient_lots')->where('id', $lot)->value('version');
        $body = ['request_id' => (string) Str::uuid(), 'version' => $version, 'counted_quantity' => '1', 'reason' => 'physical_count', 'evidence' => 'Synthetic shortage'];
        $url = "/api/pharmacy/ingredient-lots/$lot/counts";
        $count = $this->postJson($url, $body)->assertCreated()->json('data.counts.0.id');
        $this->postJson("/api/pharmacy/batch-worksheets/$b/execution", $this->executionBody())->assertUnprocessable();
        $this->actingAs($this->independentReviewer(), 'api');
        $review = ['decision' => 'apply', 'evidence' => 'Synthetic'];
        $this->postJson("$url/$count/review", $review)->assertUnprocessable();
        $this->postJson("/api/pharmacy/batch-worksheets/$b/allocation", ['version' => 3, 'action' => 'release', 'evidence' => 'Synthetic shortage'])->assertOk();
        $this->postJson("$url/$count/review", $review)->assertStatus(409);
        $this->postJson("$url/$count/review", array_replace($review, ['decision' => 'reject']))->assertOk()->assertJsonPath('data.counts.0.status', 'rejected')->assertJsonPath('data.status', 'quarantined');
        $this->assertEquals(5, DB::table('pharmacy_ingredient_lots')->where('id', $lot)->value('on_hand'));
        $this->assertSame(0, DB::table('pharmacy_ingredient_events')->where('action', 'count_adjustment_applied')->count());
    }

    public function test_count_roles_scope_validation_and_zero_balance(): void
    {
        $lot = $this->postJson('/api/pharmacy/ingredient-lots', $this->ingredientReceipt())->assertCreated()->json('data.id');
        $url = "/api/pharmacy/ingredient-lots/$lot/counts";
        $body = ['request_id' => (string) Str::uuid(), 'version' => 1, 'counted_quantity' => '0', 'reason' => 'observed_loss', 'evidence' => 'Synthetic zero count'];
        $this->postJson($url, array_replace($body, ['counted_quantity' => '-1']))->assertUnprocessable();
        $this->postJson($url, array_replace($body, ['counted_quantity' => '6']))->assertUnprocessable();
        $this->actor->role = 'pharmacy_technician';
        $count = $this->postJson($url, $body)->assertCreated()->json('data.counts.0.id');
        $this->postJson("$url/$count/review", ['decision' => 'apply', 'evidence' => 'Synthetic'])->assertForbidden();
        $reviewer = $this->independentReviewer();
        $this->actingAs($reviewer, 'api');
        $this->postJson("$url/$count/review", ['decision' => 'apply', 'evidence' => 'Synthetic'])->assertOk();
        $this->assertEquals(0, DB::table('pharmacy_ingredient_lots')->where('id', $lot)->value('on_hand'));
        $this->actor->role = 'pharmacist';
        $this->actingAs($this->actor, 'api');
        DB::table('pharmacy_staff_assignments')->where('user_id', $this->actor->id)->update(['active' => false]);
        $this->postJson($url, $body)->assertNotFound();
        $this->actor->organization_id = 2;
        $this->postJson("$url/$count/review", ['decision' => 'apply', 'evidence' => 'Synthetic'])->assertNotFound();
    }

    public function test_recall_blocks_stock_use_and_preserves_trace_and_quantities(): void
    {
        [$b, $lot] = $this->reservedWorksheet();
        $version = DB::table('pharmacy_ingredient_lots')->where('id', $lot)->value('version');
        $body = ['version' => $version, 'reference' => 'SYN-RECALL', 'evidence' => 'Synthetic receipt match'];
        $url = "/api/pharmacy/ingredient-lots/$lot";
        $this->actor->role = 'pharmacy_technician';
        $this->postJson("$url/recall", $body)->assertForbidden();
        $this->actor->role = 'pharmacist';
        $this->postJson("$url/recall", $body)->assertOk()->assertJsonPath('data.status', 'recalled')->assertJsonPath('data.trace.0.batch_id', $b)->assertJsonPath('data.trace.0.status', 'reserved');
        $this->postJson("$url/recall", $body)->assertStatus(409);
        $this->postJson("$url/status", ['version' => $version + 1, 'status' => 'available', 'evidence' => 'Bypass attempt'])->assertUnprocessable();
        $this->postJson("$url/status", ['version' => $version + 1, 'status' => 'quarantined', 'evidence' => 'Bypass attempt'])->assertUnprocessable();
        $this->postJson("$url/counts", ['version' => $version + 1, 'request_id' => (string) Str::uuid(), 'counted_quantity' => '4', 'reason' => 'physical_count', 'evidence' => 'Synthetic'])->assertUnprocessable();
        $this->postJson("/api/pharmacy/batch-worksheets/$b/execution", $this->executionBody())->assertUnprocessable();
        $this->getJson("/api/pharmacy/batch-worksheets/$b")->assertOk()->assertJsonPath('data.allocations.0.lot_status', 'recalled');
        $this->postJson("/api/pharmacy/batch-worksheets/$b/allocation", ['version' => 3, 'action' => 'release', 'evidence' => 'Synthetic recall'])->assertOk();
        $this->getJson($url)->assertOk()->assertJsonPath('data.status', 'recalled')->assertJsonPath('data.trace.0.status', 'released');
        $this->assertEquals(5, DB::table('pharmacy_ingredient_lots')->where('id', $lot)->value('on_hand'));
        $this->assertEquals(0, DB::table('pharmacy_ingredient_lots')->where('id', $lot)->value('reserved'));
        $this->assertSame(1, DB::table('pharmacy_ingredient_events')->where('action', 'recall_hold_recorded')->count());
        DB::table('pharmacy_staff_assignments')->where('user_id', $this->actor->id)->update(['active' => false]);
        $this->getJson($url)->assertNotFound();
        $this->postJson("$url/recall", $body)->assertNotFound();
    }

    public function test_recall_traces_consumed_batches_and_invalidates_pending_count(): void
    {
        [$b, $lot] = $this->reservedWorksheet();
        $this->postJson("/api/pharmacy/batch-worksheets/$b/execution", $this->executionBody())->assertCreated();
        $original = DB::table('pharmacy_batch_executions')->value('record');
        $version = DB::table('pharmacy_ingredient_lots')->where('id', $lot)->value('version');
        $url = "/api/pharmacy/ingredient-lots/$lot";
        $count = $this->postJson("$url/counts", ['version' => $version, 'request_id' => (string) Str::uuid(), 'counted_quantity' => '2', 'reason' => 'physical_count', 'evidence' => 'Synthetic'])->assertCreated()->json('data.counts.0.id');
        $this->postJson("$url/recall", ['version' => $version + 1, 'reference' => 'SYN-RECALL', 'evidence' => 'Synthetic'])->assertOk()->assertJsonPath('data.trace.0.status', 'consumed');
        $this->actingAs($this->independentReviewer(), 'api');
        $this->postJson("$url/counts/$count/review", ['decision' => 'apply', 'evidence' => 'Synthetic'])->assertStatus(409);
        $this->postJson("$url/counts/$count/review", ['decision' => 'reject', 'evidence' => 'Recalled receipt'])->assertOk()->assertJsonPath('data.status', 'recalled');
        $this->assertSame($original, DB::table('pharmacy_batch_executions')->value('record'));
        $this->assertEquals(3, DB::table('pharmacy_ingredient_lots')->where('id', $lot)->value('on_hand'));
        $this->getJson("/api/pharmacy/batch-worksheets/$b")->assertOk()->assertJsonPath('data.output_status', 'quarantined')->assertJsonPath('data.production_release_enabled', false);
    }

    public function test_recall_worklist_filters_and_search_preserve_location_and_organization_scope(): void
    {
        $lot = $this->postJson('/api/pharmacy/ingredient-lots', $this->ingredientReceipt(['supplier' => 'Unique Supplier']))->assertCreated()->json('data.id');
        $other = $this->postJson('/api/pharmacy/ingredient-lots', $this->ingredientReceipt(['location_id' => $this->otherLocation, 'receipt_reference' => 'Other receipt', 'supplier' => 'Unique Supplier']))->assertCreated()->json('data.id');
        foreach ([$lot, $other] as $id) {
            $this->postJson("/api/pharmacy/ingredient-lots/$id/recall", ['version' => 1, 'reference' => 'SYN-NOTICE-42', 'evidence' => 'Synthetic'])->assertOk();
        }
        $this->getJson('/api/pharmacy/ingredient-lots?status=recalled&search=SYN-NOTICE-42')->assertOk()->assertJsonPath('data.total', 2);
        $this->getJson('/api/pharmacy/ingredient-lots?search=Unique%20Supplier')->assertOk()->assertJsonPath('data.total', 2);
        $this->getJson('/api/pharmacy/ingredient-lots?status=available')->assertOk()->assertJsonPath('data.total', 0);
        $this->getJson('/api/pharmacy/ingredient-lots?status=unsupported')->assertUnprocessable();
        $reviewer = $this->independentReviewer();
        $this->actingAs($reviewer, 'api');
        $this->getJson('/api/pharmacy/ingredient-lots?status=recalled&search=SYN-NOTICE-42')->assertOk()->assertJsonPath('data.total', 1)->assertJsonPath('data.data.0.id', $lot)->assertJsonPath('data.data.0.recall_reference', 'SYN-NOTICE-42');
        $this->getJson("/api/pharmacy/ingredient-lots?status=recalled&location_id=$this->otherLocation")->assertOk()->assertJsonPath('data.total', 0);
        DB::table('pharmacy_ingredient_lots')->where('id', $lot)->update(['organization_id' => 2]);
        $this->getJson('/api/pharmacy/ingredient-lots?status=recalled&search=SYN-NOTICE-42')->assertOk()->assertJsonPath('data.total', 0);
    }

    private function reservedWorksheet(bool $two = false): array
    {
        $b = $this->reviewedWorksheet($two);
        $lot = $this->postJson('/api/pharmacy/ingredient-lots', $this->ingredientReceipt())->assertCreated()->json('data.id');
        $this->postJson("/api/pharmacy/ingredient-lots/$lot/status", ['version' => 1, 'status' => 'available', 'evidence' => 'Synthetic'])->assertOk();
        $lots = [['key' => 'A', 'lot_id' => $lot]];
        if ($two) {
            $lots[] = ['key' => 'B', 'lot_id' => $lot];
        }
        $this->postJson("/api/pharmacy/batch-worksheets/$b/allocation", ['version' => 2, 'action' => 'reserve', 'evidence' => 'Synthetic', 'lots' => $lots])->assertOk();

        return [$b, $lot];
    }

    private function executionBody(bool $two = false): array
    {
        $ingredients = [['key' => 'A', 'quantity' => '2.000', 'unit' => 'g', 'measurement_reference' => 'SYNTHETIC ONLY']];
        if ($two) {
            $ingredients[] = array_replace($ingredients[0], ['key' => 'B']);
        }

        return ['version' => 3, 'prepared_on' => now()->toDateString(), 'personnel_reference' => 'SYNTHETIC ONLY', 'equipment_reference' => 'SYNTHETIC ONLY',
            'process_record_reference' => 'SYNTHETIC ONLY', 'quality_results_reference' => 'SYNTHETIC ONLY', 'yield_quantity' => '9.000', 'yield_unit' => 'tablet',
            'deviations' => 'SYNTHETIC yield difference, quarantined; not for use', 'ingredients' => $ingredients];
    }

    public function test_execution_consumes_reserved_ingredients_once_and_never_releases_product(): void
    {
        [$b,$lot] = $this->reservedWorksheet();
        $body = $this->executionBody();
        $this->actor->role = 'pharmacy_technician';
        $this->postJson("/api/pharmacy/batch-worksheets/$b/execution", $body)->assertForbidden();
        $this->actor->role = 'pharmacist';
        $this->postJson("/api/pharmacy/batch-worksheets/$b/execution", $body)->assertCreated()->assertJsonPath('data.output_status', 'quarantined')->assertJsonPath('data.allocations.0.status', 'consumed')->assertJsonPath('data.production_release_enabled', false);
        $this->postJson("/api/pharmacy/batch-worksheets/$b/execution", $body)->assertStatus(409);
        $this->postJson("/api/pharmacy/batch-worksheets/$b/execution", array_replace($body, ['version' => 4]))->assertStatus(409);
        $this->assertEquals(3, DB::table('pharmacy_ingredient_lots')->where('id', $lot)->value('on_hand'));
        $this->assertEquals(0, DB::table('pharmacy_ingredient_lots')->where('id', $lot)->value('reserved'));
        $this->postJson("/api/pharmacy/batch-worksheets/$b/allocation", ['version' => 4, 'action' => 'release', 'evidence' => 'Synthetic'])->assertUnprocessable();
        $review = ['version' => 1, 'decision' => 'document_reviewed', 'evidence' => 'Synthetic document review, not product release'];
        $this->postJson("/api/pharmacy/batch-worksheets/$b/execution/review", $review)->assertUnprocessable();
        $this->actingAs($this->independentReviewer(), 'api');
        $this->postJson("/api/pharmacy/batch-worksheets/$b/execution/review", $review)->assertOk()->assertJsonPath('data.execution.status', 'document_reviewed')->assertJsonPath('data.output_status', 'quarantined')->assertJsonPath('data.production_release_enabled', false);
        $this->postJson("/api/pharmacy/batch-worksheets/$b/execution/review", $review)->assertStatus(409);
        $this->assertSame(1, DB::table('pharmacy_ingredient_events')->where('action', 'consumed_in_preparation')->count());
        $this->assertSame(1, DB::table('pharmacy_batch_executions')->count());
        $this->assertSame(0, DB::table('pharmacy_stock_lots')->count());
        $this->assertSame(0, DB::table('pharmacy_fills')->count());
    }

    public function test_execution_addenda_retain_original_reset_review_and_never_change_stock(): void
    {
        [$b, $lot] = $this->reservedWorksheet();
        $this->postJson("/api/pharmacy/batch-worksheets/$b/execution", $this->executionBody())->assertCreated();
        $original = DB::table('pharmacy_batch_executions')->where('batch_id', $b)->value('record');
        $reviewer = $this->independentReviewer();
        $this->actingAs($reviewer, 'api');
        $this->postJson("/api/pharmacy/batch-worksheets/$b/execution/review", ['version' => 1, 'decision' => 'document_reviewed', 'evidence' => 'Synthetic review'])->assertOk();
        $this->actingAs($this->actor, 'api');
        $body = ['version' => 2, 'request_id' => (string) Str::uuid(), 'section' => 'equipment', 'statement' => 'Synthetic corrected equipment reference', 'reason' => 'Transcription correction', 'evidence' => 'Synthetic source'];
        $url = "/api/pharmacy/batch-worksheets/$b/execution/addenda";
        $this->postJson($url, $body)->assertCreated()->assertJsonPath('data.execution.status', 'quarantined')->assertJsonPath('data.execution.version', 3)->assertJsonCount(1, 'data.execution.addenda')->assertJsonPath('data.output_status', 'quarantined');
        $this->postJson($url, $body)->assertOk();
        $this->postJson($url, array_replace($body, ['statement' => 'changed']))->assertStatus(409);
        $this->postJson($url, array_replace($body, ['request_id' => (string) Str::uuid()]))->assertStatus(409);
        $this->postJson("/api/pharmacy/batch-worksheets/$b/execution/review", ['version' => 3, 'decision' => 'document_reviewed', 'evidence' => 'Self review'])->assertUnprocessable();
        $this->actingAs($reviewer, 'api');
        $this->postJson("/api/pharmacy/batch-worksheets/$b/execution/review", ['version' => 2, 'decision' => 'document_reviewed', 'evidence' => 'Stale review'])->assertStatus(409);
        $this->postJson("/api/pharmacy/batch-worksheets/$b/execution/review", ['version' => 3, 'decision' => 'rejected', 'evidence' => 'Synthetic rejection'])->assertOk();
        $this->postJson($url, array_replace($body, ['version' => 4, 'request_id' => (string) Str::uuid()]))->assertCreated()->assertJsonPath('data.execution.status', 'rejected');
        $this->assertSame($original, DB::table('pharmacy_batch_executions')->where('batch_id', $b)->value('record'));
        $this->assertEquals(3, DB::table('pharmacy_ingredient_lots')->where('id', $lot)->value('on_hand'));
        $this->assertSame(1, DB::table('pharmacy_ingredient_events')->where('action', 'consumed_in_preparation')->count());
        $this->assertSame(2, DB::table('pharmacy_execution_addenda')->count());
        $this->assertSame(0, DB::table('pharmacy_stock_lots')->count());
    }

    public function test_addendum_contributors_cannot_review_and_access_is_scoped(): void
    {
        [$b] = $this->reservedWorksheet();
        $this->postJson("/api/pharmacy/batch-worksheets/$b/execution", $this->executionBody())->assertCreated();
        $reviewer = $this->independentReviewer();
        $this->actingAs($reviewer, 'api');
        $url = "/api/pharmacy/batch-worksheets/$b/execution/addenda";
        $body = ['version' => 1, 'request_id' => (string) Str::uuid(), 'section' => 'other', 'statement' => 'Synthetic clarification', 'reason' => 'Additional evidence', 'evidence' => 'Synthetic'];
        $this->postJson($url, array_replace($body, ['reason' => '']))->assertUnprocessable();
        $this->postJson($url, $body)->assertCreated();
        $this->postJson("/api/pharmacy/batch-worksheets/$b/execution/review", ['version' => 2, 'decision' => 'document_reviewed', 'evidence' => 'Contributor review'])->assertUnprocessable();
        $reviewer->role = 'pharmacy_technician';
        $this->postJson($url, $body)->assertForbidden();
        $reviewer->role = 'pharmacist';
        DB::table('pharmacy_staff_assignments')->where('user_id', $reviewer->id)->update(['active' => false]);
        $this->postJson($url, $body)->assertNotFound();
        $this->getJson("/api/pharmacy/batch-worksheets/$b")->assertForbidden();
        $reviewer->organization_id = 2;
        $this->postJson($url, $body)->assertNotFound();
        $this->assertSame(1, DB::table('pharmacy_execution_addenda')->count());
    }

    public function test_execution_rechecks_custody_and_rolls_back_partial_consumption(): void
    {
        [$b,$lot] = $this->reservedWorksheet(true);
        $body = $this->executionBody(true);
        $body['ingredients'][1]['quantity'] = '1.999';
        $this->postJson("/api/pharmacy/batch-worksheets/$b/execution", $body)->assertUnprocessable();
        $this->assertEquals(5, DB::table('pharmacy_ingredient_lots')->where('id', $lot)->value('on_hand'));
        $this->assertEquals(4, DB::table('pharmacy_ingredient_lots')->where('id', $lot)->value('reserved'));
        $this->assertSame(0, DB::table('pharmacy_ingredient_events')->where('action', 'consumed_in_preparation')->count());
        $this->assertSame(0, DB::table('pharmacy_batch_executions')->count());
        $body = $this->executionBody(true);
        $this->postJson("/api/pharmacy/ingredient-lots/$lot/status", ['version' => 4, 'status' => 'quarantined', 'evidence' => 'Synthetic hold'])->assertOk();
        $this->postJson("/api/pharmacy/batch-worksheets/$b/execution", $body)->assertUnprocessable();
        DB::table('pharmacy_ingredient_lots')->where('id', $lot)->update(['status' => 'available', 'expires_on' => now()->subDay()->toDateString()]);
        $this->postJson("/api/pharmacy/batch-worksheets/$b/execution", $body)->assertUnprocessable();
        DB::table('pharmacy_staff_assignments')->where('user_id', $this->actor->id)->where('location_id', $this->location)->update(['active' => false]);
        $this->postJson("/api/pharmacy/batch-worksheets/$b/execution", $body)->assertNotFound();
        $this->assertSame(0, DB::table('pharmacy_batch_executions')->count());
    }

    public function test_execution_requires_sterile_and_hazard_evidence_and_current_review(): void
    {
        [$b,$lot] = $this->reservedWorksheet();
        $body = $this->executionBody();
        $f = DB::table('pharmacy_batch_worksheets')->where('id', $b)->value('formulation_id');
        DB::table('pharmacy_formulations')->where('id', $f)->update(['preparation_type' => 'sterile', 'hazardous' => true]);
        $this->postJson("/api/pharmacy/batch-worksheets/$b/execution", $body)->assertUnprocessable();
        $body['environment_reference'] = 'SYNTHETIC ONLY';
        $this->postJson("/api/pharmacy/batch-worksheets/$b/execution", $body)->assertUnprocessable();
        $body['hazard_control_reference'] = 'SYNTHETIC ONLY';
        $this->postJson("/api/pharmacy/batch-worksheets/$b/execution", array_replace($body, ['yield_unit' => 'g']))->assertUnprocessable();
        $this->postJson("/api/pharmacy/batch-worksheets/$b/execution", array_replace($body, ['yield_quantity' => '11.000']))->assertUnprocessable();
        DB::table('pharmacy_formulations')->where('id', $f)->update(['status' => 'retired']);
        $this->postJson("/api/pharmacy/batch-worksheets/$b/execution", $body)->assertUnprocessable();
        $this->assertEquals(5,DB::table('pharmacy_ingredient_lots')->where('id',$lot)->value('on_hand'));
        $this->assertSame(0,DB::table('pharmacy_batch_executions')->count());
    }
}
