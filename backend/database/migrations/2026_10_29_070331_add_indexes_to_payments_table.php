<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->index('payment_status_id', 'payments_status_id_idx');
            $table->index('payment_date', 'payments_date_idx');
            $table->index(['payment_status_id', 'payment_date'], 'payments_status_date_idx');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex('payments_status_id_idx');
            $table->dropIndex('payments_date_idx');
            $table->dropIndex('payments_status_date_idx');
        });
    }
};