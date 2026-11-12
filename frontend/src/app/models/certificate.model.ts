export interface CertificadoItem {
  id: number;
  turno_id: number;
  fecha_emision: string;
  fecha_emision_fmt: string;
  medico: {
    nombre: string;
    especialidad: string;
  };
  estado_turno: string;
  valido: boolean;
  motivo_invalidez: string | null;
  tiene_archivo: boolean;
}

export interface CertificadosResponse {
  data: CertificadoItem[];
  total: number;
}