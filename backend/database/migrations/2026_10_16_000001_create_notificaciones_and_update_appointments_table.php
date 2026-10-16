<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 1. Crear tabla de notificaciones (GT-59)
        if (!Schema::hasTable('notificaciones')) {
            Schema::create('notificaciones', function (Blueprint $table) {
                $table->id();
                $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
                $table->foreignId('appointment_id')->nullable()->constrained('appointments')->nullOnDelete();
                $table->string('tipo', 50); // 'cancelacion', 'reprogramacion', 'recordatorio', etc.
                $table->string('titulo', 150);
                $table->text('mensaje');
                $table->boolean('leido')->default(false);
                $table->timestamps();

                $table->index(['patient_id', 'leido']);
            });
        }

        // 2. Agregar soporte para sala_id y motivo de cancelación en appointments si no existen
        Schema::table('appointments', function (Blueprint $table) {
            if (!Schema::hasColumn('appointments', 'sala_id')) {
                $table->foreignId('sala_id')
                    ->nullable()
                    ->after('specialty_id')
                    ->constrained('salas')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('appointments', 'cancellation_reason')) {
                $table->string('cancellation_reason', 255)
                    ->nullable()
                    ->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notificaciones');

        Schema::table('appointments', function (Blueprint $table) {
            if (Schema::hasColumn('appointments', 'sala_id')) {
                $table->dropConstrainedForeignId('sala_id');
            }
            if (Schema::hasColumn('appointments', 'cancellation_reason')) {
                $table->dropColumn('cancellation_reason');
            }
        });
    }
};