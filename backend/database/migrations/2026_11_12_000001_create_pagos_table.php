<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pagos', function (Blueprint $table) {
            $table->id();
            // FK al turno (en el sistema la tabla es 'appointments')
            $table->foreignId('turno_id')
                ->constrained('appointments')
                ->cascadeOnDelete();

            // Trazabilidad de montos
            $table->decimal('monto_original', 10, 2);
            $table->decimal('descuento_aplicado', 10, 2)->default(0.00);
            $table->decimal('monto_final', 10, 2);

            // Estados: pendiente, aprobado, rechazado, cancelado
            $table->string('estado', 30)->default('pendiente');

            // Información de pasarela y método
            $table->string('metodo_pago', 50)->nullable();
            $table->string('transaccion_id')->nullable()->unique();
            $table->string('provider_preference_id')->nullable();
            $table->dateTime('fecha_pago')->nullable();
            $table->text('error_mensaje')->nullable();

            $table->timestamps();

            // Índices para optimizar búsquedas y reportes
            $table->index(['turno_id', 'estado']);
            $table->index('transaccion_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pagos');
    }
};