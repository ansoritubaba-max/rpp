<?php

namespace Database\Seeders;

use App\Models\Curriculum;
use App\Models\CurriculumSubject;
use App\Models\EducationLevel;
use Illuminate\Database\Seeder;

class GeneralCurriculumSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedIndonesianLanguage();
        $this->seedMathematics();
    }

    private function seedIndonesianLanguage(): void
    {
        $curriculum = Curriculum::query()->where('code', 'KM')->firstOrFail();
        $source = 'https://kurikulum.kemdikbud.go.id/file/cp/dasmen/8.%20CP%20Bahasa%20Indonesia.pdf';
        $phases = [
            'A' => [
                ['Berbicara dan Mempresentasikan', 'Berbicara santun tentang topik yang dikenal, merespons percakapan, serta menceritakan kembali informasi atau narasi sederhana.', 'Menceritakan kembali informasi yang didengar dengan kalimat sendiri dan intonasi yang sesuai.'],
                ['Menulis', 'Menunjukkan keterampilan menulis permulaan dan menulis teks sederhana tentang pengalaman serta kehidupan sehari-hari.', 'Menulis beberapa kalimat sederhana tentang pengalaman diri dengan tulisan yang terbaca.'],
            ],
            'B' => [
                ['Berbicara dan Mempresentasikan', 'Berpartisipasi aktif dalam percakapan dan diskusi secara santun serta menceritakan kembali informasi dari beragam teks narasi.', 'Menyampaikan kembali isi teks narasi secara runtut dalam diskusi kelas.'],
                ['Menulis', 'Menulis narasi, deskripsi, rekon, prosedur, dan eksposisi dengan informasi yang rinci dan akurat.', 'Menulis teks prosedur dengan urutan langkah yang jelas dan informasi yang tepat.'],
            ],
            'C' => [
                ['Berbicara dan Mempresentasikan', 'Menyampaikan gagasan dan hasil pengamatan secara logis, sistematis, kreatif, dan santun untuk tujuan komunikasi tertentu.', 'Mempresentasikan hasil pengamatan dengan alasan yang runtut dan bahasa yang santun.'],
                ['Menulis', 'Menulis teks eksplanasi, laporan, dan eksposisi persuasif dari pengamatan atau pengalaman dengan kaidah bahasa yang sesuai.', 'Menyusun laporan singkat hasil pengamatan dengan hubungan sebab-akibat yang jelas.'],
            ],
            'D' => [
                ['Menyimak', 'Menganalisis informasi, pandangan, dan pesan dari beragam teks lisan maupun audiovisual serta mengevaluasi topik aktual yang didengar.', 'Mengidentifikasi gagasan utama dan menilai alasan dalam diskusi lisan tentang topik aktual.'],
                ['Membaca dan Memirsa', 'Memahami makna tersurat dan tersirat dalam teks visual atau audiovisual serta membandingkan informasi dengan sumber lain.', 'Membandingkan informasi dari dua teks dan menjelaskan alasan penilaian keakuratannya.'],
            ],
            'E' => [
                ['Menyimak', 'Mengevaluasi dan mengolah gagasan atau pesan dari teks fiksi dan nonfiksi yang disimak dalam beragam bentuk percakapan.', 'Menilai ketepatan gagasan dalam paparan lisan dengan menyebutkan bukti dari teks.'],
                ['Membaca dan Memirsa', 'Mengevaluasi gagasan dalam beragam teks visual dan audiovisual, termasuk mutu informasi dan perbandingannya dengan sumber lain.', 'Membandingkan isi dua sumber tentang isu yang sama dan menjelaskan kualitas informasinya.'],
            ],
            'F' => [
                ['Menyimak', 'Mengevaluasi gagasan dan pandangan dalam teks lisan berdasarkan logika serta menyusun tanggapan yang beralasan.', 'Menyampaikan tanggapan kritis terhadap pendapat dalam dialog dengan alasan yang dapat diuji.'],
                ['Membaca dan Memirsa', 'Mengevaluasi pandangan dalam teks fiksi maupun nonfiksi di media cetak dan elektronik serta mengapresiasi isinya.', 'Menilai argumen dalam teks nonfiksi dengan mempertimbangkan bukti dan logika penulis.'],
            ],
        ];

        foreach (EducationLevel::query()->with(['grades', 'subjects'])->whereIn('code', ['SD', 'MI', 'SMP', 'MTS', 'SMA', 'MA', 'SMK'])->get() as $level) {
            $subject = $level->subjects->firstWhere('code', 'BIND');
            if (! $subject) {
                continue;
            }

            foreach ($level->grades as $grade) {
                $elements = $phases[$grade->phase] ?? null;
                if (! $elements) {
                    continue;
                }

                $context = CurriculumSubject::query()->firstOrCreate([
                    'curriculum_id' => $curriculum->id,
                    'education_level_id' => $level->id,
                    'vocational_program_id' => null,
                    'grade_id' => $grade->id,
                    'subject_id' => $subject->id,
                    'semester' => null,
                ], ['is_active' => true]);

                foreach ($elements as $index => [$element, $summary, $objectiveText]) {
                    $outcome = $context->learningOutcomes()->firstOrCreate(
                        ['phase' => $grade->phase, 'element' => $element],
                        ['content' => $summary, 'source_url' => $source, 'source_kind' => 'summary'],
                    );
                    $outcome->objectives()->firstOrCreate(
                        ['code' => 'TP-BIND-'.($index + 1)],
                        ['content' => $objectiveText, 'sort_order' => $index + 1, 'source_kind' => 'derived'],
                    );
                }
            }
        }
    }

    private function seedMathematics(): void
    {
        $curriculum = Curriculum::query()->where('code', 'KM')->firstOrFail();
        $source = 'https://repositori.kemendikdasmen.go.id/33608/1/5.%20Final%20Panduan%20Mata%20Pelajaran%20Matematika_12_09_2025_Revisi%203.pdf';
        $byPhase = [
            'A' => ['Memahami bilangan cacah sampai 100, nilai tempat, dan penjumlahan atau pengurangan sederhana dengan benda konkret.', 'Membandingkan dua bilangan cacah sampai 100 dan menjelaskan nilai tempatnya dengan benda konkret.'],
            'B' => ['Memahami nilai tempat dan urutan bilangan cacah sampai 10.000 serta menggunakannya dalam operasi hitung.', 'Mengurutkan bilangan cacah sampai 10.000 dan menjelaskan strategi perbandingannya.'],
            'C' => ['Memahami bilangan cacah sampai 1.000.000, operasi hitung sampai 100.000, serta penerapan KPK dan FPB.', 'Menyelesaikan masalah sehari-hari yang melibatkan KPK atau FPB dengan alasan yang jelas.'],
            'D' => ['Mengoperasikan bilangan real dan menggunakan rasio, skala, proporsi, serta laju perubahan untuk menyelesaikan masalah.', 'Memecahkan masalah skala atau proporsi dengan menuliskan model dan langkah perhitungannya.'],
            'E' => ['Menggeneralisasi sifat bilangan berpangkat, termasuk pangkat pecahan, dan menggunakannya untuk menyelesaikan masalah.', 'Menerapkan sifat bilangan berpangkat untuk menyederhanakan dan menyelesaikan persoalan kontekstual.'],
            'F' => ['Menjelaskan barisan dan deret aritmetika maupun geometri serta menerapkannya pada bunga, pinjaman, dan investasi.', 'Memodelkan pertumbuhan investasi dengan deret geometri dan menafsirkan hasilnya.'],
        ];

        foreach (EducationLevel::query()->with(['grades', 'subjects'])->whereIn('code', ['SD', 'MI', 'SMP', 'MTS', 'SMA', 'MA', 'SMK'])->get() as $level) {
            $subject = $level->subjects->firstWhere('code', 'MAT');
            if (! $subject) {
                continue;
            }

            foreach ($level->grades as $grade) {
                if (! isset($byPhase[$grade->phase])) {
                    continue;
                }

                [$summary, $objectiveText] = $byPhase[$grade->phase];
                $context = CurriculumSubject::query()->firstOrCreate([
                    'curriculum_id' => $curriculum->id,
                    'education_level_id' => $level->id,
                    'vocational_program_id' => null,
                    'grade_id' => $grade->id,
                    'subject_id' => $subject->id,
                    'semester' => null,
                ], ['is_active' => true]);
                $outcome = $context->learningOutcomes()->firstOrCreate(
                    ['phase' => $grade->phase, 'element' => 'Bilangan'],
                    ['content' => $summary, 'source_url' => $source, 'source_kind' => 'summary'],
                );
                $outcome->objectives()->firstOrCreate(
                    ['code' => 'TP-MAT-1'],
                    ['content' => $objectiveText, 'sort_order' => 1, 'source_kind' => 'derived'],
                );
            }
        }
    }
}
