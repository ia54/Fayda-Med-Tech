<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\BelongsToTenant;
use App\Traits\AuditableTrait;

class HipaaAuthorization extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant, AuditableTrait;

    protected $fillable = [
        'organization_id',
        'user_id',
        'case_id',
        'authorization_type',
        'status',
        'issue_date',
        'expiry_date',
        'recipient_name',
        'purpose',
        'restrictions',
        'signed_document_path',
        'signed_at',
        'signed_by',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'expiry_date' => 'date',
        'signed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function case()
    {
        return $this->belongsTo(CaseModel::class, 'case_id');
    }

    public function signer()
    {
        return $this->belongsTo(User::class, 'signed_by');
    }
}