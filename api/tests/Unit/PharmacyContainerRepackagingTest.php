<?php

namespace Tests\Unit;

use App\Services\PharmacyContainerRepackaging;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PharmacyContainerRepackagingTest extends TestCase
{
    private function source(): array
    {
        return ['recorded_yield' => '7', 'previously_disposed' => '3', 'held_output' => '4', 'unit' => 'g',
            'containers' => [['identifier' => 'SYN-A', 'quantity' => '2', 'container_reference' => 'SYNTHETIC original container', 'storage_reference' => 'SYNTHETIC original storage']],
            'unpackaged_quantity' => '2'];
    }
    private function input(): array
    {
        return ['unit' => 'g', 'new_containers' => [['identifier' => 'SYN-B', 'container_reference' => 'SYNTHETIC replacement', 'storage_reference' => 'SYNTHETIC storage']],
            'transfers' => [['from_identifier' => 'SYN-A', 'to_identifier' => 'SYN-B', 'quantity' => '1.125', 'evidence' => 'SYNTHETIC measured movement'],
                ['from_identifier' => null, 'to_identifier' => 'SYN-B', 'quantity' => '0.875', 'evidence' => 'SYNTHETIC measured remainder']],
            'observed_containers' => [['identifier' => 'SYN-A', 'quantity' => '0.875', 'evidence' => 'SYNTHETIC final A'], ['identifier' => 'SYN-B', 'quantity' => '2', 'evidence' => 'SYNTHETIC final B']],
            'observed_unpackaged' => ['quantity' => '1.125', 'evidence' => 'SYNTHETIC final remainder'],
            'process_evidence' => 'SYNTHETIC process only', 'reconciliation_evidence' => 'SYNTHETIC measured reconciliation'];
    }
    public function test_explicit_lineage_preserves_corrected_yield_prior_disposal_and_quarantine(): void
    {
        $p = (new PharmacyContainerRepackaging)->project($this->source(), $this->input());
        $this->assertSame('7.000', $p['recorded_yield']); $this->assertSame('3.000', $p['previously_disposed']);
        $this->assertSame('4.000', $p['held_output']); $this->assertSame('0.875', $p['containers'][0]['quantity']);
        $this->assertSame('SYNTHETIC original container', $p['containers'][0]['container_reference']);
        $this->assertFalse($p['containers'][0]['new_identity']); $this->assertTrue($p['containers'][1]['new_identity']);
        $this->assertSame('2.000', $p['containers'][1]['incoming_quantity']); $this->assertSame('0.000', $p['containers'][1]['previous_quantity']);
        $this->assertSame('1.125', $p['unpackaged']['quantity']); $this->assertSame('quarantined', $p['containers'][1]['status']);
        $this->assertFalse($p['release_enabled']); $this->assertFalse($p['clinical_eligibility_verified']);
        $this->assertSame('0.000', $p['ingredient_stock_delta']); $this->assertSame('0.000', $p['output_stock_delta']);
        $input = $this->input(); $input['observed_containers'] = array_reverse($input['observed_containers']);
        $this->assertSame($p, (new PharmacyContainerRepackaging)->project($this->source(), $input));
    }
    public function test_empty_original_container_identity_is_preserved(): void
    {
        $input = $this->input(); $input['transfers'][0]['quantity'] = '2';
        $input['observed_containers'][0]['quantity'] = '0'; $input['observed_containers'][1]['quantity'] = '2.875';
        $p = (new PharmacyContainerRepackaging)->project($this->source(), $input);
        $this->assertSame('SYN-A', $p['containers'][0]['identifier']); $this->assertSame('0.000', $p['containers'][0]['quantity']);
        $this->assertSame('2.875', $p['containers'][1]['quantity']); $this->assertSame('3.000', $p['previously_disposed']);
    }
    public function test_return_to_unpackaged_remainder_is_explicit_without_new_identity(): void
    {
        $input = $this->input(); $input['new_containers'] = [];
        $input['transfers'] = [['from_identifier' => 'SYN-A', 'to_identifier' => null, 'quantity' => '1', 'evidence' => 'SYNTHETIC return to remainder']];
        $input['observed_containers'] = [['identifier' => 'SYN-A', 'quantity' => '1', 'evidence' => 'SYNTHETIC final A']];
        $input['observed_unpackaged']['quantity'] = '3';
        $p = (new PharmacyContainerRepackaging)->project($this->source(), $input);
        $this->assertCount(1, $p['containers']); $this->assertSame('3.000', $p['unpackaged']['quantity']);
        $this->assertSame('1.000', $p['unpackaged']['incoming_quantity']);
    }
    public function test_overdraft_offsets_missing_observations_and_authority_are_rejected(): void
    {
        foreach (['overdraw', 'cross_offset', 'missing', 'duplicate', 'unknown', 'reuse', 'self', 'zero', 'float', 'precision', 'unit', 'evidence', 'source', 'authority', 'relay'] as $case) {
            $source = $this->source(); $input = $this->input();
            if ($case === 'overdraw') { $input['transfers'][0]['quantity'] = '3'; }
            if ($case === 'cross_offset') { $input['observed_containers'][0]['quantity'] = '1'; $input['observed_containers'][1]['quantity'] = '1.875'; }
            if ($case === 'missing') { unset($input['observed_unpackaged']['quantity']); }
            if ($case === 'duplicate') { $input['observed_containers'][1]['identifier'] = 'SYN-A'; }
            if ($case === 'unknown') { $input['transfers'][0]['from_identifier'] = 'SYN-OTHER'; }
            if ($case === 'reuse') { $input['new_containers'][0]['identifier'] = 'SYN-A'; }
            if ($case === 'self') { $input['transfers'][0]['to_identifier'] = 'SYN-A'; }
            if ($case === 'zero') { $input['transfers'][0]['quantity'] = '0'; }
            if ($case === 'float') { $input['transfers'][0]['quantity'] = 1.125; }
            if ($case === 'precision') { $input['transfers'][0]['quantity'] = '1.1251'; }
            if ($case === 'unit') { $input['unit'] = 'mg'; }
            if ($case === 'evidence') { $input['observed_containers'][0]['evidence'] = ' '; }
            if ($case === 'source') { $source['held_output'] = '5'; }
            if ($case === 'authority') { $input['release_enabled'] = true; }
            if ($case === 'relay') { $input['transfers'][] = ['from_identifier' => 'SYN-B', 'to_identifier' => 'SYN-A', 'quantity' => '1', 'evidence' => 'SYNTHETIC unheld source']; }
            try { (new PharmacyContainerRepackaging)->project($source, $input); $this->fail('Invalid repackaging accepted: '.$case); }
            catch (ValidationException $e) { $this->assertNotEmpty($e->errors()); }
        }
    }
}
