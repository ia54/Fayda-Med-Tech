<?php

namespace Tests\Unit;

use App\Services\PharmacyBeyondUseEvidence;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PharmacyBeyondUseEvidenceTest extends TestCase
{
    private function proposal(): array
    {
        return ['prepared_at' => '2026-10-04T09:00:00-04:00', 'proposed_bud_at' => '2026-10-05T09:00:00-04:00',
            'timezone' => 'America/Detroit', 'preparation_time_reference' => 'SYNTHETIC clock evidence',
            'container_reference' => 'SYNTHETIC container', 'storage_conditions' => 'SYNTHETIC storage',
            'basis_reference' => 'SYNTHETIC no clinical duration', 'rationale' => 'SYNTHETIC boundary test',
            'limits' => [['key' => 'fixture', 'not_after' => '2026-10-05T09:00:00-04:00', 'reference' => 'SYNTHETIC limit']]];
    }

    public function test_explicit_proposal_preserves_evidence_without_clinical_authority(): void
    {
        $p = $this->proposal(); $r = (new PharmacyBeyondUseEvidence)->inspect($p);
        $this->assertSame($p, $r['evidence']);
        $this->assertSame('2026-10-05T13:00:00Z', $r['proposed_bud_at_utc']);
        $this->assertSame(['fixture'], $r['limiting_keys']);
        $this->assertFalse($r['clinical_limits_verified']); $this->assertFalse($r['release_enabled']);
        $this->assertTrue($r['requires_pharmacist_review']);
    }

    public function test_invalid_dates_offsets_missing_limits_and_extensions_fail(): void
    {
        $cases = [];
        foreach (['2026-10-05', '2026-02-30T09:00:00-05:00', '2026-10-05T09:00:00-05:00',
            '2026-10-04T09:00:00-04:00', '2026-10-05T09:00:01-04:00'] as $v) { $cases[] = ['proposed_bud_at' => $v]; }
        $cases[] = ['timezone' => '-04:00']; $cases[] = ['limits' => []]; $cases[] = ['rationale' => ' ']; $cases[] = ['release_enabled' => true];
        $p = $this->proposal(); $cases[] = ['limits' => [$p['limits'][0], $p['limits'][0]]];
        foreach ($cases as $change) {
            try { (new PharmacyBeyondUseEvidence)->inspect(array_replace($p, $change)); $this->fail('Invalid dating evidence accepted'); }
            catch (ValidationException $e) { $this->assertArrayHasKey('beyond_use_evidence', $e->errors()); }
        }
    }

    public function test_dst_overlap_uses_explicit_offset_and_gap_is_rejected(): void
    {
        $p = $this->proposal(); $p['prepared_at'] = '2026-11-01T01:30:00-04:00'; $p['proposed_bud_at'] = '2026-11-01T01:30:00-05:00';
        $p['limits'][0]['not_after'] = $p['proposed_bud_at'];
        $r = (new PharmacyBeyondUseEvidence)->inspect($p);
        $this->assertSame('2026-11-01T05:30:00Z', $r['prepared_at_utc']);
        $this->assertSame('2026-11-01T06:30:00Z', $r['proposed_bud_at_utc']);
        $p['prepared_at'] = '2026-03-08T02:30:00-05:00';
        $this->expectException(ValidationException::class); (new PharmacyBeyondUseEvidence)->inspect($p);
    }
}
