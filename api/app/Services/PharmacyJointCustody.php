<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Pure unused-material projection; database callers must verify applied accounting and current scope. */
class PharmacyJointCustody
{
    public function retain(User $actor, int $groupId, array $data): int
    {
        abort_unless($actor->role === 'pharmacist' && $actor->organization_id && $actor->status === 'active', 403);
        $data = validator($data, ['request_id' => 'required|uuid', 'version' => 'required|integer|min:1', 'evidence' => 'required|string|max:5000', 'ingredients' => 'required|array|min:1', 'ingredients.*' => 'required|array:allocation_id,return_to_quarantine,disposed_unused,evidence', 'ingredients.*.allocation_id' => 'required|integer|distinct:strict', 'ingredients.*.return_to_quarantine' => 'required|numeric|min:0|max:999999.999|decimal:0,3', 'ingredients.*.disposed_unused' => 'required|numeric|min:0|max:999999.999|decimal:0,3', 'ingredients.*.evidence' => 'required|string|max:5000'])->validate();

        return DB::transaction(function () use ($actor, $groupId, $data) {
            DB::table('organizations')->where('id', $actor->organization_id)->lockForUpdate()->first();
            $group = DB::table('pharmacy_incident_groups')->where('organization_id', $actor->organization_id)->where('id', $groupId)->lockForUpdate()->first();
            abort_unless($group, 404);
            app(PharmacyAccess::class)->requireLocation($actor, $group->location_id);
            $digest = app(PharmacyCompoundingIncident::class);
            $hash = $digest->digest($data);
            $old = DB::table('pharmacy_incident_group_proposals')->where('group_id', $groupId)->where('request_id', $data['request_id'])->first();
            if ($old) {
                abort_unless($old->phase === 'custody' && (int) $old->created_by === (int) $actor->id && hash_equals($old->request_hash, $hash), 409, 'A different joint request is retained.');

                return (int) $old->id;
            }
            abort_unless($group->status === 'accounted_custody_held' && (int) $group->version === $data['version'], 409, 'Joint custody state changed.');
            abort_if(DB::table('pharmacy_incident_group_proposals')->where('group_id', $groupId)->where('phase', 'custody')->whereIn('status', ['pending', 'applied'])->exists(), 409, 'A custody proposal is already pending or applied.');
            $records = DB::table('pharmacy_incident_group_proposals')->where('group_id', $groupId)->where('phase', 'accounting')->where('status', 'applied')->get();
            abort_unless($records->count() === 1, 409, 'One applied joint accounting decision is required.');
            $record = $records[0];
            $entries = json_decode($record->proposal, true, 512, JSON_THROW_ON_ERROR);
            $source = json_decode($record->source_snapshot, true, 512, JSON_THROW_ON_ERROR);
            abort_unless(hash_equals($record->proposal_hash, $digest->digest($entries)) && hash_equals($record->source_hash, $digest->digest($source)) && hash_equals($group->source_hash, $digest->digest($source)), 409, 'Retained accounting evidence changed.');
            $members = DB::table('pharmacy_incident_group_members')->where('group_id', $groupId)->orderBy('incident_id')->pluck('incident_id')->map(fn ($id) => (int) $id)->all();
            abort_unless($members === $source['incident_ids'], 409, 'Joint membership changed.');
            $fresh = app(PharmacyIncidentGroup::class)->discover($actor, $members[0]);
            abort_unless($fresh['incident_ids'] === $members && collect($fresh['members'])->every(fn ($member) => $member['status'] === 'accounted_custody_held'), 409, 'Shared incident custody changed.');
            $proposal = $this->proposal($entries, $data['ingredients']);
            $this->project($entries, $data['ingredients'], $fresh['lots']);
            $snapshot = ['current' => $fresh, 'accounting' => (array) $record];
            $id = DB::table('pharmacy_incident_group_proposals')->insertGetId(['group_id' => $groupId, 'created_by' => $actor->id, 'request_id' => $data['request_id'], 'request_hash' => $hash, 'phase' => 'custody', 'group_version' => $group->version, 'proposal' => json_encode($proposal, JSON_THROW_ON_ERROR), 'proposal_hash' => $digest->digest($proposal), 'source_snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR), 'source_hash' => $digest->digest($snapshot), 'evidence' => $data['evidence'], 'created_at' => now()]);
            DB::table('pharmacy_incident_groups')->where('id', $groupId)->update(['version' => $group->version + 1]);
            foreach ($fresh['members'] as $member) {
                $batch = collect($fresh['worksheets'])->firstWhere('id', $member['batch_id']);
                DB::table('pharmacy_compounding_events')->insert(['formulation_id' => $batch['formulation_id'], 'batch_id' => $batch['id'], 'actor_id' => $actor->id, 'action' => 'joint_custody_proposed', 'details' => json_encode(['group_id' => $groupId, 'proposal_id' => $id, 'incident_id' => $member['id'], 'stock_adjusted' => false, 'custody_hold_retained' => true], JSON_THROW_ON_ERROR), 'created_at' => now()]);
            }

            return (int) $id;
        });
    }

    public function apply(User $actor, int $groupId, int $proposalId, string $evidence): void
    {
        abort_unless($actor->role === 'pharmacist' && $actor->organization_id && $actor->status === 'active', 403);
        validator(['evidence' => $evidence], ['evidence' => 'required|string|max:5000'])->validate();
        DB::transaction(function () use ($actor, $groupId, $proposalId, $evidence) {
            DB::table('organizations')->where('id', $actor->organization_id)->lockForUpdate()->first();
            $group = DB::table('pharmacy_incident_groups')->where('organization_id', $actor->organization_id)->where('id', $groupId)->lockForUpdate()->first();
            abort_unless($group, 404);
            app(PharmacyAccess::class)->requireLocation($actor, $group->location_id);
            $proposal = DB::table('pharmacy_incident_group_proposals')->where('group_id', $groupId)->where('id', $proposalId)->where('phase', 'custody')->lockForUpdate()->first();
            abort_unless($proposal, 404);
            if ($proposal->status !== 'pending') {
                abort_unless($proposal->status === 'applied' && (int) $proposal->reviewed_by === (int) $actor->id && $proposal->review_evidence === $evidence, 409, 'A different custody decision is retained.');

                return;
            }
            abort_unless($group->status === 'accounted_custody_held' && (int) $group->version === (int) $proposal->group_version + 1, 409, 'Joint custody state changed.');
            $digest = app(PharmacyCompoundingIncident::class);
            $snapshot = json_decode($proposal->source_snapshot, true, 512, JSON_THROW_ON_ERROR);
            $entries = json_decode($proposal->proposal, true, 512, JSON_THROW_ON_ERROR);
            abort_unless(hash_equals($proposal->source_hash, $digest->digest($snapshot)) && hash_equals($proposal->proposal_hash, $digest->digest($entries)), 409, 'Custody evidence changed.');
            $members = DB::table('pharmacy_incident_group_members')->where('group_id', $groupId)->orderBy('incident_id')->pluck('incident_id')->map(fn ($id) => (int) $id)->all();
            abort_unless($members === $snapshot['current']['incident_ids'], 409, 'Joint membership changed.');
            $fresh = app(PharmacyIncidentGroup::class)->discover($actor, $members[0]);
            $record = DB::table('pharmacy_incident_group_proposals')->where('group_id', $groupId)->where('id', $snapshot['accounting']['id'])->where('phase', 'accounting')->where('status', 'applied')->first();
            abort_unless($record && hash_equals($proposal->source_hash, $digest->digest(['current' => $fresh, 'accounting' => (array) $record])), 409, 'Joint stock or accounting changed.');
            $authors = [(int) $group->created_by, (int) $proposal->created_by];
            foreach ($fresh['members'] as $member) {
                $authors[] = (int) $member['created_by'];
            }
            abort_if(in_array((int) $actor->id, $authors, true), 422, 'A different pharmacist must review joint custody.');
            foreach (array_unique([(int) $group->created_by, (int) $proposal->created_by]) as $id) {
                $author = User::find($id);
                abort_unless($author && $author->role === 'pharmacist' && $author->status === 'active' && (int) $author->organization_id === (int) $actor->organization_id, 422, 'Custody author no longer has pharmacy authority.');
                app(PharmacyAccess::class)->requireLocation($author, $group->location_id);
            }
            abort_if(collect($fresh['counts'])->contains(fn ($count) => $count['status'] === 'pending'), 422, 'Resolve pending ingredient counts first.');
            $accounting = json_decode($record->proposal, true, 512, JSON_THROW_ON_ERROR);
            abort_unless(hash_equals($record->proposal_hash, $digest->digest($accounting)), 409, 'Applied accounting evidence changed.');
            $inputs = array_map(fn ($entry) => $entry['custody'] + ['allocation_id' => $entry['allocation_id'], 'evidence' => $entry['evidence']], $entries);
            abort_unless(hash_equals($proposal->proposal_hash, $digest->digest($this->proposal($accounting, $inputs))), 409, 'Custody allocation evidence changed.');
            $movements = $this->project($accounting, $inputs, $fresh['lots']);
            foreach ($movements as $movement) {
                $lot = collect($fresh['lots'])->firstWhere('id', $movement['ingredient_lot_id']);
                DB::table('pharmacy_ingredient_lots')->where('id', $lot['id'])->update(['on_hand' => $movement['on_hand'], 'reserved' => $movement['reserved'], 'status' => $movement['status'], 'version' => $lot['version'] + 1, 'updated_at' => now()]);
                DB::table('pharmacy_ingredient_events')->insert(['ingredient_lot_id' => $lot['id'], 'batch_id' => null, 'actor_id' => $actor->id, 'action' => 'joint_unused_custody_resolved', 'quantity' => $movement['disposed_unused'], 'details' => json_encode(['group_id' => $groupId, 'proposal_id' => $proposalId, 'incident_ids' => $members, 'previous_on_hand' => $lot['on_hand'], 'remaining_on_hand' => $movement['on_hand'], 'remaining_reserved' => $movement['reserved'], 'release_enabled' => false], JSON_THROW_ON_ERROR), 'created_at' => now()]);
            }
            foreach ($fresh['members'] as $member) {
                DB::table('pharmacy_compounding_incidents')->where('id', $member['id'])->update(['status' => 'reconciled', 'version' => $member['version'] + 1]);
                $batch = collect($fresh['worksheets'])->firstWhere('id', $member['batch_id']);
                DB::table('pharmacy_compounding_events')->insert(['formulation_id' => $batch['formulation_id'], 'batch_id' => $batch['id'], 'actor_id' => $actor->id, 'action' => 'joint_custody_applied', 'details' => json_encode(['group_id' => $groupId, 'proposal_id' => $proposalId, 'incident_id' => $member['id'], 'evidence' => $evidence, 'output_release_enabled' => false], JSON_THROW_ON_ERROR), 'created_at' => now()]);
            }
            DB::table('pharmacy_incident_group_proposals')->where('id', $proposalId)->update(['status' => 'applied', 'reviewed_by' => $actor->id, 'review_evidence' => $evidence, 'reviewed_at' => now()]);
            DB::table('pharmacy_incident_groups')->where('id', $groupId)->update(['status' => 'reconciled', 'version' => $group->version + 1]);
        });
    }

    public function reject(User $actor, int $groupId, int $proposalId, string $evidence): void
    {
        abort_unless($actor->role === 'pharmacist' && $actor->organization_id && $actor->status === 'active', 403);
        validator(['evidence' => $evidence], ['evidence' => 'required|string|max:5000'])->validate();
        DB::transaction(function () use ($actor, $groupId, $proposalId, $evidence) {
            DB::table('organizations')->where('id', $actor->organization_id)->lockForUpdate()->first();
            $group = DB::table('pharmacy_incident_groups')->where('organization_id', $actor->organization_id)->where('id', $groupId)->lockForUpdate()->first();
            abort_unless($group, 404);
            app(PharmacyAccess::class)->requireLocation($actor, $group->location_id);
            $proposal = DB::table('pharmacy_incident_group_proposals')->where('group_id', $groupId)->where('id', $proposalId)->where('phase', 'custody')->lockForUpdate()->first();
            abort_unless($proposal, 404);
            $members = DB::table('pharmacy_incident_group_members as m')->join('pharmacy_compounding_incidents as i', 'i.id', '=', 'm.incident_id')->where('m.group_id', $groupId)->orderBy('i.id')->get(['i.id', 'i.created_by', 'i.batch_id']);
            abort_if(in_array((int) $actor->id, [(int) $group->created_by, (int) $proposal->created_by], true) || $members->contains(fn ($member) => (int) $member->created_by === (int) $actor->id), 422, 'A different pharmacist must review joint custody.');
            if ($proposal->status !== 'pending') {
                abort_unless($proposal->status === 'rejected' && (int) $proposal->reviewed_by === (int) $actor->id && $proposal->review_evidence === $evidence, 409, 'A different decision is already retained.');

                return;
            }
            abort_unless($group->status === 'accounted_custody_held', 409, 'The joint group is no longer awaiting custody resolution.');
            DB::table('pharmacy_incident_group_proposals')->where('id', $proposalId)->update(['status' => 'rejected', 'reviewed_by' => $actor->id, 'review_evidence' => $evidence, 'reviewed_at' => now()]);
            DB::table('pharmacy_incident_groups')->where('id', $groupId)->update(['version' => $group->version + 1]);
            foreach ($members as $member) {
                $batch = DB::table('pharmacy_batch_worksheets')->find($member->batch_id);
                DB::table('pharmacy_compounding_events')->insert(['formulation_id' => $batch->formulation_id, 'batch_id' => $batch->id, 'actor_id' => $actor->id, 'action' => 'joint_custody_rejected', 'details' => json_encode(['group_id' => $groupId, 'proposal_id' => $proposalId, 'incident_id' => $member->id, 'evidence' => $evidence, 'stock_adjusted' => false, 'custody_hold_retained' => true], JSON_THROW_ON_ERROR), 'created_at' => now()]);
            }
        });
    }

    public function proposal(array $accounting, array $inputs): array
    {
        $submitted = collect($inputs)->keyBy('allocation_id');
        $retained = collect($accounting)->keyBy('allocation_id');
        if ($accounting === [] || $retained->count() !== count($accounting) || $submitted->count() !== count($inputs) || $submitted->count() !== $retained->count() || ! $retained->keys()->every(fn ($id) => $submitted->has($id))) {
            throw ValidationException::withMessages(['custody' => 'Account for every joint allocation exactly once.']);
        }
        $result = [];
        foreach ($retained->sortKeys() as $id => $entry) {
            $input = $submitted[$id];
            validator($input, ['evidence' => 'required|string|max:5000'])->validate();
            if (! in_array($entry['mode'], ['new_accounting', 'already_accounted'], true)) {
                throw ValidationException::withMessages(['custody' => 'Unknown accounting source.']);
            }
            $unused = $entry['mode'] === 'new_accounting' ? $entry['accounting']['quantities']['unused_retained'] : $entry['unused_retained'];
            $result[] = ['incident_id' => $entry['incident_id'], 'allocation_id' => $id, 'ingredient_lot_id' => $entry['ingredient_lot_id'], 'unit' => $entry['unit'], 'custody' => app(PharmacyIncidentCustody::class)->line($unused, $input), 'evidence' => $input['evidence']];
        }

        return $result;
    }

    public function project(array $accounting, array $inputs, array $lots): array
    {
        $entries = $this->proposal($accounting, $inputs);
        $receipts = collect($lots)->keyBy('id');
        if ($receipts->count() !== count($lots)) {
            throw ValidationException::withMessages(['custody' => 'Duplicate receipt evidence.']);
        }
        $result = [];
        foreach (collect($entries)->groupBy('ingredient_lot_id')->sortKeys() as $id => $lines) {
            $lot = $receipts->get($id);
            if (! $lot || ! $lines->every(fn ($line) => $line['unit'] === $lot['quantity_unit'])) {
                throw ValidationException::withMessages(['custody' => 'Receipt identity or quantity unit changed.']);
            }
            $result[] = ['ingredient_lot_id' => $id] + app(PharmacyIncidentCustody::class)->projectLot($lot['on_hand'], $lot['reserved'], $lines->pluck('custody')->all(), $lot['status'], $lot['recall_reference'] ?? null);
        }

        return $result;
    }
}
