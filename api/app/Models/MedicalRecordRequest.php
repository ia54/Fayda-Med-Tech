<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\BelongsToTenant;
use App\Traits\AuditableTrait;

class MedicalRecordRequest extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant, AuditableTrait;

    protected $fillable = [
        'organization_id',
        'case_id',
        'user_id',
        'requested_by',
        'provider_id',
        'provider_name',
        'provider_fax',
        'provider_email',
        'provider_address',
        'request_date',
        'followup_date',
        'status',
        'records_requested',
        'notes',
        'authorization_form_path',
        'received_document_ids',
    ];

    protected $casts = [
        'request_date' => 'date',
        'followup_date' => 'date',
        'received_document_ids' => 'array',
    ];

    public function case()
    {
        return $this->belongsTo(CaseModel::class, 'case_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function provider()
    {
        return $this->belongsTo(Provider::class, 'provider_id');
    }
}