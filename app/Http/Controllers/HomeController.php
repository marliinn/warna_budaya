<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\CulturalEntry;
use App\Models\Media;
use App\Models\Region;
use App\Support\IndonesianDestinations;
use App\Support\NusantaraDigitalBooks;

class HomeController extends Controller
{
    public function index()
    {
        $categoryVisuals = [
            'Seni Rupa, Kriya, dan Kerajinan Tradisional' => [
                'image' => 'images/culture/batik-tulis.jpg',
                'alt' => 'Perajin membatik tulis di Imogiri, Yogyakarta',
                'place' => 'Batik tulis · Imogiri',
                'credit' => 'Aconkyeah · CC BY-SA 4.0',
                'source' => 'https://commons.wikimedia.org/wiki/File:Maestro_Batik_Tulis_di_Imogiri.jpg',
            ],
            'Arsitektur dan Hunian Tradisional' => [
                'image' => 'images/culture/tongkonan.jpg',
                'alt' => 'Rumah adat Tongkonan di Toraja, Sulawesi Selatan',
                'place' => 'Tongkonan · Toraja',
                'credit' => 'RianHS · CC BY-SA 4.0',
                'source' => 'https://commons.wikimedia.org/wiki/File:Tongkonan,_Toraja_houses.jpg',
            ],
            'Busana dan Aksesori Tradisional' => [
                'image' => 'images/culture/baju-demang.jpg',
                'alt' => 'Busana Demang Betawi, pakaian tradisional untuk acara resmi',
                'place' => 'Busana Demang · Betawi',
                'credit' => 'AWG97 · CC BY-SA 4.0',
                'source' => 'https://commons.wikimedia.org/wiki/File:Baju_Demang_Betawi.png',
            ],
            'Seni Pertunjukan dan Musik Tradisional' => [
                'image' => 'images/culture/gambang-kromong.jpg',
                'alt' => 'Pertunjukan musik tradisional Gambang Kromong',
                'place' => 'Gambang Kromong · Betawi',
                'credit' => 'Tumanisme · CC BY-SA 4.0',
                'source' => 'https://commons.wikimedia.org/wiki/File:Gambang_Kromong.jpg',
            ],
            'Bahasa, Sastra, dan Cerita Tradisional' => [
                'image' => 'images/culture/naskah-lontar.jpg',
                'alt' => 'Naskah lontar dan aksara Sunda sebagai warisan sastra',
                'place' => 'Naskah lontar · Sunda',
                'credit' => 'Hd prastya · CC BY-SA 4.0',
                'source' => 'https://commons.wikimedia.org/wiki/File:Kujang_dan_naskah_lontar_.jpg',
            ],
            'Tradisi, Adat Istiadat, dan Ritual' => [
                'image' => 'images/culture/kabasaran.jpg',
                'alt' => 'Penampil tradisi Kabasaran dari Minahasa',
                'place' => 'Kabasaran · Minahasa',
                'credit' => 'AWG97 · CC BY-SA 4.0',
                'source' => 'https://commons.wikimedia.org/wiki/File:Kabasaran_warrior_in_Tomohon,_Minahasa,_North_Sulawesi,_Indonesia.png',
            ],
            'Kuliner dan Pangan Tradisional' => [
                'image' => 'images/culture/nasi-tumpeng.jpg',
                'alt' => 'Nasi tumpeng, hidangan tradisional dalam perayaan Indonesia',
                'place' => 'Nasi tumpeng · Indonesia',
                'credit' => 'Robih Ardiatama · CC BY-SA 4.0',
                'source' => 'https://commons.wikimedia.org/wiki/File:Nasi_Tumpeng_17an.jpg',
            ],
            'Permainan dan Olahraga Tradisional' => [
                'image' => 'images/culture/egrang.jpg',
                'alt' => 'Anak-anak memainkan egrang, permainan tradisional dengan tongkat bambu',
                'place' => 'Egrang · Jawa Barat',
                'credit' => 'Masmusdjeprat · CC BY-SA 4.0',
                'source' => 'https://commons.wikimedia.org/wiki/File:Melestarikan_Permainan_Tradisional.jpg',
            ],
            'Senjata, Alat, dan Teknologi Tradisional' => [
                'image' => 'images/culture/keris.jpg',
                'alt' => 'Keris tradisional dalam pameran warisan budaya di Ngawi',
                'place' => 'Keris · Ngawi',
                'credit' => 'YT Arty · CC BY-SA 4.0',
                'source' => 'https://commons.wikimedia.org/wiki/File:Ngawi_Heritage_Exhibition_in_the_Form_of_Traditional_Keris_Weapons.jpg',
            ],
            'Pengetahuan dan Kearifan Lokal' => [
                'image' => 'images/culture/subak.jpg',
                'alt' => 'Lanskap persawahan Subak di Bali',
                'place' => 'Subak · Bali',
                'credit' => 'Anggabuana · CC BY-SA 4.0',
                'source' => 'https://commons.wikimedia.org/wiki/File:Rice_Terrace_in_Bali_-_Subak_10.jpg',
            ],
        ];

        $categories = Category::query()
            ->where('is_active', true)
            ->withCount([
                'culturalEntries' => fn ($query) => $query->publiclyVisible(),
            ])
            ->orderBy('sort_order')
            ->get()
            ->each(fn (Category $category) => $category->setAttribute(
                'visual',
                $categoryVisuals[$category->name] ?? null
            ));

        $featuredEntries = CulturalEntry::query()
            ->publiclyVisible()
            ->with([
                'region',
                'categories',
                'media' => fn ($query) => $query
                    ->where('media_type', 'image')
                    ->where('permission_status', Media::PERMISSION_GRANTED)
                    ->orderByDesc('is_cover')
                    ->orderBy('sort_order'),
            ])
            ->orderByDesc('published_at')
            ->limit(3)
            ->get();

        return view('home', [
            'categories' => $categories,
            'featuredEntries' => $featuredEntries,
            'books' => NusantaraDigitalBooks::all(),
            'destinations' => IndonesianDestinations::all(),
            'provinceCount' => Region::query()
                ->where('region_type', 'province')
                ->where('is_active', true)
                ->count(),
            'entryCount' => CulturalEntry::query()->publiclyVisible()->count(),
        ]);
    }
}
