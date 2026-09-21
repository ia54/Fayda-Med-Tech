<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * PDF Section 2: User Roles & Permissions Matrix (6 roles only)
     */
    public function run(): void
    {
        $roles = [
            // 1. Super Admin (FaydaTech platform team - Full system)
            [
                'name' => 'Super Admin',
                'slug' => 'admin',
                'description' => 'FaydaTech platform team - Full system access, tenant management, global reports, platform config',
                'permissions' => ['*'], // Wildcard = all permissions
                'color' => 'destructive',
            ],
            
            // 2. Firm Admin (Law firm owner/manager - Full tenant)
            [
                'name' => 'Firm Admin',
                'slug' => 'firm_admin',
                'description' => 'Law firm owner/office manager - Full tenant access, user management, billing setup, integrations',
                'permissions' => [
                    // User & Team Management
                    'user.manage', 'user.view', 'role.manage', 'team.manage',
                    // Case Management
                    'case.manage', 'case.view', 'case.create', 'case.edit', 'case.delete',
                    // Client/Patient Management
                    'client.manage', 'client.view', 'client.create', 'client.edit',
                    // Billing & Invoice Management
                    'billing.manage', 'invoice.manage', 'invoice.view', 'invoice.create', 'invoice.edit', 'invoice.delete',
                    'payment.manage', 'payment.view', 'payment.create', 'payment.delete',
                    'eob.manage', 'eob.view', 'appeal.manage', 'appeal.generate_ai', 'validation.manage',
                    // Insurance Management (PDF Section 7)
                    'insurance.manage', 'insurance.view', 'insurance.claim.manage',
                    // Provider & Lien Management (PDF Section 10)
                    'provider.manage', 'provider.view', 'lien.manage', 'lien.view', 'lop.manage',
                    'treatment.manage', 'treatment.view',
                    // Document Management
                    'document.manage', 'document.upload', 'document.sign', 'document.ocr', 'signature.manage',
                    // Reporting & Audit (PDF Section 11)
                    'report.view', 'audit.read', 'phi.access',
                    // Settings & Integrations (PDF Section 13)
                    'setting.manage', 'integration.manage', 'security.view', 'security.manage',
                    // Notifications (PDF Section 14)
                    'notification.manage', 'notification.view',
                    // GDPR (PDF Section 16)
                    'gdpr.export', 'gdpr.delete',
                ],
                'color' => 'default',
            ],
            
            // 3. Attorney (Lawyer/paralegal - Case-focused)
            [
                'name' => 'Attorney',
                'slug' => 'attorney',
                'description' => 'Lawyer/paralegal - Case management, client files, documents, demand letters, settlement',
                'permissions' => [
                    // Case Management
                    'case.manage', 'case.view', 'case.create', 'case.edit',
                    // Client/Patient
                    'client.view', 'client.files',
                    // Legal
                    'demand_letter.generate', 'settlement.manage', 'lien.view', 'lien.create',
                    // Documents
                    'document.manage', 'document.upload', 'document.sign', 'document.view',
                    // Billing (read-only)
                    'invoice.view', 'billing.view',
                    // Insurance
                    'insurance.view', 'insurance.claim.view',
                    // Reporting
                    'report.view',
                ],
                'color' => 'secondary',
            ],
            
            // 4. Medical Biller (Billing specialist - Billing-focused)
            [
                'name' => 'Medical Biller',
                'slug' => 'medical_biller',
                'description' => 'Billing specialist - Invoices, EOBs, insurance, payments, lien tracking',
                'permissions' => [
                    // Billing & Invoice
                    'billing.manage', 'invoice.manage', 'invoice.view', 'invoice.create', 'invoice.edit',
                    'payment.manage', 'payment.view', 'payment.create',
                    'eob.manage', 'eob.view', 'eob.process', 'appeal.manage', 'appeal.generate_ai',
                    'validation.manage', 'validation.view',
                    // Insurance (PDF Section 7)
                    'insurance.manage', 'insurance.view', 'insurance.claim.manage', 'insurance.claim.view',
                    // Liens (PDF Section 10)
                    'lien.manage', 'lien.view', 'lien.negotiate',
                    // Documents
                    'document.manage', 'document.upload', 'document.sign', 'document.ocr',
                    // Providers
                    'provider.view',
                    // Reports
                    'report.view', 'billing.report',
                ],
                'color' => 'outline',
            ],
            
            // 5. Provider Staff (Doctor's office staff - Provider portal)
            [
                'name' => 'Provider Staff',
                'slug' => 'provider_staff',
                'description' => 'Doctor\'s office staff - Patient visits, treatment records, billing submission, documents',
                'permissions' => [
                    // Provider Portal
                    'provider.dashboard', 'patient.visit', 'treatment.record', 'treatment.create', 'treatment.view',
                    // Billing Submission
                    'billing.submit', 'invoice.create', 'invoice.view',
                    // Documents
                    'document.upload', 'document.view', 'document.ocr',
                    // Case (read-only)
                    'case.view',
                    // Providers
                    'provider.view',
                    // Added per PDF Section 17 & 10
                    'insurance.view', 'insurance.claim.view', 'lien.view', 'report.view', 'cpt.view',
                ],
                'color' => 'secondary',
            ],
            
            // 6. Client/Patient (Injured patient/claimant - Self-service)
            [
                'name' => 'Client (Patient)',
                'slug' => 'client',
                'description' => 'Injured patient/claimant - Self-service portal, view case status, sign documents, view invoices',
                'permissions' => [
                    // Self-Service Portal
                    'client.dashboard', 'case.view', 'document.sign', 'document.view', 'document.upload',
                    'invoice.view', 'payment.create', 'payment.view',
                    // Profile
                    'profile.view', 'profile.edit',
                ],
                'color' => 'outline',
            ],
        ];

        foreach ($roles as $role) {
            Role::updateOrCreate(['slug' => $role['slug']], $role);
        }
    }
}
