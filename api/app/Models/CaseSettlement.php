<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\BelongsToTenant;

class CaseSettlement extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant;

    protected $fillable = [
        'case_id',
        'organization_id',
        'settlement_amount',
        'settlement_date',
        'status',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'settlement_date' => 'date',
        'settlement_amount' => 'decimal:2',
    ];

    public function case()
    {
        return $this->belongsTo(CaseModel::class, 'case_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
