<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\AuditableTrait;

class Provider extends Model
{
    use HasFactory, AuditableTrait;

    protected $fillable = [
        'name',
        'npi', // National Provider Identifier
        'specialty',
        'tax_id',
        'phone',
        'email',
        'address',
        'city',
        'state',
        'zip_code',
        'fee_schedule',
        'rating',
        'notes',
        'organization_id',
    ];

    protected $casts = [
        'fee_schedule' => 'array',
        'rating' => 'decimal:2',
    ];

    /**
     * Get the organization that this provider belongs to.
     */
    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Get the liens for this provider.
     */
    public function liens()
    {
        return $this->hasMany(Lien::class, 'provider_id');
    }

    /**
     * Get the treatment records for this provider.
     */
    public function treatmentRecords()
    {
        return $this->hasMany(TreatmentRecord::class, 'provider_id');
    }

    /**
     * Get the users (provider staff) for this provider.
     */
    public function users()
    {
        return $this->hasMany(User::class, 'organization_id');
    }
}
