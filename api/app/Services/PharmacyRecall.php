<?php
namespace App\Services;
use Illuminate\Support\Facades\DB;

/** Explicit notice identifiers only. No inferred conversion of ambiguous NDCs. */
class PharmacyRecall
{
    public static function keys(string $ndc, string $lot): array
    {
        return ['recall_ndc_key' => str_replace('-', '', $ndc), 'recall_lot_key' => hash('sha256', strtoupper(trim($lot)))];
    }

    public function notices($lot)
    {
        $keys = self::keys($lot->ndc, $lot->lot_number);
        return DB::table('pharmacy_recall_notices as n')->where('n.organization_id', $lot->organization_id)->whereNull('n.withdrawn_at')
            ->whereIn('n.id', DB::table('pharmacy_recall_codes')->select('notice_id')->where('ndc_key', $keys['recall_ndc_key']))
            ->where(fn ($q) => $q->where('n.all_lots', true)->orWhere('n.lot_key', $keys['recall_lot_key']));
    }

    public function held($lot): bool { return $this->notices($lot)->exists(); }

    public function matchNotice($query, $notice, string $alias = 'stock')
    {
        $query->where($alias.'.organization_id', $notice->organization_id)
            ->whereIn($alias.'.recall_ndc_key', DB::table('pharmacy_recall_codes')->select('ndc_key')->where('notice_id', $notice->id));
        if (! $notice->all_lots) { $query->where($alias.'.recall_lot_key', $notice->lot_key); }
        return $query;
    }
}
