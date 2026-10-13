<?php

namespace Database\Seeders;

use App\Models\Feature;
use App\Models\Plan;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ProductPlansSeeder extends Seeder
{
    private const PACKAGES = [
        'dasar' => [
            'name' => 'Paket Dasar',
            'description' => 'Untuk mulai membuat perangkat ajar inti dengan kuota terukur.',
            'price' => 39000,
            'sort_order' => 1,
            'is_recommended' => false,
            'limits' => [
                'rpp' => [10, 5], 'modul_ajar' => [10, 5], 'silabus' => [5, 2],
                'lkpd' => [10, 5], 'soal' => [5, 2], 'kisi_kisi' => [0, 0],
                'prota' => [0, 0], 'promes' => [0, 0], 'atp' => [0, 0], 'bank_soal' => [0, 0],
            ],
        ],
        'guru_pro' => [
            'name' => 'Paket Guru Pro',
            'description' => 'Pilihan lengkap untuk guru aktif, dengan kuota seimbang di semua fitur.',
            'price' => 79000,
            'sort_order' => 2,
            'is_recommended' => true,
            'limits' => [
                'rpp' => [40, 15], 'modul_ajar' => [40, 15], 'silabus' => [15, 10],
                'lkpd' => [30, 15], 'soal' => [30, 15], 'kisi_kisi' => [20, 10],
                'prota' => [10, 5], 'promes' => [10, 5], 'atp' => [10, 5], 'bank_soal' => [10, 5],
            ],
        ],
        'sekolah_sultan' => [
            'name' => 'Paket Sekolah / Sultan',
            'description' => 'Kuota besar untuk satu akun pengelola yang membuat banyak perangkat ajar.',
            'price' => 199000,
            'sort_order' => 3,
            'is_recommended' => false,
            'limits' => [
                'rpp' => [150, 75], 'modul_ajar' => [150, 75], 'silabus' => [50, 25],
                'lkpd' => [100, 50], 'soal' => [100, 50], 'kisi_kisi' => [75, 35],
                'prota' => [30, 15], 'promes' => [30, 15], 'atp' => [30, 15], 'bank_soal' => [30, 15],
            ],
        ],
    ];

    public function run(): void
    {
        $features = Feature::query()->pluck('id', 'code');
        $createdAny = false;

        DB::transaction(function () use ($features, &$createdAny): void {
            foreach (self::PACKAGES as $key => $package) {
                $limits = $package['limits'];
                if ($features->count() < 10 || array_diff(array_keys($limits), $features->keys()->all())) {
                    throw new RuntimeException('Sepuluh fitur dokumen harus tersedia sebelum paket produk dimuat.');
                }

                $expected = ['dasar' => 40, 'guru_pro' => 215, 'sekolah_sultan' => 745][$key];
                if (array_sum(array_column($limits, 0)) !== $expected) {
                    throw new RuntimeException("Total kuota {$key} tidak sesuai.");
                }

                $plan = Plan::query()->firstOrCreate(['catalog_key' => $key], [
                    'name' => $package['name'],
                    'description' => $package['description'],
                    'price' => $package['price'],
                    'show_reference_price' => false,
                    'is_recommended' => $package['is_recommended'],
                    'sort_order' => $package['sort_order'],
                    'discount_percent' => 0,
                    'duration_days' => 30,
                    'is_active' => true,
                ]);

                if ($plan->wasRecentlyCreated) {
                    $createdAny = true;
                    $plan->features()->sync(collect($limits)->mapWithKeys(fn (array $amounts, string $code) => [
                        $features[$code] => [
                            'is_enabled' => true,
                            'generation_limit' => $amounts[0],
                            'regeneration_limit' => $amounts[1],
                        ],
                    ])->all());
                }
            }

            if ($createdAny) {
                Plan::query()->whereNull('catalog_key')->where('name', 'Teacher Pro')->update(['is_active' => false]);
            }
        });
    }
}
