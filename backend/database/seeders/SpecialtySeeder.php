<?php
namespace Database\Seeders;
use App\Models\Specialty;
use Illuminate\Database\Seeder;
class SpecialtySeeder extends Seeder
{
    public function run(): void
    {
        $specialties = [
            'Cardiología' => 'Diagnóstico y tratamiento de las enfermedades del corazón y del sistema circulatorio.',
            'Pediatria' => 'Atención médica de niños y adolescentes',
            'Clínica Médica' => 'Atención general de adultos',
            'Ginecología' => 'Diagnóstico y tratamiento de las enfermedades del sistema reproductor femenino.',
        ];
        // firstOrCreate: se puede correr el seeder varias veces sin duplicar nombres
        foreach ($specialties as $name => $description) {
            Specialty::firstOrCreate(['name' => $name], ['description' => $description]);
        }
    }
}