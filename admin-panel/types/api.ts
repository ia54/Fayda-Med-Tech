/**
 * Centralized Type Definitions for FaydaTech API — PDF Standard 2025
 */

export interface ApiResponse<T> {
  status: boolean;
  message: string;
  data: T;
}

export interface Pagination {
  current_page: number;
  per_page: number;
  total: number;
  last_page: number;
}

export interface PaginatedResponse<T> {
  data: T[];
  pagination: Pagination;
}

// Case Management
export interface Case {
  id: number;
  case_number: string;
  title: string;
  status: 'active' | 'settled' | 'closed' | 'archived';
  description?: string;
  organization_id: number;
  created_at: string;
  updated_at: string;
  metadata?: Record<string, any>;
}

// Documents
export interface Document {
  id: number;
  title: string;
  original_name: string;
  filename: string;
  mime_type: string;
  size: number;
  url: string;
  document_status: string;
  signature_status: string;
  ocr_status: string;
  uploaded_by: number;
  created_at: string;
  metadata?: Record<string, any>;
}

// Medical Records
export interface MedicalHistory {
  id: number;
  user_id: number;
  case_id?: number;
  record_date: string;
  provider_name: string;
  diagnosis?: string;
  treatment_description?: string;
  medications?: string;
  record_type: string;
  notes?: string;
}

export interface HipaaAuthorization {
  id: number;
  user_id: number;
  case_id?: number;
  authorization_type: string;
  status: 'pending' | 'signed' | 'expired' | 'revoked';
  issue_date?: string;
  expiry_date?: string;
  recipient_name?: string;
  signed_at?: string;
}

export interface MedicalRecordRequest {
  id: number;
  case_id?: number;
  user_id: number;
  requested_by: number;
  provider_name: string;
  request_date: string;
  status: 'pending' | 'sent' | 'received' | 'partially_received' | 'closed';
}

// Reports
export interface ReportSummary {
  id: number;
  report_name: string;
  report_type: string;
  status: string;
  completed_at?: string;
  result_summary: any;
  generated_by: number;
}

// Billing
export interface Invoice {
  id: number;
  case_id: number;
  invoice_number: string;
  amount: number;
  status: 'pending' | 'sent' | 'paid' | 'overdue' | 'cancelled';
  due_date: string;
}

export interface Payment {
  id: number;
  invoice_id: number;
  amount: number;
  payment_method: string;
  transaction_id?: string;
  paid_at: string;
}
