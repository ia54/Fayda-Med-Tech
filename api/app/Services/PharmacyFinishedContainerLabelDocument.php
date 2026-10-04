<?php
namespace App\Services;

class PharmacyFinishedContainerLabelDocument
{
    public function render(array $source, int $revision): string
    {
        abort_unless(app()->environment(['local', 'testing']), 503);
        abort_unless($revision > 0 && ($source['synthetic_only'] ?? null) === true && ($source['release_enabled'] ?? null) === false, 422, 'Only synthetic unreleased proofs are supported.');
        return view('pharmacy.finished-container-proof', ['s' => $source, 'revision' => $revision])->render();
    }
}
