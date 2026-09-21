<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\AuditableTrait;

class Lien extends Model
{
    use HasFactory, AuditableTrait;

    protected $fillable = [
        'case_id',
        'provider_id',
        'lien_type', // medical, attorney, government_medicare, government_medicaid, health_insurance
        'amount',
        'status', // pending, negotiated, settled, released
        'negotiated_amount',
        'reduction_amount',
        'payoff_date',
        'release_document_url',
        'notes',
        'organization_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'negotiated_amount' => 'decimal:2',
        'reduction_amount' => 'decimal:2',
        'payoff_date' => 'date',
    ];

    /**
     * Get the case for this lien.
     */
    public function case()
    {
        return $this->belongsTo(CaseModel::class, 'case_id');
    }

    /**
     * Get the provider for this lien.
     */
    public function provider()
    {
        return $this->belongsTo(Provider::class);
    }

    /**
     * Get the organization that this lien belongs to.
     */
    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }
}
