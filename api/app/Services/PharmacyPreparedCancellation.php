<?php

namespace App\Services;

use Illuminate\Support\Facades\Validator;

/** Cancellation accounts for retained preparation; it is not a patient-return workflow. */
class PharmacyPreparedCancellation
{
    public function evidence(array $input, object $fill, int $actorId): array
    {
        $data = Validator::make(['cancellation' => $input], [
            'cancellation' => 'required|array:custody,quantity,evidence,segregation_reference,confirmed',
            'cancellation.custody' => 'required|in:never_left_pharmacy',
            'cancellation.quantity' => 'required|numeric|min:0.001|max:999999.999|decimal:0,3',
            'cancellation.evidence' => 'required|string|max:2000',
            'cancellation.segregation_reference' => 'required|string|max:2000',
            'cancellation.confirmed' => 'required|accepted',
        ])->validate()['cancellation'];
        abort_unless(PharmacyStock::milli($data['quantity']) === PharmacyStock::milli($fill->quantity), 422,
            'Account for the entire prepared quantity. Missing, dispatched or returned medication requires a separate custody resolution; its reservation cannot be released here.');
        unset($data['confirmed']);
        $data['quantity'] = PharmacyStock::decimal(PharmacyStock::milli($data['quantity']));

        return $data + ['format_version' => 1, 'recorded_by' => $actorId, 'recorded_at' => now()->toIso8601String(),
            'stock_receipt_id' => (int) $fill->stock_lot_id, 'stock_release_authorized' => false];
    }
}
