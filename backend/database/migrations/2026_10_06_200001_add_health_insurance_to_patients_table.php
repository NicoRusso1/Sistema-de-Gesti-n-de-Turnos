<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->foreignId('health_insurance_id')
                ->nullable()
                ->after('phone')
                ->constrained('health_insurances');
            $table->string('health_insurance_number')
                ->nullable()
                ->after('health_insurance_id');
        });
    }
    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropConstrainedForeignId('health_insurance_id');
            $table->dropColumn('health_insurance_number');
        });
    }
};