export type EstadoEmision = 'pendiente' | 'emitida' | 'fallida';

// GT-219: receta digital emitida por el médico al finalizar la consulta
export interface Prescription {
  id: number;
  appointment_id: number;
  doctor_id: number;
  patient_id: number;
  content: string;
  issue_date: string | null;
  file_url: string | null;
  estado_emision: EstadoEmision;
  error_emision: string | null;
  created_at: string;
}

export interface PrescriptionResponse {
  message: string;
  prescription: Prescription;
  error?: string;
}

// GT-220: ítem del historial de recetas (GET /recetas)
export interface RecetaHistorialItem {
  id: number;
  turno_id: number;
  fecha_turno: string | null;
  fecha_emision: string | null;
  medico: string;
  paciente: string | null;
  especialidad: string;
  contenido: string;
  estado_emision: EstadoEmision;
  tiene_archivo: boolean;
}

export interface RecetasHistorialResponse {
  data: RecetaHistorialItem[];
  total: number;
}
