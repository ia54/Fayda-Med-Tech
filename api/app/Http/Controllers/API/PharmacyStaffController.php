<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PharmacyStaffController extends Controller
{
    private function org(Request $r): int
    {
        abort_unless($r->user()->role === 'admin' && $r->user()->organization_id, 403);
        return (int) $r->user()->organization_id;
    }

    public function index(Request $r)
    {
        $org = $this->org($r);
        return response()->json(['data' => [
            'staff' => DB::table('users')->where('organization_id', $org)->where('status', 'active')
                ->whereIn('role', ['pharmacist', 'pharmacy_technician', 'medical_biller'])
                ->orderBy('last_name')->get(['id', 'first_name', 'last_name', 'role']),
            'assignments' => DB::table('pharmacy_staff_assignments as a')->join('pharmacy_locations as l', 'l.id', '=', 'a.location_id')
                ->where('l.organization_id', $org)->select('a.*')->orderBy('a.id')->get(),
            'history' => DB::table('pharmacy_access_events as e')->join('pharmacy_locations as l', 'l.id', '=', 'e.location_id')
                ->where('l.organization_id', $org)->select('e.*')->orderByDesc('e.id')->limit(100)->get(),
        ]]);
    }

    public function save(Request $r)
    {
        $org = $this->org($r);
        $d = $r->validate(['location_id' => 'required|integer', 'user_id' => 'required|integer', 'active' => 'required|boolean',
            'valid_until' => 'required|date_format:Y-m-d', 'version' => 'required|integer|min:0', 'reason' => 'required|string|max:2000']);
        abort_unless(DB::table('pharmacy_locations')->where('organization_id', $org)->where('id', $d['location_id'])->exists(), 404);
        // Allow revocation even after a user is disabled or changes role.
        $staff = DB::table('users')->where('organization_id', $org)->where('id', $d['user_id'])->first();
        abort_unless($staff, 404);
        if ($d['active']) {
            abort_unless($staff->status === 'active' && in_array($staff->role, ['pharmacist', 'pharmacy_technician', 'medical_biller'], true), 422, 'Select active pharmacy or billing staff.');
            abort_unless($d['valid_until'] >= now()->toDateString(), 422, 'An active assignment cannot already be expired.');
        }
        $keys = ['location_id' => $d['location_id'], 'user_id' => $d['user_id']];
        $old = DB::table('pharmacy_staff_assignments')->where($keys)->lockForUpdate()->first();
        abort_unless((int)($old->version ?? 0) === $d['version'], 409, 'Assignment changed. Refresh before saving.');
        $values = ['active' => $d['active'], 'valid_until' => $d['valid_until'], 'version' => $d['version'] + 1, 'updated_at' => now()];
        if ($old) {
            DB::table('pharmacy_staff_assignments')->where('id', $old->id)->update($values);
        } else {
            DB::table('pharmacy_staff_assignments')->insert($keys + $values + ['created_at' => now()]);
        }
        DB::table('pharmacy_access_events')->insert($keys + ['actor_id' => $r->user()->id, 'created_at' => now(), 'details' => json_encode([
            'previous' => $old ? ['active' => (bool)$old->active, 'valid_until' => $old->valid_until, 'version' => $old->version] : null,
            'active' => $d['active'], 'valid_until' => $d['valid_until'], 'reason' => $d['reason'],
        ], JSON_THROW_ON_ERROR)]);
        return $this->index($r);
    }
}
