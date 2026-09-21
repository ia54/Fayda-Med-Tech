<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use App\Traits\BelongsToTenant;

class Document extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'organization_id',
        'uploaded_by',
        'title',
        'original_name',
        'filename',
        'mime_type',
        'size',
        'path',
        'url',
        'document_status',
        'signature_status',
        'ocr_status',
        'docusign_envelope_id',
        'sent_at',
        'signed_at',
        'metadata',
    ];

    protected $casts = [
        'size' => 'integer',
        'metadata' => 'array',
        'sent_at' => 'datetime',
        'signed_at' => 'datetime',
    ];

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function signers()
    {
        return $this->hasMany(DocumentSigner::class);
    }

    public function signatures()
    {
        return $this->hasMany(Signature::class);
    }

    public function ocrResults()
    {
        return $this->hasMany(OcrResult::class);
    }

    public function apiLogs()
    {
        return $this->hasMany(ApiLog::class);
    }

    public function categories()
    {
        return $this->belongsToMany(DocumentCategory::class, 'document_category_document');
    }
}
