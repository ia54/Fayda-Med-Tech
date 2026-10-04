<?php

namespace App\Services;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Validation\ValidationException;

/** Checks a pharmacist-supplied dating proposal; never selects clinical limits or authorizes release. */
class PharmacyBeyondUseEvidence
{
    public function inspect(array $input): array
    {
        $fields = ['prepared_at', 'proposed_bud_at', 'timezone', 'preparation_time_reference', 'container_reference',
            'storage_conditions', 'basis_reference', 'rationale', 'limits'];
        if (array_diff(array_keys($input), $fields) || array_diff($fields, array_keys($input))) {
            $this->fail('Retain all dating evidence fields and no additional authority fields.');
        }
        foreach (['preparation_time_reference', 'container_reference', 'storage_conditions', 'basis_reference', 'rationale'] as $key) {
            $this->text($input[$key]);
        }
        if (! is_string($input['timezone']) || ! in_array($input['timezone'], DateTimeZone::listIdentifiers(DateTimeZone::ALL_WITH_BC), true)) {
            $this->fail('Select an explicit IANA timezone.');
        }
        $zone = new DateTimeZone($input['timezone']);
        $prepared = $this->timestamp($input['prepared_at'], $zone);
        $bud = $this->timestamp($input['proposed_bud_at'], $zone);
        if ($bud <= $prepared) { $this->fail('The proposed beyond-use timestamp must follow preparation.'); }
        $limits = $input['limits'];
        if (! is_array($limits) || ! array_is_list($limits) || count($limits) < 1 || count($limits) > 100) {
            $this->fail('Retain the pharmacist-supplied limiting timestamps and their evidence.');
        }
        $keys = []; $earliest = null; $limitingKeys = [];
        foreach ($limits as $limit) {
            if (! is_array($limit) || array_diff(array_keys($limit), ['key', 'not_after', 'reference'])
                || array_diff(['key', 'not_after', 'reference'], array_keys($limit))) { $this->fail('Invalid limiting evidence.'); }
            $key = $limit['key'];
            if (! is_string($key) || ! preg_match('/^[A-Za-z][A-Za-z0-9_-]{0,39}$/D', $key) || isset($keys[$key])) {
                $this->fail('Each limiting factor needs a unique identifier.');
            }
            $keys[$key] = true; $this->text($limit['reference']);
            $at = $this->timestamp($limit['not_after'], $zone);
            if ($at <= $prepared || $bud > $at) { $this->fail('The proposal exceeds a supplied limit or the limit does not follow preparation.'); }
            if ($earliest === null || $at < $earliest) { $earliest = $at; $limitingKeys = [$key]; }
            elseif ($at == $earliest) { $limitingKeys[] = $key; }
        }
        return ['evidence' => $input, 'prepared_at_utc' => $prepared->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z'),
            'proposed_bud_at_utc' => $bud->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z'),
            'earliest_supplied_limit_utc' => $earliest->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z'),
            'limiting_keys' => $limitingKeys, 'requires_pharmacist_review' => true, 'clinical_limits_verified' => false,
            'output_status' => 'quarantined', 'release_enabled' => false];
    }

    private function timestamp(mixed $value, DateTimeZone $zone): DateTimeImmutable
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/D', $value)) {
            $this->fail('Use an exact timestamp including seconds and an explicit UTC offset.');
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:sP', $value);
        $errors = DateTimeImmutable::getLastErrors();
        if (! $date || ($errors && ($errors['warning_count'] || $errors['error_count'])) || $date->format('Y-m-d\TH:i:sP') !== $value
            || $date->setTimezone($zone)->format('Y-m-d\TH:i:sP') !== $value) {
            $this->fail('The timestamp is invalid or its offset does not match the timezone at that instant.');
        }
        return $date;
    }

    private function text(mixed $value): void
    {
        if (! is_string($value) || trim($value) === '' || strlen($value) > 5000) { $this->fail('Dating references and rationale must be explicit.'); }
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['beyond_use_evidence' => $message]);
    }
}
