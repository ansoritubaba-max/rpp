<?php

namespace App\Services\AI;

use App\Services\Documents\DocumentContentFormatter;
use Illuminate\Support\Str;
use JsonException;
use UnexpectedValueException;

class AiResponseProcessor
{
    public function decode(string $content, string $taskKey, array $input, array $requiredKeys): array
    {
        if (strlen($content) > 500000) {
            throw new UnexpectedValueException('Respons AI terlalu besar.');
        }

        $content = trim(preg_replace('/^\xEF\xBB\xBF/', '', $content));
        $content = preg_replace('/^```(?:json)?\s*|\s*```$/iu', '', $content);
        $content = trim($content);

        try {
            $decoded = json_decode($content, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $start = strpos($content, '{');
            $end = strrpos($content, '}');
            if ($start === false || $end === false || $end < $start) {
                throw new UnexpectedValueException('Respons AI bukan objek JSON yang valid.');
            }
            try {
                $decoded = json_decode(substr($content, $start, $end - $start + 1), true, flags: JSON_THROW_ON_ERROR);
            } catch (JsonException $error) {
                throw new UnexpectedValueException('Respons AI bukan objek JSON yang valid.', previous: $error);
            }
        }

        return $this->validate($decoded, $taskKey, $input, $requiredKeys);
    }

    public function validate(mixed $value, string $taskKey, array $input, array $requiredKeys): array
    {
        if (! is_array($value) || array_is_list($value)) {
            throw new UnexpectedValueException('Respons AI harus berupa objek JSON.');
        }

        if (count($value) === 1 && is_array($value['data'] ?? $value['result'] ?? null)) {
            $value = $value['data'] ?? $value['result'];
        }

        if ($taskKey === 'rpp_sections') {
            $value = $this->rpp($value);
        } elseif (str_ends_with($taskKey, '_sections')) {
            if ($taskKey === 'kisi_kisi_sections' && ! isset($value['sections']) && isset($value['kisi_kisi'])) {
                $value['sections'] = [[
                    'key' => 'kisi_kisi',
                    'title' => 'Kisi-Kisi Penilaian',
                    'type' => 'table',
                    'headers' => ['No.', 'Materi', 'Indikator Soal', 'Level Kognitif', 'Bentuk', 'No. Soal'],
                    'content' => $value['kisi_kisi'],
                ]];
            }
            $value['sections'] = $this->sections($value['sections'] ?? null);
            if ($taskKey === 'lkpd_sections') {
                $value['sections'] = $this->lkpdSections($value['sections'], $input);
            } elseif ($taskKey === 'modul_ajar_sections') {
                $value['sections'] = $this->moduleSections($value['sections']);
            } else {
                $value['sections'] = $this->documentSections($value['sections'], $taskKey, $input);
            }
        } elseif (str_starts_with($taskKey, 'regenerate_')) {
            $value['content'] = $this->regeneratedContent($value['content'] ?? null, $input['output_type'] ?? null);
        }

        foreach ($requiredKeys as $key) {
            if (! array_key_exists($key, $value) || $value[$key] === null || $value[$key] === '' || $value[$key] === []) {
                throw new UnexpectedValueException("Respons AI tidak memiliki bagian {$key} yang valid.");
            }
        }

        return $value;
    }

    private function rpp(array $value): array
    {
        $value['objectives'] ??= $value['learning_objectives'] ?? $value['tujuan_pembelajaran'] ?? null;
        $value['apperception'] ??= $value['apersepsi'] ?? null;
        $value['main_activity'] ??= $value['mainActivity'] ?? null;
        $value['main_activity'] ??= $value['kegiatan_inti'] ?? null;
        $value['assessment'] ??= $value['asesmen'] ?? null;
        if (is_array($value['main_activity']) && array_is_list($value['main_activity'])) {
            $value['main_activity'] = array_map(fn ($activity) => is_array($activity)
                ? DocumentContentFormatter::text($activity)
                : $activity, $value['main_activity']);
        }
        foreach (['objectives', 'main_activity'] as $key) {
            $value[$key] = $this->stringList($value[$key] ?? null, $key, 30);
        }
        $value['apperception'] = $this->plainText($value['apperception'] ?? null, 'apperception', 3000);

        $value['assessment'] = $this->assessment($value['assessment'] ?? null);

        return $value;
    }

    private function moduleSections(array $sections): array
    {
        $expected = [
            'informasi_umum' => 'text', 'kompetensi_awal' => 'list', 'tujuan' => 'list',
            'pemahaman_bermakna' => 'text', 'pertanyaan_pemantik' => 'list',
            'kegiatan' => 'list', 'asesmen' => 'table', 'tindak_lanjut' => 'list',
        ];
        $byKey = collect($sections)->keyBy('key');
        if ($byKey->keys()->sort()->values()->all() !== collect(array_keys($expected))->sort()->values()->all()) {
            throw new UnexpectedValueException('Modul ajar belum memiliki seluruh bagian yang dibutuhkan template cetak.');
        }
        foreach ($expected as $key => $type) {
            if ($byKey[$key]['type'] !== $type) {
                throw new UnexpectedValueException("Format bagian {$key} modul ajar tidak sesuai.");
            }
        }
        if (count($byKey['kegiatan']['content']) < 3 || count($byKey['asesmen']['headers']) !== 3 || count($byKey['tindak_lanjut']['content']) < 3) {
            throw new UnexpectedValueException('Kegiatan, asesmen, atau tindak lanjut modul ajar belum lengkap.');
        }

        return array_map(fn ($key) => $byKey[$key], array_keys($expected));
    }

    private function documentSections(array $sections, string $taskKey, array $input): array
    {
        $schemas = [
            'silabus_sections' => ['rasional' => ['text'], 'silabus' => ['table', 7]],
            'soal_sections' => ['daftar_soal' => ['questions']],
            'kisi_kisi_sections' => ['kisi_kisi' => ['table', 6]],
            'prota_sections' => ['landasan' => ['text'], 'program_tahunan' => ['table', 4]],
            'promes_sections' => ['ringkasan' => ['text'], 'program_semester' => ['table', 4]],
            'atp_sections' => ['capaian' => ['text'], 'rasional' => ['text'], 'alur_tujuan' => ['table', 5]],
        ];
        $schema = $schemas[$taskKey] ?? null;
        if ($schema === null) {
            return $sections;
        }

        $byKey = collect($sections)->keyBy('key');
        foreach ($schema as $key => $format) {
            [$type] = $format;
            $columnCount = $format[1] ?? null;
            $section = $byKey->get($key);
            if (! $section || $section['type'] !== $type
                || ($type === 'table' && count($section['headers']) !== $columnCount)) {
                throw new UnexpectedValueException("Bagian {$key} tidak sesuai format {$taskKey}.");
            }
        }

        if ($taskKey === 'soal_sections') {
            $expectedCount = (int) data_get($input, 'settings.question_count', 0);
            if ($expectedCount > 0 && count($byKey['daftar_soal']['content']) !== $expectedCount) {
                throw new UnexpectedValueException('Jumlah soal tidak sesuai permintaan.');
            }
            if (data_get($input, 'settings.include_blueprint') && (! $byKey->has('kisi_kisi_ringkas')
                || $byKey['kisi_kisi_ringkas']['type'] !== 'table'
                || count($byKey['kisi_kisi_ringkas']['headers']) !== 5)) {
                throw new UnexpectedValueException('Kisi-kisi ringkas soal belum lengkap.');
            }
        }

        $countKey = match ($taskKey) {
            'kisi_kisi_sections' => 'settings.question_count',
            'atp_sections' => 'settings.objective_count',
            default => null,
        };
        if ($countKey !== null) {
            $expectedCount = (int) data_get($input, $countKey, 0);
            $tableKey = array_key_last($schema);
            if ($expectedCount > 0 && count($byKey[$tableKey]['content']) !== $expectedCount) {
                throw new UnexpectedValueException('Jumlah baris dokumen tidak sesuai permintaan.');
            }
        }

        return $sections;
    }

    private function assessment(mixed $assessment): array
    {
        $rows = DocumentContentFormatter::normalizeRpp(['assessment' => $assessment])['assessment'];
        if ($rows === [] || count($rows) > 20) {
            throw new UnexpectedValueException('Asesmen RPP tidak valid.');
        }
        return array_map(fn ($row) => [
            'type' => $this->plainText($row['type'] ?? null, 'jenis asesmen', 100),
            'method' => $this->plainText($row['method'] ?? null, 'metode asesmen', 2000),
        ], $rows);
    }

    private function sections(mixed $sections): array
    {
        if (! is_array($sections) || ! array_is_list($sections) || $sections === [] || count($sections) > 30) {
            throw new UnexpectedValueException('Daftar bagian dokumen tidak valid.');
        }

        $seen = [];
        $normalized = [];
        foreach ($sections as $section) {
            if (! is_array($section)) {
                throw new UnexpectedValueException('Bagian dokumen bukan objek.');
            }
            $title = $this->plainText($section['title'] ?? $section['judul'] ?? null, 'judul bagian', 200);
            $key = $this->plainText($section['key'] ?? Str::slug($title, '_'), 'kunci bagian', 80);
            if (! preg_match('/^[a-z][a-z0-9_]*$/', $key) || isset($seen[$key])) {
                throw new UnexpectedValueException('Kunci bagian dokumen tidak valid atau berulang.');
            }
            $seen[$key] = true;
            $contentValue = $section['content'] ?? $section['isi'] ?? null;
            $headers = $section['headers'] ?? $section['columns'] ?? null;
            $type = $section['type'] ?? (is_array($headers) ? 'table' : (is_string($contentValue)
                && ! preg_match('/^\s*(?:[-*•]|\d+[.)])\s+/u', $contentValue) ? 'text' : 'list'));
            if (! in_array($type, ['text', 'list', 'table', 'questions'], true)) {
                throw new UnexpectedValueException('Tipe bagian dokumen tidak dikenal.');
            }

            $content = match ($type) {
                'text' => $this->plainText($contentValue, 'isi teks', 20000),
                'list' => $this->stringList($contentValue, 'isi daftar', 50),
                'table' => $this->table($headers, $contentValue),
                'questions' => $this->questions($contentValue),
            };
            if ($type === 'list' && count($content) !== count(array_unique(array_map(
                fn (string $item) => mb_strtolower(trim($item)),
                $content,
            )))) {
                throw new UnexpectedValueException('Daftar dokumen memiliki butir yang berulang.');
            }
            $normalized[] = ['key' => $key, 'title' => $title, 'type' => $type, 'content' => $content]
                + ($type === 'table' ? ['headers' => array_map(fn ($header) => trim(strip_tags($header)), $headers)] : []);
        }

        return $normalized;
    }

    private function table(mixed $headers, mixed $rows): array
    {
        if (! is_array($headers) || ! array_is_list($headers) || count($headers) < 2 || count($headers) > 12
            || ! is_array($rows) || ! array_is_list($rows) || $rows === [] || count($rows) > 100) {
            throw new UnexpectedValueException('Tabel dokumen tidak valid.');
        }
        foreach ($headers as $header) {
            $this->plainText($header, 'judul kolom', 200);
        }
        foreach ($rows as $row) {
            if (! is_array($row) || ! array_is_list($row) || count($row) !== count($headers)) {
                throw new UnexpectedValueException('Jumlah sel tabel tidak sesuai kolom.');
            }
            foreach ($row as $cell) {
                if (! is_string($cell) && ! is_numeric($cell)) {
                    throw new UnexpectedValueException('Sel tabel harus berupa teks.');
                }
                if (mb_strlen((string) $cell) > 3000) {
                    throw new UnexpectedValueException('Sel tabel terlalu panjang.');
                }
            }
        }

        return $rows;
    }

    private function lkpdSections(array $sections, array $input): array
    {
        $expected = ['petunjuk' => 'text', 'tujuan' => 'list', 'aktivitas' => 'list', 'hasil' => 'table'];
        $byKey = collect($sections)->keyBy('key');
        if ($byKey->keys()->sort()->values()->all() !== collect(array_keys($expected))->sort()->values()->all()) {
            throw new UnexpectedValueException('LKPD harus memiliki petunjuk, tujuan, aktivitas, dan lembar hasil yang terpisah.');
        }

        foreach ($expected as $key => $type) {
            if ($byKey[$key]['type'] !== $type) {
                throw new UnexpectedValueException("Format bagian {$key} LKPD tidak sesuai.");
            }
        }

        $activityCount = (int) data_get($input, 'settings.activity_count', 0);
        $activities = $byKey['aktivitas']['content'];
        $results = $byKey['hasil']['content'];
        if ($activityCount > 0 && (count($activities) !== $activityCount || count($results) !== $activityCount)) {
            throw new UnexpectedValueException('Jumlah aktivitas dan ruang jawaban LKPD tidak sesuai permintaan.');
        }
        if (count($activities) < 1 || count($results) !== count($activities)) {
            throw new UnexpectedValueException('LKPD tidak memiliki aktivitas dan ruang jawaban yang lengkap.');
        }
        if (count($byKey['hasil']['headers']) !== 2 || collect($results)->contains(fn ($row) => trim((string) $row[0]) === '' || trim((string) $row[1]) !== '')) {
            throw new UnexpectedValueException('Lembar hasil LKPD harus memiliki dua kolom, label kegiatan, dan ruang jawaban kosong.');
        }

        $activityFingerprints = [];
        foreach ($activities as $activity) {
            if (mb_strlen($activity) < 25 || preg_match('/(?:^|\n)\s*(?:#{1,6}\s|\|.*\||```)/u', $activity)) {
                throw new UnexpectedValueException('Instruksi aktivitas LKPD terlalu singkat atau berisi format Markdown.');
            }
            $fingerprint = mb_strtolower(trim(preg_replace('/^\s*(?:aktivitas|kegiatan)\s*\d+\s*[:.)-]?\s*/iu', '', $activity)));
            if (isset($activityFingerprints[$fingerprint])) {
                throw new UnexpectedValueException('LKPD memiliki aktivitas yang berulang.');
            }
            $activityFingerprints[$fingerprint] = true;
        }
        foreach (['petunjuk', 'tujuan'] as $key) {
            foreach ((array) $byKey[$key]['content'] as $text) {
                if (preg_match('/(?:^|\n)\s*(?:#{1,6}\s|\|.*\||```)/u', $text)) {
                    throw new UnexpectedValueException('LKPD berisi format Markdown yang tidak dapat dicetak dengan baik.');
                }
            }
        }

        return array_map(fn ($key) => $byKey[$key], array_keys($expected));
    }

    private function questions(mixed $items): array
    {
        if (! is_array($items) || ! array_is_list($items) || $items === [] || count($items) > 60) {
            throw new UnexpectedValueException('Daftar soal tidak valid.');
        }

        $seenQuestions = [];
        foreach ($items as $index => &$item) {
            if (! is_array($item)) {
                throw new UnexpectedValueException('Butir soal tidak valid.');
            }
            $item['number'] = $index + 1;
            $item['question'] = $this->plainText($item['question'] ?? null, 'pertanyaan', 5000);
            $fingerprint = mb_strtolower(preg_replace('/\s+/u', ' ', $item['question']))
                .'|'.(string) data_get($item, 'image.asset', '')
                .'|'.(string) data_get($item, 'image.count', '');
            if (isset($seenQuestions[$fingerprint])) {
                throw new UnexpectedValueException('Daftar soal memiliki pertanyaan yang berulang.');
            }
            $seenQuestions[$fingerprint] = true;
            $item['answer'] = $this->plainText($item['answer'] ?? null, 'jawaban', 3000);
            $item['type'] = $item['type'] ?? (empty($item['options']) ? 'uraian' : 'pilihan_ganda');
            if (! in_array($item['type'], ['uraian', 'pilihan_ganda'], true)) {
                throw new UnexpectedValueException('Bentuk soal tidak valid.');
            }
            $item['explanation'] = is_string($item['explanation'] ?? null) ? trim(strip_tags($item['explanation'])) : '';
            if (mb_strlen($item['explanation']) > 5000) {
                throw new UnexpectedValueException('Pembahasan soal terlalu panjang.');
            }
            $options = $item['options'] ?? [];
            if ($item['type'] === 'pilihan_ganda') {
                if (! is_array($options) || ! array_is_list($options) || count($options) < 2 || count($options) > 6) {
                    throw new UnexpectedValueException('Pilihan jawaban soal tidak valid.');
                }
                foreach ($options as $optionIndex => &$option) {
                    if (! is_array($option)) {
                        throw new UnexpectedValueException('Pilihan jawaban bukan objek.');
                    }
                    $option['label'] = chr(65 + $optionIndex);
                    $option['content'] = $this->plainText($option['content'] ?? null, 'pilihan jawaban', 1500);
                    $option['is_correct'] = (bool) ($option['is_correct'] ?? strtoupper(trim($item['answer'])) === $option['label']);
                }
                unset($option);
                if (count(array_filter($options, fn ($option) => $option['is_correct'])) !== 1) {
                    throw new UnexpectedValueException('Soal harus memiliki tepat satu jawaban benar.');
                }
                $item['options'] = $options;
            } elseif ($options !== [] && (! is_array($options) || ! array_is_list($options))) {
                throw new UnexpectedValueException('Pilihan jawaban soal tidak valid.');
            }
        }
        unset($item);

        return $items;
    }

    private function regeneratedContent(mixed $content, mixed $type): array|string
    {
        return match ($type) {
            'text' => $this->plainText($content, 'isi bagian', 20000),
            'list' => $this->stringList($content, 'isi bagian', 20),
            'assessment' => $this->assessment($content),
            default => throw new UnexpectedValueException('Tipe bagian yang dibuat ulang tidak dikenal.'),
        };
    }

    private function stringList(mixed $value, string $label, int $maximum): array
    {
        if (is_string($value)) {
            $value = preg_split('/\r\n|\r|\n/u', $value);
            $value = array_map(fn (string $line) => preg_replace('/^\s*(?:[-*•]|\d+[.)])\s*/u', '', $line), $value);
        }
        if (! is_array($value) || ! array_is_list($value) || $value === [] || count($value) > $maximum) {
            throw new UnexpectedValueException("{$label} harus berupa daftar yang tidak kosong.");
        }

        return array_map(fn ($item) => $this->plainText($item, $label, 3000), $value);
    }

    private function plainText(mixed $value, string $label, int $maximum): string
    {
        if (! is_string($value)) {
            throw new UnexpectedValueException("{$label} harus berupa teks.");
        }
        $text = trim(strip_tags($value));
        if ($text === '' || mb_strlen($text) > $maximum) {
            throw new UnexpectedValueException("{$label} kosong atau terlalu panjang.");
        }

        return $text;
    }
}
