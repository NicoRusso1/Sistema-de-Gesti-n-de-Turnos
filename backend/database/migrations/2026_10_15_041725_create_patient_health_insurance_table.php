<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patient_health_insurance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->unique()->constrained('patients')->cascadeOnDelete();
            $table->foreignId('health_insurance_id')->constrained('health_insurances');
            $table->string('member_number', 50)->nullable();
        });

        $rows = DB::table('patients')
            ->whereNotNull('health_insurance_id')
            ->get(['id', 'health_insurance_id', 'health_insurance_number'])
            ->map(fn($p) => [
                'patient_id' => $p->id,
                'health_insurance_id' => $p->health_insurance_id,
                'member_number' => $p->health_insurance_number,
            ])
            ->all();

        if ($rows) {
            DB::table('patient_health_insurance')->insert($rows);
        }

        Schema::table('patients', function (Blueprint $table) {
            $table->dropConstrainedForeignId('health_insurance_id');
            $table->dropColumn('health_insurance_number');
        });
    }

    public function down(): void
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

        foreach (DB::table('patient_health_insurance')->get() as $row) {
            DB::table('patients')->where('id', $row->patient_id)->update([
                'health_insurance_id' => $row->health_insurance_id,
                'health_insurance_number' => $row->member_number,
            ]);
        }

        Schema::dropIfExists('patient_health_insurance');
    }
};