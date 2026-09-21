<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TreatmentRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'case_id',
        'provider_id',
        'patient_id',
        'treatment_date',
        'treatment_type',
        'diagnosis_codes',
        'procedure_codes',
        'notes',
        'mmi_status', // Maximum Medical Improvement status
        'disability_rating',
        'organization_id',
    ];

    protected $casts = [
        'treatment_date' => 'date',
        'diagnosis_codes' => 'array',
        'procedure_codes' => 'array',
        'disability_rating' => 'decimal:2',
    ];

    /**
     * Get the case for this treatment record.
     */
    public function case()
    {
        return $this->belongsTo(CaseModel::class, 'case_id');
    }

    /**
     * Get the provider for this treatment record.
     */
    public function provider()
    {
        return $this->belongsTo(Provider::class);
    }

    /**
     * Get the patient (client) for this treatment record.
     */
    public function patient()
    {
        return $this->belongsTo(User::class, 'patient_id');
    }

    /**
     * Get the organization that this record belongs to.
     */
    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }
}
