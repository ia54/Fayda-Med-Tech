<?php

namespace App\Support;

/** Explicit exception for the gateway-authenticated, isolated synthetic pilot. */
final class HostedPharmacyPreview
{
    public static function allows(array $state): bool
    {
        return ($state['environment'] ?? null) === 'production'
            && ($state['synthetic'] ?? null) === true
            && ($state['enabled'] ?? null) === true
            && ($state['gateway_verified'] ?? null) === true
            && ($state['debug'] ?? null) === false
            && ($state['connection'] ?? null) === 'mysql'
            && ($state['host'] ?? null) === 'localhost'
            && ($state['database'] ?? null) === 'faydamed_pilot'
            && ($state['url'] ?? null) === 'https://pilot-api.faydamed.tech'
            && ($state['mail'] ?? null) === 'array'
            && ($state['queue'] ?? null) === 'sync'
            && ($state['broadcast'] ?? null) === 'null';
    }
}
