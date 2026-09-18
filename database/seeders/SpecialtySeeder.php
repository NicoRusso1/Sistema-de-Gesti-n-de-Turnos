<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Specialty;


class SpecialtySeeder extends Seeder
{
    public function run(): void
    {
        Specialty::create([
            'name' => 'Cardiología',
            'description' => 'Especialidad médica que se ocupa del diagnóstico y tratamiento de las enfermedades del corazón y del sistema circulatorio.',
        ]);

        Specialty::create([
            'name' => 'Pediatria',
            'description' => 'Atención médica de niños y adolescentes'
        ]);

        Specialty::create([
            'name' => 'Clínica Médica',
            'description' => 'Atención general de adultos'
        ]);

        Specialty::create([
            'name' => 'Ginecología',
            'description' => 'Especialidad médica que se ocupa del diagnóstico y tratamiento de las enfermedades del sistema reproductor femenino.'
        ]);
    }
}