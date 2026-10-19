<?php

namespace App\Services;

use Illuminate\Support\Facades\Validator;

/** Retains a pharmacist's factual handover checks; does not verify identity externally. */
class PharmacyHandover
{
    public function evidence(array $input, string $action, string $occurredOn, array $prepared, int $actorId): array
    {
        $delivery = $action === 'delivered';
        $data = Validator::make(['handover' => $input], [
            'handover' => 'required|array:recipient_type,recipient_name,identity_reference,relationship,authority_reference,counseling_reference,delivery_method,delivery_reference,confirmed',
            'handover.recipient_type' => 'required|in:patient,representative',
            'handover.recipient_name' => 'required|string|max:255',
            'handover.identity_reference' => 'required|string|max:2000',
            'handover.relationship' => 'nullable|required_if:handover.recipient_type,representative|string|max:255',
            'handover.authority_reference' => 'nullable|required_if:handover.recipient_type,representative|string|max:2000',
            'handover.counseling_reference' => 'required|string|max:2000',
            'handover.delivery_method' => $delivery ? 'required|in:pharmacy_staff,tracked_carrier' : 'prohibited',
            'handover.delivery_reference' => $delivery ? 'required|string|max:2000' : 'prohibited',
            'handover.confirmed' => 'required|accepted',
        ])->validate()['handover'];
        abort_unless(! empty($prepared['prepared_at']), 422, 'Final preparation evidence is missing. Cancel this open fill and prepare a new one.');
        abort_if($occurredOn < substr($prepared['prepared_at'], 0, 10), 422, 'Handover cannot predate final preparation.');
        if ($data['recipient_type'] === 'patient') {
            abort_if(! empty($data['relationship']) || ! empty($data['authority_reference']), 422, 'Record representative authority only when a representative received the medication.');
        }
        unset($data['confirmed']);
        return $data + ['format_version' => 1, 'recorded_by' => $actorId, 'recorded_at' => now()->toIso8601String()];
    }
}
