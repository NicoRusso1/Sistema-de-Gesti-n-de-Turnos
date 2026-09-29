<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salas', function (Blueprint $table) {
            $table->id();
            $table->string('numero');
            $table->integer('piso');
            $table->foreignId('especialidad_id')
                  ->nullable()
                  ->constrained('specialties')
                  ->nullOnDelete();
            $table->enum('estado', ['disponible', 'fuera_de_servicio'])->default('disponible');
            $table->timestamps();

            // Restricción única: no puede haber dos salas con el mismo número en el mismo piso
            $table->unique(['numero', 'piso']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salas');
    }
};