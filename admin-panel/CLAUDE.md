# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Fayda Wellness Pharmacy Admin Dashboard - A modern Next.js 14 admin application built with TypeScript, Redux Toolkit, and Ant Design/shadcn/ui components. The application features role-based authentication, state persistence, and a modular architecture.

**Tech Stack**: Next.js 14 (App Router), TypeScript, Redux Toolkit + RTK Query, Ant Design, shadcn/ui, Tailwind CSS

**API Base URL**: https://faydaapi.dotprogrammers.com/api

## Development Commands

```bash
# Development
pnpm dev                    # Start development server at http://localhost:3000
pnpm install               # Install dependencies

# Build & Production
pnpm build                 # Create production build
pnpm start                 # Start production server

# Code Quality
pnpm lint                  # Run ESLint
pnpm lint --fix           # Fix auto-fixable lint issues
npx tsc --noEmit          # Type check without emitting files
```

**Note**: The project uses `pnpm` as the package manager.

## Architecture Overview

### Route Structure (App Router)

The application uses Next.js App Router with route groups:

- `app/(public)/auth/` - Authentication pages (login, signup, forgot-password, reset-password)
- `app/(private)/dashboard/` - Protected dashboard routes with role-based layouts
  - `admin/` - Admin dashboard with nested pages (users, organizations, settings, etc.)
  - `provider/`, `billing/`, `legal/`, `supervisor/`, `support/` - Role-specific dashboards
  - `profile/` - User profile (accessible to all authenticated users)

**Route Protection**: The `(private)` route group uses a layout with `ProtectedRoute` component for client-side authentication checks.

### State Management Architecture

**Redux Store** ([store/store.ts](store/store.ts)):
- Uses Redux Toolkit with Redux Persist for state persistence
- Only the `auth` slice is persisted (whitelist configuration)
- Middleware includes RTK Query for API caching

**Slices**:
- [store/slices/authSlice.ts](store/slices/authSlice.ts) - Authentication state (user, tokens, expiry)
- [store/slices/modalSlice.ts](store/slices/modalSlice.ts) - Modal management

**API Slices** (RTK Query):
- [store/api/apiSlice.ts](store/api/apiSlice.ts) - Base API configuration with token refresh logic
- [store/api/authApiSlice.ts](store/api/authApiSlice.ts) - Auth endpoints (login, register, logout)
- [store/api/usersApiSlice.ts](store/api/usersApiSlice.ts) - User management
- [store/api/organizationsApiSlice.ts](store/api/organizationsApiSlice.ts) - Organization management
- [store/api/organizationTypesApiSlice.ts](store/api/organizationTypesApiSlice.ts) - Organization types
- [store/api/subscriptionPlansApiSlice.ts](store/api/subscriptionPlansApiSlice.ts) - Subscription plans

**Important**: The base API slice automatically handles:
- Adding Authorization headers (except for auth endpoints)
- Token refresh on 401 responses
- Automatic logout and redirect on failed token refresh

### Authentication Flow

1. User logs in via [app/(public)/auth/login/page.tsx](app/(public)/auth/login/page.tsx)
2. Credentials are sent to `/api/login` endpoint
3. On success, `setCredentials` action stores token and user in Redux
4. Redux Persist saves auth state to localStorage
5. Protected routes check for token via `useAuth` hook
6. API calls automatically include Bearer token
7. On 401 error, token refresh is attempted automatically
8. Failed refresh triggers logout and redirect to login

**Token Management**: Access tokens expire after `expires_in` seconds. The `apiSlice` base query handles automatic refresh using the refresh token.

### Role-Based Access Control

**Role Constants** ([lib/roleConstants.ts](lib/roleConstants.ts)):
```typescript
ROLES = {
  ADMIN: "admin",
  PROVIDER_STAFF: "provider_staff",
  BILLING_TEAM: "billing_team",
  LAW_FIRM_STAFF: "law_firm_staff",
  SUPERVISOR: "supervisor",
  TECH_SUPPORT: "tech_support"
}
```

**Role-Specific Routing**:
- Each role has a specific dashboard path defined in `DASHBOARD_PATHS`
- Navigation menus filter items based on user role
- Use `usePermissions` hook for role checks in components
- Profile page is accessible to all authenticated users (no role restriction)

### Component Architecture

**Reusable Systems**:

1. **Modal System** ([components/modals/README.md](components/modals/README.md))
   - Centralized modal state in Redux
   - Two modal types: confirm modals and custom component modals
   - Use `useModal` hook to open/close modals
   - Test page: `/test/modal-test`

2. **Filter Components** ([components/ui/filters/README.md](components/ui/filters/README.md))
   - `SearchFilter` - Debounced search input
   - `DropdownFilter` - Multi-select dropdown with search
   - `UserFilter` - User selection with avatars
   - `DateRangeFilter` - Date range picker with presets
   - `FilterContainer` - Layout wrapper for filters
   - Test page: `/test/filters-test`

3. **Notification System**
   - Uses `sonner` library via `useNotifications` hook
   - Centralized toast management
   - Test page: `/test/notifications-test`

**Layout Components**:
- Role-specific layouts in [components/layouts/](components/layouts/)
- Each role (provider, legal, supervisor, support) has its own layout with custom navigation
- Layouts include sidebar navigation with role-based menu items

**Guards & Protection**:
- [components/protected-route.tsx](components/protected-route.tsx) - Client-side route protection
- [components/guards/](components/guards/) - Additional guard components

### Custom Hooks

- [hooks/useAuth.ts](hooks/useAuth.ts) - Access auth state from Redux
- [hooks/useModal.ts](hooks/useModal.ts) - Modal management
- [hooks/useApiError.ts](hooks/useApiError.ts) - Standardized API error handling
- [hooks/useDarkMode.ts](hooks/useDarkMode.ts) - Theme management
- [hooks/usePermissions.ts](hooks/usePermissions.ts) - Role-based permission checks
- [hooks/useBreadcrumbs.ts](hooks/useBreadcrumbs.ts) - Dynamic breadcrumb generation
- [hooks/useNotifications.ts](hooks/useNotifications.ts) - Toast notifications

### Import Aliases

TypeScript is configured with `@/*` path alias pointing to the root directory:
```typescript
import { useAuth } from "@/hooks/useAuth";
import { Button } from "@/components/ui/button";
```

## Development Guidelines

### Adding New Features

**When adding a new protected page**:
1. Create page in `app/(private)/dashboard/[role]/` directory
2. Route protection is handled by the `(private)` layout
3. Use `useAuth` hook to access current user
4. Use `usePermissions` for additional role checks

**When adding new API endpoints**:
1. Define endpoint in appropriate API slice (or create new one)
2. Use `apiSlice.injectEndpoints()` pattern
3. Add appropriate tags for cache invalidation (see `TAG_TYPES` in [store/api/apiSlice.ts](store/api/apiSlice.ts))
4. Authorization headers are added automatically by base query

**When adding new Redux state**:
1. Create slice in [store/slices/](store/slices/)
2. Add reducer to [store/store.ts](store/store.ts)
3. Update `whitelist` in persist config if state should be persisted
4. Export hooks from [store/hooks.ts](store/hooks.ts)

### Code Patterns

**API Error Handling**:
- RTK Query handles errors automatically
- Use `useApiError` hook for consistent error display
- Error responses follow API convention: `{ message: string, errors?: object }`

**Component Composition**:
- Use functional components with hooks
- Prefer composition over prop drilling
- Extract reusable logic into custom hooks
- Use Ant Design components for tables, forms, and complex UI
- Use shadcn/ui components for basic UI elements

**Type Safety**:
- Define interfaces for all API responses
- Use TypeScript strict mode
- Export types from [types/](types/) directory when shared across files

### Build Configuration

**Next.js Config** ([next.config.mjs](next.config.mjs)):
- ESLint and TypeScript errors are ignored during builds
- Images are unoptimized (no Next.js image optimization)

**Important**: While builds ignore errors, you should still run `pnpm lint` and `npx tsc --noEmit` locally to catch issues.

## Common Development Patterns

### Creating a New Role-Based Dashboard Page

```typescript
// app/(private)/dashboard/admin/new-page/page.tsx
"use client";

import { ProtectedRoute } from "@/components/protected-route";
import { ROLES } from "@/lib/roleConstants";

export default function NewPage() {
  return (
    <ProtectedRoute requiredRole={ROLES.ADMIN}>
      <div>
        {/* Page content */}
      </div>
    </ProtectedRoute>
  );
}
```

### Using RTK Query for Data Fetching

```typescript
import { useGetUsersQuery } from "@/store/api/usersApiSlice";

const { data, error, isLoading, refetch } = useGetUsersQuery({
  page: 1,
  per_page: 10
});
```

### Opening a Modal

```typescript
import { useModal } from "@/hooks/useModal";

const { openConfirmModal, openCustomModal } = useModal();

// Confirm modal
openConfirmModal("Delete User", "Are you sure?", () => {
  // Action on confirm
});

// Custom modal
openCustomModal(UserDetailsModal, { userId: 123 });
```

## Testing Pages

- `/test/modal-test` - Test modal system
- `/test/filters-test` - Test filter components
- `/test/notifications-test` - Test notification system
- `/test-table` - Test table components

## Environment Variables

Required environment variables (create `.env.local`):
```env
NEXT_PUBLIC_API_BASE_URL=https://faydaapi.dotprogrammers.com
```

## Troubleshooting

**Build fails with type errors**: Run `npx tsc --noEmit` to see all type errors. While builds ignore errors, fixing them improves code quality.

**Authentication not persisting**: Check Redux DevTools to verify `auth` state is being persisted. Clear localStorage if state is corrupted.

**API calls failing**: Check Network tab for request headers. Verify token is being sent and API base URL is correct.

**Role-based routing issues**: Verify role string matches exactly with `ROLES` constants. Check `DASHBOARD_PATHS` mapping.
