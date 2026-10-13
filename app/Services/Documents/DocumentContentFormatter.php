<?php

namespace App\Services\Documents;

use Illuminate\Support\Str;

final class DocumentContentFormatter
{
    public static function text(mixed $value): string
    {
        if (is_string($value) || is_numeric($value)) {
            return trim((string) $value);
        }

        if (! is_array($value) || $value === []) {
            return '';
        }

        if (array_key_exists('meeting', $value)) {
            $meeting = self::text($value['meeting']);
            $activities = self::text($value['activities'] ?? []);

            return trim('Pertemuan '.$meeting.($activities !== '' ? ': '.$activities : ''));
        }

        if (array_is_list($value)) {
            return implode('; ', array_filter(array_map(self::text(...), $value), fn (string $part) => $part !== ''));
        }

        $parts = [];
        foreach ($value as $key => $item) {
            $text = self::text($item);
            if ($text === '') {
                continue;
            }

            $label = match ($key) {
                'technique' => 'Teknik',
                'indicators' => 'Indikator',
                'components' => 'Komponen',
                'component' => 'Komponen',
                'criteria' => 'Kriteria',
                'activities' => 'Kegiatan',
                default => is_string($key) ? Str::headline($key) : '',
            };
            $parts[] = $label === '' ? $text : $label.': '.$text;
        }

        return implode('; ', $parts);
    }

    public static function list(mixed $value): array
    {
        if (! is_array($value) || ! array_is_list($value)) {
            $text = self::text($value);

            return $text === '' ? [] : [$text];
        }

        return array_values(array_filter(array_map(self::text(...), $value), fn (string $item) => $item !== ''));
    }

    public static function normalizeRpp(array $content): array
    {
        foreach (['objectives', 'opening', 'main_activity', 'closing'] as $key) {
            $content[$key] = self::list($content[$key] ?? []);
        }

        $content['assessment'] = self::assessmentRows($content['assessment'] ?? []);

        return $content;
    }

    public static function activityGroups(mixed $value): array
    {
        if (! is_array($value) || ! array_is_list($value)) {
            $value = self::list($value);
        }

        $groups = [];
        foreach ($value as $item) {
            if (is_array($item) && array_key_exists('meeting', $item)) {
                $meeting = self::text($item['meeting']);
                $steps = self::list($item['activities'] ?? []);
                $title = $meeting !== '' ? 'Pertemuan '.$meeting : null;
            } else {
                $text = self::text($item);
                if ($text === '') {
                    continue;
                }

                $title = null;
                $steps = [$text];
                if (preg_match('/^Pertemuan\s+(\d+)\s*:\s*(.*)$/iu', $text, $matches)) {
                    $title = 'Pertemuan '.$matches[1];
                    $steps = preg_split('/;\s+/u', $matches[2]);
                }
            }

            $steps = array_values(array_filter(array_map(self::text(...), $steps), fn (string $step) => $step !== ''));
            if ($steps === []) {
                continue;
            }

            if ($title === null && $groups !== [] && end($groups)['title'] === null) {
                $groups[array_key_last($groups)]['steps'] = array_merge($groups[array_key_last($groups)]['steps'], $steps);
            } else {
                $groups[] = ['title' => $title, 'steps' => $steps];
            }
        }

        return $groups;
    }

    private static function assessmentRows(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        if (array_is_list($value)) {
            return array_values(array_map(fn ($row) => [
                'type' => self::text(is_array($row) ? ($row['type'] ?? '') : ''),
                'method' => self::text(is_array($row) ? ($row['method'] ?? $row) : $row),
            ], $value));
        }

        $rows = [];
        foreach ($value as $category => $items) {
            $label = match ($category) {
                'diagnostic' => 'Diagnostik',
                'formative' => 'Formatif',
                'summative' => 'Sumatif',
                default => Str::headline((string) $category),
            };

            $entries = is_array($items) && array_is_list($items) ? $items : [$items];
            foreach ($entries as $item) {
                $method = self::text($item);
                if ($method !== '') {
                    $rows[] = ['type' => $label, 'method' => $method];
                }
            }
        }

        return $rows;
    }
}
