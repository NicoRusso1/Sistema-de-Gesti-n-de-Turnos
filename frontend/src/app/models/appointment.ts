import { HealthInsurance } from './health-insurance';
import { Specialty } from './specialty';

export interface Appointment {
  id: number;
  patient_id: number;
  doctor_id: number;
  specialty_id: number | null;
  scheduled_at: string;
  status: 'reservado' | 'scheduled' | 'confirmed' | 'cancelled';
  health_insurance_id: number | null;
  affiliate_number: string | null;
  monto_base: number;
  monto_descuento: number;
  monto_final: number;
  doctor?: {
    id: number;
    first_name: string;
    last_name: string;
  };
  specialty?: Specialty;
  healthInsurance?: HealthInsurance;
}

export interface AppointmentCreatePayload {
  doctor_id: number;
  specialty_id?: number | null;
  scheduled_at: string; // Formato: YYYY-MM-DD HH:mm
  health_insurance_id?: number | null;
  affiliate_number?: string | null;
  monto_base: number;
}

export interface AppointmentResponse {
  message: string;
  appointment: Appointment;
}