<?php
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\PharmacyAccess;
use App\Services\PharmacyCompoundingIncident;
use App\Services\PharmacyFinishedContainerLabelContext;
use App\Services\PharmacyFinishedContainerLabelLedger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PharmacyFinishedContainerLabelController extends Controller
{
    private function scope(Request $r, int $executionId): void
    {
        $a = $r->user();
        abort_unless(in_array($a?->role, ['pharmacist', 'pharmacy_technician'], true) && $a->status === 'active' && $a->organization_id, 403);
        $e = DB::table('pharmacy_batch_executions')->find($executionId);
        $b = $e ? DB::table('pharmacy_batch_worksheets')->where('id', $e->batch_id)->where('organization_id', $a->organization_id)->first() : null;
        abort_unless($b, 404);app(PharmacyAccess::class)->requireLocation($a, $b->location_id);
    }
    public function context(Request $r, int $executionId)
    {
        $this->scope($r, $executionId);
        $d = $r->validate(['identifier' => 'required|string|max:64']);
        $source = app(PharmacyFinishedContainerLabelContext::class)->inspect($r->user(), $executionId, $d['identifier']);
        $data = array_intersect_key($source, array_flip(['container', 'unit', 'patient', 'location', 'prescription', 'dating_evidence']));
        $data['previous_id'] = DB::table('pharmacy_container_label_proofs')->where('execution_id', $executionId)->where('container_identifier', $d['identifier'])->orderByDesc('revision')->value('id');
        $data['source_hash'] = app(PharmacyCompoundingIncident::class)->digest($source);
        return response()->json(['data' => $data, 'release_enabled' => false]);
    }
    public function history(Request $r, int $executionId)
    {
        $this->scope($r, $executionId);$r->validate(['page' => 'nullable|integer|min:1']);
        return response()->json(['data' => DB::table('pharmacy_container_label_proofs')->where('execution_id', $executionId)->orderByDesc('id')
            ->paginate(20, ['id', 'execution_id', 'container_identifier', 'revision', 'previous_id', 'created_by', 'created_at', 'document_hash']), 'release_enabled' => false]);
    }
    public function store(Request $r, int $executionId)
    {
        $this->scope($r, $executionId);
        $id = app(PharmacyFinishedContainerLabelLedger::class)->retain($r->user(), $executionId, $r->all());
        return response()->json(['data' => ['id' => $id], 'release_enabled' => false], 201);
    }
    public function prints(Request $r, int $id)
    {
        $p = DB::table('pharmacy_container_label_proofs')->find($id); abort_unless($p, 404);
        $this->scope($r, $p->execution_id); $r->validate(['page' => 'nullable|integer|min:1']);
        return response()->json(['data' => DB::table('pharmacy_container_label_prints')->where('label_id', $id)->orderByDesc('id')
            ->paginate(20, ['id', 'label_id', 'created_by', 'document_hash', 'copies', 'occurred_on', 'reason', 'reference', 'created_at']),
            'simulated_only' => true, 'printer_command_sent' => false, 'release_enabled' => false]);
    }
    public function storePrint(Request $r, int $id)
    {
        $p = DB::table('pharmacy_container_label_proofs')->find($id); abort_unless($p, 404);
        $this->scope($r, $p->execution_id);
        $saved = app(\App\Services\PharmacyContainerLabelPrintLedger::class)->retain($r->user(), $id, $r->all());
        return response()->json(['data' => ['id' => $saved], 'simulated_only' => true, 'printer_command_sent' => false, 'release_enabled' => false], 201);
    }
    public function document(Request $r, int $id)
    {
        $p = DB::table('pharmacy_container_label_proofs')->find($id);abort_unless($p, 404);
        $this->scope($r, $p->execution_id);
        // Current proofs require a pharmacist to revalidate all documentary prerequisites.
        app(PharmacyFinishedContainerLabelLedger::class)->current($r->user(), $p);
        return response($p->document)->header('Content-Type', 'text/html; charset=UTF-8')->header('Cache-Control', 'private, no-store')
            ->header('Content-Security-Policy', "default-src 'none'; style-src 'unsafe-inline'; base-uri 'none'; form-action 'none'; frame-ancestors 'none'; sandbox")
            ->header('X-Content-Type-Options', 'nosniff')->header('Content-Disposition', 'inline; filename="synthetic-container-proof-'.$p->id.'.html"');
    }
}
