<?php

namespace Database\Seeders;

use App\Models\DocumentTemplate;
use App\Models\Feature;
use Illuminate\Database\Seeder;

class DocumentTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $sectionMap = [
            'rpp' => ['objectives' => 'A. Tujuan Pembelajaran', 'material' => 'B. Materi Pembelajaran', 'opening' => 'C. Kegiatan Pendahuluan', 'main_activity' => 'D. Kegiatan Inti', 'closing' => 'E. Kegiatan Penutup', 'assessment' => 'F. Asesmen', 'differentiation' => 'G. Remedial dan Pengayaan', 'reflection' => 'H. Refleksi'],
            'modul_ajar' => ['informasi_umum' => 'Informasi Umum', 'kompetensi_awal' => 'Kompetensi Awal', 'tujuan' => 'Tujuan Pembelajaran', 'pemahaman_bermakna' => 'Pemahaman Bermakna', 'pertanyaan_pemantik' => 'Pertanyaan Pemantik', 'kegiatan' => 'Kegiatan Pembelajaran', 'asesmen' => 'Asesmen', 'tindak_lanjut' => 'Remedial, Pengayaan, dan Refleksi'],
            'silabus' => ['rasional' => 'Rasional', 'silabus' => 'Pemetaan Pembelajaran'],
            'lkpd' => ['petunjuk' => 'Petunjuk Belajar', 'tujuan' => 'Tujuan Kegiatan', 'aktivitas' => 'Langkah Aktivitas', 'hasil' => 'Lembar Hasil'],
            'soal' => ['daftar_soal' => 'Daftar Soal', 'kisi_kisi_ringkas' => 'Kisi-Kisi Ringkas'],
            'kisi_kisi' => ['kisi_kisi' => 'Kisi-Kisi Penilaian'],
            'prota' => ['landasan' => 'Landasan Penyusunan', 'program_tahunan' => 'Program Tahunan'],
            'promes' => ['ringkasan' => 'Ringkasan Semester', 'program_semester' => 'Program Semester'],
            'atp' => ['capaian' => 'Capaian Pembelajaran', 'rasional' => 'Rasional Alur', 'alur_tujuan' => 'Alur Tujuan Pembelajaran'],
        ];

        foreach (Feature::query()->whereIn('code', array_keys($sectionMap))->get() as $feature) {
            $template = DocumentTemplate::query()->firstOrCreate(
                ['name' => 'Template Standar '.$feature->name, 'feature_id' => $feature->id],
                ['paper_size' => 'a4', 'orientation' => 'portrait', 'margin_top' => 20, 'margin_right' => 18, 'margin_bottom' => 20, 'margin_left' => 18, 'font_family' => 'DejaVu Sans', 'font_size' => 10, 'line_height' => 1.5, 'is_active' => true],
            );

            foreach (array_keys($sectionMap[$feature->code]) as $index => $key) {
                $title = $sectionMap[$feature->code][$key];
                $template->sections()->firstOrCreate(
                    ['section_key' => $key],
                    ['title' => $title, 'sort_order' => $index + 1, 'source_type' => 'hybrid', 'is_required' => in_array($index, [0, 1], true), 'is_enabled' => true],
                );
            }
        }
    }
}
