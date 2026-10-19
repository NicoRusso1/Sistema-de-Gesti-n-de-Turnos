<?php

namespace Database\Seeders;

use App\Models\Sala;
use App\Models\Specialty;
use Illuminate\Database\Seeder;

class SalaSeeder extends Seeder
{
    public function run(): void
    {
        // [numero, piso, especialidad, estado]
        // Dos salas por especialidad para probar la asignación automática (GT-41),
        // una fuera de servicio que nunca se asigna y una general sin especialidad.
        $salas = [
            ['101', 1, 'Cardiología', 'disponible'],
            ['102', 1, 'Cardiología', 'disponible'],
            ['103', 1, 'Clínica Médica', 'disponible'],
            ['104', 1, 'Clínica Médica', 'disponible'],
            ['201', 2, 'Pediatria', 'disponible'],
            ['202', 2, 'Pediatria', 'disponible'],
            ['203', 2, 'Ginecología', 'disponible'],
            ['204', 2, 'Ginecología', 'disponible'],
            ['105', 1, 'Cardiología', 'fuera_de_servicio'],
            ['001', 0, null, 'disponible'],
        ];

        $specialtyIds = Specialty::pluck('id', 'name');

        // firstOrCreate: se puede correr el seeder varias veces sin duplicar salas
        foreach ($salas as [$numero, $piso, $specialty, $estado]) {
            Sala::firstOrCreate(
                ['numero' => $numero, 'piso' => $piso],
                [
                    'especialidad_id' => $specialty ? $specialtyIds->get($specialty) : null,
                    'estado' => $estado,
                ]
            );
        }
    }
}
