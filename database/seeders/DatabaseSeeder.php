<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Curriculum;
use App\Models\EducationLevel;
use App\Models\Feature;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        if (app()->environment('production')) {
            throw new \RuntimeException('DatabaseSeeder berisi akun demo dengan sandi publik dan tidak boleh dijalankan di Production. Pulihkan backup terverifikasi atau siapkan seeder Production yang aman.');
        }

        $admin = User::query()->create([
            'name' => 'Admin RuangAjar',
            'email' => 'admin@ruangajar.id',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        $teacher = User::query()->create([
            'name' => 'Ibu Siti Rahmawati',
            'email' => 'guru@ruangajar.id',
            'password' => Hash::make('password'),
            'role' => 'guru',
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        $teacher->schools()->create([
            'name' => 'SD Negeri Harapan Bangsa',
            'npsn' => '20501234',
            'address' => 'Jl. Pendidikan No. 12',
            'village' => 'Sukamaju',
            'district' => 'Taman Sari',
            'city' => 'Kota Bandung',
            'province' => 'Jawa Barat',
            'email' => 'info@sdnharapan.sch.id',
            'principal_name' => 'Drs. Ahmad Santoso',
            'principal_identifier' => '196805121994031006',
        ]);

        Curriculum::query()->insert([
            ['code' => 'KM', 'name' => 'Kurikulum Merdeka', 'is_active' => true],
            ['code' => 'K13', 'name' => 'Kurikulum 2013', 'is_active' => true],
        ]);

        AcademicYear::query()->firstOrCreate(['name' => '2026/2027'], [
            'name' => '2026/2027',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
            'is_active' => true,
        ]);

        $levelData = [
            ['code' => 'SD', 'name' => 'Sekolah Dasar', 'type' => 'school', 'grades' => range(1, 6)],
            ['code' => 'MI', 'name' => 'Madrasah Ibtidaiyah', 'type' => 'madrasah', 'grades' => range(1, 6)],
            ['code' => 'SMP', 'name' => 'Sekolah Menengah Pertama', 'type' => 'school', 'grades' => range(7, 9)],
            ['code' => 'MTS', 'name' => 'Madrasah Tsanawiyah', 'type' => 'madrasah', 'grades' => range(7, 9)],
            ['code' => 'SMA', 'name' => 'Sekolah Menengah Atas', 'type' => 'school', 'grades' => range(10, 12)],
            ['code' => 'MA', 'name' => 'Madrasah Aliyah', 'type' => 'madrasah', 'grades' => range(10, 12)],
        ];

        foreach ($levelData as $sort => $item) {
            $level = EducationLevel::query()->create([
                'code' => $item['code'], 'name' => $item['name'], 'type' => $item['type'], 'sort_order' => $sort + 1,
            ]);

            foreach ($item['grades'] as $grade) {
                $phase = match (true) {
                    $grade <= 2 => 'A', $grade <= 4 => 'B', $grade <= 6 => 'C',
                    $grade <= 9 => 'D', $grade === 10 => 'E', default => 'F',
                };
                $level->grades()->create([
                    'name' => "Kelas {$grade}", 'numeric_value' => $grade, 'phase' => $phase, 'sort_order' => $grade,
                ]);
            }

            $subjects = ['BIND' => 'Bahasa Indonesia', 'MAT' => 'Matematika', 'IPAS' => 'IPAS', 'PPKN' => 'Pendidikan Pancasila', 'SB' => 'Seni Budaya'];
            if (max($item['grades']) >= 7) {
                $subjects += ['IPA' => 'Ilmu Pengetahuan Alam', 'IPS' => 'Ilmu Pengetahuan Sosial', 'BIG' => 'Bahasa Inggris'];
            }
            if ($item['type'] === 'madrasah') {
                $subjects += ['QH' => "Al-Qur'an Hadis", 'AA' => 'Akidah Akhlak', 'FIK' => 'Fikih', 'SKI' => 'Sejarah Kebudayaan Islam', 'BAR' => 'Bahasa Arab'];
            }
            foreach ($subjects as $code => $name) {
                $level->subjects()->create([
                    'code' => $code,
                    'name' => $name,
                    'is_religious' => in_array($code, ['QH', 'AA', 'FIK', 'SKI', 'BAR']),
                    'is_active' => true,
                ]);
            }
        }

        $this->call(VocationalProgramSeeder::class);
        $this->call(VerifiedCurriculumSeeder::class);
        $this->call(OfficialCurriculumSeeder::class);

        $featureNames = [
            'rpp' => 'RPP', 'modul_ajar' => 'Modul Ajar', 'silabus' => 'Silabus',
            'lkpd' => 'LKPD', 'soal' => 'Generator Soal', 'kisi_kisi' => 'Kisi-Kisi',
            'prota' => 'Program Tahunan', 'promes' => 'Program Semester', 'atp' => 'ATP',
            'bank_soal' => 'Bank Soal',
        ];
        foreach ($featureNames as $code => $name) {
            Feature::query()->create(['code' => $code, 'name' => $name, 'description' => "Buat {$name} secara terstruktur."]);
        }

        $plan = Plan::query()->create([
            'name' => 'Teacher Pro',
            'description' => 'Semua perangkat inti untuk guru aktif.',
            'price' => 79000,
            'duration_days' => 30,
            'is_active' => true,
        ]);

        foreach (Feature::all() as $feature) {
            $plan->features()->attach($feature->id, [
                'is_enabled' => true,
                'generation_limit' => $feature->code === 'rpp' ? 30 : 20,
                'regeneration_limit' => 60,
            ]);
        }

        $this->call(DocumentTemplateSeeder::class);

        $teacher->subscriptions()->create([
            'plan_id' => $plan->id,
            'starts_at' => now()->startOfDay(),
            'expires_at' => now()->addDays(24)->endOfDay(),
            'status' => 'active',
            'created_by' => $admin->id,
        ]);

        $this->call(ProductPlansSeeder::class);
    }
}
