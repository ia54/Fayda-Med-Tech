<?php

return [
    // Does not open PharmacyPreviewOnly or authorize patient data / clinical release.
    'synthetic' => env('SYNTHETIC_PILOT', false),
    // Separate opt-in; still requires the verified gateway and isolated database.
    'pharmacy_preview' => env('SYNTHETIC_PHARMACY_PREVIEW', false),
    'email_login' => env('PILOT_EMAIL_LOGIN', false),
    'login_email' => env('PILOT_LOGIN_EMAIL'),
    'login_from' => env('PILOT_LOGIN_FROM'),
];
