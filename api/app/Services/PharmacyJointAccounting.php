<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Builds a complete proposal without moving stock or inferring clinical approval. */
class PharmacyJointAccounting
{
    public function retain(User $actor, int $groupId, array $data): int
    {
        abort_unless($actor->role === 'pharmacist' && $actor->organization_id, 403);
        $rules = ['request_id' => 'required|uuid', 'version' => 'required|integer|min:1', 'evidence' => 'required|string|max:5000',
            'ingredients' => 'required|array|min:1', 'ingredients.*' => 'required|array:allocation_id,additional_taken,consumed,unused_retained,disposed_unused,unaccounted,evidence',
            'ingredients.*.allocation_id' => 'required|integer|distinct:strict', 'ingredients.*.evidence' => 'required|string|max:5000'];
        foreach (['additional_taken', 'consumed', 'unused_retained', 'disposed_unused', 'unaccounted'] as $field) {
            $rules['ingredients.*.'.$field] = 'required|numeric|min:0|max:999999.999|decimal:0,3';
        }
        $data = validator($data, $rules)->validate();

        return DB::transaction(function () use ($actor, $groupId, $data) {
            DB::table('organizations')->where('id', $actor->organization_id)->lockForUpdate()->first();
            $group = DB::table('pharmacy_incident_groups')->where('organization_id', $actor->organization_id)->where('id', $groupId)->first();
            abort_unless($group, 404);
            app(PharmacyAccess::class)->requireLocation($actor, $group->location_id);
            $digest = app(PharmacyCompoundingIncident::class);
            $hash = $digest->digest($data);
            $old = DB::table('pharmacy_incident_group_proposals')->where('group_id', $groupId)->where('request_id', $data['request_id'])->first();
            if ($old) {
                abort_unless((int) $old->created_by === (int) $actor->id && hash_equals($old->request_hash, $hash), 409, 'A different joint proposal request is already retained.');

                return (int) $old->id;
            }
            $current = app(PharmacyIncidentGroup::class)->current($actor, $groupId);
            abort_unless((int) $group->version === $data['version'], 409, 'Joint group version changed.');
            abort_if(DB::table('pharmacy_incident_group_proposals')->where('group_id', $groupId)->whereIn('status', ['pending', 'applied'])->exists(), 409, 'A joint proposal is already pending or applied.');
            $proposal = $this->proposal($current['snapshot'], $data['ingredients']);
            $id = DB::table('pharmacy_incident_group_proposals')->insertGetId(['group_id' => $groupId, 'created_by' => $actor->id, 'request_id' => $data['request_id'], 'request_hash' => $hash, 'phase' => 'accounting', 'group_version' => $group->version, 'proposal' => json_encode($proposal, JSON_THROW_ON_ERROR), 'proposal_hash' => $digest->digest($proposal), 'source_snapshot' => json_encode($current['snapshot'], JSON_THROW_ON_ERROR), 'source_hash' => $digest->digest($current['snapshot']), 'evidence' => $data['evidence'], 'created_at' => now()]);
            DB::table('pharmacy_incident_groups')->where('id', $groupId)->update(['version' => $group->version + 1]);
            foreach ($current['snapshot']['members'] as $member) {
                $batch = DB::table('pharmacy_batch_worksheets')->find($member['batch_id']);
                DB::table('pharmacy_compounding_events')->insert(['formulation_id' => $batch->formulation_id, 'batch_id' => $batch->id, 'actor_id' => $actor->id, 'action' => 'joint_accounting_proposed', 'details' => json_encode(['group_id' => $groupId, 'proposal_id' => $id, 'incident_id' => $member['id'], 'stock_adjusted' => false], JSON_THROW_ON_ERROR), 'created_at' => now()]);
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
            $proposal = DB::table('pharmacy_incident_group_proposals')->where('group_id', $groupId)->where('id', $proposalId)->where('phase', 'accounting')->lockForUpdate()->first();
            abort_unless($proposal, 404);
            if ($proposal->status !== 'pending') {
                abort_unless($proposal->status === 'applied' && (int) $proposal->reviewed_by === (int) $actor->id && $proposal->review_evidence === $evidence, 409, 'A different joint decision is retained.');

                return;
            }
            $current = app(PharmacyIncidentGroup::class)->current($actor, $groupId);
            $snapshot = $current['snapshot'];
            abort_unless((int) $group->version === (int) $proposal->group_version + 1, 409, 'Group version changed.');
            $authors = [(int) $group->created_by, (int) $proposal->created_by];
            foreach ($snapshot['members'] as $member) {
                $authors[] = (int) $member['created_by'];
            }
            abort_if(in_array((int) $actor->id, $authors, true), 422, 'A different pharmacist must review joint accounting.');
            foreach (array_unique([(int) $group->created_by, (int) $proposal->created_by]) as $id) {
                $author = User::find($id);
                abort_unless($author && $author->role === 'pharmacist' && $author->status === 'active' && (int) $author->organization_id === (int) $actor->organization_id, 422, 'Joint proposal author no longer has pharmacy authority.');
                app(PharmacyAccess::class)->requireLocation($author, $group->location_id);
            }
            $digest = app(PharmacyCompoundingIncident::class);
            $entries = json_decode($proposal->proposal, true, 512, JSON_THROW_ON_ERROR);
            $source = json_decode($proposal->source_snapshot, true, 512, JSON_THROW_ON_ERROR);
            abort_unless(hash_equals($proposal->source_hash, $digest->digest($source)) && hash_equals($proposal->source_hash, $digest->digest($snapshot)) && hash_equals($proposal->proposal_hash, $digest->digest($entries)), 409, 'Joint proposal evidence changed.');
            abort_if(collect($snapshot['counts'])->contains(fn ($count) => $count['status'] === 'pending'), 422, 'Resolve pending ingredient counts first.');
            $inputs = [];
            foreach ($entries as $entry) {
                $quantities = $entry['mode'] === 'new_accounting' ? $entry['accounting']['quantities'] : ['additional_taken' => 0, 'consumed' => 0, 'unused_retained' => $entry['unused_retained'], 'disposed_unused' => 0, 'unaccounted' => 0];
                $inputs[] = $quantities + ['allocation_id' => $entry['allocation_id'], 'evidence' => $entry['evidence']];
            }
            abort_unless(hash_equals($proposal->proposal_hash, $digest->digest($this->proposal($snapshot, $inputs))), 409, 'Joint allocation accounting changed.');
            $movements = $this->project($snapshot, $inputs);
            foreach ($movements as $movement) {
                $lot = collect($snapshot['lots'])->firstWhere('id', $movement['ingredient_lot_id']);
                DB::table('pharmacy_ingredient_lots')->where('id', $lot['id'])->update(['on_hand' => $movement['on_hand'], 'reserved' => $movement['reserved'], 'status' => $movement['status'], 'version' => $lot['version'] + 1, 'updated_at' => now()]);
                DB::table('pharmacy_ingredient_events')->insert(['ingredient_lot_id' => $lot['id'], 'batch_id' => null, 'actor_id' => $actor->id, 'action' => 'joint_incident_accounting_applied', 'quantity' => $movement['deduct'], 'details' => json_encode(['group_id' => $groupId, 'proposal_id' => $proposalId, 'incident_ids' => $snapshot['incident_ids'], 'previous_on_hand' => $lot['on_hand'], 'remaining_on_hand' => $movement['on_hand'], 'remaining_reserved' => $movement['reserved'], 'custody_hold_retained' => true], JSON_THROW_ON_ERROR), 'created_at' => now()]);
            }
            foreach ($snapshot['members'] as $member) {
                if ($member['status'] === 'unresolved') {
                    $ids = collect($snapshot['lines'])->where('incident_id', $member['id'])->pluck('allocation_id');
                    DB::table('pharmacy_ingredient_allocations')->whereIn('id', $ids)->update(['status' => 'incident_accounted', 'updated_at' => now()]);
                    DB::table('pharmacy_compounding_incidents')->where('id', $member['id'])->update(['status' => 'accounted_custody_held', 'version' => $member['version'] + 1]);
                }
                $batch = collect($snapshot['worksheets'])->firstWhere('id', $member['batch_id']);
                DB::table('pharmacy_compounding_events')->insert(['formulation_id' => $batch['formulation_id'], 'batch_id' => $batch['id'], 'actor_id' => $actor->id, 'action' => 'joint_accounting_applied', 'details' => json_encode(['group_id' => $groupId, 'proposal_id' => $proposalId, 'incident_id' => $member['id'], 'evidence' => $evidence, 'custody_hold_retained' => true, 'output_release_enabled' => false], JSON_THROW_ON_ERROR), 'created_at' => now()]);
            }
            DB::table('pharmacy_incident_group_proposals')->where('id', $proposalId)->update(['status' => 'applied', 'reviewed_by' => $actor->id, 'review_evidence' => $evidence, 'reviewed_at' => now()]);
            DB::table('pharmacy_incident_groups')->where('id', $groupId)->update(['status' => 'accounted_custody_held', 'version' => $group->version + 1, 'reviewed_by' => $actor->id, 'review_evidence' => $evidence, 'reviewed_at' => now()]);
        });
    }

    public function reject(User $actor, int $groupId, int $proposalId, string $evidence): void
    {
        abort_unless($actor->role === 'pharmacist' && $actor->organization_id, 403);
        validator(['evidence' => $evidence], ['evidence' => 'required|string|max:5000'])->validate();
        DB::transaction(function () use ($actor, $groupId, $proposalId, $evidence) {
            DB::table('organizations')->where('id', $actor->organization_id)->lockForUpdate()->first();
            $group = DB::table('pharmacy_incident_groups')->where('organization_id', $actor->organization_id)->where('id', $groupId)->lockForUpdate()->first();
            abort_unless($group, 404);
            app(PharmacyAccess::class)->requireLocation($actor, $group->location_id);
            $proposal = DB::table('pharmacy_incident_group_proposals')->where('group_id', $groupId)->where('id', $proposalId)->where('phase', 'accounting')->lockForUpdate()->first();
            abort_unless($proposal, 404);
            $members = DB::table('pharmacy_incident_group_members as m')->join('pharmacy_compounding_incidents as i', 'i.id', '=', 'm.incident_id')->where('m.group_id', $groupId)->orderBy('i.id')->get(['i.id', 'i.created_by', 'i.batch_id']);
            abort_if(in_array((int) $actor->id, [(int) $group->created_by, (int) $proposal->created_by], true) || $members->contains(fn ($member) => (int) $member->created_by === (int) $actor->id), 422, 'A different pharmacist must review joint accounting.');
            if ($proposal->status !== 'pending') {
                abort_unless($proposal->status === 'rejected' && (int) $proposal->reviewed_by === (int) $actor->id && $proposal->review_evidence === $evidence, 409, 'A different decision is already retained.');

                return;
            }
            abort_unless($group->status === 'pending', 409, 'The joint group is no longer pending.');
            DB::table('pharmacy_incident_group_proposals')->where('id', $proposalId)->update(['status' => 'rejected', 'reviewed_by' => $actor->id, 'review_evidence' => $evidence, 'reviewed_at' => now()]);
            DB::table('pharmacy_incident_groups')->where('id', $groupId)->update(['version' => $group->version + 1]);
            foreach ($members as $member) {
                $batch = DB::table('pharmacy_batch_worksheets')->find($member->batch_id);
                DB::table('pharmacy_compounding_events')->insert(['formulation_id' => $batch->formulation_id, 'batch_id' => $batch->id, 'actor_id' => $actor->id, 'action' => 'joint_accounting_rejected', 'details' => json_encode(['group_id' => $groupId, 'proposal_id' => $proposalId, 'incident_id' => $member->id, 'evidence' => $evidence, 'stock_adjusted' => false, 'custody_hold_retained' => true], JSON_THROW_ON_ERROR), 'created_at' => now()]);
            }
        });
    }

    /** Projects one atomic movement per receipt; retained custody stays reserved. */
    public function project(array $snapshot, array $inputs): array
    {
        $entries = $this->proposal($snapshot, $inputs);
        $totals = [];
        foreach ($entries as $entry) {
            $id = $entry['ingredient_lot_id'];
            $totals[$id] ??= ['deduct' => 0, 'original_reserved' => 0, 'new_unused' => 0, 'prior_unused' => 0];
            if ($entry['mode'] === 'already_accounted') {
                $totals[$id]['prior_unused'] += PharmacyStock::milli($entry['unused_retained']);

                continue;
            }
            $accounting = $entry['accounting'];
            if ($accounting['unaccounted_remaining']) {
                throw ValidationException::withMessages(['accounting' => 'All shared material must be accounted for before ledger application.']);
            }
            $q = $accounting['quantities'];
            $totals[$id]['deduct'] += PharmacyStock::milli($q['consumed']) + PharmacyStock::milli($q['disposed_unused']);
            $totals[$id]['original_reserved'] += PharmacyStock::milli($q['reserved_quantity']);
            $totals[$id]['new_unused'] += PharmacyStock::milli($q['unused_retained']);
        }
        $lots = collect($snapshot['lots'])->keyBy('id');
        if ($lots->count() !== count($snapshot['lots'])) {
            throw ValidationException::withMessages(['stock' => 'Duplicate ingredient receipt evidence.']);
        }
        ksort($totals, SORT_NUMERIC);
        $result = [];
        foreach ($totals as $id => $total) {
            $lot = $lots->get($id);
            if (! $lot) {
                throw ValidationException::withMessages(['stock' => 'Shared ingredient receipt evidence is missing.']);
            }
            $onHand = PharmacyStock::milli($lot['on_hand']);
            $reserved = PharmacyStock::milli($lot['reserved']);
            $remaining = $onHand - $total['deduct'];
            $held = $reserved - $total['original_reserved'] + $total['new_unused'];
            // Include prior unused custody in the coverage check, but never deduct it twice.
            if ($onHand < $reserved || $reserved < $total['original_reserved'] + $total['prior_unused'] || $remaining < 0 || $held < 0 || $remaining < $held) {
                throw ValidationException::withMessages(['stock' => 'Joint accounting would consume another reservation or create inconsistent stock.']);
            }
            $result[] = ['ingredient_lot_id' => $id, 'deduct' => PharmacyStock::decimal($total['deduct']), 'on_hand' => PharmacyStock::decimal($remaining), 'reserved' => PharmacyStock::decimal($held), 'status' => $lot['status'] === 'recalled' || ($lot['recall_reference'] ?? null) !== null ? 'recalled' : 'quarantined'];
        }

        return $result;
    }

    public function proposal(array $snapshot, array $inputs): array
    {
        $submitted = collect($inputs)->keyBy('allocation_id');
        $lines = collect($snapshot['lines']);
        if ($submitted->count() !== count($inputs) || $submitted->count() !== $lines->count() || ! $lines->every(fn ($line) => $submitted->has($line['allocation_id']))) {
            throw ValidationException::withMessages(['ingredients' => 'Provide each retained allocation exactly once.']);
        }
        $result = [];
        foreach ($lines as $line) {
            $member = collect($snapshot['members'])->firstWhere('id', $line['incident_id']);
            $input = $submitted[$line['allocation_id']];
            validator($input, ['evidence' => 'required|string|max:5000'])->validate();
            if ($member['status'] === 'unresolved') {
                $accounting = app(PharmacyIncidentAccounting::class)->line(array_replace($input, ['reserved_quantity' => $line['reserved_quantity']]));
                $result[] = ['incident_id' => $line['incident_id'], 'allocation_id' => $line['allocation_id'], 'ingredient_lot_id' => $line['ingredient_lot_id'], 'unit' => $line['quantity_unit'], 'mode' => 'new_accounting', 'accounting' => $accounting, 'evidence' => $input['evidence']];

                continue;
            }
            if ($member['status'] !== 'accounted_custody_held') {
                throw ValidationException::withMessages(['incident' => 'Unsupported incident accounting state.']);
            }
            $applied = collect($snapshot['accounting'])->where('incident_id', $member['id'])->where('status', 'applied')->values();
            if ($applied->count() !== 1) {
                throw ValidationException::withMessages(['incident' => 'Previously accounted incident requires one retained applied decision.']);
            }
            $record = $applied[0];
            $entries = json_decode($record['proposal'], true, 512, JSON_THROW_ON_ERROR);
            if (! hash_equals($record['proposal_hash'], app(PharmacyCompoundingIncident::class)->digest($entries))) {
                throw ValidationException::withMessages(['incident' => 'Prior accounting integrity failed.']);
            }
            $prior = collect($entries)->where('allocation_id', $line['allocation_id'])->values();
            if ($prior->count() !== 1 || (int) $prior[0]['ingredient_lot_id'] !== (int) $line['ingredient_lot_id'] || $prior[0]['unit'] !== $line['quantity_unit']) {
                throw ValidationException::withMessages(['incident' => 'Prior allocation identity is inconsistent.']);
            }
            // A matching digest proves retention, not quantity conservation or eligibility.
            $quantities = $prior[0]['accounting']['quantities'] ?? [];
            $validated = app(PharmacyIncidentAccounting::class)->line($quantities);
            if (PharmacyStock::milli($quantities['reserved_quantity']) !== PharmacyStock::milli($line['reserved_quantity']) || $validated['unaccounted_remaining']) {
                throw ValidationException::withMessages(['incident' => 'Prior accounting must preserve the original reservation and account for all material.']);
            }
            $unused = $validated['quantities']['unused_retained'];
            foreach (['additional_taken', 'consumed', 'disposed_unused', 'unaccounted'] as $key) {
                if (! array_key_exists($key, $input) || is_bool($input[$key]) || PharmacyStock::milli($input[$key]) !== 0) {
                    throw ValidationException::withMessages(['ingredients' => 'Previously applied consumption cannot be recorded again.']);
                }
            }
            if (! isset($input['unused_retained']) || is_bool($input['unused_retained']) || PharmacyStock::milli($input['unused_retained']) !== PharmacyStock::milli($unused)) {
                throw ValidationException::withMessages(['ingredients' => 'Preserve exactly the previously retained unused custody.']);
            }
            $result[] = ['incident_id' => $line['incident_id'], 'allocation_id' => $line['allocation_id'], 'ingredient_lot_id' => $line['ingredient_lot_id'], 'unit' => $line['quantity_unit'], 'mode' => 'already_accounted', 'reconciliation_id' => $record['id'], 'unused_retained' => PharmacyStock::decimal(PharmacyStock::milli($unused)), 'evidence' => $input['evidence']];
        }

        return $result;
    }
}
