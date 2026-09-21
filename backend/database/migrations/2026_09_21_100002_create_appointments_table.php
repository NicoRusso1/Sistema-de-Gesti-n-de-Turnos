<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('patients');
            $table->foreignId('doctor_id')->constrained('patients');
            $table->foreignId('room_id')->constrained('rooms');
            $table->date('date');
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedSmallInteger('duration')->default(15); // minutos, valor fijo (Sección 2 del documento)
            $table->foreignId('state_id')->constrained('appointment_states');

            // health_insurance_id, base_amount, discount_amount y final_amount
            // quedan para cuando se implemente el módulo de obras sociales/pago
            // (Secciones 4.1 y 4.2 del documento). No se agregan todavía para
            // no dejar una FK apuntando a una tabla que no existe.

            $table->dateTime('creation_date')->useCurrent();

            // Un mismo médico no puede tener dos turnos que empiecen a la
            // misma hora el mismo día.
            $table->unique(['doctor_id', 'date', 'start_time']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
