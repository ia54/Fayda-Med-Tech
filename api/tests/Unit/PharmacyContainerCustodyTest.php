<?php

namespace Tests\Unit;

use App\Services\PharmacyContainerCustody;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PharmacyContainerCustodyTest extends TestCase
{
    private function source(): array
    {
        return ['unit' => 'g', 'recorded_yield' => '8.000', 'previously_disposed' => '2.000', 'held_output' => '6.000',
            'containers' => [['identifier' => 'SYN-A', 'quantity' => '2.125'], ['identifier' => 'SYN-B', 'quantity' => '2.875']], 'unpackaged_quantity' => '1.000'];
    }

    private function finding(string $retained, string $disposed, string $missing): array
    {
        return ['retained_quarantined' => $retained, 'disposed_output' => $disposed, 'unaccounted_output' => $missing, 'evidence' => 'SYNTHETIC observed custody'];
    }

    private function decision(): array
    {
        return ['unit' => 'g', 'containers' => [['identifier' => 'SYN-A'] + $this->finding('1.000', '1.000', '0.125'),
            ['identifier' => 'SYN-B'] + $this->finding('2.875', '0', '0')], 'unpackaged' => $this->finding('0.500', '0.500', '0'), 'evidence' => 'SYNTHETIC aggregate reconciliation'];
    }

    public function test_fractional_custody_preserves_identity_prior_disposal_and_unresolved_loss(): void
    {
        $service = new PharmacyContainerCustody;
        $source = $this->source(); $decision = $this->decision();
        $result = $service->project($source, $decision);
        $this->assertSame('4.375', $result['retained_quarantined']); $this->assertSame('1.500', $result['disposed_output']);
        $this->assertSame('3.500', $result['total_disposed']); $this->assertSame('0.125', $result['unaccounted_output']);
        $this->assertFalse($result['accounting_complete']); $this->assertFalse($result['release_enabled']);
        $this->assertSame('0.000', $result['ingredient_stock_delta']); $this->assertSame('SYN-A', $result['containers'][0]['identifier']);
        $decision['containers'] = array_reverse($decision['containers']);
        $this->assertSame($result, $service->project($source, $decision));
        $decision['containers'][1]['retained_quarantined'] = '1.125'; $decision['containers'][1]['unaccounted_output'] = '0';
        $complete = $service->project($source, $decision);
        $this->assertTrue($complete['accounting_complete']); $this->assertFalse($complete['release_enabled']);
    }

    public function test_aggregate_balance_cannot_hide_container_loss_or_missing_evidence(): void
    {
        foreach (['shift', 'duplicate', 'unknown', 'omitted', 'missing_zero', 'float', 'precision', 'authority', 'unit', 'evidence', 'source_balance', 'prior_disposal'] as $case) {
            $source = $this->source(); $d = $this->decision();
            if ($case === 'shift') { $d['containers'][0]['retained_quarantined'] = '2.000'; $d['containers'][1]['retained_quarantined'] = '1.875'; }
            if ($case === 'duplicate') { $d['containers'][1]['identifier'] = 'SYN-A'; }
            if ($case === 'unknown') { $d['containers'][1]['identifier'] = 'UNKNOWN'; }
            if ($case === 'omitted') { array_pop($d['containers']); }
            if ($case === 'missing_zero') { unset($d['unpackaged']['unaccounted_output']); }
            if ($case === 'float') { $d['containers'][0]['retained_quarantined'] = 1.0; }
            if ($case === 'precision') { $d['containers'][0]['retained_quarantined'] = '1.0001'; }
            if ($case === 'authority') { $d['release_enabled'] = true; }
            if ($case === 'unit') { $d['unit'] = 'mg'; }
            if ($case === 'evidence') { $d['unpackaged']['evidence'] = ' '; }
            if ($case === 'source_balance') { $source['held_output'] = '7'; }
            if ($case === 'prior_disposal') { $source['previously_disposed'] = '0'; }
            try { (new PharmacyContainerCustody)->project($source, $d); $this->fail('Invalid custody accepted: '.$case); }
            catch (ValidationException $e) { $this->assertNotEmpty($e->errors()); }
        }
    }
}
