import { z } from 'zod';

// Role enum
export const UserRoleEnum = z.enum([
  'admin',
  'firm_admin',
  'attorney',
  'medical_biller',
  'provider_staff',
  'pharmacist',
  'pharmacy_technician',
  'client',
]);

export type UserRole = z.infer<typeof UserRoleEnum>;

// Status enum
export const UserStatusEnum = z.enum(['active', 'inactive']);

export type UserStatus = z.infer<typeof UserStatusEnum>;

// User validation schema
export const userSchema = z.object({
  first_name: z
    .string()
    .min(1, 'First name is required')
    .min(2, 'First name must be at least 2 characters')
    .max(50, 'First name must not exceed 50 characters'),

  last_name: z
    .string()
    .min(1, 'Last name is required')
    .min(2, 'Last name must be at least 2 characters')
    .max(50, 'Last name must not exceed 50 characters'),

  email: z
    .string()
    .min(1, 'Email is required')
    .email('Invalid email address'),

  password: z
    .string()
    .optional()
    .refine(
      (val) => !val || val.length >= 8,
      'Password must be at least 8 characters if provided'
    ),

  organization_id: z
    .number({
      required_error: 'Organization is required',
      invalid_type_error: 'Organization must be selected',
    })
    .positive('Organization is required'),

  role: UserRoleEnum,

  status: UserStatusEnum,

  send_email: z.boolean().optional(),
});

// Create user schema (password required)
export const createUserSchema = userSchema.extend({
  password: z
    .string()
    .min(1, 'Password is required')
    .min(8, 'Password must be at least 8 characters')
    .regex(
      /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/,
      'Password must contain at least one uppercase letter, one lowercase letter, and one number'
    ),
});

// Update user schema (password optional)
export const updateUserSchema = userSchema.extend({
  password: z
    .string()
    .optional()
    .refine(
      (val) => !val || (val.length >= 8 && /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/.test(val)),
      'Password must be at least 8 characters and contain at least one uppercase letter, one lowercase letter, and one number if provided'
    ),
});

// Infer TypeScript types
export type UserFormValues = z.infer<typeof userSchema>;
export type CreateUserFormValues = z.infer<typeof createUserSchema>;
export type UpdateUserFormValues = z.infer<typeof updateUserSchema>;

// Role display names
export const ROLE_LABELS: Record<UserRole, string> = {
  admin: 'Super Admin',
  firm_admin: 'Firm Admin',
  attorney: 'Attorney',
  medical_biller: 'Medical Biller',
  provider_staff: 'Provider Staff',
  pharmacist: 'Pharmacist',
  pharmacy_technician: 'Pharmacy Technician',
  client: 'Client (Patient)',
};

// Status display names
export const STATUS_LABELS: Record<UserStatus, string> = {
  active: 'Active',
  inactive: 'Inactive',
};
