<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\PharmacyAccess;
use App\Services\PharmacyCompoundingIncident;
use App\Services\PharmacyIncidentGroup;
use App\Services\PharmacyJointAccounting;
use App\Services\PharmacyJointCustody;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PharmacyIncidentGroupController extends Controller
{
    public function history(Request $r, int $incidentId)
    {
        abort_unless(in_array($r->user()?->role, ['pharmacist', 'pharmacy_technician'], true) && $r->user()->organization_id, 403);
        $r->validate(['page' => 'nullable|integer|min:1']);
        $incident = DB::table('pharmacy_compounding_incidents')->where('organization_id', $r->user()->organization_id)->where('id', $incidentId)->first();
        abort_unless($incident, 404);
        app(PharmacyAccess::class)->requireLocation($r->user(), $incident->location_id);
        $groups = DB::table('pharmacy_incident_groups as g')->join('pharmacy_incident_group_members as m', 'm.group_id', '=', 'g.id')->where('m.incident_id', $incidentId)->where('g.organization_id', $r->user()->organization_id)->orderByDesc('g.id')->paginate(20, ['g.id', 'g.status', 'g.created_at']);
        $managed = DB::table('pharmacy_incident_groups as g')->join('pharmacy_incident_group_members as m', 'm.group_id', '=', 'g.id')->where('m.incident_id', $incidentId)->where('g.organization_id', $r->user()->organization_id)->where('g.status', '<>', 'rejected')->exists();

        return response()->json(['data' => $groups, 'joint_managed' => $managed]);
    }

    public function discover(Request $r, int $incidentId)
    {
        $snapshot = app(PharmacyIncidentGroup::class)->discover($r->user(), $incidentId);

        return response()->json(['data' => ['active_group_id' => DB::table('pharmacy_incident_group_members as m')->join('pharmacy_incident_groups as g', 'g.id', '=', 'm.group_id')->where('m.incident_id', $incidentId)->where('g.organization_id', $r->user()->organization_id)->whereNotIn('g.status', ['rejected', 'reconciled'])->value('g.id'), 'incident_ids' => $snapshot['incident_ids'], 'location_id' => $snapshot['location_id'], 'ingredients' => collect($snapshot['lines'])->map(fn ($line) => collect($line)->only(['incident_id', 'allocation_id', 'ingredient_lot_id', 'quantity_unit', 'reserved_quantity'])->all())->all()]]);
    }

    public function store(Request $r, int $incidentId)
    {
        return response()->json(['data' => ['id' => app(PharmacyIncidentGroup::class)->retain($r->user(), $incidentId, $r->all())]], 201);
    }

    public function show(Request $r, int $groupId)
    {
        abort_unless(in_array($r->user()?->role, ['pharmacist', 'pharmacy_technician'], true) && $r->user()->organization_id, 403);
        $r->validate(['page' => 'nullable|integer|min:1']);
        $group = DB::table('pharmacy_incident_groups')->where('organization_id', $r->user()->organization_id)->where('id', $groupId)->first(['id', 'location_id', 'created_by', 'status', 'version', 'evidence', 'created_at', 'reviewed_by', 'review_evidence', 'reviewed_at']);
        abort_unless($group, 404);
        app(PharmacyAccess::class)->requireLocation($r->user(), $group->location_id);
        $members = DB::table('pharmacy_incident_group_members as m')->join('pharmacy_compounding_incidents as i', 'i.id', '=', 'm.incident_id')->where('m.group_id', $groupId)->orderBy('i.id')->get(['i.id', 'i.batch_id', 'i.created_by', 'i.status', 'i.version']);
        $proposals = DB::table('pharmacy_incident_group_proposals')->where('group_id', $groupId)->orderByDesc('id')->paginate(20, ['id', 'phase', 'created_by', 'group_version', 'proposal', 'evidence', 'status', 'reviewed_by', 'review_evidence', 'reviewed_at', 'created_at']);
        $proposals->getCollection()->transform(function ($row) {
            $row->proposal = json_decode($row->proposal, true, 512, JSON_THROW_ON_ERROR);

            return $row;
        });

        $ingredients = DB::table('pharmacy_compounding_incident_lines')->whereIn('incident_id', $members->pluck('id'))->orderBy('allocation_id')->get(['incident_id', 'allocation_id', 'ingredient_lot_id', 'quantity_unit', 'reserved_quantity']);
        $applied = DB::table('pharmacy_incident_group_proposals')->where('group_id', $groupId)->where('phase', 'accounting')->where('status', 'applied')->first();
        $unused = $applied ? collect(json_decode($applied->proposal, true, 512, JSON_THROW_ON_ERROR))->map(fn ($entry) => ['allocation_id' => $entry['allocation_id'], 'unused_retained' => $entry['mode'] === 'new_accounting' ? $entry['accounting']['quantities']['unused_retained'] : $entry['unused_retained']])->all() : [];
        $priorUnused = [];
        foreach ($members->where('status', 'accounted_custody_held') as $member) {
            $prior = DB::table('pharmacy_compounding_reconciliations')->where('incident_id', $member->id)->where('status', 'applied')->get();
            if ($prior->count() !== 1) {
                continue;
            }
            $entries = json_decode($prior[0]->proposal, true, 512, JSON_THROW_ON_ERROR);
            abort_unless(hash_equals($prior[0]->proposal_hash, app(PharmacyCompoundingIncident::class)->digest($entries)), 409, 'Prior accounting evidence changed.');
            foreach ($entries as $entry) {
                $priorUnused[] = ['allocation_id' => $entry['allocation_id'], 'unused_retained' => $entry['accounting']['quantities']['unused_retained']];
            }
        }
        $pending = DB::table('pharmacy_incident_group_proposals')->where('group_id', $groupId)->where('status', 'pending')->exists();

        return response()->json(['data' => ['group' => $group, 'members' => $members, 'proposals' => $proposals, 'ingredients' => $ingredients, 'unused' => $unused, 'prior_unused' => $priorUnused, 'pending' => $pending]]);
    }

    public function reject(Request $r, int $groupId)
    {
        $data = $r->validate(['evidence' => 'required|string|max:5000']);
        app(PharmacyIncidentGroup::class)->reject($r->user(), $groupId, $data['evidence']);

        return response()->json(['data' => ['id' => $groupId, 'status' => 'rejected']]);
    }

    public function propose(Request $r, int $groupId, string $phase)
    {
        $service = $this->service($phase);

        return response()->json(['data' => ['id' => $service->retain($r->user(), $groupId, $r->all())]], 201);
    }

    public function review(Request $r, int $groupId, string $phase, int $proposalId, string $decision)
    {
        abort_unless(in_array($decision, ['apply', 'reject'], true), 404);
        $data = $r->validate(['evidence' => 'required|string|max:5000']);
        $this->service($phase)->{$decision}($r->user(), $groupId, $proposalId, $data['evidence']);

        return response()->json(['data' => ['id' => $proposalId, 'status' => $decision === 'apply' ? 'applied' : 'rejected']]);
    }

    private function service(string $phase): PharmacyJointAccounting|PharmacyJointCustody
    {
        abort_unless(in_array($phase, ['accounting', 'custody'], true), 404);

        return $phase === 'accounting' ? app(PharmacyJointAccounting::class) : app(PharmacyJointCustody::class);
    }
}
