import { Appointment } from './appointment';

export interface PaymentStartResponse {
  message: string;
  payment_id: number;
  checkout_url: string;
}

export interface Payment {
  id: number;
  appointment_id: number;
  amount: string;
  payment_date: string | null;
  status: { id: number; name: 'pending' | 'approved' | 'rejected' };
  method: { id: number; name: 'transfer' | 'credit' | 'debit' } | null;
  appointment: Appointment;
}

export interface PaymentVerifyResponse {
  message: string;
  payment: Payment;
}

export interface PaymentFilters {
  status?: string;
  fecha_desde?: string;
  fecha_hasta?: string;
  patient_id?: number;
  page?: number;
  per_page?: number;
}

export interface PaginatedPayments {
  current_page: number;
  data: Payment[];
  total: number;
  last_page: number;
  per_page: number;
}