<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Organization extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'org_name',
        'org_type',
        'subscription_plan',
        'email',
        'no_of_employees',
        'monthly_revenue',
        'yearly_revenue',
        'company_logo',
        'primary_color',
        'secondary_color',
        'tax_bin_no',
        'support_documents',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'support_documents' => 'array',
        'no_of_employees' => 'integer',
        'monthly_revenue' => 'decimal:2',
        'yearly_revenue' => 'decimal:2',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = ['company_logo_url', 'support_documents_urls'];

    /**
     * Get the full URL for company logo.
     *
     * @return string|null
     */
    public function getCompanyLogoUrlAttribute(): ?string
    {
        if ($this->company_logo) {
            return url('storage/' . $this->company_logo);
        }
        return null;
    }

    /**
     * Get the full URLs for support documents.
     *
     * @return array
     */
    public function getSupportDocumentsUrlsAttribute(): array
    {
        if ($this->support_documents && is_array($this->support_documents)) {
            return array_map(function($doc) {
                return url('storage/' . $doc);
            }, $this->support_documents);
        }
        return [];
    }
}
