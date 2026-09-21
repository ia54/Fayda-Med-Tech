# Fayda Med Tech

Legal-tech + medical-billing platform. This repository is a monorepo holding the
three applications that together make up the platform.

| Folder | Application | Live domain | Stack |
|---|---|---|---|
| `frontend/` | Public landing site | https://faydamed.tech | Vite 7, React 19, Tailwind CSS 4, Ant Design 6 |
| `admin-panel/` | Role-based admin panel and portals | https://admin.faydamed.tech | Next.js 14, TypeScript, Radix UI |
| `api/` | Backend API | https://api.faydamed.tech | Laravel 10, PHP 8.3, MySQL |

## What the platform does

Injury-case and medical-billing workflow: case intake and parties, documents with
e-signatures, liens and settlements, provider treatment records, claims, CPT coding,
EOB processing, appeals, invoices and payments, plus the public marketing content
(FAQs, testimonials, settings) that the landing site renders.

## Roles

Six roles drive the admin panel, each seeing only its own menus:

- **Super Admin** - platform team, full system: users and roles, organizations, content, feature flags, audit logs
- **Firm Admin** - law-firm owner/manager: full tenant, staff, cases, billing and plans, firm settings
- **Attorney** - case-focused: intake, parties, documents, demand letters, liens, settlements, signatures
- **Medical Biller** - billing-focused: claims, CPT library, EOB processing, appeals, invoices, payments
- **Provider Staff** - provider portal: treatment records, document uploads, claim visibility
- **Client (Patient)** - self-service: own cases, own documents, in-app signing

Demo accounts for every role are listed on the admin login page.

## Environment

Each application is configured by its own env file, which is not committed:

- `frontend/.env` - `VITE_BASE_URL`
- `admin-panel/.env.local` - `NEXT_PUBLIC_API_BASE_URL`, `NEXT_PUBLIC_API_URL`
- `api/.env` - application, database and mail settings

Copy the matching `.env.example` and fill in local values.

## Running locally

```bash
# frontend
cd frontend && npm install && npm run dev

# admin panel
cd admin-panel && pnpm install && pnpm dev

# api
cd api && composer install && php artisan serve
```
