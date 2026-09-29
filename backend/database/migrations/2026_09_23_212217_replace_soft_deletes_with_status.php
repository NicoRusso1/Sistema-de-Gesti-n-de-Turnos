<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Los pacientes "borrados" pasan a estar dados de baja
        DB::table('patients')->whereNotNull('deleted_at')->update(['status' => 0]);

        // Los que tienen status NULL (ej. el SuperAdmin del seeder) pasan a activos
        DB::table('patients')->whereNull('deleted_at')->whereNull('status')->update(['status' => 1]);

        // Las especialidades borradas se eliminan de verdad (si no, reaparecen como activas)
        DB::table('specialties')->whereNotNull('deleted_at')->delete();

        Schema::table('patients', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->tinyInteger('status')->default(1)->change();
        });

        Schema::table('specialties', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->softDeletes();
            $table->tinyInteger('status')->nullable()->change();
        });

        Schema::table('specialties', function (Blueprint $table) {
            $table->softDeletes();
        });

        DB::table('patients')->where('status', 0)->update(['deleted_at' => now()]);
    }
};
