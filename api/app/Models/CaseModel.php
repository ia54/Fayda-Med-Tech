<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\BelongsToTenant;

class CaseModel extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant;

    protected $table = 'cases';

    protected $fillable = [
        'organization_id',
        'case_number',
        'title',
        'jurisdiction',
        'description',
        'status',
        'accident_date',
        'sol_date',
        'total_case_value',
        'created_by',
        'metadata',
    ];

    protected $casts = [
        'accident_date' => 'date',
        'sol_date' => 'date',
        'total_case_value' => 'decimal:2',
        'metadata' => 'array',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function parties()
    {
        return $this->hasMany(CaseParty::class, 'case_id');
    }

    public function notes()
    {
        return $this->hasMany(CaseNote::class, 'case_id');
    }

    public function tasks()
    {
        return $this->hasMany(CaseTask::class, 'case_id');
    }

    public function timeline()
    {
        return $this->hasMany(CaseTimeline::class, 'case_id');
    }

    /**
     * Scope a query to only include cases where a specific user is a party (Client/Plaintiff).
     */
    public function scopeForClient($query, $userId)
    {
        return $query->whereHas('parties', function ($q) use ($userId) {
            $q->where('user_id', $userId);
        });
    }

    /**
     * Scope a query to only include cases assigned to a specific attorney.
     */
    public function scopeAssignedToAttorney($query, $userId)
    {
        return $query->whereHas('parties', function ($q) use ($userId) {
            $q->where('user_id', $userId)
              ->where('role_in_case', 'attorney');
        });
    }
}
