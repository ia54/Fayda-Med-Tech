<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\AuditableTrait;

class InsuranceCompany extends Model
{
    use HasFactory, AuditableTrait;

    protected $fillable = [
        'name',
        'phone',
        'email',
        'address',
        'city',
        'state',
        'zip_code',
        'claims_office_address',
        'payment_rating',
        'notes',
        'organization_id',
    ];

    protected $casts = [
        'payment_rating' => 'decimal:2',
    ];

    /**
     * Get the organization that this insurance company belongs to.
     */
    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Get the claims for this insurance company.
     */
    public function claims()
    {
        return $this->hasMany(InsuranceClaim::class);
    }

    /**
     * Get the adjusters for this insurance company.
     */
    public function adjusters()
    {
        return $this->hasMany(InsuranceAdjuster::class);
    }
}
