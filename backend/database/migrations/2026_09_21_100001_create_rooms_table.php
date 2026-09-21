<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->string('number');
            $table->string('floor')->nullable();
            // Sala reservada para una especialidad puntual, si aplica (Sección 5 del documento).
            $table->foreignId('specialty_id')->nullable()->constrained('specialties')->nullOnDelete();
            $table->tinyInteger('status')->default(1); // 1 = disponible, 0 = fuera de servicio
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rooms');
    }
};
