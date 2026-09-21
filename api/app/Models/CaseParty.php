<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CaseParty extends Model
{
    use HasFactory;

    protected $fillable = [
        'case_id',
        'user_id',
        'name',
        'email',
        'phone',
        'role_in_case',
    ];

    public function case()
    {
        return $this->belongsTo(CaseModel::class, 'case_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
