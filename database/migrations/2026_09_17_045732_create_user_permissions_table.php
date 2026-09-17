<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::create('user_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('patients');
            $table->boolean('view_own_appointments')->default(false);
            $table->boolean('view_all_appointments')->default(false);
            $table->boolean('view_assigned_patients')->default(false);
            $table->boolean('cancel_appointments')->default(false);
            $table->boolean('edit_schedules')->default(false);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_permissions');
    }
};
