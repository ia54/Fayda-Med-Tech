<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\PharmacyAccess;
use App\Services\PharmacyCompoundingIncident;
use App\Services\PharmacyQualityProtocolLedger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PharmacyQualityProtocolController extends Controller
{
    private function staff(Request $r): void
    {
        abort_unless(in_array($r->user()?->role, ['pharmacist', 'pharmacy_technician'], true)
            && $r->user()->status === 'active' && $r->user()->organization_id, 403);
    }

    private function scoped(Request $r, int $id): object
    {
        $this->staff($r);
        $p = DB::table('pharmacy_quality_protocols')->where('id', $id)->where('organization_id', $r->user()->organization_id)->first();
        abort_unless($p, 404);
        app(PharmacyAccess::class)->requireLocation($r->user(), $p->location_id);
        return $p;
    }

    public function index(Request $r)
    {
        $this->staff($r);
        $d = $r->validate(['page' => 'nullable|integer|min:1', 'location_id' => 'nullable|integer|min:1',
            'formulation_id' => 'nullable|integer|min:1', 'status' => 'nullable|in:draft,reviewed,rejected,retired', 'reviewable' => 'nullable|boolean']);
        $q = DB::table('pharmacy_quality_protocols')->where('organization_id', $r->user()->organization_id);
        app(PharmacyAccess::class)->scope($q, $r->user());
        if (! empty($d['location_id'])) {
            app(PharmacyAccess::class)->requireLocation($r->user(), $d['location_id']);
            $q->where('location_id', $d['location_id']);
        }
        foreach (['formulation_id', 'status'] as $field) { if (! empty($d[$field])) { $q->where($field, $d[$field]); } }
        if ($r->boolean('reviewable')) {
            abort_unless($r->user()->role === 'pharmacist', 403);
            $q->where('status', 'draft')->where('created_by', '<>', $r->user()->id);
        }
        return response()->json(['data' => $q->orderByDesc('id')->paginate(20,
            ['id', 'location_id', 'formulation_id', 'revision_number', 'previous_id', 'status', 'version', 'created_by', 'created_at']), 'release_enabled' => false]);
    }

    public function show(Request $r, int $id)
    {
        $p = $this->scoped($r, $id);
        $record = json_decode($p->record, true, 512, JSON_THROW_ON_ERROR);
        $digest = app(PharmacyCompoundingIncident::class);
        abort_unless(hash_equals($p->record_hash, $digest->digest($record)), 409, 'Protocol integrity failed.');
        $f = DB::table('pharmacy_formulations')->where('id', $p->formulation_id)->where('organization_id', $r->user()->organization_id)->first();
        $data = array_intersect_key((array) $p, array_flip(['id', 'location_id', 'formulation_id', 'revision_number', 'previous_id', 'status', 'version', 'created_by', 'created_at']));
        $data['record'] = $record;
        $data['formulation_evidence_current'] = $f && $f->status === 'reviewed' && hash_equals($p->formulation_hash, $digest->digest((array) $f));
        return response()->json(['data' => $data, 'release_enabled' => false, 'operational_acceptance' => false]);
    }

    public function history(Request $r, int $id)
    {
        $this->scoped($r, $id);
        $r->validate(['page' => 'nullable|integer|min:1']);
        return response()->json(['data' => DB::table('pharmacy_quality_protocol_events')->where('protocol_id', $id)->orderByDesc('id')
            ->paginate(20, ['id', 'actor_id', 'action', 'version', 'evidence', 'created_at']), 'release_enabled' => false]);
    }

    public function store(Request $r)
    {
        $this->staff($r);
        $d = $r->validate(['location_id' => 'required|integer|min:1', 'formulation_id' => 'required|integer|min:1']);
        $id = app(PharmacyQualityProtocolLedger::class)->retain($r->user(), $d['location_id'], $d['formulation_id'], $r->all());
        return $this->show($r, $id)->setStatusCode(201);
    }

    public function decide(Request $r, int $id)
    {
        $this->scoped($r, $id);
        $d = $r->validate(['version' => 'required|integer|min:1', 'decision' => 'required|in:reviewed,rejected,retired', 'evidence' => 'required|string|max:5000']);
        app(PharmacyQualityProtocolLedger::class)->decide($r->user(), $id, $d['version'], $d['decision'], $d['evidence']);
        return response()->json(['data' => ['id' => $id, 'status' => $d['decision']], 'release_enabled' => false]);
    }
}
