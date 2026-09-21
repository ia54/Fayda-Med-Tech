<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SecuritySetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'min_password_length',
        'require_uppercase',
        'require_numbers',
        'require_symbols',
        'enforce_2fa_all',
        'enforce_2fa_admin',
        'two_fa_grace_period',
        'session_timeout',
    ];

    protected $casts = [
        'require_uppercase' => 'boolean',
        'require_numbers' => 'boolean',
        'require_symbols' => 'boolean',
        'enforce_2fa_all' => 'boolean',
        'enforce_2fa_admin' => 'boolean',
    ];
}
