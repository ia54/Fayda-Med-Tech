<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\BelongsToTenant;

class CaseSettlement extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant;

    protected $fillable = [
        'case_id',
        'organization_id',
        'settlement_amount',
        'attorney_fees',
        'costs',
        'other_deductions',
        'settlement_date',
        'status',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'settlement_date' => 'date',
        'settlement_amount' => 'decimal:2',
        'attorney_fees' => 'decimal:2',
        'costs' => 'decimal:2',
        'other_deductions' => 'decimal:2',
    ];

    protected $appends = ['net_to_client'];

    public static function cents($value): int
    {
        $parts = explode('.', (string) $value, 2);
        return ((int) $parts[0]) * 100 + (int) str_pad($parts[1] ?? '', 2, '0');
    }

    public function getNetToClientAttribute(): ?string
    {
        if ($this->other_deductions === null) return null;
        $net = self::cents($this->settlement_amount) - self::cents($this->attorney_fees) - self::cents($this->costs) - self::cents($this->other_deductions);
        return sprintf('%d.%02d', intdiv($net, 100), $net % 100);
    }

    public function case()
    {
        return $this->belongsTo(CaseModel::class, 'case_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
