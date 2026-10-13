<?php

namespace Database\Seeders;

use App\Models\EducationLevel;
use Illuminate\Database\Seeder;

class VocationalProgramSeeder extends Seeder
{
    public function run(): void
    {
        $level = EducationLevel::query()->firstOrCreate(
            ['code' => 'SMK'],
            ['name' => 'Sekolah Menengah Kejuruan', 'type' => 'school', 'sort_order' => 7],
        );

        foreach ([10 => 'E', 11 => 'F', 12 => 'F'] as $number => $phase) {
            $level->grades()->firstOrCreate(
                ['numeric_value' => $number],
                ['name' => "Kelas {$number}", 'phase' => $phase, 'sort_order' => $number],
            );
        }

        foreach (['BIND' => 'Bahasa Indonesia', 'MAT' => 'Matematika', 'BIG' => 'Bahasa Inggris', 'PPKN' => 'Pendidikan Pancasila'] as $code => $name) {
            $level->subjects()->firstOrCreate(['code' => $code], ['name' => $name, 'is_religious' => false, 'is_active' => true]);
        }

        $programs = [
            'TKJ' => 'Teknik Komputer dan Jaringan',
            'OTM' => 'Teknik Otomotif',
            'TKR' => 'Teknik Kendaraan Ringan',
            'TBSM' => 'Teknik dan Bisnis Sepeda Motor',
            'DKV' => 'Desain Komunikasi Visual',
            'DKF' => 'DKF',
            'TBG' => 'Tata Boga / Kuliner',
        ];

        $position = 0;
        foreach ($programs as $code => $name) {
            $position++;
            $program = $level->vocationalPrograms()->firstOrCreate(
                ['code' => $code],
                ['name' => $name, 'is_active' => true, 'sort_order' => $position],
            );
            $level->subjects()->firstOrCreate(
                ['code' => $code],
                ['name' => $name, 'vocational_program_id' => $program->id, 'is_religious' => false, 'is_active' => true],
            );
        }
    }
}
