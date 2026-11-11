<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class WarnaBudayaCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['Seni Rupa, Kriya, dan Kerajinan Tradisional', 'seni-rupa-kriya-kerajinan'],
            ['Arsitektur dan Hunian Tradisional', 'arsitektur-hunian-tradisional'],
            ['Busana dan Aksesori Tradisional', 'busana-aksesori-tradisional'],
            ['Seni Pertunjukan dan Musik Tradisional', 'seni-pertunjukan-musik'],
            ['Bahasa, Sastra, dan Cerita Tradisional', 'bahasa-sastra-cerita'],
            ['Tradisi, Adat Istiadat, dan Ritual', 'tradisi-adat-ritual'],
            ['Kuliner dan Pangan Tradisional', 'kuliner-pangan-tradisional'],
            ['Permainan dan Olahraga Tradisional', 'permainan-olahraga-tradisional'],
            ['Senjata, Alat, dan Teknologi Tradisional', 'senjata-alat-teknologi'],
            ['Pengetahuan dan Kearifan Lokal', 'pengetahuan-kearifan-lokal'],
        ];

        foreach ($categories as $index => [$name, $slug]) {
            DB::table('categories')->updateOrInsert(
                ['slug' => $slug],
                ['name' => $name, 'sort_order' => $index + 1, 'is_active' => true, 'updated_at' => now(), 'created_at' => now()]
            );
        }
    }
}
