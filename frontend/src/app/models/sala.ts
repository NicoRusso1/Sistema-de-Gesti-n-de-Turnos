import { Specialty } from './specialty';

export interface Sala {
  id: number;
  numero: string;
  piso: number;
  especialidad_id: number | null;
  estado: 'disponible' | 'fuera_de_servicio';
  specialty?: Specialty | null;
  created_at?: string;
  updated_at?: string;
}

export interface SalaFormData {
  numero: string;
  piso: number;
  especialidad_id: number | null;
  estado: 'disponible' | 'fuera_de_servicio';
}

export interface SalaDisponibilidad {
  id: number;
  numero: string;
  piso: number;
  especialidad: string;
  estado_base: string;
  ocupacion: 'disponible' | 'ocupada';
  turno_asociado?: { id: number; detalle: string } | null;
}