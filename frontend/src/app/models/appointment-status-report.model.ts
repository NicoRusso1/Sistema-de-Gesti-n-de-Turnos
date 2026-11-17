// GT-67: reporte de turnos por estado
export type EstadoTurnoReporte = 'reservado' | 'abonado' | 'en_espera' | 'completado' | 'cancelado';

export interface EstadoTurnoTotal {
  estado: EstadoTurnoReporte;
  etiqueta: string;
  descripcion: string;
  cantidad: number;
  porcentaje: number;
}

export interface ReporteTurnosPorEstado {
  estados: EstadoTurnoTotal[];
  total: number;
  filtros: {
    fecha_desde: string | null;
    fecha_hasta: string | null;
    specialty_id: number | null;
    doctor_id: number | null;
  };
  generado_en: string;
  opciones: {
    especialidades: { id: number; name: string }[];
    medicos: { id: number; nombre: string }[];
  };
}
