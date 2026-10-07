<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void
    {
        Schema::create('medico_especialidad', function (Blueprint $table) {
            $table->id();
            // Los médicos viven en la tabla patients
            $table->foreignId('medico_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignId('especialidad_id')->constrained('specialties')->restrictOnDelete();
            // Un médico no puede tener dos veces la misma especialidad
            $table->unique(['medico_id', 'especialidad_id']);
        });
        // Se pasa a la tabla nueva la especialidad única que tenía cada médico
        $rows = DB::table('patients')
            ->whereNotNull('specialty_id')
            ->get(['id', 'specialty_id'])
            ->map(fn($p) => ['medico_id' => $p->id, 'especialidad_id' => $p->specialty_id])
            ->all();
        if ($rows) {
            DB::table('medico_especialidad')->insert($rows);
        }
        // La columna de "una sola especialidad" queda reemplazada por la tabla nueva
        Schema::table('patients', function (Blueprint $table) {
            $table->dropConstrainedForeignId('specialty_id');
        });
    }
    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->foreignId('specialty_id')
                ->nullable()
                ->after('user_type_id')
                ->constrained('specialties')
                ->restrictOnDelete();
        });
        // Se conserva una especialidad por médico (la primera asignada)
        $rows = DB::table('medico_especialidad')->orderBy('id')->get();
        foreach ($rows->unique('medico_id') as $row) {
            DB::table('patients')
                ->where('id', $row->medico_id)
                ->update(['specialty_id' => $row->especialidad_id]);
        }
        Schema::dropIfExists('medico_especialidad');
    }
};