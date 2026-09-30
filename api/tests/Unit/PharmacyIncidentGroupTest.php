<?php

namespace Tests\Unit;

use App\Services\PharmacyIncidentGroup;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PharmacyIncidentGroupTest extends TestCase
{
    public function test_complete_transitive_group_is_discovered_from_each_seed(): void
    {
        $incidents = array_map(fn ($id) => ['id' => $id, 'organization_id' => 1, 'location_id' => 1, 'status' => 'unresolved'], [1, 2, 3, 4, 5]);
        $incidents[4]['status'] = 'reconciled';
        $lines = array_map(fn ($pair) => ['incident_id' => $pair[0], 'ingredient_lot_id' => $pair[1]], [[1, 10], [1, 11], [2, 11], [2, 12], [3, 12], [4, 99], [5, 12], [5, 99]]);
        foreach ([1, 2, 3] as $seed) {
            $result = (new PharmacyIncidentGroup)->connected($seed, $incidents, $lines);
            $this->assertSame([1, 2, 3], $result['incident_ids']);
            $this->assertSame([10, 11, 12], $result['ingredient_lot_ids']);
        }
        $this->assertSame([4], (new PharmacyIncidentGroup)->connected(4, $incidents, $lines)['incident_ids']);
    }

    public function test_missing_evidence_and_connected_cross_scope_fail_closed(): void
    {
        $base = ['id' => 1, 'organization_id' => 1, 'location_id' => 1, 'status' => 'unresolved'];
        $lines = [['incident_id' => 1, 'ingredient_lot_id' => 10], ['incident_id' => 2, 'ingredient_lot_id' => 10]];
        foreach ([[[$base], []], [[$base, array_replace($base, ['id' => 2, 'organization_id' => 2])], $lines], [[$base, array_replace($base, ['id' => 2, 'location_id' => 2])], $lines], [[$base, $base], $lines], [[array_replace($base, ['status' => 'reconciled'])], $lines]] as [$incidents, $input]) {
            try {
                (new PharmacyIncidentGroup)->connected(1, $incidents, $input);
                $this->fail('Incomplete or inconsistent group accepted');
            } catch (ValidationException $e) {
                $this->assertNotEmpty($e->errors());
            }
        }
    }
}
