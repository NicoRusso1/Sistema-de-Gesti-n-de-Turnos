<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointment_states', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // reserved / paid / waiting / cancelled / completed
            $table->string('description')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_states');
    }
};
