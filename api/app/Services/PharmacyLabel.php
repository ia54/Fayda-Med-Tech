<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class PharmacyLabel
{
    public function current(int $fillId): ?object
    {
        return DB::table('pharmacy_fill_labels')->where('fill_id', $fillId)->orderByDesc('revision')->first();
    }

    public function context(object $rx, object $fill): array
    {
        $episode = DB::table('pharmacy_episodes')->where('id', $rx->episode_id)->first();
        $patient = $episode->pharmacy_patient_id
            ? DB::table('pharmacy_patients')->where('organization_id', $rx->organization_id)->where('id', $episode->pharmacy_patient_id)->first(['first_name', 'last_name', 'version'])
            : DB::table('users')->where('organization_id', $rx->organization_id)->where('id', $episode->patient_id)->first(['first_name', 'last_name']);
        $stock = DB::table('pharmacy_stock_lots')->where('organization_id', $rx->organization_id)->where('location_id', $rx->location_id)->where('id', $fill->stock_lot_id)->first();
        $location = DB::table('pharmacy_locations')->where('organization_id', $rx->organization_id)->where('id', $rx->location_id)->first(['id', 'name', 'address']);
        return [
            'prescription' => ['id' => $rx->id, 'rx_number' => $rx->rx_number, 'medication' => $rx->medication, 'strength' => $rx->strength, 'dosage_form' => $rx->dosage_form, 'directions' => $rx->directions, 'prescriber_name' => $rx->prescriber_name, 'written_on' => $rx->written_on, 'expires_on' => $rx->expires_on],
            'fill' => ['id' => $fill->id, 'fill_number' => $fill->fill_number, 'quantity' => PharmacyStock::decimal(PharmacyStock::milli($fill->quantity)), 'quantity_unit' => $rx->quantity_unit, 'ndc' => $fill->ndc],
            'location' => $location, 'patient' => $patient,
            'stock' => $stock ? ['id' => $stock->id, 'ndc' => $stock->ndc, 'lot_number' => $stock->lot_number, 'expires_on' => $stock->expires_on] : null,
            'product' => $stock ? app(PharmacyProduct::class)->current((int) $stock->id) : null,
            'review' => is_array($fill->review) ? $fill->review : json_decode($fill->review ?: '{}', true, 512, JSON_THROW_ON_ERROR),
            'source_last_id' => DB::table('pharmacy_source_documents')->where('prescription_id', $rx->id)->max('id') ?? 0,
        ];
    }

    public function token(array $context): string
    {
        return hash('sha256', json_encode($context, JSON_THROW_ON_ERROR));
    }

    public function intact(object $label): bool
    {
        return hash_equals($label->sha256, hash('sha256', $label->document))
            && hash_equals($label->snapshot_sha256, hash('sha256', $label->snapshot))
            && (json_decode($label->snapshot, true, 512, JSON_THROW_ON_ERROR)['barcode_code'] ?? null) === $label->barcode_code;
    }

    public function summary(object $rx, object $fill): ?array
    {
        $label = $this->current((int) $fill->id);
        if (! $label) { return null; }
        $intact = $this->intact($label);
        $data = $intact ? json_decode($label->snapshot, true, 512, JSON_THROW_ON_ERROR) : [];
        return ['id' => $label->id, 'revision' => $label->revision, 'sha256' => $label->sha256, 'intact' => $intact,
            'fresh' => $intact && hash_equals($label->source_token, $this->token($this->context($rx, $fill))),
            'dispensed_on' => $data['decisions']['dispensed_on'] ?? null, 'use_by' => $data['decisions']['use_by'] ?? null,
            'barcode_supported' => (bool) $label->barcode_code, 'print_count' => DB::table('pharmacy_label_prints')->where('label_id', $label->id)->count()];
    }

    public function requireCurrent(object $rx, object $fill, int $labelId): object
    {
        $label = $this->current((int) $fill->id);
        abort_unless($label && (int) $label->id === $labelId, 422, 'Select the latest retained label.');
        abort_unless($this->intact($label), 409, 'The retained label failed its integrity check. Investigate before use.');
        abort_unless(hash_equals($label->source_token, $this->token($this->context($rx, $fill))), 422, 'Label source information changed. Review the fill and retain a new label; cancel a prepared fill and start again.');
        $snapshot = json_decode($label->snapshot, true, 512, JSON_THROW_ON_ERROR);
        abort_if($snapshot['decisions']['use_by'] < now()->toDateString(), 422, 'The label use-by date has passed.');
        return $label;
    }

    public function assertCanLabel(object $rx, object $fill): array
    {
        abort_if($rx->controlled || $rx->compounded || $rx->discontinued_at || $rx->expires_on < now()->toDateString(), 422, 'This prescription is not available for general dispensing labels.');
        abort_unless(in_array($fill->fulfillment_status, ['pending', 'ready'], true) && $fill->review_status === 'approved', 422, 'An open fill with pharmacist approval is required.');
        $context = $this->context($rx, $fill);
        $product = $context['product'];
        abort_unless($product && $product->package_code && app(PharmacyBarcode::class)->validPackageCode($product->package_code), 422, 'Verify the exact source-package GTIN before issuing a new label.');
        abort_unless($product && (int) ($context['review']['product_id'] ?? 0) === (int) $product->id, 422, 'Verify the current product and review this fill first.');
        abort_unless((int) ($context['review']['patient_version'] ?? 0) === (int) ($context['patient']->version ?? 0)
            && (int) ($context['review']['source_last_id'] ?? 0) === (int) $context['source_last_id'], 422, 'Patient or prescription evidence needs a fresh pharmacist review.');
        $stock = DB::table('pharmacy_stock_lots')->where('organization_id', $rx->organization_id)->where('location_id', $rx->location_id)->where('id', $fill->stock_lot_id)->first();
        abort_unless($stock, 404);
        app(PharmacyStock::class)->assertUsable($stock);
        return $context;
    }

    public function document(array $snapshot, int $revision): string
    {
        return view('pharmacy.label-proof', ['s' => $snapshot, 'revision' => $revision])->render();
    }
}
