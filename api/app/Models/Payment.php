<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;

class Payment extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'organization_id',
        'reversal_of_id',
        'recorded_by',
        'invoice_id',
        'amount',
        'payment_method',
        'transaction_id',
        'payment_date',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_date' => 'date',
    ];

    public function reversal()
    {
        return $this->hasOne(self::class, 'reversal_of_id');
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }
}
