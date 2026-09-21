<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Organization;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $platform = Organization::where('org_type', 'platform')->first();
        $lawFirm = Organization::where('org_type', 'law_firm')->first();
        $medical = Organization::where('org_type', 'medical_provider')->first();

        // 1. Super Admin
        User::updateOrCreate(
            ['email' => 'admin@faydatech.com'],
            [
                'first_name' => 'System',
                'last_name' => 'Admin',
                'password' => Hash::make('password123'),
                'role' => User::ROLE_SUPER_ADMIN,
                'permissions' => ['*'],
                'status' => 'active',
                'organization_id' => $platform->id,
            ]
        );

        // 2. Firm Admin
        User::updateOrCreate(
            ['email' => 'firmadmin@smithlegal.com'],
            [
                'first_name' => 'Sarah',
                'last_name' => 'Manager',
                'password' => Hash::make('password123'),
                'role' => User::ROLE_FIRM_ADMIN,
                'permissions' => ['firm.all'],
                'status' => 'active',
                'organization_id' => $lawFirm->id,
            ]
        );

        // 3. Attorney
        User::updateOrCreate(
            ['email' => 'attorney@smithlegal.com'],
            [
                'first_name' => 'John',
                'last_name' => 'Attorney',
                'password' => Hash::make('password123'),
                'role' => User::ROLE_ATTORNEY,
                'permissions' => ['case.manage', 'client.files', 'document.manage', 'demand.letter', 'settlement.manage'],
                'status' => 'active',
                'organization_id' => $lawFirm->id,
            ]
        );

        // 4. Medical Biller
        User::updateOrCreate(
            ['email' => 'biller@wellnessmedical.com'],
            [
                'first_name' => 'Michael',
                'last_name' => 'Biller',
                'password' => Hash::make('password123'),
                'role' => User::ROLE_MEDICAL_BILLER,
                'permissions' => ['invoice.manage', 'eob.manage', 'insurance.manage', 'payment.manage', 'lien.track'],
                'status' => 'active',
                'organization_id' => $medical->id,
            ]
        );

        // 5. Provider Staff
        User::updateOrCreate(
            ['email' => 'staff@wellnessmedical.com'],
            [
                'first_name' => 'Emily',
                'last_name' => 'Staff',
                'password' => Hash::make('password123'),
                'role' => User::ROLE_PROVIDER_STAFF,
                'permissions' => ['patient.visit', 'treatment.record', 'billing.submit', 'document.upload'],
                'status' => 'active',
                'organization_id' => $medical->id,
            ]
        );

        // 6. Client (Patient)
        User::updateOrCreate(
            ['email' => 'client@example.com'],
            [
                'first_name' => 'Jane',
                'last_name' => 'Patient',
                'password' => Hash::make('password123'),
                'role' => User::ROLE_CLIENT,
                'permissions' => ['case.view', 'document.sign', 'document.upload', 'invoice.view'],
                'status' => 'active',
                'organization_id' => $lawFirm->id,
            ]
        );
    }
}
