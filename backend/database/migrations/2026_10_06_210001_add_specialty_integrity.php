<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void
    {
        // Si el seeder se corrió más de una vez hay nombres repetidos: se conserva el primero
        $duplicates = DB::table('specialties')
            ->select('name', DB::raw('MIN(id) as keep_id'))
            ->groupBy('name')
            ->havingRaw('COUNT(*) > 1')
            ->get();
        foreach ($duplicates as $duplicate) {
            $ids = DB::table('specialties')
                ->where('name', $duplicate->name)
                ->where('id', '!=', $duplicate->keep_id)
                ->pluck('id');
            DB::table('salas')
                ->whereIn('especialidad_id', $ids)
                ->update(['especialidad_id' => $duplicate->keep_id]);
            DB::table('specialties')->whereIn('id', $ids)->delete();
        }
        Schema::table('specialties', function (Blueprint $table) {
            $table->unique('name');
        });
        // Especialidad del médico (los médicos viven en la tabla patients)
        Schema::table('patients', function (Blueprint $table) {
            $table->foreignId('specialty_id')
                ->nullable()
                ->after('user_type_id')
                ->constrained('specialties')
                ->restrictOnDelete();
        });
        // Especialidad por la que se reservó el turno
        Schema::table('appointments', function (Blueprint $table) {
            $table->foreignId('specialty_id')
                ->nullable()
                ->after('doctor_id')
                ->constrained('specialties')
                ->restrictOnDelete();
        });
    }
    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('specialty_id');
        });
        Schema::table('patients', function (Blueprint $table) {
            $table->dropConstrainedForeignId('specialty_id');
        });
        Schema::table('specialties', function (Blueprint $table) {
            $table->dropUnique(['name']);
        });
    }
};