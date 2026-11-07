<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Pure connected-membership discovery. The caller supplies authorized scoped records. */
class PharmacyIncidentGroup
{
    /** Read-only preview; write callers must rediscover under the organization transaction lock. */
    public function discover(User $actor, int $seed): array
    {
        abort_unless(in_array($actor->role, ['pharmacist', 'pharmacy_technician'], true) && $actor->organization_id, 403);
        $incident = DB::table('pharmacy_compounding_incidents')->where('organization_id', $actor->organization_id)->where('id', $seed)->first();
        abort_unless($incident, 404);
        app(PharmacyAccess::class)->requireLocation($actor, $incident->location_id);
        $incidents = DB::table('pharmacy_compounding_incidents')->where('organization_id', $actor->organization_id)->where('status', '<>', 'reconciled')->orderBy('id')->get();
        $lines = DB::table('pharmacy_compounding_incident_lines')->whereIn('incident_id', $incidents->pluck('id'))->orderBy('id')->get();
        $group = $this->connected($seed, $incidents->map(fn ($row) => (array) $row)->all(), $lines->map(fn ($row) => (array) $row)->all());
        $lots = DB::table('pharmacy_ingredient_lots')->where('organization_id', $actor->organization_id)->where('location_id', $incident->location_id)->whereIn('id', $group['ingredient_lot_ids'])->orderBy('id')->get();
        abort_unless($lots->count() === count($group['ingredient_lot_ids']), 409, 'Connected ingredient evidence is outside the incident scope.');
        $members = $incidents->whereIn('id', $group['incident_ids'])->values();
        $selectedLines = $lines->whereIn('incident_id', $group['incident_ids'])->values();
        abort_unless($selectedLines->pluck('allocation_id')->unique()->count() === $selectedLines->count(), 409, 'An allocation cannot be accounted for by multiple incidents.');
        $allocations = DB::table('pharmacy_ingredient_allocations')->whereIn('id', $selectedLines->pluck('allocation_id'))->orderBy('id')->get()->keyBy('id');
        $batches = DB::table('pharmacy_batch_worksheets')->where('organization_id', $actor->organization_id)->where('location_id', $incident->location_id)->whereIn('id', $members->pluck('batch_id'))->get()->keyBy('id');
        abort_if(DB::table('pharmacy_batch_executions')->whereIn('batch_id', $members->pluck('batch_id'))->exists(), 409, 'Executed batches require their own correction workflow.');
        foreach ($selectedLines as $line) {
            $member = $members->firstWhere('id', $line->incident_id);
            $allocation = $allocations->get($line->allocation_id);
            $lot = $lots->firstWhere('id', $line->ingredient_lot_id);
            $expectedStatus = match ($member->status) {
                'unresolved' => 'reserved', 'accounted_custody_held' => 'incident_accounted', default => null
            };
            abort_unless($expectedStatus && $allocation && $allocation->status === $expectedStatus, 409, 'Allocation custody is inconsistent with the incident.');
            abort_unless($allocation && $batches->has($member->batch_id) && (int) $allocation->batch_id === (int) $member->batch_id && (int) $allocation->ingredient_lot_id === (int) $line->ingredient_lot_id && $lot->quantity_unit === $line->quantity_unit && PharmacyStock::milli($allocation->quantity) === PharmacyStock::milli($line->reserved_quantity), 409, 'Original allocation evidence is inconsistent.');
        }

        return $group + ['organization_id' => (int) $actor->organization_id, 'location_id' => (int) $incident->location_id,
            'members' => $members->map(fn ($row) => (array) $row)->all(),
            'lines' => $selectedLines->map(fn ($row) => (array) $row)->all(),
            'lots' => $lots->map(fn ($row) => (array) $row)->all(),
            'allocations' => $allocations->values()->map(fn ($row) => (array) $row)->all(),
            'worksheets' => $batches->sortKeys()->values()->map(fn ($row) => (array) $row)->all(),
            'competing_allocations' => DB::table('pharmacy_ingredient_allocations')->whereIn('ingredient_lot_id', $group['ingredient_lot_ids'])->orderBy('id')->get()->map(fn ($row) => (array) $row)->all(),
            'counts' => DB::table('pharmacy_ingredient_counts')->whereIn('ingredient_lot_id', $group['ingredient_lot_ids'])->orderBy('id')->get()->map(fn ($row) => (array) $row)->all(),
            'accounting' => DB::table('pharmacy_compounding_reconciliations')->whereIn('incident_id', $group['incident_ids'])->orderBy('id')->get()->map(fn ($row) => (array) $row)->all(),
            'custody' => DB::table('pharmacy_compounding_custody_decisions')->whereIn('incident_id', $group['incident_ids'])->orderBy('id')->get()->map(fn ($row) => (array) $row)->all()];
    }

    public function assertIndividualClear(int $incidentId): void
    {
        abort_if(DB::table('pharmacy_incident_group_members as m')->join('pharmacy_incident_groups as g', 'g.id', '=', 'm.group_id')->where('m.incident_id', $incidentId)->whereNotIn('g.status', ['rejected', 'reconciled'])->exists(), 409, 'This incident belongs to an active joint reconciliation group.');
    }

    /** Caller must hold the organization write lock when using this for a mutation. */
    public function current(User $actor, int $groupId): array
    {
        abort_unless($actor->role === 'pharmacist' && $actor->organization_id, 403);
        $group = DB::table('pharmacy_incident_groups')->where('organization_id', $actor->organization_id)->where('id', $groupId)->first();
        abort_unless($group, 404);
        app(PharmacyAccess::class)->requireLocation($actor, $group->location_id);
        abort_unless($group->status === 'pending', 409, 'Group is no longer pending.');
        $snapshot = json_decode($group->source_snapshot, true, 512, JSON_THROW_ON_ERROR);
        $digest = app(PharmacyCompoundingIncident::class);
        abort_unless(hash_equals($group->source_hash, $digest->digest($snapshot)), 409, 'Retained joint evidence changed.');
        $members = DB::table('pharmacy_incident_group_members')->where('group_id', $groupId)->orderBy('incident_id')->get();
        abort_unless($members->pluck('incident_id')->map(fn ($id) => (int) $id)->all() === $snapshot['incident_ids'], 409, 'Retained group membership changed.');
        foreach ($members as $member) {
            $source = collect($snapshot['members'])->firstWhere('id', $member->incident_id);
            $lines = array_values(array_filter($snapshot['lines'], fn ($line) => (int) $line['incident_id'] === (int) $member->incident_id));
            abort_unless($source && (int) $source['version'] === (int) $member->incident_version && $source['status'] === $member->incident_status && hash_equals($digest->digest($lines), $digest->digest(json_decode($member->allocation_snapshot, true, 512, JSON_THROW_ON_ERROR))), 409, 'Retained member evidence changed.');
        }
        $fresh = $this->discover($actor, $snapshot['incident_ids'][0]);
        abort_unless(hash_equals($group->source_hash, $digest->digest($fresh)), 409, 'Joint membership or source evidence changed; reject and replace the group.');

        return ['group' => $group, 'snapshot' => $fresh];
    }

    public function retain(User $actor, int $seed, array $data): int
    {
        abort_unless($actor->role === 'pharmacist' && $actor->organization_id, 403);
        $data = validator($data, ['request_id' => 'required|uuid', 'evidence' => 'required|string|max:5000'])->validate();

        return DB::transaction(function () use ($actor, $seed, $data) {
            DB::table('organizations')->where('id', $actor->organization_id)->lockForUpdate()->first();
            $digest = app(PharmacyCompoundingIncident::class);
            $hash = $digest->digest(['seed' => $seed, 'data' => $data]);
            $old = DB::table('pharmacy_incident_groups')->where('organization_id', $actor->organization_id)->where('request_id', $data['request_id'])->first();
            if ($old) {
                app(PharmacyAccess::class)->requireLocation($actor, $old->location_id);
                abort_unless((int) $old->created_by === (int) $actor->id && hash_equals($old->request_hash, $hash), 409, 'A different group request is already retained.');

                return (int) $old->id;
            }
            $group = $this->discover($actor, $seed);
            abort_unless(count($group['incident_ids']) > 1, 422, 'Use the single-incident workflow for an isolated incident.');
            foreach (['pharmacy_compounding_reconciliations', 'pharmacy_compounding_custody_decisions'] as $table) {
                abort_if(DB::table($table)->whereIn('incident_id', $group['incident_ids'])->where('status', 'pending')->exists(), 409, 'Reject pending individual proposals before retaining a joint group.');
            }
            abort_if(DB::table('pharmacy_incident_group_members as m')->join('pharmacy_incident_groups as g', 'g.id', '=', 'm.group_id')->whereIn('m.incident_id', $group['incident_ids'])->whereNotIn('g.status', ['rejected', 'reconciled'])->exists(), 409, 'An incident already belongs to an active joint group.');
            $id = DB::table('pharmacy_incident_groups')->insertGetId(['organization_id' => $actor->organization_id, 'location_id' => $group['location_id'], 'created_by' => $actor->id, 'request_id' => $data['request_id'], 'request_hash' => $hash, 'source_snapshot' => json_encode($group, JSON_THROW_ON_ERROR), 'source_hash' => $digest->digest($group), 'evidence' => $data['evidence'], 'created_at' => now()]);
            foreach ($group['members'] as $member) {
                $lines = array_values(array_filter($group['lines'], fn ($line) => (int) $line['incident_id'] === (int) $member['id']));
                DB::table('pharmacy_incident_group_members')->insert(['group_id' => $id, 'incident_id' => $member['id'], 'incident_version' => $member['version'], 'incident_status' => $member['status'], 'allocation_snapshot' => json_encode($lines, JSON_THROW_ON_ERROR)]);
                $batch = DB::table('pharmacy_batch_worksheets')->find($member['batch_id']);
                DB::table('pharmacy_compounding_events')->insert(['formulation_id' => $batch->formulation_id, 'batch_id' => $batch->id, 'actor_id' => $actor->id, 'action' => 'joint_incident_group_retained', 'details' => json_encode(['group_id' => $id, 'incident_id' => $member['id'], 'source_hash' => $digest->digest($group), 'stock_adjusted' => false], JSON_THROW_ON_ERROR), 'created_at' => now()]);
            }

            return (int) $id;
        });
    }

    public function reject(User $actor, int $groupId, string $evidence): void
    {
        abort_unless($actor->role === 'pharmacist' && $actor->organization_id, 403);
        validator(['evidence' => $evidence], ['evidence' => 'required|string|max:5000'])->validate();
        DB::transaction(function () use ($actor, $groupId, $evidence) {
            DB::table('organizations')->where('id', $actor->organization_id)->lockForUpdate()->first();
            $group = DB::table('pharmacy_incident_groups')->where('organization_id', $actor->organization_id)->where('id', $groupId)->lockForUpdate()->first();
            abort_unless($group, 404);
            app(PharmacyAccess::class)->requireLocation($actor, $group->location_id);
            $members = DB::table('pharmacy_incident_group_members as m')->join('pharmacy_compounding_incidents as i', 'i.id', '=', 'm.incident_id')->where('m.group_id', $groupId)->orderBy('i.id')->get(['i.id', 'i.created_by', 'i.batch_id']);
            abort_if((int) $group->created_by === (int) $actor->id || $members->contains(fn ($m) => (int) $m->created_by === (int) $actor->id), 422, 'A different pharmacist must review the joint group.');
            if ($group->status === 'rejected') {
                abort_unless((int) $group->reviewed_by === (int) $actor->id && $group->review_evidence === $evidence, 409, 'A different rejection is already retained.');

                return;
            }
            abort_unless($group->status === 'pending', 409, 'Only an unapplied group can be rejected.');
            abort_if(DB::table('pharmacy_incident_group_proposals')->where('group_id', $groupId)->whereIn('status', ['pending', 'applied'])->exists(), 409, 'Resolve the group proposal before rejecting the group.');
            DB::table('pharmacy_incident_groups')->where('id', $groupId)->update(['status' => 'rejected', 'version' => $group->version + 1, 'reviewed_by' => $actor->id, 'review_evidence' => $evidence, 'reviewed_at' => now()]);
            foreach ($members as $member) {
                $batch = DB::table('pharmacy_batch_worksheets')->find($member->batch_id);
                DB::table('pharmacy_compounding_events')->insert(['formulation_id' => $batch->formulation_id, 'batch_id' => $batch->id, 'actor_id' => $actor->id, 'action' => 'joint_incident_group_rejected', 'details' => json_encode(['group_id' => $groupId, 'incident_id' => $member->id, 'evidence' => $evidence, 'custody_hold_retained' => true, 'stock_adjusted' => false], JSON_THROW_ON_ERROR), 'created_at' => now()]);
            }
        });
    }

    public function connected(int $seed, array $incidents, array $lines): array
    {
        $active = [];
        $seen = [];
        foreach ($incidents as $incident) {
            $id = (int) $incident['id'];
            if (isset($seen[$id])) {
                throw ValidationException::withMessages(['incidents' => 'Duplicate incident identity.']);
            }
            $seen[$id] = true;
            if ($incident['status'] !== 'reconciled') {
                $active[$id] = $incident;
            }
        }
        if (! isset($active[$seed])) {
            throw ValidationException::withMessages(['incident' => 'An unresolved incident is required.']);
        }
        $byIncident = [];
        $byLot = [];
        foreach ($lines as $line) {
            $id = (int) $line['incident_id'];
            if (! isset($active[$id])) {
                continue;
            }
            $lot = (int) $line['ingredient_lot_id'];
            $byIncident[$id][$lot] = true;
            $byLot[$lot][$id] = true;
        }
        $members = [];
        $lots = [];
        $queue = [$seed];
        while ($queue !== []) {
            $id = array_pop($queue);
            if (isset($members[$id])) {
                continue;
            }
            if (empty($byIncident[$id])) {
                throw ValidationException::withMessages(['incident' => 'Incident ingredient evidence is incomplete.']);
            }
            $member = $active[$id];
            if ((int) $member['organization_id'] !== (int) $active[$seed]['organization_id'] || (int) $member['location_id'] !== (int) $active[$seed]['location_id']) {
                throw ValidationException::withMessages(['incident' => 'Connected incident scope is inconsistent.']);
            }
            $members[$id] = true;
            foreach ($byIncident[$id] as $lot => $_) {
                if (isset($lots[$lot])) {
                    continue;
                }
                $lots[$lot] = true;
                foreach ($byLot[$lot] as $next => $_unused) {
                    if (! isset($members[$next])) {
                        $queue[] = $next;
                    }
                }
            }
        }
        $ids = array_keys($members);
        $lotIds = array_keys($lots);
        sort($ids, SORT_NUMERIC);
        sort($lotIds, SORT_NUMERIC);

        return ['incident_ids' => $ids, 'ingredient_lot_ids' => $lotIds];
    }
}
