<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\BelongsToTenant;
use App\Traits\AuditableTrait;

class MedicalHistory extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant, AuditableTrait;

    protected $fillable = [
        'organization_id',
        'user_id',
        'case_id',
        'record_date',
        'provider_name',
        'diagnosis',
        'treatment_description',
        'medications',
        'notes',
        'attachments',
        'record_type',
    ];

    protected $casts = [
        'record_date' => 'date',
        'attachments' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function case()
    {
        return $this->belongsTo(CaseModel::class, 'case_id');
    }
}