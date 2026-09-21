<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\AuditableTrait;

class InsuranceClaim extends Model
{
    use HasFactory, AuditableTrait;

    protected $fillable = [
        'insurance_company_id',
        'case_id',
        'claim_number',
        'coverage_type', // liability, pip, medpay, uninsured_motorist
        'coverage_limit',
        'claim_status', // open, pending, settled, denied
        'demand_amount',
        'settlement_offer',
        'final_settlement',
        'adjuster_id',
        'adjuster_notes',
        'correspondence_log',
        'organization_id',
    ];

    protected $casts = [
        'coverage_limit' => 'decimal:2',
        'demand_amount' => 'decimal:2',
        'settlement_offer' => 'decimal:2',
        'final_settlement' => 'decimal:2',
        'correspondence_log' => 'array',
    ];

    /**
     * Get the insurance company for this claim.
     */
    public function insuranceCompany()
    {
        return $this->belongsTo(InsuranceCompany::class);
    }

    /**
     * Get the case for this claim.
     */
    public function case()
    {
        return $this->belongsTo(CaseModel::class, 'case_id');
    }

    /**
     * Get the adjuster for this claim.
     */
    public function adjuster()
    {
        return $this->belongsTo(InsuranceAdjuster::class, 'adjuster_id');
    }

    /**
     * Get the organization that this claim belongs to.
     */
    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }
}
