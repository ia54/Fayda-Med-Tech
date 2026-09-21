<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\AuditableTrait;

class Eob extends Model
{
    use HasFactory, AuditableTrait;

    protected $fillable = [
        'case_id',
        'organization_id',
        'document_id',
        'invoice_id',
        'uploaded_by',
        'provider_name',
        'patient_name',
        'payer_name',
        'billed_amount',
        'allowed_amount',
        'paid_amount',
        'patient_responsibility',
        'service_date',
        'eob_date',
        'ai_confidence',
        'status',
        'extracted_data',
        'notes',
    ];

    protected $casts = [
        'billed_amount' => 'decimal:2',
        'allowed_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'patient_responsibility' => 'decimal:2',
        'ai_confidence' => 'decimal:2',
        'service_date' => 'date',
        'eob_date' => 'date',
        'extracted_data' => 'array',
    ];

    public function case()
    {
        return $this->belongsTo(CaseModel::class, 'case_id');
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function document()
    {
        return $this->belongsTo(Document::class);
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
