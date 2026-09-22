<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use App\Traits\BelongsToTenant;

class ApiCredential extends Model
{
    use HasFactory, BelongsToTenant;

    // Encrypted at rest does not make fields safe to serialize.
    protected $hidden = ['key', 'value', 'credential_scope'];

    protected $fillable = [
        'organization_id',
        'provider',
        'name',
        'key',
        'value',
        'is_active',
        'metadata',
    ];

    protected $casts = [
        'key' => 'encrypted',
        'value' => 'encrypted',
        'is_active' => 'boolean',
        'metadata' => 'json',
    ];
}
