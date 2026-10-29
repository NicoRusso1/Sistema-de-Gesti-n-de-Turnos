import { HealthInsurance } from './health-insurance';
import { Specialty } from './specialty';

export interface Sala {
  id: number;
  numero: string;
  piso: number;
  estado: string;
}

export interface Appointment {
  id: number;
  patient_id: number;
  doctor_id: number;
  specialty_id: number | null;
  sala_id?: number | null;
  scheduled_at: string;
  status: 'reservado' | 'abonado' | 'scheduled' | 'confirmed' | 'cancelado' | 'cancelled';
  cancellation_reason?: string | null;
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
  patient?: {
    id: number;
    first_name: string;
    last_name: string;
    email: string;
  };
  specialty?: Specialty;
  sala?: Sala;
  healthInsurance?: HealthInsurance;
  
  certificado_url?: string | null;
  fecha_emision?: string | null;
}

// Sala que se puede asignar a mano a un turno (GET /appointments/{id}/available-salas)
export interface AssignableSala {
  id: number;
  numero: string;
  piso: number;
  especialidad: string;
  matches_specialty: boolean;
}

export interface AppointmentCreatePayload {
  doctor_id: number;
  specialty_id?: number | null;
  sala_id?: number | null;
  scheduled_at: string; // Formato: YYYY-MM-DD HH:mm
  health_insurance_id?: number | null;
  affiliate_number?: string | null;
  monto_base: number;
}

export interface AppointmentResponse {
  message: string;
  appointment: Appointment;
}

export interface AppointmentFilters {
  status?: string;
  date?: string;
  doctor_id?: number;
  patient_id?: number;
  page?: number;
}

// Alcance de la agenda que resuelve el backend según rol y permisos
export type AppointmentScope = 'own' | 'assigned' | 'all';

export interface PaginatedAppointments {
  scope: AppointmentScope;
  current_page: number;
  data: Appointment[];
  total: number;
  last_page: number;
  per_page: number;
}