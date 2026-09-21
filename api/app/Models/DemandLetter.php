<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\AuditableTrait;

class DemandLetter extends Model
{
    use HasFactory, AuditableTrait;

    protected $fillable = [
        'case_id',
        'organization_id',
        'created_by',
        'recipient_name',
        'recipient_company',
        'recipient_address',
        'demand_amount',
        'content',
        'status',
        'sent_at',
        'response_date',
        'response_amount',
        'notes',
    ];

    protected $casts = [
        'demand_amount' => 'decimal:2',
        'response_amount' => 'decimal:2',
        'sent_at' => 'date',
        'response_date' => 'date',
    ];

    /**
     * Get the case for this demand letter.
     */
    public function case()
    {
        return $this->belongsTo(CaseModel::class, 'case_id');
    }

    /**
     * Get the organization.
     */
    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Get the user who created this demand letter.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
