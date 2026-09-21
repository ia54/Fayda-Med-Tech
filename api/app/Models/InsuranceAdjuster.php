<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InsuranceAdjuster extends Model
{
    use HasFactory;

    protected $fillable = [
        'insurance_company_id',
        'name',
        'phone',
        'email',
        'notes',
    ];

    /**
     * Get the insurance company for this adjuster.
     */
    public function insuranceCompany()
    {
        return $this->belongsTo(InsuranceCompany::class);
    }

    /**
     * Get the claims for this adjuster.
     */
    public function claims()
    {
        return $this->hasMany(InsuranceClaim::class);
    }
}
