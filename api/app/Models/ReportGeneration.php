<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;

class ReportGeneration extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'organization_id',
        'report_definition_id',
        'generated_by',
        'report_name',
        'report_type',
        'parameters',
        'format',
        'status',
        'file_path',
        'completed_at',
        'error_message',
        'result_summary',
    ];

    protected $casts = [
        'parameters' => 'array',
        'result_summary' => 'array',
        'completed_at' => 'datetime',
    ];

    public function definition()
    {
        return $this->belongsTo(ReportDefinition::class, 'report_definition_id');
    }

    public function generator()
    {
        return $this->belongsTo(User::class, 'generated_by');
    }
}