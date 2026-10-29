export interface AppNotification {
  id: number;
  patient_id: number;
  appointment_id?: number | null;
  tipo: string;
  titulo: string;
  mensaje: string;
  leido: boolean;
  created_at: string;
  patient?: {
    first_name: string;
    last_name: string;
    email: string;
  };
}