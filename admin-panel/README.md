# Fayda Wellness Pharmacy Admin Dashboard

A modern, responsive admin dashboard for Fayda Wellness Pharmacy built with Next.js, TypeScript, and Redux Toolkit.

## Features

- **Authentication System**
  - User registration
  - Login/logout functionality
  - Password reset
  - JWT token management
  - Role-based access control

- **State Management**
  - Redux Toolkit for global state
  - RTK Query for API calls
  - Redux Persist for state persistence
  - Modular slice architecture

- **UI Components**
  - Responsive sidebar navigation
  - Top navigation bar with user profile
  - Dark mode support
  - Reusable UI components
  - Role-based menu filtering

- **Dashboard Pages**
  - Admin dashboard with analytics
  - Role-specific dashboards
  - User profile management
  - System settings

## Tech Stack

- **Frontend Framework**: Next.js 14 (App Router)
- **Language**: TypeScript
- **State Management**: Redux Toolkit + RTK Query
- **UI Components**: shadcn/ui + Tailwind CSS
- **Styling**: Tailwind CSS
- **Authentication**: JWT tokens
- **Icons**: Lucide React

## Project Structure

```
app/
  ├── auth/                 # Authentication pages
  │   ├── login/
  │   ├── signup/
  │   ├── forgot-password/
  │   └── reset-password/
  ├── dashboard/            # Dashboard pages
  │   ├── admin/            # Admin-specific pages
  │   │   └── settings/
  │   └── page.tsx          # Dashboard redirect
  ├── profile/              # User profile page
  └── page.tsx              # Home page redirect

components/
  ├── layouts/              # Page layouts
  ├── nav/                  # Navigation components
  ├── providers/            # Context providers
  └── ui/                   # Reusable UI components

hooks/
  ├── useAuth.ts            # Authentication hook
  ├── useApiError.ts        # API error handling
  └── useDarkMode.ts        # Dark mode hook

lib/
  ├── auth.ts               # Authentication utilities
  └── utils.ts              # General utilities

store/
  ├── api/                  # RTK Query API slices
  ├── slices/               # Redux slices
  └── store.ts              # Redux store configuration

types/                      # TypeScript type definitions
```

## Authentication Flow

1. **Registration**: Users register with email, password, and role
2. **Login**: Users authenticate with email/password
3. **Token Management**: JWT tokens are stored in Redux and persisted
4. **Protected Routes**: Middleware protects authenticated routes
5. **Role-based Access**: UI adapts based on user role
6. **Auto Refresh**: Tokens automatically refresh when expired

## Dark Mode Implementation

The application supports both light and dark themes:

- Uses `next-themes` for theme management
- Persists theme preference in localStorage
- Respects system preference by default
- Custom styling for both themes

## Role-based Navigation

Different user roles see different menu items:

- **Admin**: Full access to all features
- **Supervisor**: Limited admin access
- **Provider Staff**: Provider-specific features
- **Billing Team**: Billing-related features
- **Law Firm Staff**: Legal case management
- **Tech Support**: Support tools

## Getting Started

1. Install dependencies:
   ```bash
   npm install
   ```

2. Run the development server:
   ```bash
   npm run dev
   ```

3. Open [http://localhost:3000](http://localhost:3000) in your browser

## Environment Variables

Create a `.env.local` file with the following variables:

```env
NEXT_PUBLIC_API_BASE_URL=https://faydaapi.dotprogrammers.com
```

## API Integration

The application integrates with the Fayda API at `https://faydaapi.dotprogrammers.com` with the following endpoints:

- `POST /api/register` - User registration
- `POST /api/login` - User authentication
- `POST /api/logout` - User logout
- `POST /api/refresh-token` - Token refresh

## State Persistence

User authentication state is persisted using Redux Persist:

- Auth tokens and user data survive page refreshes
- State is automatically rehydrated on app load
- Sensitive data is properly handled

## Development Guidelines

- Follow SOLID principles
- Use TypeScript for type safety
- Implement DRY architecture
- Maintain modular, reusable components
- Write clean, well-documented code

## Deployment

Build the application for production:

```bash
npm run build
```

Start the production server:

```bash
npm start
```
#xgx
