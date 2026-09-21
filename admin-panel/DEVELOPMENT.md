# Fayda Wellness Pharmacy Admin - Development Guide

## Getting Started

### Prerequisites
- Node.js (version specified in package.json)
- pnpm (package manager)
- Git

### Installation
1. Clone the repository
2. Install dependencies:
   ```bash
   pnpm install
   ```

### Development Server
Start the development server with hot reloading:
```bash
pnpm dev
```
The application will be available at http://localhost:3000

## Development Workflow

### Project Structure
```
app/                 # Next.js app directory routes
components/          # Reusable UI components
hooks/               # Custom React hooks
lib/                 # Utility functions and constants
store/               # Redux store configuration
styles/              # Global styles and Tailwind configuration
public/              # Static assets
```

### Creating New Features
1. Follow the existing component structure and naming conventions
2. Use TypeScript for all new components and functions
3. Place components in appropriate directories
4. Use absolute imports with `@/` prefix

### Code Quality Standards

#### TypeScript
- Use strong typing for all functions and components
- Define interfaces for props and state
- Run type checking regularly:
  ```bash
  npx tsc --noEmit
  ```

#### ESLint
- Follow Next.js core-web-vitals rules
- Run linting to check for issues:
  ```bash
  pnpm lint
  ```
- Automatically fix issues when possible:
  ```bash
  pnpm lint --fix
  ```

#### Component Development
- Use functional components with React hooks
- Implement proper error handling
- Follow existing patterns in the codebase
- Add JSDoc comments for functions and components

## Build Process

### Development Build
```bash
pnpm dev
```

### Production Build
```bash
pnpm build
```

### Start Production Server
```bash
pnpm start
```

## Testing

### Type Checking
```bash
npx tsc --noEmit
```

### Linting
```bash
pnpm lint
```

### Manual Testing
1. Run development server
2. Test functionality in browser
3. Check console for errors
4. Verify responsive design

## Deployment Checklist

Before committing changes:
1. Run production build: `pnpm build`
2. Run type checking: `npx tsc --noEmit`
3. Run linting: `pnpm lint`
4. Test functionality manually
5. Verify no console errors

## Project Specific Rules

### Authentication
- Use the centralized ProtectedRoute component for role-based access control
- Profile page uses generic ProtectedRoute without requiredRole for all authenticated users
- Role constants are centralized in `lib/roleConstants.ts`

### Routing
- Use Next.js App Router conventions
- Implement layout-based protection instead of individual page wrappers
- Follow existing route structure patterns

### State Management
- Use Redux Toolkit for global state
- Implement RTK Query for API calls
- Use Redux Persist for state persistence

### UI Components
- Use shadcn/ui components when available
- Follow existing styling patterns
- Implement responsive design with Tailwind CSS

### Error Handling
- Display user-friendly error messages
- Log errors appropriately
- Handle API error responses gracefully

## Common Commands

| Command | Description |
|---------|-------------|
| `pnpm dev` | Start development server |
| `pnpm build` | Create production build |
| `pnpm start` | Start production server |
| `pnpm lint` | Check for code quality issues |
| `pnpm lint --fix` | Fix automatically fixable issues |
| `npx tsc --noEmit` | Run TypeScript type checking |

## Troubleshooting

### ESLint Issues
1. Ensure ESLint dependencies are installed
2. Check `.eslintrc.json` configuration
3. Restart development server after configuration changes

### Build Failures
1. Check for TypeScript errors
2. Verify all imports are correct
3. Ensure environment variables are set

### Development Server Issues
1. Clear browser cache
2. Restart development server
3. Check console for specific error messages