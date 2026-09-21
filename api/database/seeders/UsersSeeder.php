<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UsersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Creates test users for each role (PDF Section 2)
     */
    public function run(): void
    {
        // Get the FaydaTech Platform organization
        $platformOrg = \App\Models\Organization::where('org_type', 'platform')->first();
        // Get the Law Firm organization
        $lawFirmOrg = \App\Models\Organization::where('org_type', 'law_firm')->first();
        // Get the Medical Provider organization
        $medicalOrg = \App\Models\Organization::where('org_type', 'medical_provider')->first();

        $users = [
            // ========== Super Admin (PDF: Section 2 - FaydaTech Platform Team) ==========
            [
                'first_name' => 'Super',
                'last_name' => 'Admin',
                'email' => 'admin@faydatech.com',
                'password' => Hash::make('password123'),
                'role' => 'admin',
                'permissions' => json_encode(['*']), // All permissions
                'organization_id' => $platformOrg?->id,
                'status' => 'active',
                'two_factor_enabled' => true,
                'email_verified_at' => now(),
            ],

            // ========== Firm Admin (PDF: Section 2 - Law Firm Owner) ==========
            [
                'first_name' => 'Sarah',
                'last_name' => 'Manager',
                'email' => 'firmadmin@smithlegal.com',
                'password' => Hash::make('password123'),
                'role' => 'firm_admin',
                'permissions' => json_encode([
                    'user.manage', 'case.manage', 'billing.manage', 'invoice.manage',
                    'insurance.manage', 'document.manage', 'report.view', 'setting.manage'
                ]),
                'organization_id' => $lawFirmOrg?->id,
                'status' => 'active',
                'two_factor_enabled' => true,
                'email_verified_at' => now(),
            ],

            // ========== Attorney (PDF: Section 2 - Lawyer/Paralegal) ==========
            [
                'first_name' => 'John',
                'last_name' => 'Attorney',
                'email' => 'attorney@smithlegal.com',
                'password' => Hash::make('password123'),
                'role' => 'attorney',
                'permissions' => json_encode([
                    'case.manage', 'case.view', 'client.files', 'demand_letter.generate',
                    'settlement.manage', 'lien.create', 'lien.view', 'document.manage'
                ]),
                'organization_id' => $lawFirmOrg?->id,
                'status' => 'active',
                'two_factor_enabled' => false,
                'email_verified_at' => now(),
            ],

            // ========== Medical Biller (PDF: Section 2 - Billing Specialist) ==========
            [
                'first_name' => 'Michael',
                'last_name' => 'Biller',
                'email' => 'biller@wellnessmedical.com',
                'password' => Hash::make('password123'),
                'role' => 'medical_biller',
                'permissions' => json_encode([
                    'invoice.manage', 'invoice.view', 'payment.manage', 'insurance.manage',
                    'eob.manage', 'appeal.manage', 'validation.manage', 'lien.track'
                ]),
                'organization_id' => $lawFirmOrg?->id,
                'status' => 'active',
                'two_factor_enabled' => false,
                'email_verified_at' => now(),
            ],

            // ========== Provider Staff (PDF: Section 2 - Doctor's Office Staff) ==========
            [
                'first_name' => 'Emily',
                'last_name' => 'Staff',
                'email' => 'staff@wellnessmedical.com',
                'password' => Hash::make('password123'),
                'role' => 'provider_staff',
                'permissions' => json_encode([
                    'patient.visit', 'treatment.record', 'billing.submit',
                    'document.upload', 'document.manage', 'case.view'
                ]),
                'organization_id' => $medicalOrg?->id,
                'status' => 'active',
                'two_factor_enabled' => false,
                'email_verified_at' => now(),
            ],

            // ========== Client/Patient (PDF: Section 2 - Injured Patient) ==========
            [
                'first_name' => 'Jane',
                'last_name' => 'Patient',
                'email' => 'client@example.com',
                'password' => Hash::make('password123'),
                'role' => 'client',
                'dob' => '1985-06-15',
                'ssn' => '123-45-6789', // Will be encrypted by the model cast
                'permissions' => json_encode([
                    'case.view', 'document.sign', 'document.view', 'invoice.view', 'payment.create'
                ]),
                'organization_id' => $lawFirmOrg?->id,
                'status' => 'active',
                'two_factor_enabled' => false,
                'email_verified_at' => now(),
            ],

            // ========== Another Client for Testing ==========
            [
                'first_name' => 'Jane',
                'last_name' => 'Doe',
                'email' => 'jane.doe@example.com',
                'password' => Hash::make('password123'),
                'role' => 'client',
                'dob' => '1990-03-22',
                'ssn' => '987-65-4321',
                'permissions' => json_encode(['case.view', 'document.sign', 'invoice.view']),
                'organization_id' => $lawFirmOrg?->id,
                'status' => 'active',
                'two_factor_enabled' => false,
                'email_verified_at' => now(),
            ],
        ];

        foreach ($users as $userData) {
            User::updateOrCreate(
                ['email' => $userData['email']],
                $userData
            );
        }

        $this->command->info('✅ Users seeded successfully!');
        $this->command->info('Test accounts created:');
        $this->command->info('- admin@faydatech.com (Super Admin)');
        $this->command->info('- john@smithlegal.com (Firm Admin)');
        $this->command->info('- sarah@smithlegal.com (Attorney)');
        $this->command->info('- mike@smithlegal.com (Medical Biller)');
        $this->command->info('- emily@wellnessmedical.com (Provider Staff)');
        $this->command->info('- robert.patient@example.com (Client)');
        $this->command->info('- jane.doe@example.com (Client)');
        $this->command->info('Password for all: "password"');
    }
}