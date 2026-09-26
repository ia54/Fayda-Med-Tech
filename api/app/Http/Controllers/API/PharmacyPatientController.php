<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\PharmacyAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PharmacyPatientController extends Controller
{
    private function query(Request $r)
    {
        abort_unless(in_array($r->user()->role, ['pharmacist', 'pharmacy_technician'], true), 403);
        return DB::table('pharmacy_patients')->where('organization_id', $r->user()->organization_id)
            ->whereIn('id', DB::table('pharmacy_patient_locations')->select('patient_id')->whereIn('location_id',
                app(PharmacyAccess::class)->locations($r->user())->select('id')));
    }

    public function index(Request $r)
    {
        $d = $r->validate(['search' => 'nullable|string|max:100', 'location_id' => 'nullable|integer', 'page' => 'nullable|integer|min:1']);
        $q = $this->query($r);
        if (!empty($d['location_id'])) {
            app(PharmacyAccess::class)->requireLocation($r->user(), $d['location_id']);
            $q->whereIn('id', DB::table('pharmacy_patient_locations')->select('patient_id')->where('location_id', $d['location_id']));
        }
        if (!empty($d['search'])) {
            $q->where(fn ($q) => $q->where('record_number', 'like', '%'.$d['search'].'%')->orWhere('last_name', 'like', '%'.$d['search'].'%'));
        }
        return response()->json(['data' => $q->select('id', 'record_number', 'first_name', 'last_name', 'date_of_birth', 'version')->orderBy('last_name')->orderBy('id')->paginate(30)]);
    }

    public function show(Request $r, $id)
    {
        $p = $this->query($r)->where('id', $id)->first();
        abort_unless($p, 404);
        unset($p->request_hash, $p->request_id);
        $p->clinical = json_decode($p->clinical, true, 512, JSON_THROW_ON_ERROR);
        $p->history = DB::table('pharmacy_patient_events')->where('patient_id', $id)->orderByDesc('id')->limit(100)->get()->map(function ($e) {
            $e->details = json_decode($e->details, true, 512, JSON_THROW_ON_ERROR);
            return $e;
        });
        return response()->json(['data' => $p]);
    }

    public function store(Request $r)
    {
        $this->query($r); // Role check, including intake without an existing record.
        $d = $r->validate(['request_id' => 'required|uuid', 'record_number' => 'required|string|max:100', 'location_id' => 'required|integer',
            'first_name' => 'required|string|max:100', 'last_name' => 'required|string|max:100', 'date_of_birth' => 'required|date_format:Y-m-d|before_or_equal:today',
            'phone' => 'nullable|string|max:50', 'address' => 'nullable|string|max:255', 'identity_reference' => 'required|string|max:255']);
        app(PharmacyAccess::class)->requireLocation($r->user(), $d['location_id']);
        $org = $r->user()->organization_id;
        $hash = $d; unset($hash['request_id']); ksort($hash);
        $hash = hash('sha256', json_encode($hash, JSON_THROW_ON_ERROR));
        $old = DB::table('pharmacy_patients')->where('organization_id', $org)->where('request_id', $d['request_id'])->first();
        if ($old) {
            abort_unless(hash_equals($old->request_hash, $hash), 409, 'Request key already used for different content.');
            return $this->show($r, $old->id);
        }
        abort_if(DB::table('pharmacy_patients')->where('organization_id', $org)->where('record_number', $d['record_number'])->exists(), 409, 'Record number already exists. Review identity; do not create or merge by email.');
        $row = $d; unset($row['location_id'], $row['identity_reference']);
        $id = DB::table('pharmacy_patients')->insertGetId($row + ['organization_id' => $org, 'request_hash' => $hash,
            'clinical' => json_encode(['allergies_status' => 'unknown', 'allergies' => '', 'medications_status' => 'unknown', 'medications' => '', 'history' => '', 'reviewed_on' => null]),
            'created_at' => now(), 'updated_at' => now()]);
        DB::table('pharmacy_patient_locations')->insert(['patient_id' => $id, 'location_id' => $d['location_id']]);
        $this->event($r, $id, ['action' => 'created', 'identity_reference' => $d['identity_reference'], 'location_id' => $d['location_id']]);
        return $this->show($r, $id)->setStatusCode(201);
    }

    public function clinical(Request $r, $id)
    {
        abort_unless($r->user()->role === 'pharmacist', 403);
        $p = $this->query($r)->where('id', $id)->lockForUpdate()->first();
        abort_unless($p, 404);
        $d = $r->validate(['version' => 'required|integer|min:1', 'allergies_status' => 'required|in:unknown,none_reported,documented',
            'allergies' => 'nullable|string|max:10000|required_if:allergies_status,documented',
            'medications_status' => 'required|in:unknown,none_reported,documented', 'medications' => 'nullable|string|max:10000|required_if:medications_status,documented',
            'history' => 'nullable|string|max:10000', 'reviewed_on' => 'required|date_format:Y-m-d|before_or_equal:today', 'source_reference' => 'required|string|max:2000']);
        abort_unless($p->version === $d['version'], 409, 'Patient record changed. Refresh before saving.');
        foreach (['allergies', 'medications'] as $field) {
            abort_if($d[$field.'_status'] !== 'documented' && !empty($d[$field]), 422, 'Choose documented when recording '.$field.'.');
        }
        unset($d['version']); $d['reviewed_by'] = $r->user()->id;
        DB::table('pharmacy_patients')->where('id', $id)->update(['clinical' => json_encode($d), 'version' => $p->version + 1, 'updated_at' => now()]);
        $this->event($r, $id, ['action' => 'clinical_review', 'previous' => json_decode($p->clinical, true), 'recorded' => $d, 'version' => $p->version + 1]);
        return $this->show($r, $id);
    }

    private function event(Request $r, int $id, array $details): void
    {
        DB::table('pharmacy_patient_events')->insert(['patient_id' => $id, 'actor_id' => $r->user()->id, 'details' => json_encode($details, JSON_THROW_ON_ERROR), 'created_at' => now()]);
    }
}
