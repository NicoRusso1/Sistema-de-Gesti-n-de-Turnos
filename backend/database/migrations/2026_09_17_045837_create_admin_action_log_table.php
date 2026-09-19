<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::create('admin_action_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('superadmin_id')->constrained('patients');
            $table->foreignId('affected_admin_id')->constrained('patients');
            $table->foreignId('action_id')->constrained('admin_actions');
            $table->dateTime('date')->useCurrent();
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('admin_action_log');
    }
};
