<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('prescriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->constrained('appointments')->cascadeOnDelete();
            $table->foreignId('doctor_id')->constrained('patients');
            $table->foreignId('patient_id')->constrained('patients');
            $table->text('content');
            $table->dateTime('issue_date')->nullable();
            $table->string('file_url')->nullable();
            // El contenido se guarda antes de generar el PDF: si falla, se reintenta con lo mismo
            $table->enum('estado_emision', ['pendiente', 'emitida', 'fallida'])->default('pendiente');
            $table->text('error_emision')->nullable();
            $table->timestamps();

            $table->index(['appointment_id', 'estado_emision']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prescriptions');
    }
};
