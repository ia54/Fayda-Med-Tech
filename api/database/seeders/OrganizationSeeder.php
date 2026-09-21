<?php

namespace Database\Seeders;

use App\Models\Organization;
use Illuminate\Database\Seeder;

class OrganizationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * PDF: Multi-tenant SaaS platform
     */
    public function run(): void
    {
        $organizations = [
            // ========== Platform Tenant (FaydaTech) ==========
            [
                'org_name' => 'FaydaTech Platform',
                'org_type' => 'platform',
                'email' => 'admin@faydatech.com',
                'subscription_plan' => 'enterprise',
                'settings' => json_encode([
                    'branding' => [
                        'primary_color' => '#1e40af',
                        'secondary_color' => '#3b82f6',
                    ],
                    'features' => ['all'],
                ]),
            ],

            // ========== Law Firm Tenant ==========
            [
                'org_name' => 'Smith & Associates Law Firm',
                'org_type' => 'law_firm',
                'email' => 'contact@smithlegal.com',
                'phone' => '(555) 123-4567',
                'address' => '123 Legal Street, Suite 100',
                'city' => 'Los Angeles',
                'state' => 'CA',
                'zip_code' => '90001',
                'subscription_plan' => 'standard',
                'settings' => json_encode([
                    'branding' => [
                        'primary_color' => '#059669',
                        'secondary_color' => '#10b981',
                        'logo_url' => '/logos/smith-legal.png',
                    ],
                    'features' => ['cases', 'billing', 'documents', 'insurance', 'liens'],
                    'business_hours' => '9:00-17:00',
                    'timezone' => 'America/Los_Angeles',
                    'jurisdiction' => 'California',
                    'state_license' => 'CA-12345',
                ]),
            ],

            // ========== Medical Provider Tenant ==========
            [
                'org_name' => 'Wellness Medical Group',
                'org_type' => 'medical_provider',
                'email' => 'info@wellnessmedical.com',
                'phone' => '(555) 987-6543',
                'address' => '456 Health Avenue',
                'city' => 'Beverly Hills',
                'state' => 'CA',
                'zip_code' => '90210',
                'subscription_plan' => 'premium',
                'settings' => json_encode([
                    'branding' => [
                        'primary_color' => '#dc2626',
                        'secondary_color' => '#ef4444',
                        'logo_url' => '/logos/wellness-medical.png',
                    ],
                    'features' => ['treatments', 'billing_submission', 'documents', 'patient_portal'],
                    'tax_id' => '12-3456789',
                    'fee_schedule' => [
                        'CPT_99213' => 150.00,
                        'CPT_99214' => 200.00,
                    ],
                ]),
            ],

            // ========== Another Law Firm (for testing multi-tenancy) ==========
            [
                'org_name' => 'Johnson Injury Law',
                'org_type' => 'law_firm',
                'email' => 'hello@johnsoninjurylaw.com',
                'phone' => '(555) 456-7890',
                'address' => '789 Justice Boulevard',
                'city' => 'San Francisco',
                'state' => 'CA',
                'zip_code' => '94102',
                'subscription_plan' => 'basic',
                'settings' => json_encode([
                    'branding' => [
                        'primary_color' => '#7c3aed',
                        'secondary_color' => '#8b5cf6',
                    ],
                    'features' => ['cases', 'billing', 'documents'],
                ]),
            ],
        ];

        foreach ($organizations as $org) {
            Organization::updateOrCreate(
                ['email' => $org['email']],
                $org
            );
        }
    }
}
