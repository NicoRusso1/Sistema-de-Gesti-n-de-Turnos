<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->foreignId('health_insurance_id')
                ->nullable()
                ->after('specialty_id')
                ->constrained('health_insurances')
                ->nullOnDelete();

            $table->string('affiliate_number', 50)
                ->nullable()
                ->after('health_insurance_id');

            $table->decimal('monto_base', 10, 2)
                ->default(0)
                ->after('affiliate_number');

            $table->decimal('monto_descuento', 10, 2)
                ->default(0)
                ->after('monto_base');

            $table->decimal('monto_final', 10, 2)
                ->default(0)
                ->after('monto_descuento');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('health_insurance_id');
            $table->dropColumn([
                'affiliate_number',
                'monto_base',
                'monto_descuento',
                'monto_final',
            ]);
        });
    }
};