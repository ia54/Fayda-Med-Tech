<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\PharmacyAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Documentary amendments only. Original completion, stock and financial records are never rewritten. */
class PharmacyHandoverAddendumController extends Controller
{
    private function records(Request $r, $id, $fillId): array
    {
        abort_unless(in_array($r->user()->role, ['pharmacist', 'pharmacy_technician'], true), 403);
        $q = DB::table('pharmacy_prescriptions')->where('organization_id', $r->user()->organization_id)->where('id', $id);
        app(PharmacyAccess::class)->scope($q, $r->user());
        $rx = $q->first();
        abort_unless($rx, 404);
        $fill = DB::table('pharmacy_fills')->where('prescription_id', $id)->where('id', $fillId)->first();
        abort_unless($fill, 404);
        abort_unless(in_array($fill->fulfillment_status, ['collected', 'delivered'], true), 422, 'Only a completed handover can receive a retained addendum.');
        return [$rx, $fill];
    }

    private function source(object $fill): string
    {
        // Claim updates do not invalidate documentary corrections; clinical/custody facts do.
        return hash('sha256', json_encode([$fill->id, $fill->prescription_id, $fill->stock_lot_id,
            $fill->quantity, $fill->days_supply, $fill->ndc, $fill->fulfillment_status, $fill->fulfillment], JSON_THROW_ON_ERROR));
    }

    private function token(object $fill): string
    {
        $ledger = DB::table('pharmacy_handover_addenda')->where('fill_id', $fill->id)->orderBy('id')->get(['id', 'status', 'reviewed_by', 'reviewed_at']);
        return hash('sha256', $this->source($fill).json_encode($ledger, JSON_THROW_ON_ERROR));
    }

    private function event(Request $r, object $rx, string $action, int $fillId, int $addendumId): void
    {
        DB::table('pharmacy_events')->insert(['organization_id' => $rx->organization_id, 'prescription_id' => $rx->id,
            'actor_id' => $r->user()->id, 'action' => $action,
            'details' => json_encode(['fill_id' => $fillId, 'addendum_id' => $addendumId], JSON_THROW_ON_ERROR), 'created_at' => now()]);
    }

    public function index(Request $r, $id, $fillId)
    {
        [, $fill] = $this->records($r, $id, $fillId);
        $r->validate(['page' => 'nullable|integer|min:1']);
        $rows = DB::table('pharmacy_handover_addenda')->where('fill_id', $fillId)
            ->select('id', 'section', 'statement', 'reason', 'evidence', 'status', 'created_by', 'created_at', 'reviewed_by', 'review_evidence', 'reviewed_at')
            ->orderByDesc('id')->paginate(10);
        return response()->json(['data' => ['ledger_token' => $this->token($fill), 'addenda' => $rows]]);
    }

    public function store(Request $r, $id, $fillId)
    {
        abort_unless($r->user()->role === 'pharmacist', 403);
        [$rx, $fill] = $this->records($r, $id, $fillId);
        $d = $r->validate(['request_id' => 'required|uuid', 'ledger_token' => 'required|string|size:64|regex:/^[a-f0-9]+$/D',
            'section' => 'required|in:recipient,identity_checks,representative_authority,counseling,delivery,completion_reference',
            'statement' => 'required|string|max:5000', 'reason' => 'required|string|max:2000', 'evidence' => 'required|string|max:5000',
            'confirmed' => 'required|accepted']);
        ksort($d); $hash = hash('sha256', json_encode($d, JSON_THROW_ON_ERROR));
        $old = DB::table('pharmacy_handover_addenda')->where('fill_id', $fillId)->where('request_id', $d['request_id'])->first();
        if ($old) {
            abort_unless((int) $old->created_by === (int) $r->user()->id && hash_equals($old->request_hash, $hash), 409, 'This request identifier belongs to another addendum.');
            return $this->index($r, $id, $fillId);
        }
        abort_unless(hash_equals($this->token($fill), $d['ledger_token']), 409, 'The handover or addenda changed. Refresh and review the current record.');
        unset($d['ledger_token'], $d['confirmed']);
        $addendumId = DB::table('pharmacy_handover_addenda')->insertGetId($d + ['fill_id' => $fillId,
            'source_hash' => $this->source($fill), 'request_hash' => $hash, 'created_by' => $r->user()->id, 'created_at' => now()]);
        $this->event($r, $rx, 'handover_addendum_requested', (int) $fillId, $addendumId);
        return $this->index($r, $id, $fillId)->setStatusCode(201);
    }

    public function review(Request $r, $id, $fillId, $addendumId)
    {
        abort_unless($r->user()->role === 'pharmacist', 403);
        [$rx, $fill] = $this->records($r, $id, $fillId);
        $d = $r->validate(['decision' => 'required|in:accepted,rejected', 'evidence' => 'required|string|max:5000', 'confirmed' => 'required|accepted']);
        $a = DB::table('pharmacy_handover_addenda')->where('fill_id', $fillId)->where('id', $addendumId)->first();
        abort_unless($a, 404);
        abort_if((int) $a->created_by === (int) $r->user()->id, 422, 'A different assigned pharmacist must review this addendum.');
        if ($a->status !== 'pending') {
            abort_unless((int) $a->reviewed_by === (int) $r->user()->id && $a->status === $d['decision'] && $a->review_evidence === $d['evidence'], 409, 'A review is already retained. Add a new addendum instead of overwriting it.');
            return $this->index($r, $id, $fillId);
        }
        // A rejected request can always be retained even if its original source has changed.
        abort_if($d['decision'] === 'accepted' && !hash_equals($a->source_hash, $this->source($fill)), 409, 'Original handover changed. Reject this request and investigate before recording a new addendum.');
        DB::table('pharmacy_handover_addenda')->where('id', $a->id)->update(['status' => $d['decision'],
            'reviewed_by' => $r->user()->id, 'review_evidence' => $d['evidence'], 'reviewed_at' => now()]);
        $this->event($r, $rx, 'handover_addendum_'.$d['decision'], (int) $fillId, (int) $a->id);
        return $this->index($r, $id, $fillId);
    }
}
