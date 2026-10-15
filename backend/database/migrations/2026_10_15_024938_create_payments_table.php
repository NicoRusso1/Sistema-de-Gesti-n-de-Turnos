<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->constrained('appointments');
            $table->foreignId('payment_method_id')->nullable()->constrained('payment_methods');
            $table->foreignId('payment_status_id')->constrained('payment_statuses');
            $table->decimal('amount', 10, 2);
            $table->dateTime('payment_date')->nullable();
            $table->string('provider_preference_id')->nullable();
            $table->string('provider_payment_id')->nullable()->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};