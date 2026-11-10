<?php

return [

    /*
    | Cuántas horas antes del turno se envía el recordatorio por email.
    | El cron corre cada hora y avisa a los turnos que entran en este plazo.
    */
    'reminder_hours_before' => (int) env('REMINDER_HOURS_BEFORE', 24),

    /*
    | Reintento automático de la factura cuando falla la emisión o el envío.
    | max_attempts: cuántos fallos tolera el cron antes de dejarlo para reintento manual.
    | days: solo revisa pagos aprobados en los últimos N días.
    */
    'invoice_retry' => [
        'max_attempts' => (int) env('INVOICE_RETRY_MAX_ATTEMPTS', 5),
        'days' => (int) env('INVOICE_RETRY_DAYS', 7),
    ],

];
