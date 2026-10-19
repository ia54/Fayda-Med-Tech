<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],
    
    'passport' => [
        'client_id' => env('PASSPORT_PASSWORD_GRANT_ID'),
        'client_secret' => env('PASSPORT_PASSWORD_GRANT_SECRET'),
    ],

    'docusign' => [
        'base_uri' => env('DOCUSIGN_BASE_URI', 'https://demo.docusign.net'),
        'account_id' => env('DOCUSIGN_ACCOUNT_ID'),
        'client_id' => env('DOCUSIGN_CLIENT_ID'),
        'impersonated_user_id' => env('DOCUSIGN_IMPERSONATED_USER_ID'),
        'private_key_path' => env('DOCUSIGN_PRIVATE_KEY_PATH'),
        'private_key' => env('DOCUSIGN_PRIVATE_KEY'),
        'private_key_b64' => env('DOCUSIGN_PRIVATE_KEY_B64'),
        'webhook_secret' => env('DOCUSIGN_WEBHOOK_SECRET'),
    ],

    'google_vision' => [
        'api_key' => env('GOOGLE_VISION_API_KEY'),
    ],

];
