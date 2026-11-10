<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('turnos:cancelar-expirados')
    ->everyFiveMinutes()
    ->withoutOverlapping();

// Corre cada hora y avisa a los turnos que entran en el plazo de recordatorio (24 hs por defecto)
Schedule::command('turnos:enviar-recordatorios')
    ->hourly()
    ->withoutOverlapping();

// Reintenta las facturas cuya emisión o envío por email falló
Schedule::command('facturas:reintentar')
    ->everyFifteenMinutes()
    ->withoutOverlapping();
