<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;
use App\Traits\AuditableTrait;

class ReportDefinition extends Model
{
    use HasFactory, BelongsToTenant, AuditableTrait;

    protected $fillable = [
        'organization_id',
        'name',
        'slug',
        'category',
        'description',
        'default_columns',
        'filters',
        'available_formats',
        'is_system',
        'is_active',
    ];

    protected $casts = [
        'default_columns' => 'array',
        'filters' => 'array',
        'available_formats' => 'array',
        'is_system' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function generations()
    {
        return $this->hasMany(ReportGeneration::class, 'report_definition_id');
    }
}