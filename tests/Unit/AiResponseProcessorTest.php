<?php

namespace Tests\Unit;

use App\Services\AI\AiResponseProcessor;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

class AiResponseProcessorTest extends TestCase
{
    public function test_repairs_json_fence_wrapper_and_safe_list_formatting(): void
    {
        $processor = new AiResponseProcessor;
        $content = "```json\n".json_encode(['data' => ['sections' => [
            ['key' => 'tujuan', 'title' => 'Tujuan', 'content' => "- Memahami pecahan\n- Membandingkan pecahan"],
        ]]], JSON_UNESCAPED_UNICODE)."\n```";

        $result = $processor->decode($content, 'custom_sections', [], ['sections']);

        $this->assertSame('list', $result['sections'][0]['type']);
        $this->assertSame(['Memahami pecahan', 'Membandingkan pecahan'], $result['sections'][0]['content']);
    }

    public function test_rejects_nested_invalid_section_instead_of_accepting_its_keys(): void
    {
        $this->expectException(UnexpectedValueException::class);

        (new AiResponseProcessor)->validate(['sections' => [[
            'key' => 'pemetaan', 'title' => 'Pemetaan', 'type' => 'table',
            'headers' => ['Materi', 'Tujuan'], 'content' => [['Pecahan']],
        ]]], 'silabus_sections', [], ['sections']);
    }

    public function test_generic_sections_repair_missing_key_and_safe_indonesian_field_aliases(): void
    {
        $result = (new AiResponseProcessor)->validate(['sections' => [
            ['judul' => 'Rasional', 'isi' => 'Silabus disusun untuk pembelajaran terarah.'],
            ['judul' => 'Pemetaan Pembelajaran', 'columns' => ['Materi', 'Tujuan'],
                'isi' => [['Bahasa Pemrograman', 'Menulis program sederhana']]],
        ]], 'custom_sections', [], ['sections']);

        $this->assertSame('rasional', $result['sections'][0]['key']);
        $this->assertSame('text', $result['sections'][0]['type']);
        $this->assertSame('pemetaan_pembelajaran', $result['sections'][1]['key']);
        $this->assertSame('table', $result['sections'][1]['type']);
        $this->assertSame(['Materi', 'Tujuan'], $result['sections'][1]['headers']);
    }

    public function test_rejects_question_without_a_valid_answer_and_options(): void
    {
        $this->expectException(UnexpectedValueException::class);

        (new AiResponseProcessor)->validate(['sections' => [[
            'key' => 'daftar_soal', 'title' => 'Soal', 'type' => 'questions',
            'content' => [['question' => 'Berapa 2 + 2?', 'answer' => 'A', 'type' => 'pilihan_ganda', 'options' => [['content' => '4']]]],
        ]]], 'soal_sections', [], ['sections']);
    }

    public function test_rejects_duplicate_questions_instead_of_publishing_repeated_items(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('pertanyaan yang berulang');

        (new AiResponseProcessor)->validate(['sections' => [[
            'key' => 'daftar_soal', 'title' => 'Daftar Soal', 'type' => 'questions',
            'content' => [
                ['question' => 'Jelaskan manfaat membaca.', 'answer' => 'Menambah pengetahuan.'],
                ['question' => '  jelaskan manfaat membaca. ', 'answer' => 'Menambah pengetahuan.'],
            ],
        ]]], 'soal_sections', [], ['sections']);
    }

    public function test_rejects_duplicate_activity_list_items(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('butir yang berulang');

        (new AiResponseProcessor)->validate(['sections' => [[
            'key' => 'aktivitas', 'title' => 'Langkah Aktivitas', 'type' => 'list',
            'content' => ['Amati gambar pecahan.', ' amati gambar pecahan. '],
        ]]], 'prota_sections', [], ['sections']);
    }

    public function test_lkpd_rejects_single_markdown_blob_even_when_json_is_valid(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('petunjuk, tujuan, aktivitas');

        (new AiResponseProcessor)->validate(['sections' => [[
            'key' => 'lembar_kegiatan_peserta_didik', 'title' => 'LKPD', 'type' => 'text',
            'content' => "## Kegiatan 1\nTuliskan jawabanmu.",
        ]]], 'lkpd_sections', ['settings' => ['activity_count' => 5]], ['sections']);
    }

    public function test_lkpd_requires_requested_number_of_activities_and_answer_rows(): void
    {
        $sections = [
            ['key' => 'petunjuk', 'title' => 'Petunjuk', 'type' => 'text', 'content' => 'Baca cerita, lalu kerjakan setiap kegiatan.'],
            ['key' => 'tujuan', 'title' => 'Tujuan', 'type' => 'list', 'content' => ['Menemukan amanat cerita.']],
            ['key' => 'aktivitas', 'title' => 'Aktivitas', 'type' => 'list', 'content' => [
                'Temukan tokoh utama dan tuliskan satu bukti dari cerita.',
                'Jelaskan amanat cerita dengan alasan yang dapat diperiksa.',
            ]],
            ['key' => 'hasil', 'title' => 'Lembar Hasil', 'type' => 'table',
                'headers' => ['Kegiatan', 'Jawaban'], 'content' => [['Kegiatan 1', ''], ['Kegiatan 2', '']]],
        ];

        $processor = new AiResponseProcessor;
        $result = $processor->validate(['sections' => $sections], 'lkpd_sections', ['settings' => ['activity_count' => 2]], ['sections']);
        $this->assertSame(['petunjuk', 'tujuan', 'aktivitas', 'hasil'], array_column($result['sections'], 'key'));

        $this->expectException(UnexpectedValueException::class);
        $processor->validate(['sections' => $sections], 'lkpd_sections', ['settings' => ['activity_count' => 3]], ['sections']);
    }

    public function test_normalizes_rpp_activity_and_assessment_but_rejects_empty_objectives(): void
    {
        $processor = new AiResponseProcessor;
        $result = $processor->validate([
            'objectives' => ['Memahami pecahan'],
            'apperception' => 'Mengamati kue.',
            'mainActivity' => ['Membandingkan bagian kue.'],
            'assessment' => ['formative' => [['technique' => 'Observasi']]],
        ], 'rpp_sections', [], ['objectives', 'apperception', 'main_activity', 'assessment']);

        $this->assertSame(['Membandingkan bagian kue.'], $result['main_activity']);
        $this->assertSame('Formatif', $result['assessment'][0]['type']);

        $this->expectException(UnexpectedValueException::class);
        $processor->validate([
            'objectives' => [], 'apperception' => 'Tes', 'main_activity' => ['Kegiatan'],
            'assessment' => [['type' => 'Formatif', 'method' => 'Observasi']],
        ], 'rpp_sections', [], ['objectives', 'apperception', 'main_activity', 'assessment']);
    }

    public function test_rpp_accepts_common_field_aliases_without_weakening_required_content(): void
    {
        $result = (new AiResponseProcessor)->validate([
            'tujuan_pembelajaran' => ['Menjelaskan proses penguapan air.'],
            'apersepsi' => 'Guru mengajak peserta didik mengamati embun di kelas.',
            'kegiatan_inti' => ['Peserta didik mengamati air yang dipanaskan dan mencatat perubahan.'],
            'asesmen' => [['type' => 'Formatif', 'method' => 'Catatan pengamatan dan penjelasan lisan.']],
        ], 'rpp_sections', [], ['objectives', 'apperception', 'main_activity', 'assessment']);

        $this->assertSame('Menjelaskan proses penguapan air.', $result['objectives'][0]);
        $this->assertSame('Formatif', $result['assessment'][0]['type']);
    }

    public function test_module_rejects_sections_that_would_disappear_from_print_template(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('seluruh bagian');

        (new AiResponseProcessor)->validate(['sections' => [[
            'key' => 'ringkasan', 'title' => 'Ringkasan Modul', 'type' => 'text',
            'content' => 'Peserta didik mengamati penguapan.',
        ]]], 'modul_ajar_sections', [], ['sections']);
    }

    public function test_module_accepts_eight_printable_sections(): void
    {
        $sections = [
            ['key' => 'informasi_umum', 'title' => 'Informasi Umum', 'type' => 'text', 'content' => 'Pembelajaran penguapan melalui pengamatan.'],
            ['key' => 'kompetensi_awal', 'title' => 'Kompetensi Awal', 'type' => 'list', 'content' => ['Mengenal perubahan wujud air.']],
            ['key' => 'tujuan', 'title' => 'Tujuan', 'type' => 'list', 'content' => ['Menjelaskan penguapan berdasarkan pengamatan.']],
            ['key' => 'pemahaman_bermakna', 'title' => 'Pemahaman Bermakna', 'type' => 'text', 'content' => 'Penguapan terjadi dalam kehidupan sehari-hari.'],
            ['key' => 'pertanyaan_pemantik', 'title' => 'Pertanyaan Pemantik', 'type' => 'list', 'content' => ['Mengapa pakaian basah dapat kering?']],
            ['key' => 'kegiatan', 'title' => 'Kegiatan', 'type' => 'list', 'content' => ['Pendahuluan: guru menunjukkan air.', 'Inti: peserta didik mengamati air menguap.', 'Penutup: peserta didik menyimpulkan temuan.']],
            ['key' => 'asesmen', 'title' => 'Asesmen', 'type' => 'table', 'headers' => ['Tahap', 'Teknik', 'Instrumen'], 'content' => [['Formatif', 'Observasi', 'Catatan pengamatan']]],
            ['key' => 'tindak_lanjut', 'title' => 'Tindak Lanjut', 'type' => 'list', 'content' => ['Remedial: ulangi pengamatan.', 'Pengayaan: bandingkan kondisi lain.', 'Refleksi: tulis hal yang dipahami.']],
        ];

        $result = (new AiResponseProcessor)->validate(['sections' => $sections], 'modul_ajar_sections', [], ['sections']);

        $this->assertSame(array_column($sections, 'key'), array_column($result['sections'], 'key'));
    }

    public function test_kisi_kisi_safely_wraps_shallow_rows_without_changing_cells(): void
    {
        $rows = [
            ['1', 'Pecahan', 'Menentukan pecahan dari gambar', 'C2', 'Uraian', '1'],
            ['2', 'Pecahan', 'Menjelaskan pecahan senilai', 'C2', 'Uraian', '2'],
        ];

        $result = (new AiResponseProcessor)->validate(['kisi_kisi' => $rows], 'kisi_kisi_sections',
            ['settings' => ['question_count' => 2]], ['sections']);

        $this->assertSame('kisi_kisi', $result['sections'][0]['key']);
        $this->assertSame($rows, $result['sections'][0]['content']);
    }

    public function test_atp_rejects_extra_objectives_instead_of_silently_truncating(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('Jumlah baris');

        (new AiResponseProcessor)->validate(['sections' => [
            ['key' => 'capaian', 'title' => 'Capaian', 'type' => 'text', 'content' => 'Memahami pecahan.'],
            ['key' => 'rasional', 'title' => 'Rasional', 'type' => 'text', 'content' => 'Dari dasar ke penerapan.'],
            ['key' => 'alur_tujuan', 'title' => 'Alur', 'type' => 'table',
                'headers' => ['Urutan', 'Tujuan', 'Materi', 'Alokasi', 'Asesmen'],
                'content' => [['1', 'Mengenal pecahan', 'Pecahan', '2 JP', 'Formatif'], ['2', 'Membandingkan pecahan', 'Pecahan', '2 JP', 'Formatif'], ['3', 'Menerapkan pecahan', 'Pecahan', '2 JP', 'Sumatif']]],
        ]], 'atp_sections', ['settings' => ['objective_count' => 2]], ['sections']);
    }

    public function test_regeneration_validates_content_before_it_is_accepted(): void
    {
        $this->expectException(UnexpectedValueException::class);

        (new AiResponseProcessor)->validate(['content' => ['baris valid', ['bukan teks']]],
            'regenerate_tujuan', ['output_type' => 'list'], ['content']);
    }
}
