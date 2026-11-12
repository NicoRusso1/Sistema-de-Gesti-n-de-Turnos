export interface DesglosePago {
  monto_original: number;
  porcentaje_descuento: number;
  descuento_aplicado: number;
  monto_final: number;
  tiene_obra_social: boolean;
  obra_social_nombre: string | null;
  afiliado_numero: string | null;
}

export interface PagoResponse {
  message: string;
  estado: 'aprobado' | 'rechazado' | 'pendiente';
  motivo?: string;
  pago: {
    id: number;
    turno_id: number;
    monto_original: number;
    descuento_aplicado: number;
    monto_final: number;
    estado: string;
    transaccion_id?: string;
    fecha_pago?: string;
  };
  trazabilidad: DesglosePago;
}