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
        $f = $this->fill($rx, $dest);
        $this->act($rx, $f, 'approve', $this->checks(), 422); // source verification is not inherited
        $this->postJson("/api/pharmacy/stock/$dest/product", $this->productBody())->assertCreated();
        $f = $this->getJson("/api/pharmacy/prescriptions/$rx")->assertOk()->json("data.fills.0");
        $f = $this->act($rx, $f, 'approve', $this->checks());
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

    private function dispositionBody(int $lot, array $changes = []): array
    {
        return array_replace(['request_id' => (string) Str::uuid(), 'version' => (int) DB::table('pharmacy_stock_lots')->where('id', $lot)->value('version'),
            'product_id' => (int) DB::table('pharmacy_stock_products')->where('stock_lot_id', $lot)->max('id'),
            'quantity' => '2.125', 'kind' => 'supplier_return', 'occurred_on' => now()->toDateString(), 'destination' => 'SYNTHETIC receiving supplier',
            'classification_evidence' => 'SYNTHETIC manufactured noncontrolled nonhazardous stock', 'authority_reference' => 'SYNTHETIC return instructions',
            'completion_reference' => 'SYNTHETIC completed receipt, no actual transport', 'reason' => 'Synthetic acceptance', 'scope_confirmed' => true], $changes);
    }

    private function dispositionReview(array $changes = []): array
    {
        return array_replace(['request_id' => (string) Str::uuid(), 'decision' => 'apply', 'evidence' => 'SYNTHETIC independently verified classification, source, quantity and completed event', 'confirmed' => true], $changes);
    }

    public function test_disposition_holds_then_independently_deducts_once_without_clearing_recall(): void
    {
        $lot = $this->lot(); $url = "/api/pharmacy/stock/$lot/dispositions";
        $this->recallStock($lot)->assertOk();
        $body = $this->dispositionBody($lot);
        $id = $this->postJson($url, $body)->assertCreated()->assertJsonPath('data.pending', true)->json('data.records.data.0.id');
        $this->postJson($url, $body)->assertOk()->assertJsonPath('data.records.total', 1);
        $this->postJson($url, array_replace($body, ['quantity' => '3']))->assertStatus(409);
        $this->assertEquals(50, DB::table('pharmacy_stock_lots')->find($lot)->on_hand);
        $this->assertSame('recalled', DB::table('pharmacy_stock_lots')->find($lot)->status);
        $this->postJson($url, $this->dispositionBody($lot))->assertStatus(422);
        $this->putJson("/api/pharmacy/stock/$lot/status", ['version' => 3, 'status' => 'available', 'note' => 'Synthetic'])->assertStatus(422);
        $review = $this->dispositionReview();
        $this->postJson("$url/$id/review", $review)->assertStatus(422);
        $this->actingAs($this->independentReviewer(), 'api');
        $this->postJson("$url/$id/review", $review)->assertOk()->assertJsonPath('data.pending', false)->assertJsonPath('data.records.data.0.status', 'applied');
        $this->postJson("$url/$id/review", $review)->assertOk();
        $this->postJson("$url/$id/review", array_replace($review, ['evidence' => 'changed']))->assertStatus(409);
        $this->assertEquals(47.875, DB::table('pharmacy_stock_lots')->find($lot)->on_hand);
        $this->assertSame('recalled', DB::table('pharmacy_stock_lots')->find($lot)->status);
        $this->assertSame(1, DB::table('pharmacy_stock_events')->where('action', 'disposition_applied')->count());
        $this->assertEquals(-2.125, DB::table('pharmacy_stock_events')->where('action', 'disposition_applied')->value('quantity'));
        $this->getJson($url)->assertOk()->assertJsonMissingPath('data.records.data.0.source_snapshot')->assertJsonMissingPath('data.records.data.0.request_hash');
        $this->deleteJson("$url/$id")->assertNotFound(); $this->putJson("$url/$id", ['quantity' => '1'])->assertNotFound();
        $this->actingAs($this->actor, 'api'); $this->postJson($url, $body)->assertOk();
        $this->assertSame(0, DB::table('payments')->count()); $this->assertSame(0, DB::table('invoices')->count());
    }

    public function test_disposition_pending_blocks_use_counts_and_stock_release_then_rejection_keeps_quarantine(): void
    {
        $lot = $this->lot(); $rx = $this->rx(); $url = "/api/pharmacy/stock/$lot/dispositions";
        $id = $this->postJson($url, $this->dispositionBody($lot))->assertCreated()->json('data.records.data.0.id');
        $this->postJson("/api/pharmacy/stock/$lot/counts", $this->stockCountBody($lot))->assertStatus(422);
        $this->postJson("/api/pharmacy/prescriptions/$rx/fills", $this->fillBody($lot))->assertStatus(422);
        $this->postJson('/api/pharmacy/stock-transfers', $this->transferBody($lot))->assertStatus(422);
        // Even an out-of-band status change cannot bypass the pending disposition check.
        DB::table('pharmacy_stock_lots')->where('id', $lot)->update(['status' => 'available']);
        $this->postJson("/api/pharmacy/prescriptions/$rx/fills", $this->fillBody($lot))->assertStatus(422);
        DB::table('pharmacy_stock_lots')->where('id', $lot)->update(['status' => 'quarantined']);
        $this->actingAs($this->independentReviewer(), 'api');
        $review = $this->dispositionReview(['decision' => 'reject']);
        $this->postJson("$url/$id/review", $review)->assertOk()->assertJsonPath('data.records.data.0.status', 'rejected');
        $this->postJson("$url/$id/review", $review)->assertOk();
        $this->assertEquals(50, DB::table('pharmacy_stock_lots')->find($lot)->on_hand);
        $this->assertSame('quarantined', DB::table('pharmacy_stock_lots')->find($lot)->status);
        $this->assertSame(0, DB::table('pharmacy_stock_events')->where('action', 'disposition_applied')->count());
    }

    public function test_disposition_validation_reservations_counts_and_restricted_product_scope(): void
    {
        $lot = $this->lot(); $url = "/api/pharmacy/stock/$lot/dispositions";
        foreach (['0', '-1', '50.001', '1.0001'] as $quantity) { $this->postJson($url, $this->dispositionBody($lot, ['quantity' => $quantity]))->assertStatus(422); }
        foreach (['occurred_on' => now()->addDay()->toDateString(), 'kind' => 'patient_return', 'scope_confirmed' => false, 'completion_reference' => '', 'classification_evidence' => ''] as $k => $v) {
            $this->postJson($url, $this->dispositionBody($lot, [$k => $v]))->assertStatus(422);
        }
        $this->postJson($url, $this->dispositionBody($lot, ['version' => 99]))->assertStatus(409);
        $this->postJson($url, $this->dispositionBody($lot, ['product_id' => 99]))->assertStatus(409);
        $rx = $this->rx(); $fill = $this->fill($rx, $lot);
        $this->postJson($url, $this->dispositionBody($lot))->assertStatus(422);
        $this->act($rx, $fill, 'cancel');
        $count = $this->postJson("/api/pharmacy/stock/$lot/counts", $this->stockCountBody($lot))->assertCreated()->json('data.counts.data.0.id');
        $this->postJson($url, $this->dispositionBody($lot))->assertStatus(422);
        $this->actingAs($this->independentReviewer(), 'api');
        $this->postJson("/api/pharmacy/stock/$lot/counts/$count/review", ['decision' => 'reject', 'evidence' => 'Synthetic'])->assertOk();
        $this->actingAs($this->actor, 'api');
        DB::table('pharmacy_prescriptions')->where('id', $rx)->update(['controlled' => true]);
        $this->postJson($url, $this->dispositionBody($lot))->assertStatus(422);
        DB::table('pharmacy_prescriptions')->where('id', $rx)->update(['controlled' => false, 'compounded' => true]);
        $this->postJson($url, $this->dispositionBody($lot))->assertStatus(422);
        DB::table('pharmacy_prescriptions')->where('id', $rx)->update(['compounded' => false]);
        DB::table('pharmacy_stock_lots')->where('id', $lot)->update(['created_at' => now()->addDay()]);
        $this->postJson($url, $this->dispositionBody($lot))->assertStatus(422);
        $other = $this->lot([], false);
        $this->postJson("/api/pharmacy/stock/$other/dispositions", $this->dispositionBody($other, ['product_id' => 1]))->assertStatus(422);
        $this->assertSame(0, DB::table('pharmacy_stock_dispositions')->count());
    }

    public function test_disposition_stale_stock_product_and_recall_evidence_cannot_be_applied(): void
    {
        $reviewer = $this->independentReviewer();
        foreach (['version', 'product', 'notice', 'corruption'] as $change) {
            $this->actingAs($this->actor, 'api'); $lot = $this->lot(['lot_number' => 'SYN-'.$change]); $url = "/api/pharmacy/stock/$lot/dispositions";
            $id = $this->postJson($url, $this->dispositionBody($lot))->assertCreated()->json('data.records.data.0.id');
            if ($change === 'version') { DB::table('pharmacy_stock_lots')->where('id', $lot)->increment('version'); }
            if ($change === 'product') { $this->postJson("/api/pharmacy/stock/$lot/product", $this->productBody(['revision' => 1]))->assertCreated(); }
            if ($change === 'notice') { $this->postJson('/api/pharmacy/recall-notices', $this->noticeBody(['reference' => 'SYN-disposition', 'lot_number' => 'SYN-notice']))->assertCreated(); }
            if ($change === 'corruption') { DB::table('pharmacy_stock_dispositions')->where('id', $id)->update(['source_snapshot' => '{}']); }
            $this->actingAs($reviewer, 'api');
            $this->postJson("$url/$id/review", $this->dispositionReview())->assertStatus(409);
            $this->postJson("$url/$id/review", $this->dispositionReview(['decision' => 'reject']))->assertOk();
            $this->assertEquals(50, DB::table('pharmacy_stock_lots')->find($lot)->on_hand);
        }
    }

    public function test_disposition_scope_and_actor_bound_retries(): void
    {
        $lot = $this->lot(); $url = "/api/pharmacy/stock/$lot/dispositions"; $body = $this->dispositionBody($lot);
        $id = $this->postJson($url, $body)->assertCreated()->json('data.records.data.0.id');
        foreach (['admin', 'medical_biller', 'client'] as $role) {
            $this->actor->role = $role; $this->actor->save();
            $this->getJson($url)->assertForbidden(); $this->postJson($url, $body)->assertForbidden(); $this->postJson("$url/$id/review", $this->dispositionReview())->assertForbidden();
        }
        $this->actor->role = 'pharmacy_technician'; $this->actor->save();
        $this->getJson($url)->assertOk(); $this->postJson($url, $body)->assertForbidden(); $this->postJson("$url/$id/review", $this->dispositionReview())->assertForbidden();
        $this->actor->role = 'pharmacist'; $this->actor->save();
        DB::table('pharmacy_staff_assignments')->where('user_id', $this->actor->id)->where('location_id', $this->location)->update(['active' => false]);
        $this->getJson($url)->assertNotFound(); $this->postJson($url, $body)->assertNotFound();
        DB::table('pharmacy_staff_assignments')->where('user_id', $this->actor->id)->update(['active' => true]);
        $this->actor->organization_id = 2; $this->actor->save(); $this->getJson($url)->assertNotFound(); $this->postJson($url, $body)->assertNotFound();
        $this->actor->organization_id = 1; $this->actor->save();
        $reviewer = $this->independentReviewer(); $this->actingAs($reviewer, 'api');
        $this->postJson($url, $body)->assertStatus(409);
        $review = $this->dispositionReview(); $this->postJson("$url/$id/review", $review)->assertOk();
        $this->actingAs($this->actor, 'api'); $this->postJson("$url/$id/review", $review)->assertStatus(409);
        $other = $this->lot(); $this->postJson("/api/pharmacy/stock/$other/dispositions/$id/review", $review)->assertNotFound();
    }

    public function test_disposition_audit_failure_rolls_back_hold_and_quantity_deduction(): void
    {
        $lot = $this->lot(); $url = "/api/pharmacy/stock/$lot/dispositions"; $fail = true;
        DB::connection()->beforeExecuting(function ($query) use (&$fail) { if ($fail && str_contains(strtolower($query), 'insert into') && str_contains($query, 'pharmacy_stock_events')) { throw new \RuntimeException('Synthetic audit failure'); } });
        $this->postJson($url, $this->dispositionBody($lot))->assertStatus(500);
        $this->assertSame(0, DB::table('pharmacy_stock_dispositions')->count());
        $this->assertSame('available', DB::table('pharmacy_stock_lots')->find($lot)->status);
        $fail = false; $id = $this->postJson($url, $this->dispositionBody($lot))->assertCreated()->json('data.records.data.0.id');
        $reviewer = $this->independentReviewer(); $this->actingAs($reviewer, 'api'); $fail = true;
        $this->postJson("$url/$id/review", $this->dispositionReview())->assertStatus(500);
        $this->assertSame('pending', DB::table('pharmacy_stock_dispositions')->find($id)->status);
        $this->assertEquals(50, DB::table('pharmacy_stock_lots')->find($lot)->on_hand);
        $fail = false; $this->postJson("$url/$id/review", $this->dispositionReview())->assertOk();
        $this->assertEquals(47.875, DB::table('pharmacy_stock_lots')->find($lot)->on_hand);
    }

    public function test_disposition_requires_reconciled_transfer_custody_and_preserves_upstream_recalls(): void
    {
        $receiver = $this->receivingPharmacist();
        [$transfer, $source, $dest] = $this->receivedDiscrepancy($receiver);
        $this->postJson("/api/pharmacy/stock/$dest/product", $this->productBody())->assertCreated();
        $url = "/api/pharmacy/stock/$dest/dispositions";
        $this->postJson($url, $this->dispositionBody($dest))->assertStatus(422);
        $count = $this->correctedReceiptCount($dest, $receiver);
        $this->actingAs($receiver, 'api');
        $c = $this->postJson("/api/pharmacy/stock-transfers/$transfer/corrections", $this->correctionBody($transfer, $count))->assertCreated()->json('data.corrections.data.0.id');
        $this->actingAs($this->actor, 'api');
        $this->postJson("/api/pharmacy/stock-transfers/$transfer/corrections/$c/review", ['request_id' => (string) Str::uuid(), 'version' => 4, 'decision' => 'apply', 'evidence' => 'SYNTHETIC receipt correction'])->assertOk();
        $this->recallStock($source)->assertOk();
        $this->actingAs($receiver, 'api');
        $id = $this->postJson($url, $this->dispositionBody($dest, ['kind' => 'disposal']))->assertCreated()->json('data.records.data.0.id');
        $this->actingAs($this->actor, 'api');
        $this->postJson("$url/$id/review", $this->dispositionReview())->assertOk();
        $this->assertEquals(8, DB::table('pharmacy_stock_lots')->find($dest)->on_hand);
        $this->assertEquals(39.875, DB::table('pharmacy_stock_lots')->find($source)->on_hand);
        $this->assertSame('received_corrected', DB::table('pharmacy_stock_transfers')->find($transfer)->status);
        $this->putJson("/api/pharmacy/stock/$dest/status", ['version' => (int) DB::table('pharmacy_stock_lots')->find($dest)->version, 'status' => 'available', 'note' => 'Synthetic'])->assertStatus(422);
    }

    public function test_disposition_pagination_and_revoked_review_cannot_leak_or_mutate_records(): void
    {
        $lot = $this->lot(); $url = "/api/pharmacy/stock/$lot/dispositions"; $reviewer = $this->independentReviewer();
        for ($i = 0; $i < 11; $i++) {
            $this->actingAs($this->actor, 'api');
            $id = $this->postJson($url, $this->dispositionBody($lot))->assertCreated()->json('data.records.data.0.id');
            $this->actingAs($reviewer, 'api');
            $this->postJson("$url/$id/review", $this->dispositionReview(['decision' => 'reject']))->assertOk();
        }
        $this->getJson($url)->assertOk()->assertJsonCount(10, 'data.records.data')->assertJsonPath('data.records.total', 11);
        $this->getJson($url.'?page=2')->assertOk()->assertJsonCount(1, 'data.records.data');
        $this->getJson($url.'?page=0')->assertStatus(422);
        $this->actingAs($this->actor, 'api');
        $id = $this->postJson($url, $this->dispositionBody($lot))->assertCreated()->json('data.records.data.0.id');
        $this->actingAs($reviewer, 'api');
        DB::table('pharmacy_staff_assignments')->where('user_id', $reviewer->id)->update(['valid_until' => now()->subDay()->toDateString()]);
        $this->postJson("$url/$id/review", $this->dispositionReview())->assertNotFound();
        $this->getJson($url)->assertNotFound();
        $this->assertSame('pending', DB::table('pharmacy_stock_dispositions')->find($id)->status);
        $this->assertEquals(50, DB::table('pharmacy_stock_lots')->find($lot)->on_hand);
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
        $this->getJson("/api/pharmacy/stock/$lot?event_page=2")->assertOk()->assertJsonPath('data.events.total', 34)->assertJsonCount(4, 'data.events.data');
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

    private function recallCorrectionBody(int $notice, array $changes = []): array
    {
        return array_replace(['request_id' => (string) Str::uuid(), 'version' => (int) DB::table('pharmacy_recall_notices')->where('id', $notice)->value('version'), 'reason' => 'Synthetic notice entered in error', 'evidence' => 'Synthetic verified source correction only', 'confirmed' => true], $changes);
    }

    private function recallCorrectionReview(int $notice, array $changes = []): array
    {
        return array_replace(['request_id' => (string) Str::uuid(), 'version' => (int) DB::table('pharmacy_recall_notices')->where('id', $notice)->value('version'), 'decision' => 'apply', 'evidence' => 'Synthetic independent source/scope review', 'confirmed' => true], $changes);
    }

    public function test_recall_correction_quarantines_all_matching_sites_without_releasing_stock_or_erasing_history(): void
    {
        $a = $this->lot(); $b = $this->lot(['location_id' => $this->otherLocation]); $other = $this->lot(['lot_number' => 'OTHER']);
        $rx = $this->rx(); $fill = $this->act($rx, $this->fill($rx, $a), 'approve', $this->checks());
        $fill = $this->act($rx, $fill, 'ready', ['checks' => ['label' => true]]);
        $beforeFills = DB::table('pharmacy_fills')->get(); $beforeOther = DB::table('pharmacy_stock_lots')->where('id', $other)->first();
        $id = $this->postJson('/api/pharmacy/recall-notices', $this->noticeBody())->assertCreated()->json('data.id');
        $foreign = $this->lot(); DB::table('pharmacy_stock_lots')->where('id', $foreign)->update(['organization_id' => 2]);
        $beforeForeign = DB::table('pharmacy_stock_lots')->where('id', $foreign)->first();
        $original = DB::table('pharmacy_recall_notices')->where('id', $id)->first();
        $url = "/api/pharmacy/recall-notices/$id/corrections"; $body = $this->recallCorrectionBody($id);
        $correction = $this->postJson($url, $body)->assertOk()->assertJsonPath('data.withdrawn_at', null)->json('data.corrections.data.0.id');
        $this->getJson('/api/pharmacy/recall-notices?status=pending')->assertOk()->assertJsonPath('data.total', 1);
        $this->postJson($url, $body)->assertOk();
        $this->postJson($url, array_replace($body, ['reason' => 'Changed']))->assertStatus(409);
        $late = $this->lot(); // Received while independent correction review was pending.
        $reviewUrl = "$url/$correction/review"; $review = $this->recallCorrectionReview($id);
        $this->postJson($reviewUrl, $review)->assertUnprocessable();
        $this->act($rx, $fill, 'collected', ['occurred_on' => now()->toDateString(), 'reference' => 'Synthetic blocked handover', 'counseling' => 'provided'], 422);
        $this->actingAs($this->independentReviewer(), 'api');
        $this->postJson($reviewUrl, $review)->assertOk()->assertJsonPath('data.stock.total', 2)->assertJsonPath('data.corrections.data.0.status', 'applied')->assertJsonMissingPath('data.corrections.data.0.request_hash');
        $this->postJson($reviewUrl, $review)->assertOk();
        $this->postJson($reviewUrl, array_replace($review, ['decision' => 'reject']))->assertStatus(409);
        foreach ([$a, $b, $late] as $lot) {
            $row = DB::table('pharmacy_stock_lots')->where('id', $lot)->first();
            $this->assertSame('quarantined', $row->status); $this->assertEquals(50, $row->on_hand);
            $this->assertSame(1, DB::table('pharmacy_stock_events')->where('stock_lot_id', $lot)->where('action', 'notice_correction_quarantine')->count());
        }
        $this->assertEquals(10, DB::table('pharmacy_stock_lots')->where('id', $a)->value('reserved'));
        $this->assertEquals($beforeForeign, DB::table('pharmacy_stock_lots')->where('id', $foreign)->first());
        $this->assertEquals($beforeFills, DB::table('pharmacy_fills')->get());
        $this->assertEquals($beforeOther, DB::table('pharmacy_stock_lots')->where('id', $other)->first());
        $this->act($rx, $fill, 'collected', ['occurred_on' => now()->toDateString(), 'reference' => 'Synthetic blocked handover', 'counseling' => 'provided'], 422);
        $retained = DB::table('pharmacy_recall_notices')->where('id', $id)->first();
        foreach (['reference', 'evidence', 'ndcs', 'lot_number', 'lot_key', 'all_lots', 'created_by', 'created_at'] as $field) $this->assertSame($original->$field, $retained->$field);
        $this->assertNotNull($retained->withdrawn_at);
        $this->getJson('/api/pharmacy/recall-notices?status=pending')->assertOk()->assertJsonPath('data.total', 0);
        $this->getJson('/api/pharmacy/recall-notices?status=withdrawn')->assertOk()->assertJsonPath('data.total', 1);
        $this->getJson('/api/pharmacy/recall-notices?status=active')->assertOk()->assertJsonPath('data.total', 0);
        $this->assertSame(0, DB::table('invoices')->count());
        $this->putJson($url, $body)->assertStatus(405);
    }

    public function test_recall_correction_preserves_overlapping_and_receipt_recalls_and_future_matching(): void
    {
        $a = $this->lot(); $b = $this->lot(['lot_number' => 'RECALLED']);
        $this->postJson("/api/pharmacy/stock/$b/recall", ['version' => 1, 'request_id' => (string) Str::uuid(), 'reference' => 'Synthetic receipt recall', 'evidence' => 'Synthetic'])->assertOk();
        $id = $this->postJson('/api/pharmacy/recall-notices', $this->noticeBody(['all_lots' => true, 'lot_number' => null]))->assertCreated()->json('data.id');
        $otherNotice = $this->postJson('/api/pharmacy/recall-notices', $this->noticeBody(['reference' => 'Synthetic valid separate notice']))->assertCreated()->json('data.id');
        $url = "/api/pharmacy/recall-notices/$id/corrections";
        $c = $this->postJson($url, $this->recallCorrectionBody($id))->assertOk()->json('data.corrections.data.0.id');
        $this->actingAs($this->independentReviewer(), 'api');
        $this->postJson("$url/$c/review", $this->recallCorrectionReview($id))->assertOk();
        $this->getJson("/api/pharmacy/stock/$a")->assertOk()->assertJsonPath('data.recall_notices.0.id', $otherNotice);
        $this->getJson("/api/pharmacy/stock/$b")->assertOk()->assertJsonPath('data.status', 'recalled')->assertJsonPath('data.recall_reference', 'Synthetic receipt recall');
        foreach ([$a,$b] as $lot) $this->putJson("/api/pharmacy/stock/$lot/status", ['version' => DB::table('pharmacy_stock_lots')->where('id',$lot)->value('version'), 'status' => 'available', 'note' => 'Synthetic'])->assertUnprocessable();
        $future = $this->lot(); $this->getJson("/api/pharmacy/stock/$future")->assertOk()->assertJsonPath('data.status', 'quarantined');
        $clearFuture = $this->lot(['lot_number' => 'DIFFERENT']); $this->getJson("/api/pharmacy/stock/$clearFuture")->assertOk()->assertJsonPath('data.status', 'available');
    }

    public function test_recall_correction_rejection_and_stale_requests_retain_original_hold(): void
    {
        $lot = $this->lot(); $id = $this->postJson('/api/pharmacy/recall-notices', $this->noticeBody())->assertCreated()->json('data.id');
        $url = "/api/pharmacy/recall-notices/$id/corrections"; $reviewer = $this->independentReviewer();
        $c = $this->postJson($url, $this->recallCorrectionBody($id))->assertOk()->json('data.corrections.data.0.id');
        $review = $this->recallCorrectionReview($id);
        DB::table('pharmacy_recall_notices')->where('id', $id)->increment('version');
        $this->actingAs($reviewer, 'api');
        $this->postJson("$url/$c/review", $review)->assertStatus(409);
        $review['decision'] = 'reject'; $this->postJson("$url/$c/review", $review)->assertOk()->assertJsonPath('data.withdrawn_at', null);
        $this->postJson("$url/$c/review", $review)->assertOk();
        $this->assertSame(0, DB::table('pharmacy_stock_events')->where('action', 'notice_correction_quarantine')->count());
        $this->getJson("/api/pharmacy/stock/$lot")->assertOk()->assertJsonPath('data.recall_notices.0.id', $id);
        $this->actingAs($this->actor, 'api');
        $this->postJson($url, $this->recallCorrectionBody($id))->assertOk()->assertJsonCount(2, 'data.corrections.data');
    }

    public function test_recall_correction_access_confirmation_and_cross_notice_boundaries(): void
    {
        $id = $this->postJson('/api/pharmacy/recall-notices', $this->noticeBody())->assertCreated()->json('data.id');
        $url = "/api/pharmacy/recall-notices/$id/corrections"; $body = $this->recallCorrectionBody($id);
        foreach ([['confirmed'=>false],['reason'=>''],['evidence'=>'']] as $bad) $this->postJson($url,array_replace($body,$bad))->assertUnprocessable();
        $this->postJson($url,array_replace($body,['version'=>99]))->assertStatus(409);
        foreach (['pharmacy_technician','medical_biller','admin'] as $role) { $this->actor->role=$role; $this->actor->save(); $this->postJson($url,$body)->assertForbidden(); }
        $this->actor->role='pharmacist'; $this->actor->save();
        DB::table('pharmacy_staff_assignments')->where('user_id',$this->actor->id)->update(['active'=>false]); $this->postJson($url,$body)->assertForbidden();
        DB::table('pharmacy_staff_assignments')->where('user_id',$this->actor->id)->update(['active'=>true]);
        DB::table('pharmacy_recall_notices')->where('id',$id)->update(['organization_id'=>2]); $this->postJson($url,$body)->assertNotFound();
        DB::table('pharmacy_recall_notices')->where('id',$id)->update(['organization_id'=>1]);
        $c=$this->postJson($url,$body)->assertOk()->json('data.corrections.data.0.id');
        $this->postJson($url,$this->recallCorrectionBody($id))->assertStatus(409);
        $reviewer=$this->independentReviewer(); $this->actingAs($reviewer,'api');
        $this->postJson($url,$body)->assertStatus(409);
        $review=$this->recallCorrectionReview($id);
        $this->postJson("$url/99999/review",$review)->assertNotFound();
        $reviewer->role='pharmacy_technician';$reviewer->save();$this->postJson("$url/$c/review",$review)->assertForbidden();
        $reviewer->role='pharmacist';$reviewer->save();
        DB::table('pharmacy_staff_assignments')->where('user_id',$reviewer->id)->update(['active'=>false]);$this->postJson("$url/$c/review",$review)->assertForbidden();
    }

    public function test_recall_correction_rolls_back_all_quarantines_and_review_on_audit_failure(): void
    {
        $this->lot();$this->lot(['location_id'=>$this->otherLocation]);
        $id=$this->postJson('/api/pharmacy/recall-notices',$this->noticeBody())->assertCreated()->json('data.id');
        $url="/api/pharmacy/recall-notices/$id/corrections"; $body=$this->recallCorrectionBody($id); $failNotice=true;
        DB::connection()->beforeExecuting(function($query)use(&$failNotice){ if($failNotice&&str_starts_with(strtolower($query),'update')&&str_contains($query,'pharmacy_recall_notices'))throw new \RuntimeException('Synthetic notice update failure'); });
        $this->postJson($url,$body)->assertStatus(500);$failNotice=false;
        $this->assertSame(0,DB::table('pharmacy_recall_corrections')->count());
        $c=$this->postJson($url,$body)->assertOk()->json('data.corrections.data.0.id');
        $this->actingAs($this->independentReviewer(),'api'); $review=$this->recallCorrectionReview($id); $before=DB::table('pharmacy_stock_lots')->get(); $fail=true;$insert=0;
        DB::connection()->beforeExecuting(function($query)use(&$fail,&$insert){if($fail&&str_starts_with(strtolower($query),'insert into')&&str_contains($query,'pharmacy_stock_events')&&++$insert===2)throw new \RuntimeException('Synthetic second stock audit failure');});
        $this->postJson("$url/$c/review",$review)->assertStatus(500);$fail=false;
        $this->assertEquals($before,DB::table('pharmacy_stock_lots')->get());
        $this->assertNull(DB::table('pharmacy_recall_notices')->where('id',$id)->value('withdrawn_at'));
        $this->assertSame('pending',DB::table('pharmacy_recall_corrections')->value('status'));
        $this->assertSame(0,DB::table('pharmacy_stock_events')->where('action','notice_correction_quarantine')->count());
        $this->postJson("$url/$c/review",$review)->assertOk();
    }

    public function test_recall_correction_retains_transfer_custody_and_existing_follow_up(): void
    {
        $receiver=$this->receivingPharmacist();[$transfer,$source,$dest]=$this->receivedDiscrepancy($receiver,'10.125');
        $this->actingAs($this->actor,'api'); $rx=$this->rx();$f=$this->fill($rx,$source);$this->act($rx,$f,'cancel');
        $id=$this->postJson('/api/pharmacy/recall-notices',$this->noticeBody())->assertCreated()->json('data.id');
        $follow="/api/pharmacy/recall-notices/$id/fills/{$f['id']}/follow-up";
        $this->postJson("$follow/events", $this->followUpBody())->assertCreated();
        $followHistory = DB::table('pharmacy_recall_follow_up_events')->get();
        $before=DB::table('pharmacy_stock_transfers')->get();
        $url="/api/pharmacy/recall-notices/$id/corrections";$c=$this->postJson($url,$this->recallCorrectionBody($id))->assertOk()->json('data.corrections.data.0.id');
        $this->actingAs($receiver,'api');$this->postJson("$url/$c/review",$this->recallCorrectionReview($id))->assertOk();
        $this->assertEquals($before,DB::table('pharmacy_stock_transfers')->get());
        foreach([$source,$dest]as$lot){$this->getJson("/api/pharmacy/stock/$lot")->assertOk()->assertJsonPath('data.status','quarantined')->assertJsonPath('data.custody_hold',null);}
        $this->actingAs($this->actor,'api');$this->getJson($follow)->assertOk()->assertJsonPath('data.fill.fulfillment_status','cancelled')->assertJsonPath('data.status','open');
        $this->assertEquals($followHistory, DB::table('pharmacy_recall_follow_up_events')->get());
        $this->putJson("/api/pharmacy/stock/$source/status",['version'=>DB::table('pharmacy_stock_lots')->where('id',$source)->value('version'),'status'=>'available','note'=>'SYNTHETIC independent local release review'])->assertOk();
        $this->getJson("/api/pharmacy/stock/$dest")->assertOk()->assertJsonPath('data.status','quarantined');
    }

    public function test_recall_correction_history_paginates_and_never_overwrites_rejected_reviews(): void
    {
        $id=$this->postJson('/api/pharmacy/recall-notices',$this->noticeBody())->assertCreated()->json('data.id');
        $url="/api/pharmacy/recall-notices/$id/corrections"; $reviewer=$this->independentReviewer();
        for($i=0;$i<11;$i++){
            $this->actingAs($this->actor,'api');
            $c=$this->postJson($url,$this->recallCorrectionBody($id,['reason'=>"Synthetic correction attempt $i"]))->assertOk()->json('data.corrections.data.0.id');
            $this->actingAs($reviewer,'api');
            $this->postJson("$url/$c/review",$this->recallCorrectionReview($id,['decision'=>'reject']))->assertOk();
        }
        $this->getJson("/api/pharmacy/recall-notices/$id")->assertOk()->assertJsonPath('data.corrections.total',11)->assertJsonCount(10,'data.corrections.data')->assertJsonPath('data.corrections.data.0.reason','Synthetic correction attempt 10');
        $this->getJson("/api/pharmacy/recall-notices/$id?correction_page=2")->assertOk()->assertJsonCount(1,'data.corrections.data')->assertJsonPath('data.corrections.data.0.reason','Synthetic correction attempt 0');
        $this->assertSame(11,DB::table('pharmacy_recall_corrections')->where('status','rejected')->count());
        $this->getJson("/api/pharmacy/recall-notices/$id?correction_page=0")->assertUnprocessable();
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

    public function test_atomic_replacement_stops_original_preserves_fill_history_and_retries_once(): void
    {
        $original = $this->rx(); $lot = $this->lot();
        $fill = $this->fill($original, $lot);
        $replacement = $this->rx(['quantity' => '7.000', 'refills_authorized' => 0]);
        $url = "/api/pharmacy/prescriptions/$original/replacement";
        $body = ['request_id' => (string) Str::uuid(), 'replacement_id' => $replacement, 'discontinue_original' => true, 'reason' => 'Synthetic prescriber change', 'reference' => 'SYNTHETIC received replacement authority'];
        $beforeStock = DB::table('pharmacy_stock_lots')->get();
        $beforeFills = DB::table('pharmacy_fills')->get();
        $beforeReplacement = DB::table('pharmacy_prescriptions')->where('id', $replacement)->first();
        $this->getJson("/api/pharmacy/prescriptions?replacement_for=$original")->assertOk()->assertJsonPath('data.total', 1);
        $this->postJson($url, $body)->assertOk()->assertJsonPath('data.replacement.id', $replacement)->assertJsonPath('data.discontinuation_reason', $body['reason']);
        $this->postJson($url, $body)->assertOk();
        $this->postJson($url, array_replace($body, ['discontinue_original' => false]))->assertStatus(409);
        $this->postJson($url, array_replace($body, ['reference' => 'Changed']))->assertStatus(409);
        $this->assertTrue((bool) DB::table('pharmacy_prescription_replacements')->value('discontinued_original'));
        foreach (['prescription_discontinued', 'replacement_linked', 'original_prescription_linked'] as $action) {
            $this->assertSame(1, DB::table('pharmacy_events')->where('action', $action)->count());
        }
        $this->assertEquals($beforeStock, DB::table('pharmacy_stock_lots')->get());
        $this->assertEquals($beforeFills, DB::table('pharmacy_fills')->get());
        $this->assertEquals($beforeReplacement, DB::table('pharmacy_prescriptions')->where('id', $replacement)->first());
        $this->postJson("/api/pharmacy/prescriptions/$original/fills", $this->fillBody($lot))->assertUnprocessable();
        $this->act($original, $fill, 'approve', $this->checks(), 422);
        $this->getJson('/api/pharmacy/prescriptions?attention=discontinued_work')->assertOk()->assertJsonPath('data.total', 1);
        $this->act($original, $fill, 'cancel');
        $this->getJson('/api/pharmacy/prescriptions?attention=discontinued_work')->assertOk()->assertJsonPath('data.total', 0);
    }

    public function test_atomic_replacement_validation_and_access_do_not_discontinue_original(): void
    {
        $original = $this->rx(); $replacement = $this->rx();
        $url = "/api/pharmacy/prescriptions/$original/replacement";
        $body = ['request_id' => (string) Str::uuid(), 'replacement_id' => $replacement, 'discontinue_original' => true, 'reason' => 'Synthetic', 'reference' => 'Synthetic'];
        foreach ([['discontinue_original' => false], ['discontinue_original' => 'invalid'], ['reference' => ''], ['replacement_id' => $original]] as $invalid) {
            $this->postJson($url, array_replace($body, $invalid))->assertUnprocessable();
        }
        DB::table('pharmacy_prescriptions')->where('id', $replacement)->update(['expires_on' => now()->subDay()->toDateString()]);
        $this->getJson("/api/pharmacy/prescriptions?replacement_for=$original")->assertOk()->assertJsonPath('data.total', 0);
        $this->postJson($url, $body)->assertUnprocessable();
        DB::table('pharmacy_prescriptions')->where('id', $replacement)->update(['expires_on' => now()->addYear()->toDateString(), 'location_id' => $this->otherLocation]);
        $this->postJson($url, $body)->assertUnprocessable();
        DB::table('pharmacy_prescriptions')->where('id', $replacement)->update(['location_id' => $this->location, 'organization_id' => 2]);
        $this->postJson($url, $body)->assertNotFound();
        DB::table('pharmacy_prescriptions')->where('id', $replacement)->update(['organization_id' => 1]);
        foreach (['pharmacy_technician', 'medical_biller', 'admin'] as $role) {
            $this->actor->role = $role; $this->actor->save();
            $this->postJson($url, $body)->assertForbidden();
        }
        $this->actor->role = 'pharmacist'; $this->actor->save();
        DB::table('pharmacy_staff_assignments')->where('location_id', $this->location)->update(['active' => false]);
        $this->postJson($url, $body)->assertNotFound();
        $this->assertNull(DB::table('pharmacy_prescriptions')->where('id', $original)->value('discontinued_at'));
        $this->assertSame(0, DB::table('pharmacy_prescription_replacements')->count());
        $this->assertSame(0, DB::table('pharmacy_events')->where('action', 'prescription_discontinued')->count());
        DB::table('pharmacy_staff_assignments')->where('location_id', $this->location)->update(['active' => true]);
        $this->stopPrescription($original)->assertOk();
        $this->postJson($url, $body)->assertStatus(409);
        $this->postJson($url, array_replace($body, ['discontinue_original' => false]))->assertOk()->assertJsonPath('data.discontinuation_reason', 'Synthetic discontinuation');
    }

    public function test_atomic_replacement_rolls_back_discontinuation_and_link_on_second_audit_failure(): void
    {
        $original = $this->rx(); $replacement = $this->rx();
        $url = "/api/pharmacy/prescriptions/$original/replacement";
        $body = ['request_id' => (string) Str::uuid(), 'replacement_id' => $replacement, 'discontinue_original' => true, 'reason' => 'Synthetic', 'reference' => 'Synthetic'];
        $fail = true; $inserts = 0;
        DB::connection()->beforeExecuting(function ($query) use (&$fail, &$inserts) {
            if ($fail && str_starts_with(strtolower($query), 'insert into') && str_contains($query, 'pharmacy_events') && ++$inserts === 2) {
                throw new \RuntimeException('Synthetic replacement audit failure');
            }
        });
        $this->postJson($url, $body)->assertStatus(500); $fail = false;
        $this->assertSame(2, $inserts);
        $this->assertNull(DB::table('pharmacy_prescriptions')->where('id', $original)->value('discontinued_at'));
        $this->assertSame(0, DB::table('pharmacy_prescription_replacements')->count());
        $this->assertSame(0, DB::table('pharmacy_events')->whereIn('action', ['prescription_discontinued', 'replacement_linked', 'original_prescription_linked'])->count());
        $this->postJson($url, $body)->assertOk();
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

    public function test_independent_chart_intake_requires_retained_case_identity_evidence(): void
    {
        $id = $this->postJson('/api/pharmacy/patients', ['request_id' => (string) Str::uuid(),
            'record_number' => 'SYN-CASE-LINK', 'location_id' => $this->location, 'first_name' => 'Synthetic',
            'last_name' => 'Linkage', 'date_of_birth' => '1980-01-01', 'identity_reference' => 'Synthetic intake'])->assertCreated()->json('data.id');
        $body = $this->body(['patient_id' => null, 'pharmacy_patient_id' => $id]);
        foreach (['case_link_reference', 'case_link_confirmed', 'patient_version'] as $field) {
            $missing = $body; unset($missing[$field]);
            $this->postJson('/api/pharmacy/prescriptions', $missing)->assertUnprocessable()->assertJsonValidationErrors($field);
        }
        $this->postJson('/api/pharmacy/prescriptions', array_replace($body, ['case_link_confirmed' => false]))->assertUnprocessable();
        $this->postJson('/api/pharmacy/prescriptions', array_replace($body, ['patient_version' => 2]))->assertConflict();
        $this->assertSame(0, DB::table('pharmacy_prescriptions')->count());
        $fail = true;
        DB::connection()->beforeExecuting(function ($query) use (&$fail) {
            if ($fail && str_starts_with(strtolower($query), 'insert into') && str_contains($query, 'pharmacy_events')) {
                throw new \RuntimeException('Synthetic intake audit failure');
            }
        });
        $this->postJson('/api/pharmacy/prescriptions', $body)->assertStatus(500); $fail = false;
        $this->assertSame(0, DB::table('pharmacy_prescriptions')->count());
        $this->assertSame(0, DB::table('pharmacy_episodes')->count());
        $rx = $this->postJson('/api/pharmacy/prescriptions', $body)->assertCreated()->json('data.id');
        $this->postJson('/api/pharmacy/prescriptions', $body)->assertCreated()->assertJsonPath('data.id', $rx);
        $this->postJson('/api/pharmacy/prescriptions', array_replace($body, ['case_link_reference' => 'Different evidence']))->assertConflict();
        $this->getJson("/api/pharmacy/prescriptions/$rx")->assertOk()->assertJsonPath('data.case_link.reference', $body['case_link_reference']);
        $event = DB::table('pharmacy_events')->where('prescription_id', $rx)->where('action', 'prescription_received')->sole();
        $link = json_decode($event->details, true)['case_link'];
        $this->assertSame($id, $link['pharmacy_patient_id']);
        $this->assertSame($this->case->id, $link['case_id']);
        $this->assertSame('SYN-CASE-LINK', $link['record_number']);
        $this->assertSame('Linkage', $link['last_name']);
        $this->assertSame(1, $link['patient_version']);
        $this->assertSame($body['case_link_reference'], $link['reference']);
        $this->assertTrue($link['confirmed']);
        $this->assertSame($this->actor->id, $event->actor_id);
        $this->assertSame(1, DB::table('pharmacy_prescriptions')->count());
        $this->assertSame(1, DB::table('pharmacy_events')->where('action', 'prescription_received')->count());
        for ($i = 0; $i < 101; $i++) {
            DB::table('pharmacy_events')->insert(['organization_id' => 1, 'prescription_id' => $rx,
                'actor_id' => $this->actor->id, 'action' => 'synthetic_history', 'details' => '{}', 'created_at' => now()]);
        }
        $this->getJson("/api/pharmacy/prescriptions/$rx")->assertOk()->assertJsonCount(100, 'data.events')
            ->assertJsonPath('data.case_link.reference', $body['case_link_reference']);
    }

    private function body(array $overrides = []): array
    {
        if (! empty($overrides['pharmacy_patient_id'])) {
            $overrides += ['case_link_reference' => 'SYNTHETIC case and chart identity evidence', 'case_link_confirmed' => true,
                'patient_version' => DB::table('pharmacy_patients')->where('id', $overrides['pharmacy_patient_id'])->value('version') ?? 1];
        }

        if (! empty($overrides['compounded']) && ! array_key_exists('compound_type', $overrides)) {
            $overrides['compound_type'] = 'nonsterile';
        }

        return array_replace(['request_id' => (string) Str::uuid(), 'location_id' => $this->location, 'case_id' => $this->case->id, 'patient_id' => $this->patient->id, 'rx_number' => 'SYN-'.Str::random(10), 'medication' => 'Synthetic medication', 'strength' => 'Synthetic strength', 'dosage_form' => 'tablet', 'directions' => 'Synthetic fixture only', 'quantity' => '10.000', 'quantity_unit' => 'tablet', 'refills_authorized' => 1, 'written_on' => now()->subDay()->toDateString(), 'expires_on' => now()->addMonth()->toDateString(), 'prescriber_name' => 'Synthetic prescriber', 'prescriber_identifier' => 'NOT VALID', 'source_reference' => 'synthetic fixture', 'controlled' => false, 'compounded' => false], $overrides);
    }

    private function amendmentBody(int $rx, array $values = []): array
    {
        \Illuminate\Support\Facades\Storage::fake('documents');
        $source = $this->post("/api/pharmacy/prescriptions/$rx/sources", ['request_id' => (string) Str::uuid(), 'reference' => 'SYNTHETIC prescriber consultation',
            'file' => \Illuminate\Http\UploadedFile::fake()->createWithContent('consultation.pdf', "%PDF-1.4\n% SYNTHETIC ONLY\n%%EOF")], ['Accept' => 'application/json'])->assertCreated()->json('data.id');
        $row = DB::table('pharmacy_prescriptions')->find($rx);
        return ['request_id' => (string) Str::uuid(), 'source_token' => $this->getJson("/api/pharmacy/prescriptions/$rx/amendments")->assertOk()->json('data.source_token'),
            'values' => array_replace(['strength' => $row->strength, 'dosage_form' => $row->dosage_form, 'directions' => 'SYNTHETIC corrected directions', 'quantity' => '12.500', 'refills_authorized' => 2], $values),
            'source_document_id' => $source, 'consulted_on' => now()->toDateString(), 'consultation_evidence' => 'SYNTHETIC prescriber identity and direct consultation', 'reason' => 'SYNTHETIC correction', 'confirmed' => true];
    }

    public function test_presupply_amendment_retains_original_values_and_new_fills_use_new_authorization(): void
    {
        $rx = $this->rx(); $url = "/api/pharmacy/prescriptions/$rx/amendments";
        $before = (array) DB::table('pharmacy_prescriptions')->find($rx);
        $body = $this->amendmentBody($rx);
        $this->postJson($url, $body)->assertCreated()->assertJsonPath('data.revision', 2)->assertJsonPath('data.amendments.data.0.before_snapshot.directions', $before['directions'])
            ->assertJsonPath('data.amendments.data.0.after_snapshot.directions', 'SYNTHETIC corrected directions');
        $this->postJson($url, $body)->assertOk();
        $this->postJson($url, array_replace($body, ['reason' => 'changed retry']))->assertStatus(409);
        $this->assertSame(1, DB::table('pharmacy_prescription_amendments')->count());
        $this->assertSame(1, DB::table('pharmacy_events')->where('action', 'prescription_amended')->count());
        $row = (array) DB::table('pharmacy_prescriptions')->find($rx);
        foreach (['rx_number', 'medication', 'episode_id', 'location_id', 'prescriber_name', 'prescriber_identifier', 'quantity_unit', 'written_on', 'expires_on', 'request_hash', 'source_reference'] as $field) $this->assertSame($before[$field], $row[$field]);
        $this->getJson("/api/pharmacy/prescriptions/$rx")->assertOk()->assertJsonPath('data.quantity_balance.available_quantity', '12.500')->assertJsonPath('data.quantity_balance.allowances_remaining', 3);
        $lot = $this->lot();
        $this->postJson("/api/pharmacy/prescriptions/$rx/fills", $this->fillBody($lot, ['quantity' => '12.501']))->assertStatus(422);
        $fill = $this->postJson("/api/pharmacy/prescriptions/$rx/fills", $this->fillBody($lot, ['quantity' => '12.500']))->assertOk()->json('data.fills.0');
        $this->assertSame(2, (int) $fill['prescription_revision']); $this->assertSame('pending', $fill['review_status']);
        $this->act($rx, $fill, 'ready', ['checks' => ['label' => true], 'label_id' => 999], 422);
        $this->deleteJson($url.'/1')->assertNotFound();
        $this->putJson($url.'/1', ['reason' => 'overwrite'])->assertNotFound();
    }

    public function test_amendments_require_cancellation_and_preserve_cancelled_fill_and_label_history(): void
    {
        $rx = $this->rx(); $url = "/api/pharmacy/prescriptions/$rx/amendments"; $lot = $this->lot(); $f = $this->fill($rx, $lot);
        $f = $this->act($rx, $f, 'approve', ['checks' => array_fill_keys(['identity', 'prescriber', 'therapy', 'product'], true)]);
        $f = $this->act($rx, $f, 'ready', ['checks' => ['label' => true]]);
        $body = $this->amendmentBody($rx); $labels = DB::table('pharmacy_fill_labels')->get()->toJson();
        $this->postJson($url, $body)->assertStatus(422);
        $f = $this->act($rx, $f, 'cancel');
        $cancelled = (array) DB::table('pharmacy_fills')->find($f['id']);
        $stock = (array) DB::table('pharmacy_stock_lots')->find($lot);
        $this->postJson($url, $body)->assertStatus(409);
        $body['source_token'] = $this->getJson($url)->json('data.source_token');
        $this->postJson($url, $body)->assertCreated();
        $this->assertSame($cancelled, (array) DB::table('pharmacy_fills')->find($f['id']));
        $this->assertSame($stock, (array) DB::table('pharmacy_stock_lots')->find($lot));
        $this->assertSame($labels, DB::table('pharmacy_fill_labels')->get()->toJson());
        $this->assertSame(1, (int) $cancelled['prescription_revision']);
        $this->assertSame(0, DB::table('invoices')->count());
    }

    public function test_amendments_cannot_rewrite_a_completed_supply_or_restricted_or_expired_orders(): void
    {
        $rx = $this->rx(); $lot = $this->lot(); $f = $this->fill($rx, $lot);
        $f = $this->act($rx, $f, 'approve', ['checks' => array_fill_keys(['identity', 'prescriber', 'therapy', 'product'], true)]);
        $f = $this->act($rx, $f, 'ready', ['checks' => ['label' => true]]);
        $f = $this->act($rx, $f, 'collected', ['occurred_on' => now()->toDateString(), 'reference' => 'Synthetic', 'counseling' => 'provided']);
        $body = $this->amendmentBody($rx); $before = (array) DB::table('pharmacy_prescriptions')->find($rx); $fill = (array) DB::table('pharmacy_fills')->find($f['id']);
        $this->postJson("/api/pharmacy/prescriptions/$rx/amendments", $body)->assertStatus(422);
        $this->assertSame($before, (array) DB::table('pharmacy_prescriptions')->find($rx));
        $this->assertSame($fill, (array) DB::table('pharmacy_fills')->find($f['id']));
        foreach ([['controlled' => true], ['compounded' => true, 'compound_type' => 'nonsterile'], ['expires_on' => now()->subDay()->toDateString()]] as $overrides) {
            $id = $this->rx($overrides); $body = $this->amendmentBody($id); $this->postJson("/api/pharmacy/prescriptions/$id/amendments", $body)->assertStatus(422);
        }
        $id = $this->rx(); $body = $this->amendmentBody($id); DB::table('pharmacy_prescriptions')->where('id', $id)->update(['discontinued_at' => now()]);
        $body['source_token'] = $this->getJson("/api/pharmacy/prescriptions/$id/amendments")->json('data.source_token');
        $this->postJson("/api/pharmacy/prescriptions/$id/amendments", $body)->assertStatus(422);
        $this->assertSame(0, DB::table('pharmacy_prescription_amendments')->count());
    }

    public function test_amendments_validate_source_identity_values_roles_and_site_scope(): void
    {
        $rx = $this->rx(); $url = "/api/pharmacy/prescriptions/$rx/amendments"; $body = $this->amendmentBody($rx);
        foreach (['patient_id', 'medication', 'prescriber_name', 'written_on', 'expires_on', 'quantity_unit'] as $field) {
            $bad = $body; $bad['values'][$field] = 'changed'; $this->postJson($url, $bad)->assertStatus(422);
        }
        $this->postJson($url, array_replace($body, ['confirmed' => false]))->assertStatus(422);
        $this->postJson($url, array_replace($body, ['consulted_on' => now()->subYears(2)->toDateString()]))->assertStatus(422);
        $this->postJson($url, array_replace($body, ['source_document_id' => 99999]))->assertNotFound();
        $source = DB::table('pharmacy_source_documents')->find($body['source_document_id']);
        \Illuminate\Support\Facades\Storage::disk('documents')->put($source->path, 'corrupt');
        $this->postJson($url, $body)->assertStatus(409);
        foreach (['pharmacy_technician', 'medical_biller', 'admin'] as $role) {
            $this->actor->role = $role; $this->actor->save(); $this->postJson($url, $body)->assertForbidden();
            $this->getJson($url)->assertStatus($role === 'pharmacy_technician' ? 200 : 403);
        }
        $this->actor->role = 'pharmacist'; $this->actor->save();
        DB::table('pharmacy_staff_assignments')->where('location_id', $this->location)->update(['valid_until' => now()->subDay()->toDateString()]);
        $this->getJson($url)->assertNotFound(); $this->postJson($url, $body)->assertNotFound();
        $this->actor->organization_id = 2; $this->actor->save(); $this->getJson($url)->assertNotFound();
        $this->assertSame(0, DB::table('pharmacy_prescription_amendments')->count());
    }

    public function test_amendment_revisions_reject_stale_noop_and_foreign_actor_retries(): void
    {
        $rx = $this->rx(); $url = "/api/pharmacy/prescriptions/$rx/amendments"; $body = $this->amendmentBody($rx);
        $original = DB::table('pharmacy_prescriptions')->find($rx); $noop = $body;
        foreach (['strength', 'dosage_form', 'directions', 'quantity', 'refills_authorized'] as $field) $noop['values'][$field] = $original->$field;
        $this->postJson($url, $noop)->assertStatus(422);
        $this->postJson($url, $body)->assertCreated(); $first = (array) DB::table('pharmacy_prescription_amendments')->first();
        $next = $body; $next['request_id'] = (string) Str::uuid(); $next['values']['directions'] = 'SYNTHETIC second correction';
        $this->postJson($url, $next)->assertStatus(409);
        $next['source_token'] = $this->getJson($url)->json('data.source_token');
        $this->postJson($url, $next)->assertCreated()->assertJsonPath('data.revision', 3)
            ->assertJsonPath('data.amendments.data.0.before_snapshot.directions', 'SYNTHETIC corrected directions');
        $this->assertSame($first, (array) DB::table('pharmacy_prescription_amendments')->find($first['id']));
        $reviewer = User::create(['first_name' => 'Other', 'last_name' => 'Synthetic', 'email' => 'amendment-reviewer@example.invalid', 'password' => 'synthetic-only', 'role' => 'pharmacist', 'organization_id' => 1, 'status' => 'active']);
        $reviewer->withAccessToken(new Token(['expires_at' => now()->addHour()]));
        DB::table('pharmacy_staff_assignments')->insert(['location_id' => $this->location, 'user_id' => $reviewer->id, 'active' => true, 'valid_until' => now()->addYear()->toDateString()]);
        $this->actingAs($reviewer, 'api'); $this->postJson($url, $body)->assertStatus(409);
        $this->assertSame(2, DB::table('pharmacy_prescription_amendments')->count());
    }

    public function test_amendment_audit_failure_rolls_back_values_history_and_revision(): void
    {
        $rx = $this->rx(); $body = $this->amendmentBody($rx); $before = (array) DB::table('pharmacy_prescriptions')->find($rx);
        DB::connection()->beforeExecuting(function ($query) { if (str_starts_with(strtolower($query), 'insert into') && str_contains($query, 'pharmacy_events')) throw new \RuntimeException('Synthetic amendment audit failure'); });
        $this->postJson("/api/pharmacy/prescriptions/$rx/amendments", $body)->assertStatus(500);
        $this->assertSame($before, (array) DB::table('pharmacy_prescriptions')->find($rx));
        $this->assertSame(0, DB::table('pharmacy_prescription_amendments')->count());
    }

    private function rx(array $overrides = []): int
    {
        return $this->postJson('/api/pharmacy/prescriptions', $this->body($overrides))->assertCreated()->json('data.id');
    }

    private function lot(array $overrides = [], bool $verified = true): int
    {
        $id = $this->postJson('/api/pharmacy/stock', array_replace(['request_id' => (string) Str::uuid(), 'location_id' => $this->location, 'ndc' => '00000-0000-00', 'medication' => 'Synthetic medication', 'lot_number' => 'SYN-LOT', 'quantity_unit' => 'tablet', 'expires_on' => now()->addMonth()->toDateString(), 'quantity' => '50.000', 'receipt_reference' => 'Synthetic receipt'], $overrides))->assertCreated()->json('data.id');
        if ($verified && $this->actor->role === 'pharmacist') {
            $this->postJson("/api/pharmacy/stock/$id/product", $this->productBody())->assertCreated();
        }
        return $id;
    }

    private function productBody(array $overrides = []): array
    {
        return array_replace(['request_id' => (string) Str::uuid(), 'revision' => 0,
            'generic_name' => 'SYNTHETIC established name', 'brand_name' => null, 'strength' => 'SYNTHETIC strength',
            'dosage_form' => 'tablet', 'package_code' => '99999999999997', 'manufacturer' => 'SYNTHETIC manufacturer', 'verified_on' => now()->toDateString(),
            'evidence' => 'SYNTHETIC package and NDC/lot/expiry check', 'reason' => 'Initial synthetic product verification', 'confirmed' => true], $overrides);
    }

    public function test_product_verification_retains_revisions_and_does_not_change_stock_or_holds(): void
    {
        $lot = $this->lot([], false); $url = "/api/pharmacy/stock/$lot/product";
        DB::table('pharmacy_stock_lots')->where('id', $lot)->update(['status' => 'quarantined']);
        $baseline = (array) DB::table('pharmacy_stock_lots')->find($lot);
        $this->getJson("/api/pharmacy/stock/$lot")->assertOk()->assertJsonPath('data.product', null);
        $body = $this->productBody();
        $first = $this->postJson($url, $body)->assertCreated()->assertJsonPath('data.product.revision', 1)->json('data.product');
        $this->postJson($url, $body)->assertOk()->assertJsonPath('data.product.id', $first['id']);
        $this->postJson($url, array_replace($body, ['manufacturer' => 'changed']))->assertStatus(409);
        $this->postJson($url, $this->productBody())->assertStatus(409);
        $second = $this->postJson($url, $this->productBody(['revision' => 1, 'brand_name' => 'SYNTHETIC brand', 'manufacturer' => 'SYNTHETIC corrected supplier', 'reason' => 'Synthetic transcription correction']))->assertCreated()->assertJsonPath('data.product.revision', 2)->json('data.product');
        // An exact late retry is safe after a newer revision and returns current state.
        $this->postJson($url, $body)->assertOk()->assertJsonPath('data.product.id', $second['id']);
        $this->getJson("/api/pharmacy/stock/$lot")->assertOk()->assertJsonPath('data.product_history.total', 2)
            ->assertJsonPath('data.product_history.data.1.manufacturer', $first['manufacturer'])
            ->assertJsonMissingPath('data.product.request_hash')->assertJsonMissingPath('data.product_history.data.0.request_id');
        $this->assertSame($baseline, (array) DB::table('pharmacy_stock_lots')->find($lot));
        $this->assertSame(2, DB::table('pharmacy_stock_events')->where('action', 'product_verified')->count());
        $this->putJson($url, $body)->assertStatus(405);
        $this->deleteJson($url)->assertStatus(405);
        $other = $this->independentReviewer();
        $this->actingAs($other, 'api');
        $this->postJson($url, $body)->assertStatus(409);
    }

    public function test_product_verification_requires_scoped_pharmacist_complete_evidence_and_atomic_audit(): void
    {
        $lot = $this->lot([], false); $url = "/api/pharmacy/stock/$lot/product";
        $body = $this->productBody();
        foreach (['generic_name', 'strength', 'dosage_form', 'manufacturer', 'verified_on', 'evidence', 'reason', 'confirmed'] as $field) {
            $this->postJson($url, array_replace($body, [$field => null]))->assertStatus(422);
        }
        $this->postJson($url, array_replace($body, ['verified_on' => now()->addDay()->toDateString()]))->assertStatus(422);
        $this->postJson($url, array_replace($body, ['confirmed' => false]))->assertStatus(422);
        $this->postJson($url, array_replace($body, ['revision' => 1]))->assertStatus(409);
        foreach (['pharmacy_technician', 'medical_biller', 'admin'] as $role) {
            $this->actor->role = $role; $this->actor->save();
            $this->postJson($url, $body)->assertForbidden();
        }
        $this->actor->role = 'pharmacist'; $this->actor->save();
        DB::table('pharmacy_staff_assignments')->where('location_id', $this->location)->update(['active' => false]);
        $this->postJson($url, $body)->assertNotFound();
        $this->getJson("/api/pharmacy/stock/$lot")->assertNotFound();
        DB::table('pharmacy_staff_assignments')->where('location_id', $this->location)->update(['active' => true]);
        $this->actor->organization_id = 2; $this->actor->save();
        $this->postJson($url, $body)->assertNotFound();
        $this->actor->organization_id = 1; $this->actor->save();
        $this->postJson('/api/pharmacy/stock/99999/product', $body)->assertNotFound();
        DB::connection()->beforeExecuting(function ($query) {
            if (str_starts_with(strtolower($query), 'insert into') && str_contains($query, 'pharmacy_stock_events')) {
                throw new \RuntimeException('Synthetic product audit failure');
            }
        });
        $this->postJson($url, $body)->assertStatus(500);
        $this->assertSame(0, DB::table('pharmacy_stock_products')->count());
    }

    public function test_product_correction_invalidates_open_review_but_retains_completed_selection(): void
    {
        $rx = $this->rx(); $lot = $this->lot([], false); $url = "/api/pharmacy/stock/$lot/product";
        $f = $this->fill($rx, $lot);
        $this->act($rx, $f, 'approve', $this->checks(), 422);
        $first = $this->postJson($url, $this->productBody())->assertCreated()->json('data.product');
        $this->act($rx, $f, 'approve', $this->checks(), 409); // stale screen cannot attest to newly verified details
        $f = $this->getJson("/api/pharmacy/prescriptions/$rx")->assertOk()->json('data.fills.0');
        $f = $this->act($rx, $f, 'approve', $this->checks());
        $this->assertSame($first['id'], $f['reviewed_product']['id']);
        $this->postJson($url, $this->productBody(['revision' => 1, 'reason' => 'SYNTHETIC correction']))->assertCreated();
        $this->act($rx, $f, 'ready', ['checks' => ['label' => true]], 422);
        $this->act($rx, $f, 'approve', $this->checks(), 409);
        $f = $this->getJson("/api/pharmacy/prescriptions/$rx")->assertOk()->json('data.fills.0');
        $f = $this->act($rx, $f, 'approve', $this->checks());
        $f = $this->act($rx, $f, 'ready', ['checks' => ['label' => true]]);
        $this->postJson($url, $this->productBody(['revision' => 2]))->assertCreated();
        $this->act($rx, $f, 'collected', ['occurred_on' => now()->toDateString(), 'reference' => 'Synthetic handover', 'counseling' => 'provided'], 422);
        $this->act($rx, $f, 'approve', $this->checks(), 422);
        $this->act($rx, $f, 'cancel');
        $newFill = collect($this->postJson("/api/pharmacy/prescriptions/$rx/fills", $this->fillBody($lot))->assertOk()->json('data.fills'))->last();
        $f = $this->complete($rx, $newFill);
        $this->assertSame(3, $f['reviewed_product']['revision']);
        $handed = (array) DB::table('pharmacy_fills')->find($f['id']);
        $this->postJson($url, $this->productBody(['revision' => 3, 'generic_name' => 'SYNTHETIC corrected name']))->assertCreated();
        $r = $this->getJson("/api/pharmacy/prescriptions/$rx")->assertOk();
        $saved = collect($r->json('data.fills'))->firstWhere('id', $f['id']);
        $this->assertSame(3, $saved['reviewed_product']['revision']);
        $this->assertSame(4, $saved['current_product']['revision']);
        $this->assertSame($handed, (array) DB::table('pharmacy_fills')->find($f['id']));
        // A legacy approval without a retained product is not grandfathered into preparation.
        $next = collect($this->postJson("/api/pharmacy/prescriptions/$rx/fills", $this->fillBody($lot))->assertOk()->json('data.fills'))->last();
        DB::table('pharmacy_fills')->where('id', $next['id'])->update(['review_status' => 'approved', 'review' => json_encode(['checks' => $this->checks()['checks']])]);
        $this->act($rx, $next, 'ready', ['checks' => ['label' => true]], 422);
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
        if ($a === 'ready' && $status === 200 && ! array_key_exists('label_id', $extra)) {
            $f = $this->labelFixture($rx, $f);
            $extra['label_id'] = $f['current_label']['id'];
        }
        if (in_array($a, ['ready', 'collected', 'delivered'], true)) {
            $extra += ['scan' => $this->scanEvidence($f, $a === 'ready')];
        }
        if (in_array($a, ['collected', 'delivered'], true)) {
            $extra += ['handover' => $this->handoverEvidence($a)];
        }
        $result = $this->postJson("/api/pharmacy/prescriptions/$rx/fills/{$f['id']}/actions", array_replace(['version' => $f['version'], 'product_id' => $f['current_product']['id'] ?? null, 'action' => $a, 'note' => 'Synthetic evidence'], $extra))->assertStatus($status);

        return $status === 200 ? collect($result->json('data.fills'))->firstWhere('id', $f['id']) : $f;
    }

    private function labelBody(int $rx, array $fill, array $overrides = []): array
    {
        $state = $this->getJson("/api/pharmacy/prescriptions/$rx/fills/{$fill['id']}/labels")->assertOk()->json('data');
        return array_replace(['request_id' => (string) Str::uuid(), 'version' => $state['fill_version'],
            'source_token' => $state['source_token'], 'previous_label_id' => $state['current']['id'] ?? 0,
            'dispensed_on' => now()->toDateString(), 'use_by' => now()->toDateString(),
            'use_by_reference' => 'SYNTHETIC package and policy basis only', 'substituted' => false, 'daw' => false,
            'selection_reference' => 'SYNTHETIC selection check', 'notification_reference' => null,
            'do_not_label' => false, 'disclosure_reference' => null, 'auxiliary_text' => 'SYNTHETIC storage instructions',
            'reason' => 'SYNTHETIC initial proof', 'confirmed' => true], $overrides);
    }

    private function printBody(array $overrides = []): array
    {
        return array_replace(['request_id' => (string) Str::uuid(), 'copies' => 1, 'occurred_on' => now()->toDateString(),
            'reason' => 'SYNTHETIC print simulation', 'reference' => 'No physical printer used; synthetic acceptance fixture', 'confirmed' => true], $overrides);
    }

    private function labelFixture(int $rx, array $fill): array
    {
        $url = "/api/pharmacy/prescriptions/$rx/fills/{$fill['id']}/labels";
        $label = $this->postJson($url, $this->labelBody($rx, $fill))->assertCreated()->json('data.current');
        $this->postJson("$url/{$label['id']}/prints", $this->printBody())->assertCreated();
        return collect($this->getJson("/api/pharmacy/prescriptions/$rx")->assertOk()->json('data.fills'))->firstWhere('id', $fill['id']);
    }

    public function test_labels_retain_escaped_documents_with_snapshot_integrity_and_replay_guards(): void
    {
        $rx = $this->rx(['directions' => '<script>alert("synthetic")</script> SYNTHETIC directions']);
        $f = $this->act($rx, $this->fill($rx, $this->lot()), 'approve', $this->checks());
        $url = "/api/pharmacy/prescriptions/$rx/fills/{$f['id']}/labels";
        $body = $this->labelBody($rx, $f);
        $label = $this->postJson($url, $body)->assertCreated()->assertJsonPath('data.current.fresh', true)->json('data.current');
        $this->postJson($url, $body)->assertOk()->assertJsonPath('data.current.id', $label['id']);
        $this->postJson($url, array_replace($body, ['reason' => 'changed']))->assertStatus(409);
        $this->assertSame(1, DB::table('pharmacy_fill_labels')->count());
        $doc = $this->get("$url/{$label['id']}/file")->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('no-store', $doc->headers->get('Cache-Control'));
        $this->assertStringContainsString('sandbox', $doc->headers->get('Content-Security-Policy'));
        $this->assertSame($label['sha256'], hash('sha256', $doc->getContent()));
        $this->assertStringContainsString('SYNTHETIC PROOF — NOT FOR DISPENSING', $doc->getContent());
        $this->assertStringContainsString('&lt;script&gt;', $doc->getContent());
        $this->assertStringNotContainsString('<script>', $doc->getContent());
        $this->assertStringContainsString('Synthetic Patient', $doc->getContent());
        $this->assertStringContainsString('SYNTHETIC manufacturer', $doc->getContent());
        $this->getJson($url)->assertJsonMissingPath('data.labels.data.0.request_hash')->assertJsonMissingPath('data.labels.data.0.document');
        $this->putJson("$url/{$label['id']}", [])->assertNotFound();
        $this->deleteJson("$url/{$label['id']}")->assertNotFound();
        $original = (array) DB::table('pharmacy_fill_labels')->find($label['id']);
        $second = $this->postJson($url, $this->labelBody($rx, $f, ['do_not_label' => true, 'disclosure_reference' => 'SYNTHETIC prescriber instruction']))->assertCreated()->json('data.current');
        $hidden = $this->get("$url/{$second['id']}/file")->assertOk()->getContent();
        foreach (['SYNTHETIC manufacturer', 'SYNTHETIC established name', 'Prescribed:', 'Dispensed:'] as $text) {
            $this->assertStringNotContainsString($text, $hidden);
        }
        $this->assertStringContainsString('SYNTHETIC directions', $hidden);
        $this->assertSame($original, (array) DB::table('pharmacy_fill_labels')->find($label['id']));
        $this->get("$url/{$label['id']}/file")->assertOk(); // retained history remains retrievable
        DB::table('pharmacy_fill_labels')->where('id', $second['id'])->update(['snapshot' => '{}']);
        $this->get("$url/{$second['id']}/file")->assertStatus(409);
        $this->getJson($url)->assertOk()->assertJsonPath('data.current.intact', false);
        $this->postJson("$url/{$second['id']}/prints", $this->printBody())->assertStatus(409);
    }

    public function test_label_decisions_scope_dates_and_stale_sources_fail_closed(): void
    {
        $rx = $this->rx(); $lot = $this->lot(); $f = $this->fill($rx, $lot);
        $url = "/api/pharmacy/prescriptions/$rx/fills/{$f['id']}/labels";
        $this->postJson($url, $this->labelBody($rx, $f))->assertStatus(422);
        $f = $this->act($rx, $f, 'approve', $this->checks());
        $body = $this->labelBody($rx, $f);
        foreach ([['substituted' => true], ['do_not_label' => true], ['substituted' => true, 'daw' => true, 'notification_reference' => 'Synthetic notification'],
            ['confirmed' => false], ['use_by' => now()->addYears(2)->toDateString()], ['dispensed_on' => now()->addDay()->toDateString()],
            ['dispensed_on' => now()->subYears(2)->toDateString()], ['use_by_reference' => '']] as $change) {
            $this->postJson($url, array_replace($body, $change))->assertStatus(422);
        }
        $this->postJson($url, array_replace($body, ['source_token' => str_repeat('0', 64)]))->assertStatus(409);
        $this->postJson($url, array_replace($body, ['previous_label_id' => 999]))->assertStatus(409);
        foreach (['pharmacy_technician', 'medical_biller', 'admin'] as $role) {
            $this->actor->role = $role; $this->actor->save();
            $this->postJson($url, $body)->assertForbidden();
        }
        $this->actor->role = 'pharmacist'; $this->actor->save();
        DB::table('pharmacy_staff_assignments')->where('location_id', $this->location)->update(['active' => false]);
        $this->getJson($url)->assertNotFound(); $this->postJson($url, $body)->assertNotFound();
        DB::table('pharmacy_staff_assignments')->where('location_id', $this->location)->update(['active' => true]);
        $this->actor->organization_id = 2; $this->actor->save();
        $this->getJson($url)->assertNotFound(); $this->postJson($url, $body)->assertNotFound();
        $this->actor->organization_id = 1; $this->actor->save();
        DB::table('pharmacy_locations')->where('id', $this->location)->update(['address' => 'SYNTHETIC changed address']);
        $this->postJson($url, $body)->assertStatus(409);
        $body = $this->labelBody($rx, $f, ['substituted' => true, 'notification_reference' => 'SYNTHETIC notification evidence']);
        $this->postJson($url, $body)->assertCreated();
        $this->actor->role = 'pharmacy_technician'; $this->actor->save();
        $this->getJson($url)->assertOk();
        $this->actor->role = 'medical_biller'; $this->actor->save();
        $this->getJson($url)->assertForbidden();
    }

    public function test_label_record_copies_preserve_historical_bytes_and_never_satisfy_dispensing_print_checks(): void
    {
        $rx = $this->rx(); $lot = $this->lot(); $f = $this->act($rx, $this->fill($rx, $lot), 'approve', $this->checks());
        $url = "/api/pharmacy/prescriptions/$rx/fills/{$f['id']}/labels";
        $label = $this->postJson($url, $this->labelBody($rx, $f))->assertCreated()->json('data.current');
        $saved = (array) DB::table('pharmacy_fill_labels')->find($label['id']);
        $f = $this->getJson("/api/pharmacy/prescriptions/$rx")->assertOk()->json('data.fills.0');
        $file = "$url/{$label['id']}/file?purpose=record_copy";
        $copy = $this->get($file)->assertOk()->assertHeader('Content-Disposition', 'attachment; filename="record-copy-'.$label['id'].'.html"')->getContent();
        $this->assertStringContainsString('RECORD COPY — NOT A DISPENSING LABEL', $copy);
        $this->assertStringContainsString($saved['sha256'], $copy);
        $this->assertStringContainsString('display:none!important', $copy);
        $body = $this->printBody(['purpose' => 'record_copy']);
        $this->postJson("$url/{$label['id']}/prints", $body)->assertCreated()->assertJsonPath('data.data.0.purpose', 'record_copy')->assertJsonPath('data.data.0.document_sha256', hash('sha256', $copy));
        $this->getJson($url)->assertJsonPath('data.current.print_count', 0);
        $this->act($rx, $f, 'ready', ['checks' => ['label' => true], 'label_id' => $label['id']], 422);
        $this->assertEquals(50, DB::table('pharmacy_stock_lots')->find($lot)->on_hand);
        // Existing callers without a purpose still record dispensing evidence; exact retries survive.
        $print = $this->printBody();
        $this->postJson("$url/{$label['id']}/prints", $print)->assertCreated()->assertJsonPath('data.data.0.purpose', 'dispensing_label');
        $this->postJson("$url/{$label['id']}/prints", $print)->assertOk();
        $this->getJson($url)->assertJsonPath('data.current.print_count', 1);
        $this->act($rx, $f, 'ready', ['checks' => ['label' => true], 'label_id' => $label['id']]);
        $this->assertSame($saved, (array) DB::table('pharmacy_fill_labels')->find($label['id']));
        $this->assertSame($saved['document'], $this->get("$url/{$label['id']}/file")->assertOk()->getContent());
    }

    public function test_label_record_copies_remain_available_after_completion_and_source_changes_without_mutation(): void
    {
        $rx = $this->rx(); $lot = $this->lot(); $f = $this->complete($rx, $this->fill($rx, $lot));
        $labelId = $f['current_label']['id']; $url = "/api/pharmacy/prescriptions/$rx/fills/{$f['id']}/labels/$labelId";
        $first = $this->get("$url/file?purpose=record_copy")->assertOk()->getContent();
        DB::table('pharmacy_locations')->where('id', $this->location)->update(['name' => 'SYNTHETIC later pharmacy name']);
        DB::table('pharmacy_prescriptions')->where('id', $rx)->update(['expires_on' => now()->subDay()->toDateString(), 'discontinued_at' => now(), 'controlled' => true]);
        $before = [];
        foreach (['pharmacy_prescriptions','pharmacy_fills','pharmacy_stock_lots','pharmacy_stock_events','pharmacy_fill_labels','invoices','payments'] as $table) { $before[$table] = DB::table($table)->get(); }
        $this->assertSame($first, $this->get("$url/file?purpose=record_copy")->assertOk()->getContent());
        $body = $this->printBody(['purpose' => 'record_copy']);
        $this->postJson("$url/prints", $body)->assertCreated();
        $this->postJson("$url/prints", $body)->assertOk();
        $this->postJson("$url/prints", array_replace($body, ['purpose' => 'dispensing_label']))->assertStatus(409);
        $this->postJson("$url/prints", $this->printBody(['purpose' => 'dispensing_label']))->assertUnprocessable();
        $this->postJson("$url/prints", $this->printBody(['purpose' => 'unknown']))->assertUnprocessable();
        $this->postJson("$url/prints", $this->printBody(['purpose' => 'record_copy', 'occurred_on' => now()->subDay()->toDateString()]))->assertUnprocessable();
        foreach ($before as $table => $rows) { $this->assertEquals($rows, DB::table($table)->get(), $table); }
        $this->assertSame(1, DB::table('pharmacy_label_prints')->where('purpose', 'record_copy')->count());
        $this->assertSame(1, DB::table('pharmacy_label_prints')->where('purpose', 'dispensing_label')->count());
    }

    public function test_label_record_copies_enforce_access_integrity_and_transactional_evidence(): void
    {
        $rx = $this->rx(); $f = $this->complete($rx, $this->fill($rx, $this->lot())); $id = $f['current_label']['id'];
        $url = "/api/pharmacy/prescriptions/$rx/fills/{$f['id']}/labels/$id"; $body = $this->printBody(['purpose' => 'record_copy']);
        $this->actor->role = 'pharmacy_technician'; $this->actor->save();
        $this->get("$url/file?purpose=record_copy")->assertOk();
        $this->postJson("$url/prints", $body)->assertCreated();
        $this->actor->role = 'medical_biller'; $this->actor->save();
        $this->getJson("$url/file?purpose=record_copy")->assertForbidden(); $this->postJson("$url/prints", $body)->assertForbidden();
        $this->actor->role = 'pharmacist'; $this->actor->save();
        DB::table('pharmacy_staff_assignments')->where('location_id', $this->location)->update(['active' => false]);
        $this->getJson("$url/file?purpose=record_copy")->assertNotFound(); $this->postJson("$url/prints", $body)->assertNotFound();
        DB::table('pharmacy_staff_assignments')->where('location_id', $this->location)->update(['active' => true]);
        $this->actor->organization_id = 2; $this->actor->save();
        $this->getJson("$url/file?purpose=record_copy")->assertNotFound(); $this->postJson("$url/prints", $body)->assertNotFound();
        $this->actor->organization_id = 1; $this->actor->save();
        $label = DB::table('pharmacy_fill_labels')->find($id);
        DB::table('pharmacy_fill_labels')->where('id', $id)->update(['document' => 'corrupt']);
        $this->getJson("$url/file?purpose=record_copy")->assertStatus(409);
        $this->postJson("$url/prints", $this->printBody(['purpose' => 'record_copy']))->assertStatus(409);
        DB::table('pharmacy_fill_labels')->where('id', $id)->update(['document' => $label->document]);
        $count = DB::table('pharmacy_label_prints')->count(); $events = DB::table('pharmacy_events')->count();
        DB::connection()->beforeExecuting(function ($query) { if (str_starts_with(strtolower($query), 'insert into') && str_contains($query, 'pharmacy_events')) { throw new \RuntimeException('Synthetic copy audit failure'); } });
        $this->postJson("$url/prints", $this->printBody(['purpose' => 'record_copy']))->assertStatus(500);
        $this->assertSame($count, DB::table('pharmacy_label_prints')->count()); $this->assertSame($events, DB::table('pharmacy_events')->count());
    }

    public function test_current_label_and_print_evidence_are_required_for_preparation_and_handover(): void
    {
        $rx = $this->rx(); $f = $this->act($rx, $this->fill($rx, $this->lot()), 'approve', $this->checks());
        $url = "/api/pharmacy/prescriptions/$rx/fills/{$f['id']}/labels";
        $this->act($rx, $f, 'ready', ['checks' => ['label' => true], 'label_id' => 0], 422);
        $body = $this->labelBody($rx, $f);
        $label = $this->postJson($url, $body)->assertCreated()->json('data.current');
        $f = $this->getJson("/api/pharmacy/prescriptions/$rx")->assertOk()->json('data.fills.0');
        $this->act($rx, $f, 'ready', ['checks' => ['label' => true], 'label_id' => $label['id']], 422);
        $print = $this->printBody();
        $this->postJson("$url/{$label['id']}/prints", array_replace($print, ['occurred_on' => now()->subDay()->toDateString()]))->assertStatus(422);
        $this->postJson("$url/{$label['id']}/prints", $print)->assertCreated();
        $this->postJson("$url/{$label['id']}/prints", $print)->assertOk();
        $this->postJson("$url/{$label['id']}/prints", array_replace($print, ['copies' => 2]))->assertStatus(409);
        $this->assertSame(1, DB::table('pharmacy_label_prints')->count());
        $second = $this->postJson($url, $this->labelBody($rx, $f, ['reason' => 'SYNTHETIC correction']))->assertCreated()->json('data.current');
        $this->postJson("$url/{$label['id']}/prints", $this->printBody())->assertStatus(422);
        $f = $this->getJson("/api/pharmacy/prescriptions/$rx")->assertOk()->json('data.fills.0');
        $this->act($rx, $f, 'ready', ['checks' => ['label' => true], 'label_id' => $label['id']], 422);
        $this->postJson("$url/{$second['id']}/prints", $this->printBody())->assertCreated();
        $f = $this->act($rx, $f, 'ready', ['checks' => ['label' => true], 'label_id' => $second['id']]);
        $this->assertSame($second['id'], $f['fulfillment']['prepared_label_id']);
        $this->postJson($url, $this->labelBody($rx, $f))->assertStatus(422);
        DB::table('pharmacy_locations')->where('id', $this->location)->update(['name' => 'SYNTHETIC changed pharmacy']);
        $before = DB::table('pharmacy_stock_lots')->first();
        $this->act($rx, $f, 'collected', ['occurred_on' => now()->toDateString(), 'reference' => 'SYNTHETIC', 'counseling' => 'provided'], 422);
        $this->assertEquals($before, DB::table('pharmacy_stock_lots')->first());
        $this->act($rx, $f, 'cancel');
        $this->postJson("$url/{$second['id']}/prints", $this->printBody())->assertStatus(422);
        $this->get("$url/{$second['id']}/file")->assertOk();
    }

    public function test_label_audit_failure_rolls_back_artifact_and_fill_version(): void
    {
        $rx = $this->rx(); $f = $this->act($rx, $this->fill($rx, $this->lot()), 'approve', $this->checks());
        $url = "/api/pharmacy/prescriptions/$rx/fills/{$f['id']}/labels";
        $body = $this->labelBody($rx, $f);
        DB::connection()->beforeExecuting(function ($query) {
            if (str_starts_with(strtolower($query), 'insert into') && str_contains($query, 'pharmacy_events')) {
                throw new \RuntimeException('Synthetic label audit failure');
            }
        });
        $this->postJson($url, $body)->assertStatus(500);
        $this->assertSame(0, DB::table('pharmacy_fill_labels')->count());
        $this->assertSame($f['version'], (int) DB::table('pharmacy_fills')->where('id', $f['id'])->value('version'));
    }

    public function test_label_print_evidence_is_scoped_and_rolls_back_with_its_audit(): void
    {
        $rx = $this->rx(); $f = $this->act($rx, $this->fill($rx, $this->lot()), 'approve', $this->checks());
        $url = "/api/pharmacy/prescriptions/$rx/fills/{$f['id']}/labels";
        $label = $this->postJson($url, $this->labelBody($rx, $f))->assertCreated()->json('data.current');
        $file = "$url/{$label['id']}/file"; $prints = "$url/{$label['id']}/prints";
        $body = $this->printBody();
        foreach ([['copies' => 0], ['copies' => 21], ['confirmed' => false], ['reference' => ''], ['occurred_on' => now()->addDay()->toDateString()]] as $change) {
            $this->postJson($prints, array_replace($body, $change))->assertStatus(422);
        }
        $this->actor->role = 'pharmacy_technician'; $this->actor->save();
        $this->get($file)->assertOk(); $this->postJson($prints, $body)->assertCreated();
        $this->actor->role = 'admin'; $this->actor->save();
        $this->get($file, ['Accept' => 'application/json'])->assertForbidden(); $this->postJson($prints, $body)->assertForbidden();
        $this->actor->role = 'pharmacist'; $this->actor->save();
        DB::table('pharmacy_staff_assignments')->where('location_id', $this->location)->update(['active' => false]);
        $this->get($file, ['Accept' => 'application/json'])->assertNotFound(); $this->getJson($prints)->assertNotFound(); $this->postJson($prints, $body)->assertNotFound();
        DB::table('pharmacy_staff_assignments')->where('location_id', $this->location)->update(['active' => true]);
        $this->actor->organization_id = 2; $this->actor->save();
        $this->get($file, ['Accept' => 'application/json'])->assertNotFound(); $this->postJson($prints, $body)->assertNotFound();
        $this->actor->organization_id = 1; $this->actor->save();
        $other = $this->independentReviewer(); $this->actingAs($other, 'api');
        $this->postJson($prints, $body)->assertStatus(409);
        $this->actingAs($this->actor, 'api');
        $original = DB::table('pharmacy_fill_labels')->where('id', $label['id'])->value('document');
        DB::table('pharmacy_fill_labels')->where('id', $label['id'])->update(['document' => '<script>corrupt</script>']);
        $this->get($file, ['Accept' => 'application/json'])->assertStatus(409);
        $this->postJson($prints, $this->printBody())->assertStatus(409);
        DB::table('pharmacy_fill_labels')->where('id', $label['id'])->update(['document' => $original]);
        DB::connection()->beforeExecuting(function ($query) {
            if (str_starts_with(strtolower($query), 'insert into') && str_contains($query, 'pharmacy_events')) {
                throw new \RuntimeException('Synthetic print audit failure');
            }
        });
        $this->postJson($prints, $this->printBody())->assertStatus(500);
        $this->assertSame(1, DB::table('pharmacy_label_prints')->count());
        $this->assertSame(0, DB::table('invoices')->count());
    }

    public function test_label_freshness_tracks_product_review_patient_identity_and_source_changes(): void
    {
        $rx = $this->rx(); $lot = $this->lot(); $f = $this->act($rx, $this->fill($rx, $lot), 'approve', $this->checks());
        $f = $this->labelFixture($rx, $f);
        $url = "/api/pharmacy/prescriptions/$rx/fills/{$f['id']}/labels";
        $labelId = $f['current_label']['id'];
        $this->postJson("/api/pharmacy/stock/$lot/product", $this->productBody(['revision' => 1]))->assertCreated();
        $this->getJson($url)->assertOk()->assertJsonPath('data.current.fresh', false);
        $this->postJson("$url/$labelId/prints", $this->printBody())->assertStatus(422);
        $this->postJson($url, $this->labelBody($rx, $f))->assertStatus(422);
        $f = $this->getJson("/api/pharmacy/prescriptions/$rx")->assertOk()->json('data.fills.0');
        $f = $this->act($rx, $f, 'approve', $this->checks());
        $this->act($rx, $f, 'ready', ['checks' => ['label' => true], 'label_id' => $labelId], 422);
        $f = $this->labelFixture($rx, $f);
        $labelId = $f['current_label']['id'];
        $this->patient->last_name = 'SYNTHETIC corrected identity'; $this->patient->save();
        $this->getJson($url)->assertOk()->assertJsonPath('data.current.fresh', false);
        $this->act($rx, $f, 'ready', ['checks' => ['label' => true], 'label_id' => $labelId], 422);
        $f = $this->labelFixture($rx, $f);
        $labelId = $f['current_label']['id'];
        \Illuminate\Support\Facades\Storage::fake('documents');
        $this->post("/api/pharmacy/prescriptions/$rx/sources", ['request_id' => (string) Str::uuid(), 'reference' => 'SYNTHETIC new source', 'file' => \Illuminate\Http\UploadedFile::fake()->createWithContent('synthetic.pdf', "%PDF-1.4\n%%EOF")], ['Accept' => 'application/json'])->assertCreated();
        $this->getJson($url)->assertOk()->assertJsonPath('data.current.fresh', false);
        $this->postJson("$url/$labelId/prints", $this->printBody())->assertStatus(422);
        $this->get("$url/$labelId/file")->assertOk();
    }

    public function test_package_barcode_requires_a_valid_exact_gtin_and_preserves_leading_zeros(): void
    {
        $service = app(\App\Services\PharmacyBarcode::class);
        foreach (['96385074', '036000291452', '4006381333931', '00012345600012', '99999999999997'] as $code) {
            $this->assertTrue($service->validPackageCode($code), $code);
        }
        foreach (['', '00000000', '036000291453', '36000291452', ' 036000291452', '036000291452 ', '1234567890', 'ABC12345', ']C1010036000291452'] as $code) {
            $this->assertFalse($service->validPackageCode($code), $code);
        }
        $lot = $this->lot([], false); $url = "/api/pharmacy/stock/$lot/product";
        $this->postJson($url, $this->productBody(['package_code' => '036000291453']))->assertUnprocessable();
        $this->postJson($url, $this->productBody(['package_code' => null]))->assertUnprocessable();
        $this->assertSame(0, DB::table('pharmacy_stock_products')->count());
        $this->postJson($url, $this->productBody(['package_code' => '036000291452']))->assertCreated()->assertJsonPath('data.product.package_code', '036000291452');
    }

    public function test_barcode_preparation_requires_exact_package_lot_and_current_label_without_inventory_change_on_failure(): void
    {
        $rx = $this->rx(); $lot = $this->lot();
        $f = $this->act($rx, $this->fill($rx, $lot), 'approve', $this->checks());
        $f = $this->labelFixture($rx, $f); $label = $f['current_label']['id'];
        $url = "/api/pharmacy/prescriptions/$rx/fills/{$f['id']}/actions";
        $body = ['action' => 'ready', 'version' => $f['version'], 'label_id' => $label, 'note' => 'SYNTHETIC final check', 'checks' => ['label' => true], 'scan' => $this->scanEvidence($f)];
        $stock = (array) DB::table('pharmacy_stock_lots')->find($lot); $saved = (array) DB::table('pharmacy_fills')->find($f['id']);
        foreach ([null, [], $this->scanEvidence($f, true, ['label_code' => 'FMTL-WRONG']), $this->scanEvidence($f, true, ['package_code' => '99999999999996']), $this->scanEvidence($f, true, ['lot_number' => 'OTHER-LOT']), $this->scanEvidence($f, true, ['input_method' => 'manual', 'manual_reason' => null]), $this->scanEvidence($f, true, ['confirmed' => false]), $this->scanEvidence($f, true, ['actor_id' => 999]), $this->scanEvidence($f, true, ['input_method' => 'scanner'])] as $bad) {
            $this->postJson($url, array_replace($body, ['scan' => $bad]))->assertUnprocessable();
        }
        $this->assertSame($stock, (array) DB::table('pharmacy_stock_lots')->find($lot));
        $this->assertSame($saved, (array) DB::table('pharmacy_fills')->find($f['id']));
        $r = $this->postJson($url, $body)->assertOk(); $ready = collect($r->json('data.fills'))->firstWhere('id', $f['id']);
        $this->assertSame($label, $ready['fulfillment']['prepared_scan']['label_id']);
        $this->assertSame('99999999999997', $ready['fulfillment']['prepared_scan']['package_code']);
        $this->assertSame($this->actor->id, $ready['fulfillment']['prepared_scan']['actor_id']);
        $this->assertSame('manual', $ready['fulfillment']['prepared_scan']['input_method']);
        $this->postJson($url, $body)->assertStatus(409);
    }

    public function test_barcode_label_revisions_retain_unique_intact_codes_and_reject_foreign_or_superseded_codes(): void
    {
        $rx = $this->rx(); $f = $this->act($rx, $this->fill($rx, $this->lot()), 'approve', $this->checks());
        $f = $this->labelFixture($rx, $f);
        $first = DB::table('pharmacy_fill_labels')->where('fill_id', $f['id'])->first();
        $this->assertMatchesRegularExpression('/^FMTL-[A-Z0-9]{20}$/D', $first->barcode_code);
        $this->assertStringContainsString('<svg', $first->document);
        $this->assertStringContainsString($first->barcode_code, $first->document);
        $this->assertSame($first->barcode_code, json_decode($first->snapshot, true)['barcode_code']);
        $f = $this->labelFixture($rx, $f); $second = DB::table('pharmacy_fill_labels')->where('fill_id', $f['id'])->orderByDesc('revision')->first();
        $this->assertNotSame($first->barcode_code, $second->barcode_code);
        $this->assertSame((array) $first, (array) DB::table('pharmacy_fill_labels')->find($first->id));
        $this->act($rx, $f, 'ready', ['label_id' => $second->id, 'checks' => ['label' => true], 'scan' => $this->scanEvidence($f, true, ['label_code' => $first->barcode_code])], 422);
        $otherRx = $this->rx(); $other = $this->act($otherRx, $this->fill($otherRx, $this->lot()), 'approve', $this->checks()); $other = $this->labelFixture($otherRx, $other);
        $foreignCode = DB::table('pharmacy_fill_labels')->where('fill_id', $other['id'])->value('barcode_code');
        $this->act($rx, $f, 'ready', ['label_id' => $second->id, 'checks' => ['label' => true], 'scan' => $this->scanEvidence($f, true, ['label_code' => $foreignCode])], 422);
        DB::table('pharmacy_fill_labels')->where('id', $second->id)->update(['barcode_code' => $first->barcode_code.'X']);
        $this->get("/api/pharmacy/prescriptions/$rx/fills/{$f['id']}/labels/{$second->id}/file")->assertStatus(409);
    }

    public function test_barcode_handover_requires_rescan_of_prepared_label_and_retains_manual_or_scanner_method(): void
    {
        $rx = $this->rx(); $lot = $this->lot(); $f = $this->act($rx, $this->fill($rx, $lot), 'approve', $this->checks());
        $f = $this->act($rx, $f, 'ready', ['checks' => ['label' => true]]);
        $url = "/api/pharmacy/prescriptions/$rx/fills/{$f['id']}/actions"; $stock = (array) DB::table('pharmacy_stock_lots')->find($lot);
        foreach ([null, $this->scanEvidence($f, false, ['label_code' => 'OTHER']), $this->scanEvidence($f, false, ['package_code' => '99999999999997']), $this->scanEvidence($f, false, ['confirmed' => false])] as $bad) {
            $this->postJson($url, $this->handoverBody($f, 'collected', ['scan' => $bad]))->assertUnprocessable();
        }
        $saved = $f['fulfillment']; $legacy = $saved; unset($legacy['prepared_scan']);
        DB::table('pharmacy_fills')->where('id', $f['id'])->update(['fulfillment' => json_encode($legacy)]);
        $this->postJson($url, $this->handoverBody($f))->assertUnprocessable()->assertJsonPath('message', 'Preparation has no matching retained code check. Cancel this open fill and prepare a new one.');
        DB::table('pharmacy_fills')->where('id', $f['id'])->update(['fulfillment' => json_encode($saved)]);
        $this->assertSame($stock, (array) DB::table('pharmacy_stock_lots')->find($lot));
        $r = $this->postJson($url, $this->handoverBody($f, 'collected', ['scan' => $this->scanEvidence($f, false, ['input_method' => 'scanner', 'manual_reason' => null])]))->assertOk();
        $done = collect($r->json('data.fills'))->firstWhere('id', $f['id']);
        $this->assertSame('scanner', $done['fulfillment']['scan']['input_method']);
        $this->assertSame($done['fulfillment']['prepared_scan']['label_code'], $done['fulfillment']['scan']['label_code']);
        $this->assertSame($this->actor->id, $done['fulfillment']['scan']['actor_id']);
        $this->assertSame(1, DB::table('pharmacy_stock_events')->where('action', 'dispensed')->count());
    }

    public function test_barcode_legacy_product_records_do_not_gain_package_verification(): void
    {
        $rx = $this->rx(); $lot = $this->lot();
        DB::table('pharmacy_stock_products')->where('stock_lot_id', $lot)->update(['package_code' => null]);
        $f = $this->act($rx, $this->fill($rx, $lot), 'approve', $this->checks());
        $this->postJson("/api/pharmacy/prescriptions/$rx/fills/{$f['id']}/labels", $this->labelBody($rx, $f))->assertUnprocessable();
        $this->assertSame(0, DB::table('pharmacy_fill_labels')->count());
        $this->assertNull(DB::table('pharmacy_stock_products')->where('stock_lot_id', $lot)->value('package_code'));
    }

    private function scanEvidence(array $fill, bool $preparation = true, array $overrides = []): array
    {
        $label = DB::table('pharmacy_fill_labels')->where('fill_id', $fill['id'])->orderByDesc('revision')->first();
        $stock = DB::table('pharmacy_stock_lots')->find($fill['stock_lot_id']);
        $product = app(\App\Services\PharmacyProduct::class)->current((int) $stock->id);
        return array_replace(['label_code' => $label->barcode_code ?? 'NO-LABEL', 'input_method' => 'manual', 'manual_reason' => 'SYNTHETIC simulated code comparison; no physical scanner used', 'confirmed' => true]
            + ($preparation ? ['package_code' => $product->package_code ?? '', 'lot_number' => $stock->lot_number] : []), $overrides);
    }

    private function handoverEvidence(string $action = 'collected', array $overrides = []): array
    {
        return array_replace(['recipient_type' => 'patient', 'recipient_name' => 'Synthetic Patient',
            'identity_reference' => 'SYNTHETIC two-identifier check; no actual ID collected',
            'counseling_reference' => 'SYNTHETIC counseling evidence only', 'confirmed' => true]
            + ($action === 'delivered' ? ['delivery_method' => 'tracked_carrier', 'delivery_reference' => 'SYNTHETIC carrier and recipient receipt confirmation'] : []), $overrides);
    }

    private function handoverBody(array $fill, string $action = 'collected', array $overrides = []): array
    {
        return array_replace(['version' => $fill['version'], 'action' => $action, 'note' => 'SYNTHETIC completed handover',
            'occurred_on' => now()->toDateString(), 'reference' => 'SYNTHETIC receipt', 'counseling' => 'provided',
            'handover' => $this->handoverEvidence($action), 'scan' => $this->scanEvidence($fill, false)], $overrides);
    }

    public function test_handover_requires_explicit_recipient_checks_and_rejects_unsupported_evidence(): void
    {
        $rx = $this->rx(); $lot = $this->lot();
        $f = $this->act($rx, $this->fill($rx, $lot), 'approve', $this->checks());
        $f = $this->act($rx, $f, 'ready', ['checks' => ['label' => true]]);
        $url = "/api/pharmacy/prescriptions/$rx/fills/{$f['id']}/actions";
        $stock = (array) DB::table('pharmacy_stock_lots')->find($lot);
        $saved = (array) DB::table('pharmacy_fills')->find($f['id']);
        $events = DB::table('pharmacy_stock_events')->count();
        $this->postJson($url, $this->handoverBody($f, 'collected', ['handover' => null]))->assertUnprocessable();
        foreach (['recipient_type', 'recipient_name', 'identity_reference', 'counseling_reference', 'confirmed'] as $field) {
            $this->postJson($url, $this->handoverBody($f, 'collected', ['handover' => $this->handoverEvidence('collected', [$field => null])]))->assertUnprocessable()->assertJsonValidationErrors("handover.$field");
        }
        foreach ([['confirmed' => false], ['recipient_type' => 'carrier'], ['recipient_name' => '   '], ['recorded_by' => 999], ['relationship' => 'friend'], ['delivery_method' => 'tracked_carrier']] as $invalid) {
            $this->postJson($url, $this->handoverBody($f, 'collected', ['handover' => $this->handoverEvidence('collected', $invalid)]))->assertUnprocessable();
        }
        $this->assertSame($stock, (array) DB::table('pharmacy_stock_lots')->find($lot));
        $this->assertSame($saved, (array) DB::table('pharmacy_fills')->find($f['id']));
        $this->assertSame($events, DB::table('pharmacy_stock_events')->count());
        $body = $this->handoverBody($f);
        $r = $this->postJson($url, $body)->assertOk();
        $evidence = collect($r->json('data.fills'))->firstWhere('id', $f['id'])['fulfillment']['handover'];
        $this->assertSame($this->actor->id, $evidence['recorded_by']);
        $this->assertSame('Synthetic Patient', $evidence['recipient_name']);
        $this->assertNotEmpty($evidence['recorded_at']);
        $this->assertArrayNotHasKey('confirmed', $evidence);
        $this->postJson($url, $body)->assertStatus(409); // stale retry cannot deduct twice
        $done = (array) DB::table('pharmacy_fills')->find($f['id']);
        $this->postJson($url, array_replace($body, ['version' => $done['version'], 'handover' => $this->handoverEvidence('collected', ['recipient_name' => 'overwrite'])]))->assertUnprocessable();
        $this->assertSame($done, (array) DB::table('pharmacy_fills')->find($f['id']));
        $this->assertSame(1, DB::table('pharmacy_stock_events')->where('action', 'dispensed')->count());
        $this->assertEquals(40, DB::table('pharmacy_stock_lots')->where('id', $lot)->value('on_hand'));
        $this->assertSame(0, DB::table('invoices')->count());
    }

    public function test_handover_to_representative_requires_authority_and_confirmed_delivery(): void
    {
        Http::preventStrayRequests();
        $rx = $this->rx(); $f = $this->act($rx, $this->fill($rx, $this->lot()), 'approve', $this->checks());
        $f = $this->act($rx, $f, 'ready', ['checks' => ['label' => true]]);
        $url = "/api/pharmacy/prescriptions/$rx/fills/{$f['id']}/actions";
        $evidence = $this->handoverEvidence('delivered', ['recipient_type' => 'representative', 'recipient_name' => 'SYNTHETIC Representative', 'relationship' => 'SYNTHETIC authorized caregiver', 'authority_reference' => 'SYNTHETIC patient authority reviewed']);
        foreach (['relationship', 'authority_reference', 'delivery_method', 'delivery_reference', 'counseling_reference'] as $field) {
            $this->postJson($url, $this->handoverBody($f, 'delivered', ['handover' => array_replace($evidence, [$field => null])]))->assertUnprocessable()->assertJsonValidationErrors("handover.$field");
        }
        $this->postJson($url, $this->handoverBody($f, 'delivered', ['handover' => array_replace($evidence, ['delivery_method' => 'unattended'])]))->assertUnprocessable();
        $r = $this->postJson($url, $this->handoverBody($f, 'delivered', ['handover' => $evidence, 'counseling' => 'documented_remote']))->assertOk();
        $done = collect($r->json('data.fills'))->firstWhere('id', $f['id']);
        $this->assertSame('delivered', $done['fulfillment_status']);
        $this->assertSame($evidence['authority_reference'], $done['fulfillment']['handover']['authority_reference']);
        $this->assertSame($evidence['delivery_reference'], $done['fulfillment']['handover']['delivery_reference']);
        $this->assertSame('documented_remote', $done['fulfillment']['counseling']);
        $this->assertSame($f['fulfillment']['prepared_label_id'], $done['fulfillment']['prepared_label_id']);
        Http::assertNothingSent();
    }

    public function test_handover_date_cannot_precede_final_preparation_or_have_missing_preparation_evidence(): void
    {
        $rx = $this->rx(['written_on' => now()->subDays(2)->toDateString()]); $lot = $this->lot();
        $f = $this->act($rx, $this->fill($rx, $lot), 'approve', $this->checks());
        $labels = "/api/pharmacy/prescriptions/$rx/fills/{$f['id']}/labels";
        $label = $this->postJson($labels, $this->labelBody($rx, $f, ['dispensed_on' => now()->subDay()->toDateString()]))->assertCreated()->json('data.current');
        $this->postJson("$labels/{$label['id']}/prints", $this->printBody())->assertCreated();
        $f = $this->getJson("/api/pharmacy/prescriptions/$rx")->assertOk()->json('data.fills.0');
        $f = $this->act($rx, $f, 'ready', ['checks' => ['label' => true], 'label_id' => $label['id']]);
        $url = "/api/pharmacy/prescriptions/$rx/fills/{$f['id']}/actions";
        $stock = (array) DB::table('pharmacy_stock_lots')->find($lot);
        $this->postJson($url, $this->handoverBody($f, 'collected', ['occurred_on' => now()->subDay()->toDateString()]))->assertUnprocessable()->assertJsonPath('message', 'Handover cannot predate final preparation.');
        $this->postJson($url, $this->handoverBody($f, 'collected', ['occurred_on' => now()->addDay()->toDateString()]))->assertUnprocessable();
        $old = $f['fulfillment']; unset($old['prepared_at']);
        DB::table('pharmacy_fills')->where('id', $f['id'])->update(['fulfillment' => json_encode($old)]);
        $this->postJson($url, $this->handoverBody($f))->assertUnprocessable()->assertJsonPath('message', 'Final preparation evidence is missing. Cancel this open fill and prepare a new one.');
        $this->assertSame($stock, (array) DB::table('pharmacy_stock_lots')->find($lot));
    }

    public function test_handover_evidence_preserves_access_controls_and_rolls_back_on_audit_failure(): void
    {
        $rx = $this->rx(); $lot = $this->lot();
        $f = $this->act($rx, $this->fill($rx, $lot), 'approve', $this->checks());
        $f = $this->act($rx, $f, 'ready', ['checks' => ['label' => true]]);
        $url = "/api/pharmacy/prescriptions/$rx/fills/{$f['id']}/actions"; $body = $this->handoverBody($f);
        foreach (['pharmacy_technician', 'medical_biller', 'admin'] as $role) {
            $this->actor->role = $role; $this->actor->save(); $this->postJson($url, $body)->assertForbidden();
        }
        $this->actor->role = 'pharmacist'; $this->actor->save();
        DB::table('pharmacy_staff_assignments')->where('location_id', $this->location)->update(['active' => false]);
        $this->postJson($url, $body)->assertNotFound();
        DB::table('pharmacy_staff_assignments')->where('location_id', $this->location)->update(['active' => true]);
        $this->actor->organization_id = 2; $this->actor->save(); $this->postJson($url, $body)->assertNotFound();
        $this->actor->organization_id = 1; $this->actor->save();
        $saved = (array) DB::table('pharmacy_fills')->find($f['id']); $stock = (array) DB::table('pharmacy_stock_lots')->find($lot);
        $count = DB::table('pharmacy_stock_events')->count();
        DB::connection()->beforeExecuting(function ($query) {
            if (str_starts_with(strtolower($query), 'insert into') && str_contains($query, 'pharmacy_events')) { throw new \RuntimeException('SYNTHETIC handover audit failure'); }
        });
        $this->postJson($url, $body)->assertStatus(500);
        $this->assertSame($saved, (array) DB::table('pharmacy_fills')->find($f['id']));
        $this->assertSame($stock, (array) DB::table('pharmacy_stock_lots')->find($lot));
        $this->assertSame($count, DB::table('pharmacy_stock_events')->count());
    }

    public function test_handover_review_worklist_includes_older_fills_without_duplicates_and_clears_after_review(): void
    {
        $rx = $this->rx(); $lot = $this->lot(); $f = $this->complete($rx, $this->fill($rx, $lot));
        $url = "/api/pharmacy/prescriptions/$rx/fills/{$f['id']}/handover-addenda";
        $ids = [];
        for ($i = 0; $i < 2; $i++) { $ids[] = $this->postJson($url, $this->handoverAddendumBody($url))->assertCreated()->json('data.addenda.data.0.id'); }
        $newFill = collect($this->postJson("/api/pharmacy/prescriptions/$rx/fills", $this->fillBody($lot))->assertOk()->json('data.fills'))->last(); $this->act($rx, $newFill, 'cancel');
        $this->postJson("/api/pharmacy/prescriptions/$rx/discontinue", ['request_id' => (string) Str::uuid(), 'reason' => 'SYNTHETIC stop', 'reference' => 'SYNTHETIC authority'])->assertOk();
        $pending = '/api/pharmacy/prescriptions?attention=handover_addenda'; $eligible = '/api/pharmacy/prescriptions?attention=handover_review';
        $this->getJson($pending)->assertOk()->assertJsonPath('data.total', 1)->assertJsonPath('data.data.0.pending_handover_count', 2)
            ->assertJsonPath('data.data.0.pending_handover_fill_id', $f['id'])->assertJsonPath('data.data.0.stage', 'cancelled')->assertJsonPath('data.data.0.reviewable_handover_count', 0);
        $this->getJson($eligible)->assertOk()->assertJsonPath('data.total', 0);
        $reviewer = $this->independentReviewer(); $this->actingAs($reviewer, 'api');
        $this->getJson($eligible)->assertOk()->assertJsonPath('data.total', 1)->assertJsonPath('data.data.0.reviewable_handover_count', 2)->assertJsonPath('data.data.0.reviewable_handover_fill_id', $f['id']);
        $own = $this->postJson($url, $this->handoverAddendumBody($url))->assertCreated()->json('data.addenda.data.0.id');
        $this->getJson($eligible)->assertJsonPath('data.data.0.pending_handover_count', 3)->assertJsonPath('data.data.0.reviewable_handover_count', 2);
        foreach ($ids as $i => $id) {
            $this->postJson("$url/$id/review", ['decision' => $i ? 'accepted' : 'rejected', 'evidence' => 'SYNTHETIC independent review', 'confirmed' => true])->assertOk();
        }
        $this->getJson($eligible)->assertJsonPath('data.total', 0);
        $this->getJson($pending)->assertJsonPath('data.total', 1)->assertJsonPath('data.data.0.pending_handover_count', 1);
        $this->actingAs($this->actor, 'api');
        $this->getJson($eligible)->assertJsonPath('data.data.0.reviewable_handover_count', 1);
        $this->postJson("$url/$own/review", ['decision' => 'accepted', 'evidence' => 'SYNTHETIC independent review', 'confirmed' => true])->assertOk();
        $this->getJson($pending)->assertJsonPath('data.total', 0);
        $this->getJson($eligible)->assertJsonPath('data.total', 0);
        $this->getJson('/api/pharmacy/prescriptions')->assertJsonPath('data.data.0.pending_handover_count', 0);
    }

    public function test_handover_review_worklist_obeys_site_organization_and_read_role_boundaries(): void
    {
        $rx = $this->rx(); $lot = $this->lot(); $f = $this->complete($rx, $this->fill($rx, $lot));
        $url = "/api/pharmacy/prescriptions/$rx/fills/{$f['id']}/handover-addenda";
        $this->postJson($url, $this->handoverAddendumBody($url))->assertCreated();
        $pending = '/api/pharmacy/prescriptions?attention=handover_addenda'; $eligible = '/api/pharmacy/prescriptions?attention=handover_review';
        $reviewer = $this->independentReviewer(); $this->actingAs($reviewer, 'api');
        $this->getJson($pending.'&location_id='.$this->otherLocation)->assertJsonPath('data.total', 0);
        $this->getJson($pending.'&search=DOES-NOT-EXIST')->assertJsonPath('data.total', 0);
        DB::table('pharmacy_staff_assignments')->where('user_id', $reviewer->id)->update(['valid_until' => now()->subDay()->toDateString()]);
        $this->getJson($pending)->assertJsonPath('data.total', 0); $this->getJson($eligible)->assertJsonPath('data.total', 0);
        DB::table('pharmacy_staff_assignments')->where('user_id', $reviewer->id)->update(['valid_until' => now()->addDay()->toDateString()]);
        $reviewer->organization_id = 2; $reviewer->save();
        $this->getJson($pending)->assertJsonPath('data.total', 0); $this->getJson($eligible)->assertJsonPath('data.total', 0);
        $reviewer->organization_id = 1; $reviewer->role = 'pharmacy_technician'; $reviewer->save();
        $this->getJson($pending)->assertOk()->assertJsonPath('data.data.0.pending_handover_count', 1)->assertJsonPath('data.data.0.reviewable_handover_count', 0);
        $this->getJson($eligible)->assertForbidden();
        foreach (['medical_biller', 'admin'] as $role) {
            $reviewer->role = $role; $reviewer->save();
            $this->getJson($pending)->assertForbidden(); $this->getJson($eligible)->assertForbidden();
            $this->getJson('/api/pharmacy/prescriptions')->assertOk()->assertJsonPath('data.data.0.pending_handover_count', 0)->assertJsonPath('data.data.0.pending_handover_fill_id', null);
        }
    }

    private function handoverAddendumBody(string $url): array
    {
        return ['request_id' => (string) Str::uuid(), 'ledger_token' => $this->getJson($url)->assertOk()->json('data.ledger_token'),
            'section' => 'recipient', 'statement' => 'SYNTHETIC recipient spelling correction; original retained',
            'reason' => 'SYNTHETIC transcription error', 'evidence' => 'SYNTHETIC source confirmation', 'confirmed' => true];
    }

    public function test_handover_addenda_require_independent_review_and_preserve_completed_records(): void
    {
        Http::preventStrayRequests();
        $rx = $this->rx(); $lot = $this->lot(); $f = $this->complete($rx, $this->fill($rx, $lot));
        $url = "/api/pharmacy/prescriptions/$rx/fills/{$f['id']}/handover-addenda";
        $fill = (array) DB::table('pharmacy_fills')->find($f['id']); $stock = (array) DB::table('pharmacy_stock_lots')->find($lot);
        $events = DB::table('pharmacy_stock_events')->get()->toJson();
        $body = $this->handoverAddendumBody($url);
        $id = $this->postJson($url, $body)->assertCreated()->assertJsonPath('data.addenda.data.0.status', 'pending')->json('data.addenda.data.0.id');
        $this->postJson($url, $body)->assertOk();
        $this->postJson($url, array_replace($body, ['statement' => 'changed retry']))->assertStatus(409);
        $this->postJson($url, array_replace($body, ['request_id' => (string) Str::uuid()]))->assertStatus(409);
        $review = ['decision' => 'accepted', 'evidence' => 'SYNTHETIC independent source review', 'confirmed' => true];
        $this->postJson("$url/$id/review", $review)->assertUnprocessable();
        $reviewer = $this->independentReviewer(); $this->actingAs($reviewer, 'api');
        $this->postJson($url, $body)->assertStatus(409);
        $this->postJson("$url/$id/review", $review)->assertOk()->assertJsonPath('data.addenda.data.0.status', 'accepted');
        $this->postJson("$url/$id/review", $review)->assertOk();
        $this->postJson("$url/$id/review", array_replace($review, ['decision' => 'rejected']))->assertStatus(409);
        $this->getJson($url)->assertJsonMissingPath('data.addenda.data.0.request_hash')->assertJsonMissingPath('data.addenda.data.0.source_hash');
        $this->putJson("$url/$id", ['statement' => 'overwrite'])->assertNotFound();
        $this->deleteJson("$url/$id")->assertNotFound();
        $this->actingAs($this->actor, 'api');
        $this->postJson("$url/$id/review", $review)->assertUnprocessable();
        $this->assertSame($fill, (array) DB::table('pharmacy_fills')->find($f['id']));
        $this->assertSame($stock, (array) DB::table('pharmacy_stock_lots')->find($lot));
        $this->assertSame($events, DB::table('pharmacy_stock_events')->get()->toJson());
        $this->assertSame(0, DB::table('invoices')->count());
        $this->assertSame(0, DB::table('payments')->count());
        $this->assertSame(2, DB::table('pharmacy_events')->where('action', 'like', 'handover_addendum_%')->count());
        // Later addenda supplement rather than overwrite the accepted statement; history paginates.
        for ($i = 0; $i < 10; $i++) { $this->postJson($url, $this->handoverAddendumBody($url))->assertCreated(); }
        $this->getJson($url)->assertJsonPath('data.addenda.total', 11)->assertJsonPath('data.addenda.last_page', 2);
        $this->getJson($url.'?page=2')->assertJsonPath('data.addenda.data.0.id', $id);
    }

    public function test_handover_addenda_validate_completion_roles_location_and_organization(): void
    {
        $rx = $this->rx(); $lot = $this->lot(); $f = $this->fill($rx, $lot);
        $url = "/api/pharmacy/prescriptions/$rx/fills/{$f['id']}/handover-addenda";
        $this->getJson($url)->assertUnprocessable();
        $f = $this->complete($rx, $f); $body = $this->handoverAddendumBody($url);
        foreach (['statement', 'reason', 'evidence', 'confirmed', 'request_id', 'ledger_token'] as $key) {
            $invalid = $body; unset($invalid[$key]); $this->postJson($url, $invalid)->assertUnprocessable();
        }
        $this->postJson($url, array_replace($body, ['section' => 'quantity']))->assertUnprocessable();
        $this->postJson($url, array_replace($body, ['statement' => str_repeat('x', 5001)]))->assertUnprocessable();
        $id = $this->postJson($url, $body)->assertCreated()->json('data.addenda.data.0.id');
        foreach (['admin', 'medical_biller', 'pharmacy_technician'] as $role) {
            $this->actor->role = $role; $this->actor->save();
            $this->getJson($url)->assertStatus($role === 'pharmacy_technician' ? 200 : 403);
            $this->postJson($url, $body)->assertForbidden();
            $this->postJson("$url/$id/review", [])->assertForbidden();
        }
        $this->actor->role = 'pharmacist'; $this->actor->save();
        DB::table('pharmacy_staff_assignments')->where('location_id', $this->location)->update(['active' => false]);
        $this->getJson($url)->assertNotFound(); $this->postJson($url, $body)->assertNotFound();
        $this->postJson("$url/$id/review", [])->assertNotFound();
        DB::table('pharmacy_staff_assignments')->where('location_id', $this->location)->update(['active' => true]);
        $this->actor->organization_id = 2; $this->actor->save();
        $this->getJson($url)->assertNotFound(); $this->postJson($url, $body)->assertNotFound();
        $this->actor->organization_id = 1; $this->actor->save();
        $other = $this->rx();
        $this->getJson("/api/pharmacy/prescriptions/$other/fills/{$f['id']}/handover-addenda")->assertNotFound();
        $this->assertSame(1, DB::table('pharmacy_handover_addenda')->count());
    }

    public function test_handover_addenda_reject_changed_source_but_allow_retained_rejection_and_legacy_evidence(): void
    {
        $rx = $this->rx(); $lot = $this->lot(); $f = $this->complete($rx, $this->fill($rx, $lot));
        $url = "/api/pharmacy/prescriptions/$rx/fills/{$f['id']}/handover-addenda";
        $id = $this->postJson($url, $this->handoverAddendumBody($url))->assertCreated()->json('data.addenda.data.0.id');
        $reviewer = $this->independentReviewer(); $this->actingAs($reviewer, 'api');
        DB::table('pharmacy_fills')->where('id', $f['id'])->update(['fulfillment' => '{}']);
        $review = ['decision' => 'accepted', 'evidence' => 'SYNTHETIC source mismatch', 'confirmed' => true];
        $this->postJson("$url/$id/review", $review)->assertStatus(409);
        $review['decision'] = 'rejected';
        $this->postJson("$url/$id/review", $review)->assertOk()->assertJsonPath('data.addenda.data.0.status', 'rejected');
        $this->postJson("$url/$id/review", $review)->assertOk();
        // A historical record can receive a statement without inventing absent structured evidence.
        $this->postJson($url, $this->handoverAddendumBody($url))->assertCreated();
        $this->assertSame('{}', DB::table('pharmacy_fills')->where('id', $f['id'])->value('fulfillment'));
    }

    public function test_handover_addenda_and_reviews_roll_back_when_audit_persistence_fails(): void
    {
        $rx = $this->rx(); $lot = $this->lot(); $f = $this->complete($rx, $this->fill($rx, $lot));
        $url = "/api/pharmacy/prescriptions/$rx/fills/{$f['id']}/handover-addenda";
        $id = $this->postJson($url, $this->handoverAddendumBody($url))->assertCreated()->json('data.addenda.data.0.id');
        $body = $this->handoverAddendumBody($url);
        $before = DB::table('pharmacy_handover_addenda')->get()->toJson();
        $events = DB::table('pharmacy_events')->get()->toJson();
        DB::connection()->beforeExecuting(function ($query) {
            if (str_starts_with(strtolower($query), 'insert into') && str_contains($query, 'pharmacy_events')) { throw new \RuntimeException('SYNTHETIC addendum audit failure'); }
        });
        $this->postJson($url, $body)->assertStatus(500);
        $reviewer = $this->independentReviewer(); $this->actingAs($reviewer, 'api');
        $this->postJson("$url/$id/review", ['decision' => 'accepted', 'evidence' => 'SYNTHETIC review', 'confirmed' => true])->assertStatus(500);
        $this->assertSame($before, DB::table('pharmacy_handover_addenda')->get()->toJson());
        $this->assertSame($events, DB::table('pharmacy_events')->get()->toJson());
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

    public function test_allowance_correction_preserves_fractional_quantity_and_expiry_at_review(): void
    {
        $rx = $this->rx(['quantity' => '0.300', 'refills_authorized' => 0, 'expires_on' => now()->toDateString()]); $lot = $this->lot();
        $fill = $this->postJson("/api/pharmacy/prescriptions/$rx/fills", $this->fillBody($lot, ['quantity' => '0.100', 'partial_reason' => 'Synthetic fractional partial']))->assertOk()->json('data.fills.0');
        $this->complete($rx, $fill);
        $this->postJson("/api/pharmacy/prescriptions/$rx/allowances/close", $this->closureBody($rx))->assertOk()->assertJsonPath('data.quantity_balance.next_authorization_number', null);
        $closure = DB::table('pharmacy_allowance_closures')->where('prescription_id', $rx)->value('id');
        $url = "/api/pharmacy/prescriptions/$rx/allowances/$closure/corrections";
        $correction = $this->postJson($url, $this->allowanceCorrectionBody($rx))->assertOk()->json('data.quantity_balance.pending_correction_id');
        $reviewer = $this->independentReviewer();
        $reviewer->withAccessToken(new Token(['expires_at' => now()->addDays(2)]));
        $this->actingAs($reviewer, 'api');
        $review = $this->allowanceCorrectionReview($rx); $reviewUrl = "/api/pharmacy/prescriptions/$rx/allowance-corrections/$correction/review";
        $this->travel(1)->days();
        // No row changed, but an expired prescription must still fail at independent review.
        $this->postJson($reviewUrl, $review)->assertUnprocessable();
        $this->travelBack();
        $this->postJson($reviewUrl, $review)->assertOk()->assertJsonPath('data.quantity_balance.available_quantity', '0.200')
            ->assertJsonPath('data.quantity_balance.next_authorization_number', 1)->assertJsonPath('data.quantity_balance.allowances.0.handed_over', '0.100');
        $this->postJson("/api/pharmacy/prescriptions/$rx/fills", $this->fillBody($lot, ['quantity' => '0.201']))->assertUnprocessable();
        $this->postJson("/api/pharmacy/prescriptions/$rx/fills", $this->fillBody($lot, ['quantity' => '0.200']))->assertOk();
    }

    private function closedAllowanceFixture(): array
    {
        $rx = $this->rx(); $lot = $this->lot();
        $fill = $this->postJson("/api/pharmacy/prescriptions/$rx/fills", $this->fillBody($lot, ['quantity' => '4.000', 'partial_reason' => 'Synthetic partial']))->assertOk()->json('data.fills.0');
        $this->complete($rx, $fill);
        $this->postJson("/api/pharmacy/prescriptions/$rx/allowances/close", $this->closureBody($rx))->assertOk();
        return [$rx, $lot, (int) DB::table('pharmacy_allowance_closures')->where('prescription_id', $rx)->value('id')];
    }

    private function allowanceCorrectionBody(int $rx, array $changes = []): array
    {
        return array_replace(['request_id' => (string) Str::uuid(), 'ledger_token' => $this->getJson("/api/pharmacy/prescriptions/$rx")->assertOk()->json('data.quantity_balance.ledger_token'), 'reason' => 'Synthetic wrong closure instruction', 'evidence' => 'Synthetic independently verifiable correction', 'confirmed' => true], $changes);
    }

    private function allowanceCorrectionReview(int $rx, array $changes = []): array
    {
        return array_replace(['request_id' => (string) Str::uuid(), 'ledger_token' => $this->getJson("/api/pharmacy/prescriptions/$rx")->assertOk()->json('data.quantity_balance.ledger_token'), 'decision' => 'apply', 'evidence' => 'Synthetic independent authority review', 'confirmed' => true], $changes);
    }

    public function test_allowance_correction_independently_restores_same_remainder_without_stock_or_history_changes(): void
    {
        [$rx, $lot, $closure] = $this->closedAllowanceFixture();
        $snapshot = [];
        foreach (['pharmacy_allowance_closures', 'pharmacy_fills', 'pharmacy_stock_lots', 'pharmacy_stock_events', 'invoices', 'payments'] as $table) $snapshot[$table] = DB::table($table)->get();
        $url = "/api/pharmacy/prescriptions/$rx/allowances/$closure/corrections"; $body = $this->allowanceCorrectionBody($rx);
        $data = $this->postJson($url, $body)->assertOk()->json('data.quantity_balance'); $correction = $data['pending_correction_id'];
        $this->assertNotNull($correction);
        $this->getJson('/api/pharmacy/prescriptions?attention=allowance_correction')->assertOk()->assertJsonPath('data.total', 1)->assertJsonPath('data.data.0.pending_correction_count', 1);
        $this->assertSame('10.000', $data['available_quantity']);
        $this->postJson($url, $body)->assertOk();
        $this->postJson($url, array_replace($body, ['evidence' => 'Changed']))->assertStatus(409);
        $reviewUrl = "/api/pharmacy/prescriptions/$rx/allowance-corrections/$correction/review"; $review = $this->allowanceCorrectionReview($rx);
        $this->postJson($reviewUrl, $review)->assertUnprocessable();
        $this->postJson("/api/pharmacy/prescriptions/$rx/fills", $this->fillBody($lot))->assertUnprocessable();
        $this->postJson("/api/pharmacy/prescriptions/$rx/allowances/close", $this->closureBody($rx, ['authorization_number' => 2]))->assertUnprocessable();
        $this->actingAs($this->independentReviewer(), 'api');
        $this->postJson($url, $body)->assertStatus(409);
        $this->postJson($reviewUrl, $review)->assertOk()->assertJsonPath('data.quantity_balance.available_quantity', '6.000')
            ->assertJsonPath('data.quantity_balance.next_authorization_number', 1)->assertJsonPath('data.quantity_balance.pending_correction_id', null)
            ->assertJsonPath('data.quantity_balance.closure_history.0.corrected', true)->assertJsonPath('data.quantity_balance.closure_history.0.corrections.0.status', 'applied')
            ->assertJsonMissingPath('data.quantity_balance.closure_history.0.corrections.0.request_hash');
        $this->postJson($reviewUrl, $review)->assertOk();
        $this->postJson($reviewUrl, array_replace($review, ['decision' => 'reject']))->assertStatus(409);
        $this->getJson('/api/pharmacy/prescriptions?attention=allowance_correction')->assertOk()->assertJsonPath('data.total', 0);
        foreach ($snapshot as $table => $rows) $this->assertEquals($rows, DB::table($table)->get(), $table);
        $this->assertSame(1, DB::table('pharmacy_events')->where('action', 'allowance_correction_requested')->count());
        $this->assertSame(1, DB::table('pharmacy_events')->where('action', 'allowance_correction_applied')->count());
        $this->postJson("/api/pharmacy/prescriptions/$rx/fills", $this->fillBody($lot, ['quantity' => '6.001']))->assertUnprocessable();
        $this->postJson("/api/pharmacy/prescriptions/$rx/fills", $this->fillBody($lot, ['quantity' => '6.000']))->assertOk()->assertJsonPath('data.fills.1.authorization_number', 1);
        $this->deleteJson($reviewUrl)->assertStatus(405);
    }

    public function test_allowance_correction_rejects_later_supply_but_allows_cancelled_future_reservation(): void
    {
        [$rx, $lot, $closure] = $this->closedAllowanceFixture();
        $url = "/api/pharmacy/prescriptions/$rx/allowances/$closure/corrections";
        $next = $this->postJson("/api/pharmacy/prescriptions/$rx/fills", $this->fillBody($lot))->assertOk()->json('data.fills.1');
        $this->postJson($url, $this->allowanceCorrectionBody($rx))->assertUnprocessable();
        $this->act($rx, $next, 'cancel');
        $request = $this->postJson($url, $this->allowanceCorrectionBody($rx))->assertOk()->json('data.quantity_balance.pending_correction_id');
        $this->actingAs($this->independentReviewer(), 'api');
        $reviewUrl = "/api/pharmacy/prescriptions/$rx/allowance-corrections/$request/review";
        $body = $this->allowanceCorrectionReview($rx, ['decision' => 'reject']);
        $this->postJson($reviewUrl, $body)->assertOk()->assertJsonPath('data.quantity_balance.next_authorization_number', 2)->assertJsonPath('data.quantity_balance.available_quantity', '10.000');
        $this->postJson($reviewUrl, $body)->assertOk();
        $next = $this->postJson("/api/pharmacy/prescriptions/$rx/fills", $this->fillBody($lot))->assertOk()->json('data.fills.2');
        $this->complete($rx, $next);
        $this->postJson($url, $this->allowanceCorrectionBody($rx))->assertUnprocessable();
        $this->assertSame(1, DB::table('pharmacy_allowance_corrections')->count());
    }

    public function test_allowance_correction_preserves_multiple_closure_and_rejection_decisions(): void
    {
        [$rx, $lot, $closure] = $this->closedAllowanceFixture(); $reviewer = $this->independentReviewer();
        $firstClosure = DB::table('pharmacy_allowance_closures')->where('id', $closure)->first();
        $url = "/api/pharmacy/prescriptions/$rx/allowances/$closure/corrections";
        foreach (['reject', 'apply'] as $decision) {
            $this->actingAs($this->actor, 'api');
            $correction = $this->postJson($url, $this->allowanceCorrectionBody($rx))->assertOk()->json('data.quantity_balance.pending_correction_id');
            $this->actingAs($reviewer, 'api');
            $this->postJson("/api/pharmacy/prescriptions/$rx/allowance-corrections/$correction/review", $this->allowanceCorrectionReview($rx, ['decision' => $decision]))->assertOk();
        }
        $this->postJson("/api/pharmacy/prescriptions/$rx/allowances/close", $this->closureBody($rx, ['reason' => 'Synthetic subsequent verified instruction']))->assertOk()
            ->assertJsonPath('data.quantity_balance.closure_history.0.corrected', true)->assertJsonPath('data.quantity_balance.closure_history.1.corrected', false)
            ->assertJsonPath('data.quantity_balance.available_quantity', '10.000');
        $this->assertEquals($firstClosure, DB::table('pharmacy_allowance_closures')->where('id', $closure)->first());
        $this->assertSame(2, DB::table('pharmacy_allowance_closures')->count());
        $this->assertSame(2, DB::table('pharmacy_allowance_corrections')->count());
        $this->postJson($url, $this->allowanceCorrectionBody($rx))->assertUnprocessable();
    }

    public function test_allowance_correction_stale_or_stopped_records_can_be_rejected_but_not_applied(): void
    {
        [$rx, $lot, $closure] = $this->closedAllowanceFixture();
        $url = "/api/pharmacy/prescriptions/$rx/allowances/$closure/corrections";
        $correction = $this->postJson($url, $this->allowanceCorrectionBody($rx))->assertOk()->json('data.quantity_balance.pending_correction_id');
        $review = $this->allowanceCorrectionReview($rx);
        $this->stopPrescription($rx)->assertOk();
        $this->actingAs($this->independentReviewer(), 'api');
        $reviewUrl = "/api/pharmacy/prescriptions/$rx/allowance-corrections/$correction/review";
        $this->postJson($reviewUrl, $review)->assertStatus(409);
        $this->postJson($reviewUrl, $this->allowanceCorrectionReview($rx))->assertStatus(409);
        $this->postJson($reviewUrl, array_replace($review, ['decision' => 'reject']))->assertOk()->assertJsonPath('data.quantity_balance.pending_correction_id', null);
        $this->postJson($url, $this->allowanceCorrectionBody($rx))->assertUnprocessable();
    }

    public function test_allowance_correction_roles_scope_confirmation_and_restrictions(): void
    {
        [$rx, $lot, $closure] = $this->closedAllowanceFixture();
        $url = "/api/pharmacy/prescriptions/$rx/allowances/$closure/corrections"; $body = $this->allowanceCorrectionBody($rx);
        foreach ([['confirmed' => false], ['reason' => ''], ['evidence' => ''], ['ledger_token' => 'invalid']] as $invalid) $this->postJson($url, array_replace($body, $invalid))->assertUnprocessable();
        $this->postJson($url, array_replace($body, ['ledger_token' => str_repeat('a', 64)]))->assertStatus(409);
        foreach (['pharmacy_technician', 'medical_biller', 'admin'] as $role) {
            $this->actor->role = $role; $this->actor->save(); $this->postJson($url, $body)->assertForbidden();
        }
        $this->actor->role = 'pharmacist'; $this->actor->save();
        DB::table('pharmacy_staff_assignments')->where('location_id', $this->location)->update(['active' => false]);
        $this->postJson($url, $body)->assertNotFound();
        DB::table('pharmacy_staff_assignments')->where('location_id', $this->location)->update(['active' => true]);
        DB::table('pharmacy_prescriptions')->where('id', $rx)->update(['organization_id' => 2]); $this->postJson($url, $body)->assertNotFound();
        DB::table('pharmacy_prescriptions')->where('id', $rx)->update(['organization_id' => 1]);
        foreach ([['controlled' => true], ['compounded' => true], ['expires_on' => now()->subDay()->toDateString()]] as $change) {
            DB::table('pharmacy_prescriptions')->where('id', $rx)->update($change);
            $this->postJson($url, $this->allowanceCorrectionBody($rx))->assertUnprocessable();
            DB::table('pharmacy_prescriptions')->where('id', $rx)->update(['controlled' => false, 'compounded' => false, 'expires_on' => now()->addYear()->toDateString()]);
        }
        $other = $this->rx();
        $this->postJson("/api/pharmacy/prescriptions/$other/allowances/$closure/corrections", $body)->assertNotFound();
        $correction = $this->postJson($url, $this->allowanceCorrectionBody($rx))->assertOk()->json('data.quantity_balance.pending_correction_id');
        $this->postJson($url, $this->allowanceCorrectionBody($rx))->assertStatus(409);
        $reviewer = $this->independentReviewer(); $this->actingAs($reviewer, 'api');
        $review = $this->allowanceCorrectionReview($rx); $reviewUrl = "/api/pharmacy/prescriptions/$rx/allowance-corrections/$correction/review";
        $this->postJson("/api/pharmacy/prescriptions/$other/allowance-corrections/$correction/review", $review)->assertNotFound();
        $reviewer->role = 'pharmacy_technician'; $reviewer->save(); $this->postJson($reviewUrl, $review)->assertForbidden();
        $reviewer->role = 'pharmacist'; $reviewer->save();
        DB::table('pharmacy_staff_assignments')->where('user_id', $reviewer->id)->update(['active' => false]);
        $this->postJson($reviewUrl, $review)->assertNotFound();
        $this->getJson('/api/pharmacy/prescriptions?attention=allowance_correction')->assertOk()->assertJsonPath('data.total', 0);
        $this->assertSame('pending', DB::table('pharmacy_allowance_corrections')->value('status'));
    }

    public function test_allowance_correction_audit_failures_roll_back_request_and_application(): void
    {
        [$rx, $lot, $closure] = $this->closedAllowanceFixture();
        $url = "/api/pharmacy/prescriptions/$rx/allowances/$closure/corrections"; $body = $this->allowanceCorrectionBody($rx);
        $fail = true;
        DB::connection()->beforeExecuting(function ($query) use (&$fail) {
            if ($fail && str_starts_with(strtolower($query), 'insert into') && str_contains($query, 'pharmacy_events')) throw new \RuntimeException('Synthetic allowance correction audit failure');
        });
        $this->postJson($url, $body)->assertStatus(500); $fail = false;
        $this->assertSame(0, DB::table('pharmacy_allowance_corrections')->count());
        $correction = $this->postJson($url, $body)->assertOk()->json('data.quantity_balance.pending_correction_id');
        $this->actingAs($this->independentReviewer(), 'api'); $review = $this->allowanceCorrectionReview($rx);
        $reviewUrl = "/api/pharmacy/prescriptions/$rx/allowance-corrections/$correction/review";
        $fail = true; $this->postJson($reviewUrl, $review)->assertStatus(500); $fail = false;
        $this->assertSame('pending', DB::table('pharmacy_allowance_corrections')->value('status'));
        $this->getJson("/api/pharmacy/prescriptions/$rx")->assertOk()->assertJsonPath('data.quantity_balance.available_quantity', '10.000');
        $this->postJson($reviewUrl, $review)->assertOk()->assertJsonPath('data.quantity_balance.available_quantity', '6.000');
    }

    private function closureBody(int $rx, array $overrides = []): array
    {
        $balance = $this->getJson("/api/pharmacy/prescriptions/$rx")->assertOk()->json('data.quantity_balance');
        return array_replace(['request_id' => (string) Str::uuid(), 'ledger_token' => $balance['ledger_token'],
            'authorization_number' => 1, 'basis' => 'patient_request', 'occurred_on' => now()->toDateString(),
            'reason' => 'Synthetic patient request to close remainder', 'evidence' => 'Synthetic evidence only', 'confirmed' => true], $overrides);
    }

    public function test_closing_partial_allowance_retains_history_and_never_pools_quantity(): void
    {
        $rx = $this->rx(); $lot = $this->lot();
        $f = $this->postJson("/api/pharmacy/prescriptions/$rx/fills", $this->fillBody($lot, ['quantity' => '4.000', 'partial_reason' => 'Synthetic partial']))->assertOk()->json('data.fills.0');
        $this->complete($rx, $f);
        $before = (array) DB::table('pharmacy_fills')->where('id', $f['id'])->first();
        $stock = (array) DB::table('pharmacy_stock_lots')->where('id', $lot)->first();
        $body = $this->closureBody($rx); $url = "/api/pharmacy/prescriptions/$rx/allowances/close";
        $this->postJson($url, $body)->assertOk()->assertJsonPath('data.quantity_balance.allowances.0.closed_quantity', '6.000')
            ->assertJsonPath('data.quantity_balance.allowances.0.handed_over', '4.000')
            ->assertJsonPath('data.quantity_balance.next_authorization_number', 2)
            ->assertJsonPath('data.quantity_balance.available_quantity', '10.000')
            ->assertJsonPath('data.quantity_balance.closable_authorization_number', null)
            ->assertJsonMissingPath('data.quantity_balance.allowances.0.closure.request_hash');
        $this->assertSame($before, (array) DB::table('pharmacy_fills')->where('id', $f['id'])->first());
        $this->assertSame($stock, (array) DB::table('pharmacy_stock_lots')->where('id', $lot)->first());
        $this->postJson($url, $body)->assertOk();
        $this->postJson($url, array_replace($body, ['reason' => 'Changed']))->assertStatus(409);
        $this->actingAs($this->independentReviewer(), 'api'); $this->postJson($url, $body)->assertStatus(409);
        $this->actingAs($this->actor, 'api');
        $this->assertSame(1, DB::table('pharmacy_allowance_closures')->count());
        $this->assertSame(1, DB::table('pharmacy_events')->where('action', 'allowance_remainder_closed')->count());
        $this->putJson($url, $body)->assertStatus(405);
        $this->deleteJson($url)->assertStatus(405);
        $this->postJson("/api/pharmacy/prescriptions/$rx/fills", $this->fillBody($lot, ['quantity' => '16.000']))->assertUnprocessable();
        $next = $this->postJson("/api/pharmacy/prescriptions/$rx/fills", $this->fillBody($lot))->assertOk()->json('data.fills.1');
        $this->assertSame(2, $next['authorization_number']);
        $this->complete($rx, $next);
        $this->postJson($url, $body)->assertOk()->assertJsonPath('data.quantity_balance.next_authorization_number', null);
        $this->postJson("/api/pharmacy/prescriptions/$rx/fills", $this->fillBody($lot, ['quantity' => '6.000', 'partial_reason' => 'Cannot reclaim closed quantity']))->assertUnprocessable();
        $this->assertEquals(36, DB::table('pharmacy_stock_lots')->where('id', $lot)->value('on_hand'));
        $this->assertSame(2, DB::table('pharmacy_stock_events')->where('action', 'dispensed')->count());
    }

    public function test_allowance_closure_is_scoped_to_assigned_pharmacists_and_current_general_records(): void
    {
        $rx = $this->rx(); $lot = $this->lot(); $url = "/api/pharmacy/prescriptions/$rx/allowances/close";
        $this->postJson($url, $this->closureBody($rx))->assertUnprocessable();
        $f = $this->postJson("/api/pharmacy/prescriptions/$rx/fills", $this->fillBody($lot, ['quantity' => '4.000', 'partial_reason' => 'Synthetic partial']))->assertOk()->json('data.fills.0');
        $this->postJson($url, $this->closureBody($rx))->assertUnprocessable();
        $this->complete($rx, $f); $body = $this->closureBody($rx);
        foreach (['pharmacy_technician', 'medical_biller', 'admin'] as $role) {
            $this->actor->role = $role; $this->actor->save(); $this->postJson($url, $body)->assertForbidden();
        }
        $this->actor->role = 'pharmacist'; $this->actor->save();
        DB::table('pharmacy_staff_assignments')->where('user_id', $this->actor->id)->where('location_id', $this->location)->update(['active' => false]);
        $this->postJson($url, $body)->assertNotFound();
        DB::table('pharmacy_staff_assignments')->where('user_id', $this->actor->id)->where('location_id', $this->location)->update(['active' => true]);
        $this->actor->organization_id = 2; $this->actor->save(); $this->postJson($url, $body)->assertNotFound();
        $this->actor->organization_id = 1; $this->actor->save();
        foreach (['controlled', 'compounded'] as $flag) {
            DB::table('pharmacy_prescriptions')->where('id', $rx)->update([$flag => true]);
            $this->getJson("/api/pharmacy/prescriptions/$rx")->assertOk()->assertJsonPath('data.quantity_balance.closable_authorization_number', null);
            $this->postJson($url, $this->closureBody($rx))->assertUnprocessable();
            DB::table('pharmacy_prescriptions')->where('id', $rx)->update([$flag => false]);
        }
        DB::table('pharmacy_prescriptions')->where('id', $rx)->update(['expires_on' => now()->subDay()->toDateString()]);
        $this->postJson($url, $this->closureBody($rx))->assertUnprocessable();
        DB::table('pharmacy_prescriptions')->where('id', $rx)->update(['expires_on' => now()->addMonth()->toDateString(), 'discontinued_at' => now()]);
        $this->postJson($url, $this->closureBody($rx))->assertUnprocessable();
        DB::table('pharmacy_prescriptions')->where('id', $rx)->update(['discontinued_at' => null]);
        DB::table('pharmacy_fills')->where('id', $f['id'])->update(['authorization_number' => null]);
        $this->postJson($url, $this->closureBody($rx))->assertUnprocessable();
        $this->assertSame(0, DB::table('pharmacy_allowance_closures')->count());
    }

    public function test_allowance_closure_rejects_stale_dates_and_rolls_back_failed_evidence(): void
    {
        $rx = $this->rx(['refills_authorized' => 0]); $lot = $this->lot(); $url = "/api/pharmacy/prescriptions/$rx/allowances/close";
        $f = $this->postJson("/api/pharmacy/prescriptions/$rx/fills", $this->fillBody($lot, ['quantity' => '4.000', 'partial_reason' => 'Synthetic partial']))->assertOk()->json('data.fills.0');
        $this->complete($rx, $f); $stale = $this->closureBody($rx);
        $pending = $this->postJson("/api/pharmacy/prescriptions/$rx/fills", $this->fillBody($lot, ['quantity' => '6.000']))->assertOk()->json('data.fills.1');
        $this->act($rx, $pending, 'cancel');
        $this->postJson($url, $stale)->assertStatus(409);
        $body = $this->closureBody($rx);
        foreach ([['authorization_number' => 2], ['confirmed' => false], ['evidence' => ''], ['occurred_on' => now()->subDay()->toDateString()], ['occurred_on' => now()->addDay()->toDateString()]] as $invalid) {
            $this->postJson($url, array_replace($body, $invalid))->assertUnprocessable();
        }
        $fail = true;
        DB::connection()->beforeExecuting(function ($query) use (&$fail) {
            if ($fail && str_starts_with(strtolower($query), 'insert into') && str_contains($query, 'pharmacy_events')) { throw new \RuntimeException('Synthetic closure audit failure'); }
        });
        $this->postJson($url, $body)->assertStatus(500); $fail = false;
        $this->assertSame(0, DB::table('pharmacy_allowance_closures')->count());
        $this->getJson("/api/pharmacy/prescriptions/$rx")->assertOk()->assertJsonPath('data.quantity_balance.available_quantity', '6.000')->assertJsonPath('data.quantity_balance.ledger_token', $body['ledger_token']);
        $this->postJson($url, $body)->assertOk()->assertJsonPath('data.quantity_balance.next_authorization_number', null);
        $this->assertEquals(46, DB::table('pharmacy_stock_lots')->where('id', $lot)->value('on_hand'));
        $this->assertEquals(0, DB::table('pharmacy_stock_lots')->where('id', $lot)->value('reserved'));
        $this->assertSame(1, DB::table('pharmacy_stock_events')->where('action', 'dispensed')->count());
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

    public function test_erroneous_patient_enrollment_withdrawal_preserves_history_and_denies_site_access(): void
    {
        $intake = ['request_id' => (string) Str::uuid(), 'record_number' => 'SYN-WITHDRAW-1', 'location_id' => $this->location,
            'first_name' => 'Synthetic', 'last_name' => 'Withdrawal', 'date_of_birth' => '1980-01-01', 'identity_reference' => 'Synthetic intake'];
        $id = $this->postJson('/api/pharmacy/patients', $intake)->assertCreated()->assertJsonPath('data.locations.0.enrollment_event_id', null)->json('data.id');
        $url = "/api/pharmacy/patients/$id";
        $sourceRx = $this->rx(['patient_id' => null, 'pharmacy_patient_id' => $id]);
        $beforeRx = DB::table('pharmacy_prescriptions')->get()->toJson();
        $enroll = ['version' => 1, 'source_location_id' => $this->location, 'location_id' => $this->otherLocation,
            'reason' => 'Synthetic enrollment', 'identity_reference' => 'Synthetic identity',
            'sharing_authority_reference' => 'Synthetic authority', 'sharing_confirmed' => true];
        $eventId = $this->postJson("$url/locations", $enroll)->assertOk()->json('data.history.0.id');
        $before = (array) DB::table('pharmacy_patients')->where('id', $id)->first();
        $body = ['version' => 2, 'enrollment_event_id' => $eventId, 'location_id' => $this->otherLocation,
            'retained_location_id' => $this->location, 'reason' => 'Synthetic incorrect enrollment',
            'correction_reference' => 'Synthetic access review', 'withdrawal_confirmed' => true];
        $withdrawn = $this->postJson("$url/locations/withdraw", $body)->assertOk()->assertJsonCount(1, 'data.locations')
            ->assertJsonPath('data.locations.0.id', $this->location)->assertJsonPath('data.version', 3)->json('data');
        $this->assertSame('location_enrollment_withdrawn', $withdrawn['history'][0]['details']['action']);
        $this->assertSame($eventId, $withdrawn['history'][0]['details']['enrollment_event_id']);
        $this->assertSame($this->actor->id, $withdrawn['history'][0]['actor_id']);
        $after = (array) DB::table('pharmacy_patients')->where('id', $id)->first();
        foreach ($before as $field => $value) {
            if (!in_array($field, ['version', 'updated_at'], true)) $this->assertSame($value, $after[$field]);
        }
        $this->assertSame(2, DB::table('pharmacy_patient_locations')->count());
        $this->assertFalse((bool) DB::table('pharmacy_patient_locations')->where('patient_id', $id)->where('location_id', $this->otherLocation)->value('active'));
        $this->assertSame($beforeRx, DB::table('pharmacy_prescriptions')->get()->toJson());
        $this->getJson("/api/pharmacy/prescriptions/$sourceRx")->assertOk();
        $this->postJson("$url/locations/withdraw", $body)->assertNotFound();
        $this->getJson('/api/pharmacy/patients?location_id='.$this->otherLocation)->assertOk()->assertJsonPath('data.total', 0);
        // Even staff assigned to both sites cannot receive new prescriptions against inactive enrollment.
        $this->postJson('/api/pharmacy/prescriptions', $this->body(['patient_id' => null, 'pharmacy_patient_id' => $id,
            'location_id' => $this->otherLocation]))->assertNotFound();
        DB::table('pharmacy_staff_assignments')->where('location_id', $this->location)->update(['active' => false]);
        $this->getJson($url)->assertNotFound();
        $this->getJson("$url/history")->assertNotFound();
        $this->getJson('/api/pharmacy/patients')->assertOk()->assertJsonPath('data.total', 0);
        $this->putJson("$url/clinical", [])->assertNotFound();
        $this->putJson("$url/demographics", [])->assertNotFound();
        $this->postJson("$url/locations", array_replace($enroll, ['version' => 3]))->assertNotFound();
        DB::table('pharmacy_staff_assignments')->where('location_id', $this->location)->update(['active' => true]);
        $newEvent = $this->postJson("$url/locations", array_replace($enroll, ['version' => 3]))->assertOk()->assertJsonCount(2, 'data.locations')
            ->assertJsonPath('data.version', 4)->json('data.history.0.id');
        $this->assertNotSame($eventId, $newEvent);
        $this->assertSame(2, DB::table('pharmacy_patient_locations')->count());
        $this->postJson("$url/locations/withdraw", array_replace($body, ['version' => 4]))->assertConflict();
        $this->postJson("$url/locations/withdraw", array_replace($body, ['version' => 4, 'enrollment_event_id' => $newEvent]))->assertOk()->assertJsonPath('data.version', 5);
        $this->assertSame(5, DB::table('pharmacy_patient_events')->count());
        $this->assertSame($beforeRx, DB::table('pharmacy_prescriptions')->get()->toJson());
    }

    public function test_patient_enrollment_withdrawal_rejects_unsafe_scope_and_preserves_records_on_failure(): void
    {
        $intake = ['request_id' => (string) Str::uuid(), 'record_number' => 'SYN-WITHDRAW-2', 'location_id' => $this->location,
            'first_name' => 'Synthetic', 'last_name' => 'Withdrawal', 'date_of_birth' => '1980-01-01', 'identity_reference' => 'Synthetic intake'];
        $id = $this->postJson('/api/pharmacy/patients', $intake)->assertCreated()->json('data.id');
        $url = "/api/pharmacy/patients/$id/locations";
        $enroll = ['version' => 1, 'source_location_id' => $this->location, 'location_id' => $this->otherLocation,
            'reason' => 'Synthetic enrollment', 'identity_reference' => 'Synthetic identity',
            'sharing_authority_reference' => 'Synthetic authority', 'sharing_confirmed' => true];
        $eventId = $this->postJson($url, $enroll)->assertOk()->json('data.history.0.id');
        $body = ['version' => 2, 'enrollment_event_id' => $eventId, 'location_id' => $this->otherLocation,
            'retained_location_id' => $this->location, 'reason' => 'Synthetic correction', 'correction_reference' => 'Synthetic review', 'withdrawal_confirmed' => true];
        foreach (['pharmacy_technician', 'medical_biller', 'admin', 'firm_admin', 'attorney', 'client', 'provider'] as $role) {
            $this->actor->role = $role; $this->postJson("$url/withdraw", $body)->assertForbidden();
        }
        $this->actor->role = 'pharmacist';
        foreach ([['reason' => ''], ['correction_reference' => ''], ['withdrawal_confirmed' => false], ['retained_location_id' => $this->otherLocation]] as $invalid) {
            $this->postJson("$url/withdraw", array_replace($body, $invalid))->assertUnprocessable();
        }
        $this->postJson("$url/withdraw", array_replace($body, ['location_id' => $this->location, 'retained_location_id' => $this->otherLocation]))->assertConflict();
        $this->postJson("$url/withdraw", array_replace($body, ['enrollment_event_id' => 999999]))->assertConflict();
        $this->postJson("$url/withdraw", array_replace($body, ['version' => 1]))->assertConflict();
        $this->actor->organization_id = 2; $this->postJson("$url/withdraw", $body)->assertNotFound(); $this->actor->organization_id = 1;
        foreach ([$this->location, $this->otherLocation] as $site) {
            DB::table('pharmacy_staff_assignments')->where('location_id', $site)->update(['active' => false]);
            $this->postJson("$url/withdraw", $body)->assertNotFound();
            DB::table('pharmacy_staff_assignments')->where('location_id', $site)->update(['active' => true]);
        }
        $patientBefore = DB::table('pharmacy_patients')->get()->toJson();
        $locationsBefore = DB::table('pharmacy_patient_locations')->get()->toJson();
        $fail = true;
        DB::connection()->beforeExecuting(function ($query) use (&$fail) {
            if ($fail && str_starts_with(strtolower($query), 'insert into') && str_contains($query, 'pharmacy_patient_events')) {
                throw new \RuntimeException('Synthetic withdrawal audit failure');
            }
        });
        $this->postJson("$url/withdraw", $body)->assertStatus(500); $fail = false;
        $this->assertSame($patientBefore, DB::table('pharmacy_patients')->get()->toJson());
        $this->assertSame($locationsBefore, DB::table('pharmacy_patient_locations')->get()->toJson());
        $this->assertSame(2, DB::table('pharmacy_patient_events')->count());
        // A prescription at the target site, even discontinued, prevents this limited access correction.
        $rx = $this->rx(['patient_id' => null, 'pharmacy_patient_id' => $id, 'location_id' => $this->otherLocation]);
        $this->postJson("$url/withdraw", $body)->assertUnprocessable();
        DB::table('pharmacy_prescriptions')->where('id', $rx)->update(['discontinued_at' => now()]);
        $this->postJson("$url/withdraw", $body)->assertUnprocessable();
        $this->assertSame($patientBefore, DB::table('pharmacy_patients')->get()->toJson());
        $this->assertSame($locationsBefore, DB::table('pharmacy_patient_locations')->get()->toJson());
        $this->assertSame(2, DB::table('pharmacy_patient_events')->count());
    }

    public function test_patient_location_enrollment_shares_one_chart_but_not_source_prescriptions(): void
    {
        $intake = ['request_id' => (string) Str::uuid(), 'record_number' => 'SYN-ENROLL-1', 'location_id' => $this->location,
            'first_name' => 'Synthetic', 'last_name' => 'Multi location', 'date_of_birth' => '1980-01-01', 'identity_reference' => 'Synthetic intake'];
        $p = $this->postJson('/api/pharmacy/patients', $intake)->assertCreated()->assertJsonCount(1, 'data.locations')->json('data');
        $url = "/api/pharmacy/patients/{$p['id']}";
        $rx = $this->rx(['patient_id' => null, 'pharmacy_patient_id' => $p['id']]);
        $beforePatient = (array) DB::table('pharmacy_patients')->where('id', $p['id'])->first();
        $beforeRx = DB::table('pharmacy_prescriptions')->get()->toJson();
        $beforeUsers = DB::table('users')->count();
        $body = ['version' => 1, 'source_location_id' => $this->location, 'location_id' => $this->otherLocation,
            'reason' => 'Synthetic care at second pharmacy', 'identity_reference' => 'Synthetic identity',
            'sharing_authority_reference' => 'Synthetic sharing authorization', 'sharing_confirmed' => true];
        // Target-only staff cannot discover or enroll a chart before an authorized source pharmacist shares it.
        DB::table('pharmacy_staff_assignments')->where('location_id', $this->location)->update(['active' => false]);
        $this->getJson($url)->assertNotFound();
        $this->postJson("$url/locations", $body)->assertNotFound();
        DB::table('pharmacy_staff_assignments')->where('location_id', $this->location)->update(['active' => true]);
        $changed = $this->postJson("$url/locations", $body)->assertOk()->assertJsonCount(2, 'data.locations')
            ->assertJsonPath('data.version', 2)->json('data');
        $event = $changed['history'][0];
        $this->assertSame($this->actor->id, $event['actor_id']);
        $this->assertSame('location_enrolled', $event['details']['action']);
        $this->assertSame($this->location, $event['details']['source_location_id']);
        $this->assertSame($this->otherLocation, $event['details']['location_id']);
        $this->assertSame($body['sharing_authority_reference'], $event['details']['sharing_authority_reference']);
        $this->assertTrue($event['details']['sharing_confirmed']);
        $afterPatient = (array) DB::table('pharmacy_patients')->where('id', $p['id'])->first();
        foreach ($beforePatient as $field => $value) {
            if (!in_array($field, ['version', 'updated_at'], true)) $this->assertSame($value, $afterPatient[$field]);
        }
        $this->postJson("$url/locations", $body)->assertConflict();
        $this->postJson("$url/locations", array_replace($body, ['version' => 2]))->assertUnprocessable();
        $this->assertSame(1, DB::table('pharmacy_patients')->count());
        $this->assertSame(2, DB::table('pharmacy_patient_locations')->count());
        $this->assertSame(2, DB::table('pharmacy_patient_events')->count());
        $this->assertSame($beforeRx, DB::table('pharmacy_prescriptions')->get()->toJson());
        $this->assertSame($beforeUsers, DB::table('users')->count());
        DB::table('pharmacy_staff_assignments')->where('location_id', $this->location)->update(['active' => false]);
        $this->getJson($url)->assertOk()->assertJsonCount(1, 'data.locations')->assertJsonPath('data.locations.0.id', $this->otherLocation);
        $this->getJson("$url/history")->assertOk()->assertJsonPath('data.total', 2);
        $this->getJson('/api/pharmacy/patients?location_id='.$this->otherLocation)->assertOk()->assertJsonPath('data.total', 1);
        $this->getJson("/api/pharmacy/prescriptions/$rx")->assertNotFound();
        $this->getJson('/api/pharmacy/prescriptions')->assertOk()->assertJsonPath('data.total', 0);
        // A separately received prescription can use the same chart at the enrolled site.
        $newRx = $this->rx(['patient_id' => null, 'pharmacy_patient_id' => $p['id'], 'location_id' => $this->otherLocation]);
        $this->getJson("/api/pharmacy/prescriptions/$newRx")->assertOk()->assertJsonPath('data.location_id', $this->otherLocation);
        $this->getJson('/api/pharmacy/prescriptions')->assertOk()->assertJsonPath('data.total', 1);
        $this->assertSame(1, DB::table('pharmacy_patients')->count());
        DB::table('pharmacy_staff_assignments')->where('location_id', $this->otherLocation)->update(['active' => false]);
        $this->getJson($url)->assertNotFound();
        $this->getJson("$url/history")->assertNotFound();
    }

    public function test_patient_location_enrollment_requires_both_assignments_evidence_and_atomic_audit(): void
    {
        $intake = ['request_id' => (string) Str::uuid(), 'record_number' => 'SYN-ENROLL-2', 'location_id' => $this->location,
            'first_name' => 'Synthetic', 'last_name' => 'Multi location', 'date_of_birth' => '1980-01-01', 'identity_reference' => 'Synthetic intake'];
        $id = $this->postJson('/api/pharmacy/patients', $intake)->assertCreated()->json('data.id');
        $url = "/api/pharmacy/patients/$id/locations";
        $body = ['version' => 1, 'source_location_id' => $this->location, 'location_id' => $this->otherLocation,
            'reason' => 'Synthetic enrollment', 'identity_reference' => 'Synthetic identity',
            'sharing_authority_reference' => 'Synthetic authority', 'sharing_confirmed' => true];
        foreach (['pharmacy_technician', 'medical_biller', 'admin', 'firm_admin', 'attorney', 'client', 'provider'] as $role) {
            $this->actor->role = $role; $this->postJson($url, $body)->assertForbidden();
        }
        $this->actor->role = 'pharmacist';
        foreach ([['sharing_confirmed' => false], ['reason' => ''], ['identity_reference' => ''], ['sharing_authority_reference' => ''],
            ['location_id' => $this->location]] as $invalid) {
            $this->postJson($url, array_replace($body, $invalid))->assertUnprocessable();
        }
        $this->postJson($url, array_replace($body, ['source_location_id' => $this->otherLocation, 'location_id' => $this->location]))->assertNotFound();
        $this->postJson($url, array_replace($body, ['version' => 2]))->assertConflict();
        $this->actor->organization_id = 2; $this->postJson($url, $body)->assertNotFound(); $this->actor->organization_id = 1;
        foreach ([['active' => false], ['valid_until' => now()->subDay()->toDateString()]] as $invalid) {
            DB::table('pharmacy_staff_assignments')->where('location_id', $this->otherLocation)->update($invalid);
            $this->postJson($url, $body)->assertNotFound();
            DB::table('pharmacy_staff_assignments')->where('location_id', $this->otherLocation)->update(['active' => true, 'valid_until' => now()->addYear()->toDateString()]);
        }
        DB::table('pharmacy_locations')->where('id', $this->otherLocation)->update(['active' => false]);
        $this->postJson($url, $body)->assertNotFound();
        DB::table('pharmacy_locations')->where('id', $this->otherLocation)->update(['active' => true, 'organization_id' => 2]);
        $this->postJson($url, $body)->assertNotFound();
        DB::table('pharmacy_locations')->where('id', $this->otherLocation)->update(['organization_id' => 1]);
        $before = (array) DB::table('pharmacy_patients')->where('id', $id)->first();
        $fail = true;
        DB::connection()->beforeExecuting(function ($query) use (&$fail) {
            if ($fail && str_starts_with(strtolower($query), 'insert into') && str_contains($query, 'pharmacy_patient_events')) {
                throw new \RuntimeException('Synthetic enrollment audit failure');
            }
        });
        $this->postJson($url, $body)->assertStatus(500); $fail = false;
        $this->assertSame($before, (array) DB::table('pharmacy_patients')->where('id', $id)->first());
        $this->assertSame(1, DB::table('pharmacy_patient_locations')->count());
        $this->assertSame(1, DB::table('pharmacy_patient_events')->count());
        $this->postJson($url, $body)->assertOk()->assertJsonPath('data.version', 2);
    }

    public function test_patient_history_pagination_reaches_older_records_without_cross_patient_or_location_disclosure(): void
    {
        $intake = ['request_id' => (string) Str::uuid(), 'record_number' => 'SYN-HISTORY-1', 'location_id' => $this->location,
            'first_name' => 'Synthetic', 'last_name' => 'History', 'date_of_birth' => '1980-01-01', 'identity_reference' => 'Synthetic intake'];
        $id = $this->postJson('/api/pharmacy/patients', $intake)->assertCreated()->json('data.id');
        $other = $this->postJson('/api/pharmacy/patients', array_replace($intake, ['request_id' => (string) Str::uuid(),
            'record_number' => 'SYN-HISTORY-2', 'location_id' => $this->otherLocation]))->assertCreated()->json('data.id');
        for ($n = 1; $n <= 104; $n++) {
            DB::table('pharmacy_patient_events')->insert(['patient_id' => $id, 'actor_id' => $this->actor->id,
                'details' => json_encode(['action' => 'synthetic_history_fixture', 'sequence' => $n]), 'created_at' => now()]);
        }
        $url = "/api/pharmacy/patients/$id/history";
        $first = $this->getJson($url)->assertOk()->assertJsonPath('data.total', 105)->assertJsonPath('data.last_page', 6)
            ->assertJsonCount(20, 'data.data')->assertJsonPath('data.data.0.details.sequence', 104)->json('data.data');
        $second = $this->getJson("$url?page=2")->assertOk()->assertJsonCount(20, 'data.data')->json('data.data');
        $this->assertSame([], array_values(array_intersect(array_column($first, 'id'), array_column($second, 'id'))));
        $last = $this->getJson("$url?page=6")->assertOk()->assertJsonCount(5, 'data.data')->json('data.data');
        $this->assertSame('created', $last[4]['details']['action']);
        foreach (array_merge($first, $second, $last) as $event) $this->assertSame($id, $event['patient_id']);
        $this->getJson("$url?page=0")->assertUnprocessable();
        $this->getJson("$url?page=bad")->assertUnprocessable();
        $this->actor->role = 'medical_biller';
        $this->getJson($url)->assertForbidden();
        $this->actor->role = 'pharmacy_technician';
        $this->getJson($url)->assertOk();
        $this->actor->role = 'pharmacist';
        $this->actor->organization_id = 2;
        $this->getJson($url)->assertNotFound();
        $this->actor->organization_id = 1;
        DB::table('pharmacy_staff_assignments')->where('location_id', $this->location)->update(['active' => false]);
        $this->getJson($url)->assertNotFound();
        $this->getJson("/api/pharmacy/patients/$other/history")->assertOk()->assertJsonPath('data.total', 1);
        $this->assertSame(106, DB::table('pharmacy_patient_events')->count());
    }

    public function test_patient_demographic_correction_preserves_identity_history_and_invalidates_prepared_fill(): void
    {
        $intake = ['request_id' => (string) Str::uuid(), 'record_number' => 'SYN-CORRECT-1', 'location_id' => $this->location,
            'first_name' => 'Synthetic', 'last_name' => 'Original', 'date_of_birth' => '1980-01-01', 'identity_reference' => 'Synthetic intake'];
        $p = $this->postJson('/api/pharmacy/patients', $intake)->assertCreated()->json('data');
        $url = "/api/pharmacy/patients/{$p['id']}";
        $review = ['version' => 1, 'allergies_status' => 'none_reported', 'medications_status' => 'none_reported',
            'reviewed_on' => now()->toDateString(), 'source_reference' => 'Synthetic interview'];
        $clinical = $this->putJson("$url/clinical", $review)->assertOk()->json('data.clinical');
        $rx = $this->rx(['patient_id' => null, 'pharmacy_patient_id' => $p['id']]);
        $f = $this->fill($rx, $this->lot());
        $f = $this->act($rx, $f, 'approve', $this->checks());
        $f = $this->act($rx, $f, 'ready', ['checks' => ['label' => true]]);
        $before = (array) DB::table('pharmacy_patients')->where('id', $p['id'])->first();
        $body = ['version' => 2, 'first_name' => 'Synthetic', 'last_name' => 'Corrected', 'date_of_birth' => '1980-02-01',
            'phone' => null, 'address' => 'Synthetic address', 'reason' => 'Transcription correction',
            'identity_reference' => 'Synthetic same-person evidence', 'same_patient_confirmed' => true,
            'record_number' => 'MUST-NOT-CHANGE', 'location_id' => $this->otherLocation, 'clinical' => ['allergies_status' => 'unknown']];
        $changed = $this->putJson("$url/demographics", $body)->assertOk()->assertJsonPath('data.version', 3)
            ->assertJsonPath('data.last_name', 'Corrected')->assertJsonPath('data.record_number', 'SYN-CORRECT-1')->json('data');
        $this->assertSame($clinical, $changed['clinical']);
        $event = $changed['history'][0];
        $this->assertSame($this->actor->id, $event['actor_id']);
        $this->assertSame('Original', $event['details']['previous']['last_name']);
        $this->assertSame('Corrected', $event['details']['recorded']['last_name']);
        $this->assertSame('Synthetic same-person evidence', $event['details']['identity_reference']);
        $after = (array) DB::table('pharmacy_patients')->where('id', $p['id'])->first();
        foreach (['request_id', 'request_hash', 'organization_id', 'record_number', 'created_at', 'clinical'] as $field) {
            $this->assertSame($before[$field], $after[$field]);
        }
        $this->assertSame([$this->location], DB::table('pharmacy_patient_locations')->where('patient_id', $p['id'])->pluck('location_id')->all());
        $this->putJson("$url/demographics", $body)->assertConflict();
        $this->putJson("$url/demographics", array_replace($body, ['version' => 3]))->assertUnprocessable();
        $this->assertSame(3, DB::table('pharmacy_patient_events')->count());
        $this->act($rx, $f, 'collected', ['occurred_on' => now()->toDateString(), 'reference' => 'Synthetic', 'counseling' => 'provided'], 422);
        $this->assertSame(0, DB::table('pharmacy_stock_events')->where('action', 'dispensed')->count());
    }

    public function test_patient_demographic_correction_checks_role_scope_validation_and_audit_atomicity(): void
    {
        $intake = ['request_id' => (string) Str::uuid(), 'record_number' => 'SYN-CORRECT-2', 'location_id' => $this->location,
            'first_name' => 'Synthetic', 'last_name' => 'Original', 'date_of_birth' => '1980-01-01', 'identity_reference' => 'Synthetic intake'];
        $id = $this->postJson('/api/pharmacy/patients', $intake)->assertCreated()->json('data.id');
        $url = "/api/pharmacy/patients/$id/demographics";
        $body = ['version' => 1, 'first_name' => 'Synthetic', 'last_name' => 'Corrected', 'date_of_birth' => '1980-01-01',
            'reason' => 'Synthetic correction', 'identity_reference' => 'Synthetic identity', 'same_patient_confirmed' => true];
        foreach (['pharmacy_technician', 'medical_biller', 'admin', 'firm_admin', 'attorney', 'client', 'provider'] as $role) {
            $this->actor->role = $role;
            $this->putJson($url, $body)->assertForbidden();
        }
        $this->actor->role = 'pharmacist';
        foreach ([['same_patient_confirmed' => false], ['reason' => ''], ['identity_reference' => ''],
            ['date_of_birth' => now()->addDay()->toDateString()], ['first_name' => '']] as $invalid) {
            $this->putJson($url, array_replace($body, $invalid))->assertUnprocessable();
        }
        $this->actor->organization_id = 2;
        $this->putJson($url, $body)->assertNotFound();
        $this->actor->organization_id = 1;
        DB::table('pharmacy_staff_assignments')->where('location_id', $this->location)->update(['active' => false]);
        $this->putJson($url, $body)->assertNotFound();
        DB::table('pharmacy_staff_assignments')->where('location_id', $this->location)->update(['active' => true]);
        $before = (array) DB::table('pharmacy_patients')->where('id', $id)->first();
        $fail = true;
        DB::connection()->beforeExecuting(function ($query) use (&$fail) {
            if ($fail && str_starts_with(strtolower($query), 'insert into') && str_contains($query, 'pharmacy_patient_events')) {
                throw new \RuntimeException('Synthetic patient audit failure');
            }
        });
        $this->putJson($url, $body)->assertStatus(500); $fail = false;
        $this->assertSame($before, (array) DB::table('pharmacy_patients')->where('id', $id)->first());
        $this->assertSame(1, DB::table('pharmacy_patient_events')->count());
        $this->putJson($url, $body)->assertOk()->assertJsonPath('data.version', 2);
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
