<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApiLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_id',
        'provider',
        'endpoint',
        'request_method',
        'response_status',
        'status',
        'request_payload',
        'response_payload',
        'error_message',
    ];

    protected $casts = [
        'response_status' => 'integer',
        'request_payload' => 'array',
        'response_payload' => 'array',
    ];

    public function document()
    {
        return $this->belongsTo(Document::class);
    }
}
