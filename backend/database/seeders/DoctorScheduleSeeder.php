<?php

namespace Database\Seeders;

use App\Models\DoctorSchedule;
use App\Models\Patient;
use Illuminate\Database\Seeder;

class DoctorScheduleSeeder extends Seeder
{
    public function run(): void
    {
        $doctor = Patient::where('email', 'medico@hospital.test')->firstOrFail();

        $ranges = [
            ['09:00', '13:00'],
            ['15:00', '18:00'],
        ];

        foreach (range(1, 5) as $day) {
            foreach ($ranges as [$start, $end]) {
                DoctorSchedule::firstOrCreate([
                    'doctor_id' => $doctor->id,
                    'day_of_week' => $day,
                    'start_time' => $start,
                ], [
                    'end_time' => $end,
                ]);
            }
        }
    }
}