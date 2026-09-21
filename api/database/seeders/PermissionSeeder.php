<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * All permissions for PDF Sections 1-16
     */
    public function run(): void
    {
        $permissions = [
            // ========== User & Team Management (PDF Section 12) ==========
            ['name' => 'user.manage', 'description' => 'Create, edit, delete users and assign roles', 'category' => 'Users'],
            ['name' => 'user.view', 'description' => 'View user profiles and activity', 'category' => 'Users'],
            ['name' => 'role.manage', 'description' => 'Manage roles and assign permissions', 'category' => 'Users'],
            ['name' => 'team.manage', 'description' => 'Manage departments and team assignments', 'category' => 'Users'],
            ['name' => 'profile.view', 'description' => 'View own profile', 'category' => 'Users'],
            ['name' => 'profile.edit', 'description' => 'Edit own profile', 'category' => 'Users'],

            // ========== Case Management (PDF Section 4) ==========
            ['name' => 'case.manage', 'description' => 'Full case management access', 'category' => 'Cases'],
            ['name' => 'case.view', 'description' => 'View cases (own or assigned)', 'category' => 'Cases'],
            ['name' => 'case.create', 'description' => 'Create new cases', 'category' => 'Cases'],
            ['name' => 'case.edit', 'description' => 'Edit case information', 'category' => 'Cases'],
            ['name' => 'case.delete', 'description' => 'Delete cases', 'category' => 'Cases'],
            ['name' => 'case.party.manage', 'description' => 'Manage case parties (plaintiff, defendant, etc.)', 'category' => 'Cases'],

            // ========== Client/Patient Management (PDF Section 5) ==========
            ['name' => 'client.manage', 'description' => 'Full client/patient management', 'category' => 'Clients'],
            ['name' => 'client.view', 'description' => 'View client profiles', 'category' => 'Clients'],
            ['name' => 'client.create', 'description' => 'Create new client records', 'category' => 'Clients'],
            ['name' => 'client.edit', 'description' => 'Edit client information', 'category' => 'Clients'],
            ['name' => 'client.files', 'description' => 'Manage client medical history and authorizations', 'category' => 'Clients'],

            // ========== Billing & Invoice Management (PDF Section 6) ==========
            ['name' => 'billing.manage', 'description' => 'Full billing management access', 'category' => 'Billing'],
            ['name' => 'billing.view', 'description' => 'View billing dashboard and stats', 'category' => 'Billing'],
            ['name' => 'billing.submit', 'description' => 'Submit billing information', 'category' => 'Billing'],
            ['name' => 'billing.report', 'description' => 'View billing reports (aging, collection)', 'category' => 'Billing'],
            ['name' => 'invoice.manage', 'description' => 'Full invoice management', 'category' => 'Invoices'],
            ['name' => 'invoice.view', 'description' => 'View invoices', 'category' => 'Invoices'],
            ['name' => 'invoice.create', 'description' => 'Create invoices', 'category' => 'Invoices'],
            ['name' => 'invoice.edit', 'description' => 'Edit invoices', 'category' => 'Invoices'],
            ['name' => 'invoice.delete', 'description' => 'Delete invoices', 'category' => 'Invoices'],
            ['name' => 'payment.manage', 'description' => 'Full payment tracking and recording', 'category' => 'Payments'],
            ['name' => 'payment.view', 'description' => 'View payments', 'category' => 'Payments'],
            ['name' => 'payment.create', 'description' => 'Record payments', 'category' => 'Payments'],
            ['name' => 'payment.delete', 'description' => 'Delete payments', 'category' => 'Payments'],
            ['name' => 'eob.manage', 'description' => 'Manage EOBs and explanation of review', 'category' => 'EOBs'],
            ['name' => 'eob.view', 'description' => 'View EOBs', 'category' => 'EOBs'],
            ['name' => 'eob.process', 'description' => 'Process EOBs with AI OCR', 'category' => 'EOBs'],
            ['name' => 'appeal.manage', 'description' => 'Manage claim appeals', 'category' => 'Appeals'],
            ['name' => 'appeal.generate_ai', 'description' => 'Generate AI-assisted appeals', 'category' => 'Appeals'],
            ['name' => 'validation.manage', 'description' => 'Manage claim validation and coding issues', 'category' => 'Validation'],
            ['name' => 'cpt.view', 'description' => 'View CPT and ICD-10 code library', 'category' => 'Billing'],

            // ========== Insurance Management (PDF Section 7) ==========
            ['name' => 'insurance.manage', 'description' => 'Manage insurance companies and adjusters', 'category' => 'Insurance'],
            ['name' => 'insurance.view', 'description' => 'View insurance companies', 'category' => 'Insurance'],
            ['name' => 'insurance.claim.manage', 'description' => 'Manage insurance claims', 'category' => 'Insurance'],
            ['name' => 'insurance.claim.view', 'description' => 'View insurance claims', 'category' => 'Insurance'],
            ['name' => 'insurance.correspondence', 'description' => 'Log correspondence with insurers', 'category' => 'Insurance'],

            // ========== Document Management with AI OCR (PDF Section 8) ==========
            ['name' => 'document.manage', 'description' => 'Full document management', 'category' => 'Documents'],
            ['name' => 'document.upload', 'description' => 'Upload documents', 'category' => 'Documents'],
            ['name' => 'document.view', 'description' => 'View documents', 'category' => 'Documents'],
            ['name' => 'document.sign', 'description' => 'Sign documents digitally', 'category' => 'Documents'],
            ['name' => 'document.ocr', 'description' => 'Process documents with AI OCR', 'category' => 'Documents'],

            // ========== AI Vision & Digital Signature (PDF Section 9) ==========
            ['name' => 'ai.vision', 'description' => 'Use AI Vision for document processing', 'category' => 'AI'],
            ['name' => 'signature.manage', 'description' => 'Manage digital signatures and templates', 'category' => 'Signatures'],

            // ========== Provider & Lien Management (PDF Section 10) ==========
            ['name' => 'provider.manage', 'description' => 'Manage provider directory', 'category' => 'Providers'],
            ['name' => 'provider.view', 'description' => 'View providers', 'category' => 'Providers'],
            ['name' => 'lien.manage', 'description' => 'Manage liens (medical, attorney, government)', 'category' => 'Liens'],
            ['name' => 'lien.view', 'description' => 'View liens', 'category' => 'Liens'],
            ['name' => 'lien.negotiate', 'description' => 'Negotiate lien reductions', 'category' => 'Liens'],
            ['name' => 'lop.manage', 'description' => 'Manage Letters of Protection', 'category' => 'Liens'],
            ['name' => 'treatment.manage', 'description' => 'Manage treatment records', 'category' => 'Treatment'],
            ['name' => 'treatment.record', 'description' => 'Create treatment records', 'category' => 'Treatment'],
            ['name' => 'treatment.view', 'description' => 'View treatment records', 'category' => 'Treatment'],

            // ========== Reporting & Audit Logs (PDF Section 11) ==========
            ['name' => 'report.view', 'description' => 'View all reports (PDF Section 11)', 'category' => 'Reports'],
            ['name' => 'audit.read', 'description' => 'Read audit logs for compliance', 'category' => 'Audit'],
            ['name' => 'phi.access', 'description' => 'Access PHI (HIPAA compliance logging)', 'category' => 'Audit'],

            // ========== Tenant/Firm Administration (PDF Section 13) ==========
            ['name' => 'setting.manage', 'description' => 'Manage firm settings and branding', 'category' => 'Settings'],
            ['name' => 'integration.manage', 'description' => 'Manage third-party integrations (Stripe, QuickBooks, etc.)', 'category' => 'Integrations'],

            // ========== Security & Compliance (PDF Section 16) ==========
            ['name' => 'security.view', 'description' => 'View security settings and events', 'category' => 'Security'],
            ['name' => 'security.manage', 'description' => 'Manage security settings (2FA, IP allowlist)', 'category' => 'Security'],

            // ========== Notifications (PDF Section 14) ==========
            ['name' => 'notification.manage', 'description' => 'Manage notification preferences', 'category' => 'Notifications'],
            ['name' => 'notification.view', 'description' => 'View notifications', 'category' => 'Notifications'],

            // ========== GDPR (PDF Section 16) ==========
            ['name' => 'gdpr.export', 'description' => 'Export user data (GDPR portability)', 'category' => 'GDPR'],
            ['name' => 'gdpr.delete', 'description' => 'Delete/anonymize user data (GDPR erasure)', 'category' => 'GDPR'],

            // ========== Dashboard Access ==========
            ['name' => 'admin.dashboard', 'description' => 'Access admin dashboard', 'category' => 'Dashboard'],
            ['name' => 'firm.dashboard', 'description' => 'Access firm dashboard', 'category' => 'Dashboard'],
            ['name' => 'provider.dashboard', 'description' => 'Access provider portal dashboard', 'category' => 'Dashboard'],
            ['name' => 'client.dashboard', 'description' => 'Access client self-service portal', 'category' => 'Dashboard'],

            // ========== Legal Features ==========
            ['name' => 'demand_letter.generate', 'description' => 'Generate demand letters', 'category' => 'Legal'],
            ['name' => 'settlement.manage', 'description' => 'Manage settlements and disbursements', 'category' => 'Legal'],
        ];

        foreach ($permissions as $permission) {
            Permission::updateOrCreate(['name' => $permission['name']], $permission);
        }
    }
}
