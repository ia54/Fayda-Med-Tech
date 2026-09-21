<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use App\Traits\BelongsToTenant;

class OcrResult extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'organization_id',
        'document_id',
        'processed_by',
        'provider',
        'status',
        'extracted_text',
        'full_response',
        'metadata',
        'processed_at',
    ];

    protected $casts = [
        'full_response' => 'array',
        'metadata' => 'array',
        'processed_at' => 'datetime',
    ];

    public function document()
    {
        return $this->belongsTo(Document::class);
    }

    public function processedBy()
    {
        return $this->belongsTo(User::class, 'processed_by');
    }
}
