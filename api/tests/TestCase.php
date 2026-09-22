<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();
        \Illuminate\Support\Facades\Schema::useNativeSchemaOperationsIfPossible();
        // Public-only fixture. Its private key is discarded; no deployment
        // signing keys are read and this fixture cannot mint access tokens.
        config(['passport.public_key' => file_get_contents(__DIR__ . '/Fixtures/passport-public.pem')]);
    }
}
