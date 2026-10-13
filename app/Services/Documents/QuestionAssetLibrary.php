<?php

namespace App\Services\Documents;

class QuestionAssetLibrary
{
    private const ASSETS = [
        'apple' => ['label' => 'Apel', 'noun' => 'apel', 'category' => 'Buah'],
        'cat' => ['label' => 'Kucing', 'noun' => 'kucing', 'category' => 'Hewan'],
        'car' => ['label' => 'Mobil', 'noun' => 'mobil', 'category' => 'Kendaraan'],
        'triangle' => ['label' => 'Segitiga', 'noun' => 'segitiga', 'category' => 'Bentuk'],
        'red' => ['label' => 'Kotak merah', 'noun' => 'kotak merah', 'category' => 'Warna'],
        'three' => ['label' => 'Angka 3', 'noun' => 'angka 3', 'category' => 'Angka'],
        'book' => ['label' => 'Buku', 'noun' => 'buku', 'category' => 'Benda kelas'],
        'teacher' => ['label' => 'Guru', 'noun' => 'guru', 'category' => 'Profesi'],
        'hand' => ['label' => 'Tangan', 'noun' => 'tangan', 'category' => 'Anggota tubuh'],
        'tree' => ['label' => 'Pohon', 'noun' => 'pohon', 'category' => 'Lingkungan'],
    ];

    public function all(): array
    {
        return self::ASSETS;
    }

    public function keys(): array
    {
        return array_keys(self::ASSETS);
    }

    public function supports(?string $key): bool
    {
        return $key !== null && array_key_exists($key, self::ASSETS);
    }

    public function noun(?string $key): string
    {
        return self::ASSETS[$key]['noun'] ?? 'gambar';
    }

    public function label(?string $key): string
    {
        return self::ASSETS[$key]['label'] ?? 'Gambar';
    }

    public function url(?string $key): ?string
    {
        return $this->supports($key) ? asset('images/question-assets/'.$key.'.svg') : null;
    }

    public function dataUri(?string $key): ?string
    {
        if (! $this->supports($key)) {
            return null;
        }

        $path = public_path('images/question-assets/'.$key.'.svg');
        if (! is_file($path)) {
            return null;
        }

        return 'data:image/svg+xml;base64,'.base64_encode(file_get_contents($path));
    }
}
