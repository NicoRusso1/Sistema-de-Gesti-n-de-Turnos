export interface MetricasGlobales {
  periodo: {
    desde: string;
    hasta: string;
    dias: number;
  };
  kpis: {
    ingresos_totales: number;
    total_turnos: number;
    total_salas_activas: number;
    total_usuarios_act: number;
  };
  turnos_por_especialidad: Array<{
    especialidad: string;
    total: number;
    porcentaje: number;
  }>;
  ocupacion_salas: Array<{
    sala_id: number;
    numero: string;
    piso: number;
    estado: string;
    turnos_atendidos: number;
  }>;
  usuarios_desglose: {
    pacientes_activos: number;
    medicos_activos: number;
    administradores_activos: number;
    total_activos: number;
  };
  estados_turnos: Record<string, number>;
}