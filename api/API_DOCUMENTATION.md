# Faydamed API Documentation (PDF 1.0, 2025 Compliant)

## Overview
This document provides comprehensive information about the FaydaTech SaaS Billing API endpoints. The API uses Laravel Passport for OAuth2 authentication with access and refresh tokens.

**PDF Compliance**: This API implements all requirements from Sections 1-16 of the FaydaTech SaaS Billing System PDF (Version 1.0, 2025).

## Base URL
```
https://faydaapi.dotprogrammers.com/api
```

## Authentication
The API uses Bearer token authentication. Include the access token in the Authorization header:
```
Authorization: Bearer {access_token}
```

## User Roles (PDF Section 2)

The system supports 6 user roles as per PDF specification:

1. **admin** (Super Admin) - Full system access
2. **firm_admin** (Firm Admin) - Full tenant access
3. **attorney** - Case-focused access
4. **medical_biller** - Billing-focused access
5. **provider_staff** - Provider portal access
6. **client** - Self-service portal access

## PDF Sections Implemented

### Section 3: Dashboard & Analytics
- `GET /api/billing/stats` - Billing dashboard stats (Roles: admin, firm_admin, medical_biller, provider_staff, client)
- `GET /api/provider/stats` - Provider dashboard stats (Roles: admin, firm_admin, provider_staff)
- `GET /api/client/stats` - Client dashboard stats (Roles: admin, firm_admin, client)
- `GET /api/firm/stats` - Firm dashboard stats (Roles: admin, firm_admin, attorney)

### Section 7: Insurance Management (NEW)
- `GET /api/insurance/companies` - List insurance companies
- `POST /api/insurance/companies` - Create insurance company
- `GET /api/insurance/companies/{id}` - Show insurance company
- `PUT /api/insurance/companies/{id}` - Update insurance company
- `DELETE /api/insurance/companies/{id}` - Delete insurance company
- `GET /api/insurance/claims` - List insurance claims
- `POST /api/insurance/claims` - Create insurance claim
- `GET /api/insurance/claims/{id}` - Show insurance claim
- `PUT /api/insurance/claims/{id}` - Update insurance claim

### Section 10: Provider & Lien Management (NEW)
- `GET /api/providers` - List providers
- `POST /api/providers` - Create provider
- `GET /api/providers/{id}` - Show provider
- `PUT /api/providers/{id}` - Update provider
- `DELETE /api/providers/{id}` - Delete provider
- `GET /api/liens` - List liens
- `POST /api/liens` - Create lien
- `PUT /api/liens/{id}` - Update lien
- `DELETE /api/liens/{id}` - Delete lien
- `GET /api/letters-of-protection` - List LOPs
- `POST /api/letters-of-protection` - Create LOP
- `GET /api/treatment-records` - List treatment records
- `POST /api/treatment-records` - Create treatment record

### Section 16: Security & Compliance (NEW)
- `GET /api/gdpr/export/{userId}` - Export user data (GDPR)
- `DELETE /api/gdpr/delete/{userId}` - Delete user data (GDPR)
- `GET /api/gdpr/audit-trail/{userId}` - User audit trail
- PHI access logging via `PhiAccessLoggingMiddleware`
- 2FA enforcement via `TwoFactorMiddleware`

## Automatic Audit Logging

All CRUD operations on key models (InsuranceCompany, InsuranceClaim, Provider, Lien, etc.) are automatically logged to the `audit_logs` table via `AuditableTrait`.

## Status Codes
- **200 OK**: Request successful
- **201 Created**: Resource created successfully
- **401 Unauthorized**: Invalid or missing authentication
- **403 Forbidden**: Insufficient permissions
- **404 Not Found**: Resource not found
- **422 Unprocessable Entity**: Validation errors

## Migration Required

Run the following migrations to update to PDF 1.0 standard:
```bash
php artisan migrate
```

New tables created:
- `insurance_companies`
- `insurance_claims`
- `providers`
- `liens`
- `letters_of_protection`
- `treatment_records`

Role ENUM updated to: `admin, firm_admin, attorney, medical_biller, provider_staff, client`