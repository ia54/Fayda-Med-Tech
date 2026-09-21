<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LetterOfProtection extends Model
{
    use HasFactory;

    protected $fillable = [
        'case_id',
        'provider_id',
        'lop_number',
        'amount_covered',
        'services_covered',
        'expiry_date',
        'status', // issued, accepted, expired, cancelled
        'provider_acceptance_date',
        'document_url',
        'notes',
        'organization_id',
    ];

    protected $casts = [
        'amount_covered' => 'decimal:2',
        'expiry_date' => 'date',
        'provider_acceptance_date' => 'date',
        'services_covered' => 'array',
    ];

    /**
     * Get the case for this LOP.
     */
    public function case()
    {
        return $this->belongsTo(CaseModel::class, 'case_id');
    }

    /**
     * Get the provider for this LOP.
     */
    public function provider()
    {
        return $this->belongsTo(Provider::class);
    }

    /**
     * Get the organization that this LOP belongs to.
     */
    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }
}
