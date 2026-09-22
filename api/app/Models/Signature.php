<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use App\Traits\BelongsToTenant;

class Signature extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'organization_id',
        'document_id',
        'document_signer_id',
        'provider',
        'provider_envelope_id',
        'provider_event',
        'status',
        'signed_file_path',
        'signed_file_url',
        'provider_payload',
        'processed_at',
    ];

    protected $hidden = ['signed_file_path', 'signed_file_url', 'provider_payload'];

    protected $casts = [
        'provider_payload' => 'array',
        'processed_at' => 'datetime',
    ];

    public function document()
    {
        return $this->belongsTo(Document::class);
    }

    public function documentSigner()
    {
        return $this->belongsTo(DocumentSigner::class);
    }
}
