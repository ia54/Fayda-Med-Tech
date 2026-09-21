# Role Management in faydamed API

This document explains how role-based access control works in the faydamed API (PDF Version 1.0, 2025 compliant).

## PDF Standard Roles

The system implements 6 roles as specified in the FaydaTech SaaS Billing System PDF:

| Role | Who Uses It | Access Level | Key Capabilities |
|------|-------------|-------------|-----------------|
| Super Admin (`admin`) | FaydaTech platform team | Full system | Tenant management, platform config, global reports |
| Firm Admin (`firm_admin`) | Law firm owner/manager | Full tenant | All menus, user management, billing setup |
| Attorney (`attorney`) | Lawyer/paralegal | Case-focused | Case management, client files, documents, settlement |
| Medical Biller (`medical_biller`) | Billing specialist | Billing-focused | Invoices, EOBs, insurance, payments, liens |
| Provider Staff (`provider_staff`) | Doctor's office staff | Provider portal | Patient visits, treatment records, billing submission |
| Client/Patient (`client`) | Injured patient/claimant | Self-service | View case status, sign documents, view invoices |

## Database Structure

The `users` table role column uses ENUM with the 6 PDF-specified roles:
```php
$table->enum('role', [
    'admin',           // Super Admin
    'firm_admin',      // Firm Admin
    'attorney',        // Attorney
    'medical_biller',  // Medical Biller
    'provider_staff',  // Provider Staff
    'client'           // Client/Patient
])->default('client');
```

## Granular Permissions

In addition to role-based access, the system supports granular permissions stored in the `permissions` JSON column:
```php
// Check role
if (in_array($user->role, ['admin', 'firm_admin'])) { ... }

// Check granular permission
if ($this->hasPermission($user, 'manage_users')) { ... }
```

## Middleware Usage

### CheckRole Middleware (Updated)
The `CheckRole` middleware now supports both role and permission checks:

```php
// Role only
Route::middleware(['role:admin,firm_admin'])->group(function () { ... });

// Permission only
Route::middleware(['role:permission:manage_users'])->group(function () { ... });

// Either role OR permission
Route::middleware(['role:admin,firm_admin|permission:manage_users'])->group(function () { ... });

// Both role AND permission
Route::middleware(['role:admin,firm_admin&permission:manage_users'])->group(function () { ... });
```

## 2FA Enforcement

Roles requiring 2FA (configurable in `TwoFactorMiddleware`):
- Super Admin (`admin`)
- Firm Admin (`firm_admin`)

## Testing Roles

### Test Users (create via seeder or registration)
```bash
# Admin
curl -X POST http://localhost:8000/api/register \
  -H "Content-Type: application/json" \
  -d '{"first_name": "Admin", "last_name": "User", "email": "admin@test.com", "password": "password", "role": "admin"}'

# Firm Admin
curl -X POST http://localhost:8000/api/register \
  -H "Content-Type: application/json" \
  -d '{"first_name": "Firm", "last_name": "Admin", "email": "firm@test.com", "password": "password", "role": "firm_admin"}'
```

## Role-Based Menu Access (PDF Section 17)

Refer to the PDF Section 17 "Complete Role-Based Menu Reference" for full menu access matrix.

## Audit Logging

All CRUD operations are automatically logged via `AuditableTrait`:
- Model events (created, updated, deleted) are logged to `audit_logs` table
- PHI access is logged via `PhiAccessLoggingMiddleware`
- Audit logs include: user, organization, event type, IP address, timestamp
