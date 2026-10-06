<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('patients');
            $table->foreignId('doctor_id')->constrained('patients');
            $table->dateTime('scheduled_at');
            $table->string('status')->default('scheduled');
            $table->timestamps();
            $table->index(['doctor_id', 'patient_id']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};