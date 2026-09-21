<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;
use App\Notifications\ResetPassword as ResetPasswordNotification;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens;

    // PDF 1.0 (2025) Standard Roles
    const ROLE_SUPER_ADMIN = 'admin';          // FaydaTech platform team - Full system
    const ROLE_FIRM_ADMIN = 'firm_admin';     // Law firm owner/manager - Full tenant
    const ROLE_ATTORNEY = 'attorney';         // Lawyer/paralegal - Case-focused
    const ROLE_MEDICAL_BILLER = 'medical_biller'; // Billing specialist - Billing-focused
    const ROLE_PROVIDER_STAFF = 'provider_staff'; // Doctor's office staff - Provider portal
    const ROLE_CLIENT = 'client';             // Injured patient/claimant - Self-service

    // Helper method to get all available roles
    public static function getAvailableRoles(): array
    {
        return [
            self::ROLE_SUPER_ADMIN,
            self::ROLE_FIRM_ADMIN,
            self::ROLE_ATTORNEY,
            self::ROLE_MEDICAL_BILLER,
            self::ROLE_PROVIDER_STAFF,
            self::ROLE_CLIENT,
        ];
    }

    // Check if user is Super Admin
    public function isSuperAdmin(): bool
    {
        return $this->role === self::ROLE_SUPER_ADMIN;
    }

    // Check if user is Firm Admin
    public function isFirmAdmin(): bool
    {
        return $this->role === self::ROLE_FIRM_ADMIN;
    }

    // Check if user can manage billing
    public function canManageBilling(): bool
    {
        return in_array($this->role, [self::ROLE_SUPER_ADMIN, self::ROLE_FIRM_ADMIN, self::ROLE_MEDICAL_BILLER]);
    }

    // Check if user can manage cases
    public function canManageCases(): bool
    {
        return in_array($this->role, [self::ROLE_SUPER_ADMIN, self::ROLE_FIRM_ADMIN, self::ROLE_ATTORNEY]);
    }

    /**
     * Send the password reset notification.
     *
     * @param  string  $token
     * @return void
     */
    public function sendPasswordResetNotification($token)
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'ssn',
        'dob',
        'email',
        'password',
        'role',
        'permissions',
        'status',
        'organization',
        'organization_id',
        'last_login',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'ssn',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'last_login' => 'datetime',
        'organization_id' => 'integer',
        'status' => 'string',
        'permissions' => 'array',
        'dob' => 'date',
        'ssn' => 'encrypted',
    ];

    /**
     * Get the user's full name.
     *
     * @return string
     */
    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    /**
     * Get the organization that the user belongs to.
     */
    public function organizationRelation()
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    /**
     * Get documents uploaded by this user.
     */
    public function documents()
    {
        return $this->hasMany(Document::class, 'uploaded_by');
    }

    /**
     * Get OCR results processed by this user.
     */
    public function processedOcrResults()
    {
        return $this->hasMany(OcrResult::class, 'processed_by');
    }
}
