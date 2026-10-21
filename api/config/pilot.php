<?php

return [
    // Does not open PharmacyPreviewOnly or authorize patient data / clinical release.
    'synthetic' => env('SYNTHETIC_PILOT', false),
];
