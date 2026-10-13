<?php

namespace Database\Seeders;

use App\Models\Curriculum;
use App\Models\CurriculumSubject;
use App\Models\EducationLevel;
use Illuminate\Database\Seeder;

/**
 * A deliberately small, traceable starter set. CP text is a paraphrased summary
 * of the linked official document; TP is a locally derived example, not an
 * official prescribed objective. No content is invented for unverified majors.
 */
class VerifiedCurriculumSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedSmkMerdeka();
        $this->seedK13Mathematics();
        $this->call(GeneralCurriculumSeeder::class);
    }

    private function seedSmkMerdeka(): void
    {
        $curriculum = Curriculum::query()->where('code', 'KM')->firstOrFail();
        $level = EducationLevel::query()->where('code', 'SMK')->firstOrFail();

        $references = [
            'TKJ' => [
                'url' => 'https://kurikulum.kemdikbud.go.id/file/cp/smk/117.%20CP%20Mata%20Pelajaran%20Teknik%20Komputer%20dan%20Jaringan.pdf',
                'elements' => [
                    ['Perencanaan dan Pengalamatan Jaringan', 'Merencanakan topologi jaringan berdasarkan kebutuhan pengguna serta menentukan pengalamatan dengan subnetting, CIDR, dan VLSM.', 'Menyusun rancangan topologi dan skema alamat IP untuk sebuah jaringan lokal sesuai kebutuhan pengguna.'],
                    ['Teknologi Jaringan Kabel dan Nirkabel', 'Memasang, menguji, merawat, dan memperbaiki jaringan kabel maupun nirkabel, termasuk layanan VoIP dan jaringan serat optik.', 'Memasang jaringan lokal berkabel, menguji koneksinya, dan mencatat hasil pemeriksaan.'],
                ],
            ],
            'TKR' => [
                'url' => 'https://kurikulum.kemdikbud.go.id/file/cp/smk/67.%20CP%20Mata%20Pelajaran%20Teknik%20Kendaraan%20Ringan.pdf',
                'elements' => [
                    ['Proses Pelayanan dan Manajemen Bengkel Kendaraan Ringan', 'Menerapkan alur penerimaan servis, pengelolaan alat dan suku cadang, pemeriksaan mutu, serta penyerahan kendaraan.', 'Memetakan urutan penerimaan dan pemeriksaan mutu servis kendaraan ringan.'],
                    ['Perawatan Berkala Kendaraan Ringan', 'Melaksanakan perawatan berkala kendaraan ringan sesuai jarak tempuh dan prosedur kerja bengkel.', 'Menyusun daftar pemeriksaan perawatan berkala kendaraan ringan berdasarkan jarak tempuh.'],
                ],
            ],
            'TBSM' => [
                'url' => 'https://kurikulum.kemdikbud.go.id/file/cp/smk/68.%20CP%20Mata%20Pelajaran%20Teknik%20Sepeda%20Motor.pdf',
                'elements' => [
                    ['Perawatan dan Perbaikan Engine Sepeda Motor', 'Merawat dan memperbaiki mesin sepeda motor beserta komponennya pada beragam jenis kendaraan.', 'Mengidentifikasi komponen mesin sepeda motor dan menyusun langkah pemeriksaan sesuai prosedur.'],
                    ['Perawatan dan Perbaikan Sistem Kelistrikan Sepeda Motor', 'Memeriksa, merawat, dan memperbaiki sistem kelistrikan sepeda motor secara menyeluruh.', 'Melakukan pemeriksaan dasar sistem kelistrikan sepeda motor dan mendokumentasikan temuannya.'],
                ],
            ],
            'DKV' => [
                'url' => 'https://kurikulum.kemdikbud.go.id/file/cp/smk/164.%20CP%20Mata%20Pelajaran%20Desain%20Komunikasi%20Visual.pdf',
                'elements' => [
                    ['Prinsip Dasar Desain dan Komunikasi', 'Menerapkan prinsip komposisi, keseimbangan, penekanan, dan kejelasan untuk menyampaikan pesan melalui rancangan visual.', 'Membuat rancangan visual sederhana dan menjelaskan pilihan prinsip desainnya.'],
                    ['Perangkat Lunak Desain', 'Mengoperasikan perangkat lunak desain yang sesuai dengan kebutuhan karya komunikasi visual.', 'Memilih perangkat lunak yang sesuai dan menghasilkan satu artefak desain untuk kebutuhan tertentu.'],
                ],
            ],
            'TBG' => [
                'url' => 'https://kurikulum.kemdikbud.go.id/file/cp/smk/159.%20CP%20Mata%20Pelajaran%20Kuliner.pdf',
                'elements' => [
                    ['Kesehatan dan Keselamatan Kerja (K3)', 'Menerapkan keselamatan kerja, alat pelindung diri, kebersihan dapur, dan kebersihan pribadi dalam kegiatan kuliner.', 'Menunjukkan prosedur kebersihan dan keselamatan sebelum praktik pengolahan makanan.'],
                    ['Persiapan Alat dan Bahan', 'Memilih, menimbang, menyimpan, menggunakan, dan membersihkan alat serta bahan makanan sesuai karakteristiknya.', 'Menyiapkan alat dan bahan sesuai resep serta menjelaskan cara penyimpanannya.'],
                ],
            ],
        ];

        foreach ($references as $programCode => $reference) {
            $program = $level->vocationalPrograms()->where('code', $programCode)->firstOrFail();
            $subject = $level->subjects()->where('code', $programCode)->firstOrFail();

            foreach ([11, 12] as $gradeNumber) {
                $grade = $level->grades()->where('numeric_value', $gradeNumber)->firstOrFail();
                $context = CurriculumSubject::query()->firstOrCreate([
                    'curriculum_id' => $curriculum->id,
                    'education_level_id' => $level->id,
                    'vocational_program_id' => $program->id,
                    'grade_id' => $grade->id,
                    'subject_id' => $subject->id,
                    'semester' => null,
                ], ['is_active' => true]);

                foreach ($reference['elements'] as $index => [$element, $summary, $objectiveText]) {
                    $outcome = $context->learningOutcomes()->firstOrCreate(
                        ['phase' => 'F', 'element' => $element],
                        ['content' => $summary, 'source_url' => $reference['url'], 'source_kind' => 'summary'],
                    );
                    $outcome->objectives()->firstOrCreate(
                        ['code' => 'TP-'.($index + 1)],
                        ['content' => $objectiveText, 'sort_order' => $index + 1, 'source_kind' => 'derived'],
                    );
                }
            }
        }
    }

    private function seedK13Mathematics(): void
    {
        $curriculum = Curriculum::query()->where('code', 'K13')->firstOrFail();
        $level = EducationLevel::query()->where('code', 'SMP')->firstOrFail();
        $grade = $level->grades()->where('numeric_value', 7)->firstOrFail();
        $subject = $level->subjects()->where('code', 'MAT')->firstOrFail();
        $context = CurriculumSubject::query()->firstOrCreate([
            'curriculum_id' => $curriculum->id,
            'education_level_id' => $level->id,
            'vocational_program_id' => null,
            'grade_id' => $grade->id,
            'subject_id' => $subject->id,
            'semester' => null,
        ], ['is_active' => true]);

        $url = 'https://jdih.kemendikdasmen.go.id/sjdih/siperpu/dokumen/salinan/Permendikbud%20Nomor%2037%20Tahun%202018.pdf';
        foreach ([
            ['3.5', 'Menjelaskan bentuk aljabar dan melakukan operasi penjumlahan, pengurangan, perkalian, dan pembagian bentuk aljabar.'],
            ['4.5', 'Menyelesaikan masalah yang berkaitan dengan bentuk aljabar dan operasi pada bentuk aljabar.'],
        ] as [$code, $content]) {
            $context->competencies()->firstOrCreate(
                ['type' => 'KD', 'code' => $code],
                ['content' => $content, 'semester' => null, 'source_url' => $url, 'source_kind' => 'summary'],
            );
        }

        $this->seedAdditionalK13Mathematics($curriculum, $url);
    }

    private function seedAdditionalK13Mathematics(Curriculum $curriculum, string $url): void
    {
        $coverage = [
            ['level' => 'MI', 'grade' => 2, 'pairs' => [
                ['3.1', 'Menjelaskan makna bilangan cacah dan lambangnya menurut nilai tempat dengan model konkret.'],
                ['4.1', 'Membaca dan menyajikan bilangan cacah berdasarkan nilai tempat dengan model konkret.'],
            ]],
            ['level' => 'SD', 'grade' => 2, 'pairs' => [
                ['3.1', 'Menjelaskan makna bilangan cacah dan lambangnya menurut nilai tempat dengan model konkret.'],
                ['4.1', 'Membaca dan menyajikan bilangan cacah berdasarkan nilai tempat dengan model konkret.'],
            ]],
            ['level' => 'MTS', 'grade' => 7, 'pairs' => [
                ['3.5', 'Menjelaskan bentuk aljabar dan melakukan operasi penjumlahan, pengurangan, perkalian, dan pembagian bentuk aljabar.'],
                ['4.5', 'Menyelesaikan masalah yang berkaitan dengan bentuk aljabar dan operasi pada bentuk aljabar.'],
            ]],
        ];

        foreach (['SMA', 'MA', 'SMK'] as $levelCode) {
            $coverage[] = ['level' => $levelCode, 'grade' => 10, 'pairs' => [
                ['3.1', 'Menginterpretasi persamaan dan pertidaksamaan nilai mutlak dari bentuk linear satu variabel.'],
                ['4.1', 'Menyelesaikan masalah yang berkaitan dengan persamaan dan pertidaksamaan nilai mutlak bentuk linear satu variabel.'],
            ]];
        }

        foreach ($coverage as $item) {
            $level = EducationLevel::query()->where('code', $item['level'])->firstOrFail();
            $grade = $level->grades()->where('numeric_value', $item['grade'])->firstOrFail();
            $subject = $level->subjects()->where('code', 'MAT')->firstOrFail();
            $context = CurriculumSubject::query()->firstOrCreate([
                'curriculum_id' => $curriculum->id,
                'education_level_id' => $level->id,
                'vocational_program_id' => null,
                'grade_id' => $grade->id,
                'subject_id' => $subject->id,
                'semester' => null,
            ], ['is_active' => true]);

            foreach ($item['pairs'] as [$code, $content]) {
                $context->competencies()->firstOrCreate(
                    ['type' => 'KD', 'code' => $code],
                    ['content' => $content, 'semester' => null, 'source_url' => $url, 'source_kind' => 'summary'],
                );
            }
        }
    }
}
