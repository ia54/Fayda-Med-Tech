<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Picqer\Barcode\Renderers\SvgRenderer;
use Picqer\Barcode\Types\TypeCode128;

class PharmacyBarcode
{
    public function validPackageCode(string $code): bool
    {
        if (! preg_match('/^(?:[0-9]{8}|[0-9]{12}|[0-9]{13}|[0-9]{14})$/D', $code) || preg_match('/^0+$/D', $code)) { return false; }
        $sum = 0; $weight = 3;
        for ($i = strlen($code) - 2; $i >= 0; $i--) {
            $sum += ((int) $code[$i]) * $weight;
            $weight = $weight === 3 ? 1 : 3;
        }
        return (10 - $sum % 10) % 10 === (int) substr($code, -1);
    }

    public function svg(string $code): string
    {
        abort_unless(preg_match('/^FMTL-[A-Z0-9]{20}$/D', $code), 422, 'Unsupported internal label code.');
        $barcode = (new TypeCode128())->getBarcode($code);
        $renderer = new SvgRenderer();
        $renderer->setSvgType(SvgRenderer::TYPE_SVG_INLINE);
        return $renderer->render($barcode, $barcode->getWidth(), 50);
    }

    public function verify(array $input, object $rx, object $fill, object $label, string $action, int $actorId): array
    {
        $preparation = $action === 'ready';
        $data = Validator::make(['scan' => $input], [
            'scan' => 'required|array:label_code,package_code,lot_number,input_method,manual_reason,confirmed',
            'scan.label_code' => 'required|string|max:64',
            'scan.package_code' => $preparation ? 'required|string|max:14' : 'prohibited',
            'scan.lot_number' => $preparation ? 'required|string|max:100' : 'prohibited',
            'scan.input_method' => 'required|in:scanner,manual',
            'scan.manual_reason' => 'nullable|required_if:scan.input_method,manual|string|max:2000',
            'scan.confirmed' => 'required|accepted',
        ])->validate()['scan'];
        abort_unless($label->barcode_code && hash_equals($label->barcode_code, $data['label_code']), 422, 'Label code does not match the current retained label. Stop and check the package.');
        if ($preparation) {
            $stock = DB::table('pharmacy_stock_lots')->where('id', $fill->stock_lot_id)->where('organization_id', $rx->organization_id)->where('location_id', $rx->location_id)->first();
            $product = app(PharmacyProduct::class)->current((int) $fill->stock_lot_id);
            abort_unless($stock && $product && $product->package_code && $this->validPackageCode($product->package_code), 422, 'Verify the source package barcode before preparation.');
            abort_unless(hash_equals($product->package_code, $data['package_code']), 422, 'Package code does not match the pharmacist-verified product. Stop and check the source package.');
            abort_unless(hash_equals($stock->lot_number, $data['lot_number']), 422, 'Lot does not match the reserved receipt. Stop and check the source package.');
            $data['product_id'] = $product->id;
            $data['stock_lot_id'] = $stock->id;
        } else {
            $prior = is_array($fill->fulfillment) ? $fill->fulfillment : json_decode($fill->fulfillment ?: '{}', true, 512, JSON_THROW_ON_ERROR);
            abort_unless(($prior['prepared_scan']['label_id'] ?? null) === $label->id && ($prior['prepared_scan']['label_code'] ?? null) === $label->barcode_code, 422, 'Preparation has no matching retained code check. Cancel this open fill and prepare a new one.');
        }
        if ($data['input_method'] === 'scanner') {
            abort_if(! empty($data['manual_reason']), 422, 'Select manual entry when recording a manual-entry reason.');
        }
        unset($data['confirmed']);
        return $data + ['label_id' => $label->id, 'label_sha256' => $label->sha256, 'actor_id' => $actorId, 'recorded_at' => now()->toIso8601String()];
    }
}
