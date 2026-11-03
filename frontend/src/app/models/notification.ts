export type EstadoEnvio = 'pendiente' | 'enviada' | 'fallida';

export interface NotificationFilters {
  estado?: EstadoEnvio | '';
  tipo?: string;
  desde?: string;
  hasta?: string;
  paciente?: string;
}

export interface AppNotification {
  id: number;
  patient_id: number;
  appointment_id?: number | null;
  tipo: string;
  titulo: string;
  mensaje: string;
  leido: boolean;
  estado_envio?: EstadoEnvio;
  error_envio?: string | null;
  enviada_at?: string | null;
  created_at: string;
  patient?: {
    first_name: string;
    last_name: string;
    email: string;
  };
}