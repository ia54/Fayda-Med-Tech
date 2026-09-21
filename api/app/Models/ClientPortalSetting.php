<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;
use App\Traits\AuditableTrait;

class ClientPortalSetting extends Model
{
    use HasFactory, BelongsToTenant, AuditableTrait;

    protected $fillable = [
        'organization_id',
        'user_id',
        'case_status_visible',
        'documents_visible',
        'invoices_visible',
        'payments_visible',
        'signatures_visible',
        'medical_history_visible',
        'allow_document_upload',
        'allow_messaging',
        'theme',
    ];

    protected $casts = [
        'case_status_visible' => 'boolean',
        'documents_visible' => 'boolean',
        'invoices_visible' => 'boolean',
        'payments_visible' => 'boolean',
        'signatures_visible' => 'boolean',
        'medical_history_visible' => 'boolean',
        'allow_document_upload' => 'boolean',
        'allow_messaging' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}