<?php

return [

    /*
    | Cuántas horas antes del turno se envía el recordatorio por email.
    | El cron corre cada hora y avisa a los turnos que entran en este plazo.
    */
    'reminder_hours_before' => (int) env('REMINDER_HOURS_BEFORE', 24),

];
