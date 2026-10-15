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