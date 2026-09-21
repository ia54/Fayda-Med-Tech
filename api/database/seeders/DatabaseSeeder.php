<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     * Order matters: Organizations → Roles → Permissions → Users
     */
    public function run(): void
    {
        $this->command->info('🚀 Starting FaydaTech SaaS Billing System seeding...');
        $this->command->info('');

        // 1. Organizations (must exist before users)
        $this->command->info('1️⃣  Seeding Organizations...');
        $this->call(OrganizationSeeder::class);

        // 2. Roles (PDF Section 2 - 6 roles)
        $this->command->info('2️⃣  Seeding Roles (PDF 6 roles)...');
        $this->call(RoleSeeder::class);

        // 3. Permissions (all PDF sections)
        $this->command->info('3️⃣  Seeding Permissions (all PDF sections)...');
        $this->call(PermissionSeeder::class);

        // 4. Users (test users for each role)
        $this->command->info('4️⃣  Seeding Users (test accounts for each role)...');
        $this->call(UsersSeeder::class);

        $this->command->info('');
        $this->command->info('✅ FaydaTech SaaS Billing System seeding complete!');
    }
}