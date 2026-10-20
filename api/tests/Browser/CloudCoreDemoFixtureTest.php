<?php
namespace Tests\Browser;

use Illuminate\Support\Facades\DB;
use Tests\Feature\PharmacyWorkflowTest;

/** Opt-in synthetic owner-demo records, built through the existing request fixtures. */
class CloudCoreDemoFixtureTest extends CloudPilotFixtureTest
{
    protected function prepareSharedBrowserEvidence(array $evidence): array
    {
        $evidence = parent::prepareSharedBrowserEvidence($evidence);
        $call = fn (string $name, array $arguments = []) => (new \ReflectionMethod(PharmacyWorkflowTest::class, $name))->invokeArgs($this, $arguments);
        $rx = $call('rx', [['rx_number' => 'SYN-CORE-ORDINARY']]);
        $lot = $call('lot');
        $fill = $call('fill', [$rx, $lot]);
        $fill = $call('complete', [$rx, $fill]);
        $this->assertSame('collected', $fill['fulfillment_status']);
        $this->assertEquals(40, DB::table('pharmacy_stock_lots')->where('id', $lot)->value('on_hand'));
        $this->assertEquals(0, DB::table('pharmacy_stock_lots')->where('id', $lot)->value('reserved'));
        $this->assertSame(0, DB::table('payments')->count());
        $evidence['ordinary_pharmacy'] = ['prescription_id' => $rx, 'stock_lot_id' => $lot, 'fill_id' => $fill['id'],
            'state' => 'seeded synthetic collected record', 'stock_on_hand' => 40, 'reserved' => 0,
            'billing_link_demonstrated' => false, 'real_dispensing' => false];
        return $evidence;
    }
}
