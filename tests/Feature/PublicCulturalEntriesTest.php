<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicCulturalEntriesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');

        $this->createSchema();
    }

    public function test_archive_only_shows_published_entries_and_granted_media_with_a_location(): void
    {
        $visibleId = $this->createEntry('Arsip Disetujui');
        $this->createEntry('Arsip Draft', 'draft', 'verified');
        $unverifiedId = $this->createEntry('Arsip Belum Terverifikasi', 'published', 'pending');
        $deletedId = $this->createEntry('Arsip Terhapus');
        DB::table('cultural_entries')->where('id', $deletedId)->update(['deleted_at' => now()]);

        $this->createMedia($visibleId, 'granted', 'images/arsip-disetujui.jpg');
        $this->createMedia($visibleId, 'pending');
        $this->createMedia($visibleId, 'approved');
        $this->createMedia($visibleId, 'granted');
        $this->createMedia($unverifiedId, 'granted', null, 'https://example.test/arsip.jpg');

        $response = $this->get('/arsip-budaya');

        $response
            ->assertOk()
            ->assertSee('Arsip Disetujui')
            ->assertDontSee('Arsip Draft')
            ->assertSee('Arsip Belum Terverifikasi')
            ->assertDontSee('Arsip Terhapus');

        $entries = $response->viewData('entries');
        $this->assertSame(
            ['images/arsip-disetujui.jpg'],
            $entries->firstWhere('id', $visibleId)->media->pluck('file_path')->all()
        );
        $this->assertSame(
            ['https://example.test/arsip.jpg'],
            $entries->firstWhere('id', $unverifiedId)->media->pluck('external_url')->all()
        );
    }

    public function test_private_cultural_media_is_served_only_when_granted_and_published(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('cultural/test-image.jpg', 'image bytes');

        $entryId = $this->createEntry('Foto Privat');
        $mediaId = DB::table('media')->insertGetId([
            'cultural_entry_id' => $entryId,
            'media_type' => 'image',
            'file_path' => 'cultural/test-image.jpg',
            'permission_status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->get(route('cultural-media.show', $mediaId))->assertNotFound();

        DB::table('media')->where('id', $mediaId)->update(['permission_status' => 'granted']);
        $this->get(route('cultural-media.show', $mediaId))
            ->assertOk()
            ->assertHeader('Cache-Control', 'max-age=0, no-store, private')
            ->assertHeader('Content-Type', 'image/jpeg')
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        DB::table('cultural_entries')->where('id', $entryId)->update(['publication_status' => 'draft']);
        $this->get(route('cultural-media.show', $mediaId))->assertNotFound();
    }

    public function test_search_and_pagination_continue_to_work_for_public_entries(): void
    {
        foreach (range(1, 13) as $number) {
            $this->createEntry(sprintf('Wayang %02d', $number));
        }
        $this->createEntry('Tari Terverifikasi');

        $response = $this->get('/arsip-budaya?search=Wayang');

        $response->assertOk();
        $entries = $response->viewData('entries');

        $this->assertSame(13, $entries->total());
        $this->assertCount(12, $entries->items());
        $this->assertStringContainsString('search=Wayang', $entries->url(2));
    }

    public function test_archive_filters_by_active_province_and_category_and_preserves_filters_in_pagination(): void
    {
        $regionId = $this->createRegion('Jawa Barat', 'jawa-barat');
        $otherRegionId = $this->createRegion('Bali', 'bali');
        $categoryId = $this->createCategory('Seni Pertunjukan', 'seni-pertunjukan');
        $otherCategoryId = $this->createCategory('Kerajinan', 'kerajinan');

        foreach (range(1, 13) as $number) {
            $entryId = $this->createEntry(sprintf('Wayang %02d', $number));
            DB::table('cultural_entries')->where('id', $entryId)->update(['origin_region_id' => $regionId]);
            DB::table('category_cultural_entry')->insert([
                'cultural_entry_id' => $entryId,
                'category_id' => $categoryId,
            ]);
        }

        $pendingEntryId = $this->createEntry('Wayang 14', 'published', 'pending');
        DB::table('cultural_entries')->where('id', $pendingEntryId)->update(['origin_region_id' => $regionId]);
        DB::table('category_cultural_entry')->insert([
            'cultural_entry_id' => $pendingEntryId,
            'category_id' => $categoryId,
        ]);

        $otherEntryId = $this->createEntry('Wayang Bali');
        DB::table('cultural_entries')->where('id', $otherEntryId)->update(['origin_region_id' => $otherRegionId]);
        DB::table('category_cultural_entry')->insert([
            'cultural_entry_id' => $otherEntryId,
            'category_id' => $otherCategoryId,
        ]);

        $response = $this->get("/arsip-budaya?search=Wayang&region_id={$regionId}&category_id={$categoryId}");

        $response
            ->assertOk()
            ->assertSee('Wayang 01')
            ->assertDontSee('Wayang Bali')
            ->assertSee('Jawa Barat')
            ->assertSee('Seni Pertunjukan');

        $entries = $response->viewData('entries');
        $this->assertSame(14, $entries->total());
        $this->assertStringContainsString("region_id={$regionId}", $entries->url(2));
        $this->assertStringContainsString("category_id={$categoryId}", $entries->url(2));
        $this->assertStringContainsString('search=Wayang', $entries->url(2));
        $this->get($entries->url(2))->assertOk()->assertSee('Wayang 14');
    }

    public function test_archive_filters_only_offer_active_provinces_and_categories(): void
    {
        $this->createRegion('Provinsi Aktif', 'provinsi-aktif');
        $this->createRegion('Kota Nonaktif', 'kota-nonaktif', 'city', false);
        $this->createCategory('Kategori Aktif', 'kategori-aktif');
        $this->createCategory('Kategori Nonaktif', 'kategori-nonaktif', false);

        $this->get('/arsip-budaya')
            ->assertOk()
            ->assertSee('Provinsi Aktif')
            ->assertDontSee('Kota Nonaktif')
            ->assertSee('Kategori Aktif')
            ->assertDontSee('Kategori Nonaktif');
    }

    public function test_archive_filter_validation_rejects_inactive_or_non_province_options(): void
    {
        $cityId = $this->createRegion('Kota Bandung', 'kota-bandung', 'city');
        $inactiveRegionId = $this->createRegion('Provinsi Nonaktif', 'provinsi-nonaktif', 'province', false);
        $inactiveCategoryId = $this->createCategory('Kategori Nonaktif', 'kategori-nonaktif', false);

        $this->get("/arsip-budaya?region_id={$cityId}")
            ->assertSessionHasErrors('region_id');
        $this->get("/arsip-budaya?region_id={$inactiveRegionId}")
            ->assertSessionHasErrors('region_id');
        $this->get("/arsip-budaya?category_id={$inactiveCategoryId}")
            ->assertSessionHasErrors('category_id');
    }

    public function test_cultural_detail_shows_public_entry_granted_media_sources_and_related_entries(): void
    {
        $regionId = $this->createRegion('Jawa Tengah', 'jawa-tengah');
        $otherRegionId = $this->createRegion('Bali', 'bali');
        $categoryId = $this->createCategory('Seni Pertunjukan', 'seni-pertunjukan');
        $otherCategoryId = $this->createCategory('Kerajinan', 'kerajinan');
        $entryId = $this->createEntry('Wayang Kulit');

        DB::table('cultural_entries')->where('id', $entryId)->update([
            'local_name' => 'Wayang kulit Jawa',
            'short_description' => 'Pertunjukan bayangan tradisional.',
            'description' => 'Wayang kulit menyampaikan kisah melalui pertunjukan boneka bayangan.',
            'history' => 'Tradisi yang diwariskan lintas generasi.',
            'meaning_and_function' => 'Sarana hiburan dan penyampaian nilai.',
            'techniques' => 'Kulit dipahat dan dimainkan di balik kelir.',
            'materials' => 'Kulit dan kayu.',
            'community_context' => 'Hidup dalam ruang budaya masyarakat.',
            'keywords' => json_encode(['wayang', 'pertunjukan']),
            'origin_region_id' => $regionId,
        ]);
        DB::table('category_cultural_entry')->insert([
            'cultural_entry_id' => $entryId,
            'category_id' => $categoryId,
        ]);

        DB::table('media')->insert([
            [
                'cultural_entry_id' => $entryId,
                'media_type' => 'image',
                'external_url' => 'https://example.test/wayang.jpg',
                'caption' => 'Pertunjukan wayang',
                'alt_text' => 'Wayang kulit di balik kelir',
                'creator' => 'Dokumentator Budaya',
                'copyright_holder' => 'Komunitas Wayang',
                'license' => 'CC BY 4.0',
                'permission_status' => 'granted',
                'is_cover' => true,
                'sort_order' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'cultural_entry_id' => $entryId,
                'media_type' => 'image',
                'external_url' => 'https://example.test/private.jpg',
                'caption' => 'PRIVATE_PENDING_MEDIA',
                'permission_status' => 'pending',
                'is_cover' => false,
                'sort_order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $sourceId = DB::table('sources')->insertGetId([
            'source_type' => 'book',
            'title' => 'Ensiklopedia Wayang Nusantara',
            'author' => 'Penulis Budaya',
            'publisher' => 'Penerbit Nusantara',
            'publication_year' => 2020,
            'url' => 'https://example.test/rujukan',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('cultural_entry_source')->insert([
            'cultural_entry_id' => $entryId,
            'source_id' => $sourceId,
        ]);

        $categoryRelatedId = $this->createEntry('Wayang Bali');
        DB::table('cultural_entries')->where('id', $categoryRelatedId)->update(['origin_region_id' => $otherRegionId]);
        DB::table('category_cultural_entry')->insert([
            'cultural_entry_id' => $categoryRelatedId,
            'category_id' => $categoryId,
        ]);

        $regionRelatedId = $this->createEntry('Tradisi Jawa Tengah');
        DB::table('cultural_entries')->where('id', $regionRelatedId)->update(['origin_region_id' => $regionId]);
        DB::table('category_cultural_entry')->insert([
            'cultural_entry_id' => $regionRelatedId,
            'category_id' => $otherCategoryId,
        ]);

        $unrelatedId = $this->createEntry('Arsip Tidak Terkait');
        DB::table('cultural_entries')->where('id', $unrelatedId)->update(['origin_region_id' => $otherRegionId]);

        $draftRelatedId = $this->createEntry('Draft Terkait', 'draft', 'verified');
        DB::table('category_cultural_entry')->insert([
            'cultural_entry_id' => $draftRelatedId,
            'category_id' => $categoryId,
        ]);

        $response = $this->get('/arsip-budaya/wayang-kulit');

        $response
            ->assertOk()
            ->assertSee('Wayang kulit Jawa')
            ->assertSee('Jawa Tengah')
            ->assertSee('Seni Pertunjukan')
            ->assertSee('Pertunjukan bayangan tradisional.')
            ->assertSee('Tradisi yang diwariskan lintas generasi.')
            ->assertSee('Dokumentator Budaya')
            ->assertSee('Komunitas Wayang')
            ->assertSee('CC BY 4.0')
            ->assertSee('Ensiklopedia Wayang Nusantara')
            ->assertSee(route('cultural-entries.index'))
            ->assertSee(route('cultural-entries.show', 'wayang-bali'))
            ->assertSee(route('cultural-entries.show', 'tradisi-jawa-tengah'))
            ->assertDontSee('PRIVATE_PENDING_MEDIA')
            ->assertDontSee('Arsip Tidak Terkait')
            ->assertDontSee('Draft Terkait');

        $entry = $response->viewData('entry');
        $this->assertSame(
            ['granted'],
            $entry->media->pluck('permission_status')->all()
        );
        $this->assertCount(2, $response->viewData('relatedEntries'));
    }

    public function test_cultural_detail_requires_publication_approval_but_not_verification(): void
    {
        $this->createEntry('Detail Draft', 'draft', 'verified');
        $this->createEntry('Detail Belum Terverifikasi', 'published', 'pending');
        $deletedId = $this->createEntry('Detail Terhapus');
        DB::table('cultural_entries')->where('id', $deletedId)->update(['deleted_at' => now()]);

        $this->get('/arsip-budaya/detail-draft')->assertNotFound();
        $this->get('/arsip-budaya/detail-belum-terverifikasi')
            ->assertOk()
            ->assertSee('Detail Belum Terverifikasi');
        $this->get('/arsip-budaya/detail-terhapus')->assertNotFound();
        $this->get('/arsip-budaya/tidak-ada')->assertNotFound();
    }

    public function test_archive_and_detail_use_clearly_labeled_editorial_art_for_honai_without_photos(): void
    {
        $honaiId = $this->createEntry('Rumah Honai');
        DB::table('cultural_entries')->where('id', $honaiId)->update(['slug' => 'rumah-honai']);

        $this->get('/arsip-budaya')
            ->assertOk()
            ->assertSee('images/cultural/editorial/rumah-honai.svg')
            ->assertSee('Ilustrasi editorial · bukan foto dokumentasi');

        $this->get('/arsip-budaya/rumah-honai')
            ->assertOk()
            ->assertSee('images/cultural/editorial/rumah-honai.svg')
            ->assertSee('bukan foto dokumentasi autentik');
    }

    public function test_archive_search_requires_a_string_of_at_most_100_characters(): void
    {
        $this->get('/arsip-budaya?search%5B%5D=Wayang')
            ->assertSessionHasErrors('search');

        $this->get('/arsip-budaya?search='.str_repeat('a', 101))
            ->assertSessionHasErrors('search');
    }

    public function test_homepage_only_features_published_verified_entries_and_granted_media(): void
    {
        $visibleId = $this->createEntry('Unggulan Terverifikasi');
        $this->createEntry('Unggulan Belum Terverifikasi', 'published', 'pending');
        $this->createMedia($visibleId, 'granted', 'image');
        $this->createMedia($visibleId, 'approved', 'image');

        $response = $this->get('/');

        $response
            ->assertOk()
            ->assertSee('Unggulan Terverifikasi')
            ->assertDontSee('Unggulan Belum Terverifikasi');

        $featuredEntries = $response->viewData('featuredEntries');
        $this->assertSame(
            ['granted'],
            $featuredEntries->first()->media->pluck('permission_status')->all()
        );
    }

    public function test_region_index_lists_active_provinces_with_public_archive_counts_and_search(): void
    {
        $javaId = $this->createRegion('Jawa Barat', 'jawa-barat');
        $this->createRegion('Bali', 'bali');
        $this->createRegion('Kota Bandung', 'kota-bandung', 'city');
        $this->createRegion('Provinsi Nonaktif', 'provinsi-nonaktif', 'province', false);

        $visibleEntryId = $this->createEntry('Angklung');
        DB::table('cultural_entries')->where('id', $visibleEntryId)->update(['origin_region_id' => $javaId]);

        $draftEntryId = $this->createEntry('Arsip Draft Wilayah', 'draft', 'verified');
        DB::table('cultural_entries')->where('id', $draftEntryId)->update(['origin_region_id' => $javaId]);

        $response = $this->get('/jelajah-nusantara?search=Jawa');

        $response
            ->assertOk()
            ->assertSee('Jawa Barat')
            ->assertDontSee('Bali')
            ->assertDontSee('Kota Bandung')
            ->assertDontSee('Provinsi Nonaktif')
            ->assertSee('1 arsip budaya')
            ->assertSee(route('regions.show', 'jawa-barat'));
    }

    public function test_region_search_requires_a_string_of_at_most_100_characters(): void
    {
        $this->get('/jelajah-nusantara?search%5B%5D=Jawa')
            ->assertSessionHasErrors('search');

        $this->get('/jelajah-nusantara?search='.str_repeat('a', 101))
            ->assertSessionHasErrors('search');
    }

    public function test_region_cards_use_documented_local_photos_or_explicit_fallbacks(): void
    {
        $this->createRegion('Bali', 'bali');
        $this->createRegion('DI Yogyakarta', 'di-yogyakarta');
        $this->createRegion('Jawa Barat', 'jawa-barat');
        $this->createRegion('Jambi', 'jambi');
        $this->createRegion('Papua Tengah', 'papua-tengah');
        $this->createRegion('Sulawesi Utara', 'sulawesi-utara');
        $this->createRegion('Kepulauan Bangka Belitung', 'kepulauan-bangka-belitung');
        $this->createRegion('Sulawesi Tenggara', 'sulawesi-tenggara');
        $this->createRegion('Provinsi Belum Dipetakan', 'provinsi-belum-dipetakan');

        $response = $this->get('/jelajah-nusantara');

        $response
            ->assertOk()
            ->assertSee('Kenali kekayaan budaya dari 9 provinsi')
            ->assertSee('src="'.asset('images/hero/pendet-dance.jpg').'"', false)
            ->assertSee('src="'.asset('images/hero/batik-tulis.jpg').'"', false)
            ->assertSee('src="'.asset('images/regions/north-sulawesi-kabasaran-warrior.png').'"', false)
            ->assertSee('src="'.asset('images/regions/bangka-belitung-kain-limar.jpg').'"', false)
            ->assertSee('src="'.asset('images/regions/papua-tengah-emawa-mee.png').'"', false)
            ->assertSee('src="'.asset('images/regions/southeast-sulawesi-tenun-buton.jpg').'"', false)
            ->assertSee('Ilustrasi arsip · foto belum tersedia')
            ->assertSee('https://commons.wikimedia.org/wiki/File:Pendet_Dance.jpg', false)
            ->assertSee('https://commons.wikimedia.org/wiki/File:Maestro_Batik_Tulis_di_Imogiri.jpg', false)
            ->assertSee('https://commons.wikimedia.org/wiki/File:Kain_limar_(shoulder_cloth)_from_Sumatra,_Bangka_Island,_late_19th_century,_HAA.JPG', false)
            ->assertSee('https://commons.wikimedia.org/wiki/File:Emawa_Mee.png', false)
            ->assertSee('https://commons.wikimedia.org/wiki/File:Tenun_Buton.jpg', false);
    }

    public function test_region_artwork_assets_are_local_and_not_reused(): void
    {
        $artworks = config('region_artworks');

        $this->assertCount(38, $artworks);
        $this->assertCount(
            count($artworks),
            collect($artworks)->pluck('path')->unique()
        );

        foreach ($artworks as $artwork) {
            $this->assertFileExists(public_path($artwork['path']));
        }

        foreach ([
            'gorontalo',
            'jambi',
            'kalimantan-utara',
            'kepulauan-riau',
            'maluku-utara',
            'nusa-tenggara-barat',
            'nusa-tenggara-timur',
            'papua',
            'papua-barat',
            'papua-barat-daya',
            'papua-pegunungan',
            'papua-selatan',
            'sulawesi-barat',
            'sulawesi-tengah',
            'sulawesi-utara',
            'jawa-tengah',
            'kepulauan-bangka-belitung',
            'papua-tengah',
            'sulawesi-tenggara',
        ] as $provinceSlug) {
            $this->assertArrayHasKey($provinceSlug, $artworks);
        }
    }

    public function test_region_detail_shows_only_published_verified_archives(): void
    {
        $regionId = $this->createRegion('Sumatera Barat', 'sumatera-barat');
        $visibleEntryId = $this->createEntry('Rumah Gadang');
        DB::table('cultural_entries')->where('id', $visibleEntryId)->update(['origin_region_id' => $regionId]);
        $this->createMedia($visibleEntryId, 'granted');
        $this->createMedia($visibleEntryId, 'pending');

        $draftEntryId = $this->createEntry('Catatan Draft', 'draft', 'verified');
        DB::table('cultural_entries')->where('id', $draftEntryId)->update(['origin_region_id' => $regionId]);

        $unverifiedEntryId = $this->createEntry('Catatan Belum Terverifikasi', 'published', 'pending');
        DB::table('cultural_entries')->where('id', $unverifiedEntryId)->update(['origin_region_id' => $regionId]);

        $deletedEntryId = $this->createEntry('Catatan Terhapus');
        DB::table('cultural_entries')->where('id', $deletedEntryId)->update([
            'origin_region_id' => $regionId,
            'deleted_at' => now(),
        ]);

        $response = $this->get('/jelajah-nusantara/sumatera-barat');

        $response
            ->assertOk()
            ->assertSee('Sumatera Barat')
            ->assertSee('Rumah Gadang')
            ->assertDontSee('Catatan Draft')
            ->assertDontSee('Catatan Belum Terverifikasi');

        $this->assertSame(1, $response->viewData('region')->cultural_entries_count);
        $entries = $response->viewData('entries');
        $this->assertSame(['granted'], $entries->first()->media->pluck('permission_status')->all());
        $this->assertSame(1, $entries->total());
    }

    public function test_region_detail_paginates_public_archives_and_preserves_page_links(): void
    {
        $regionId = $this->createRegion('Jawa Tengah', 'jawa-tengah');

        foreach (range(1, 13) as $number) {
            $entryId = $this->createEntry(sprintf('Tradisi %02d', $number));
            DB::table('cultural_entries')->where('id', $entryId)->update(['origin_region_id' => $regionId]);
        }

        $response = $this->get('/jelajah-nusantara/jawa-tengah');

        $response->assertOk()->assertSee('Tradisi 01')->assertDontSee('Tradisi 13');
        $entries = $response->viewData('entries');
        $this->assertSame(13, $entries->total());
        $this->assertCount(12, $entries->items());
        $this->assertStringContainsString('page=2', $entries->url(2));

        $secondPage = $this->get('/jelajah-nusantara/jawa-tengah?page=2');
        $secondPage->assertOk()->assertSee('Tradisi 13');
        $this->assertSame(13, $secondPage->viewData('entries')->firstItem());
    }

    public function test_region_detail_returns_not_found_for_non_province_regions(): void
    {
        $this->createRegion('Kota Bandung', 'kota-bandung', 'city');

        $this->get('/jelajah-nusantara/kota-bandung')->assertNotFound();
    }

    private function createEntry(
        string $title,
        string $publicationStatus = 'published',
        string $verificationStatus = 'verified'
    ): int {
        return DB::table('cultural_entries')->insertGetId([
            'title' => $title,
            'slug' => str($title)->slug(),
            'publication_status' => $publicationStatus,
            'verification_status' => $verificationStatus,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createMedia(
        int $entryId,
        string $permissionStatus,
        ?string $filePath = null,
        ?string $externalUrl = null
    ): void
    {
        DB::table('media')->insert([
            'cultural_entry_id' => $entryId,
            'media_type' => 'image',
            'file_path' => $filePath,
            'external_url' => $externalUrl,
            'permission_status' => $permissionStatus,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createRegion(
        string $name,
        string $slug,
        string $type = 'province',
        bool $active = true
    ): int {
        return DB::table('regions')->insertGetId([
            'name' => $name,
            'slug' => $slug,
            'region_type' => $type,
            'is_active' => $active,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createCategory(string $name, string $slug, bool $active = true): int
    {
        return DB::table('categories')->insertGetId([
            'name' => $name,
            'slug' => $slug,
            'is_active' => $active,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createSchema(): void
    {
        Schema::create('regions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('region_type', 30)->default('province');
            $table->string('code', 30)->nullable();
            $table->text('description')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('categories', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('cultural_entries', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('local_name')->nullable();
            $table->text('short_description')->nullable();
            $table->longText('description')->nullable();
            $table->longText('history')->nullable();
            $table->longText('meaning_and_function')->nullable();
            $table->longText('techniques')->nullable();
            $table->text('materials')->nullable();
            $table->text('community_context')->nullable();
            $table->json('keywords')->nullable();
            $table->unsignedBigInteger('origin_region_id')->nullable();
            $table->string('publication_status', 20)->default('draft');
            $table->string('verification_status', 20)->default('pending');
            $table->timestamp('verified_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('category_cultural_entry', function (Blueprint $table): void {
            $table->unsignedBigInteger('cultural_entry_id');
            $table->unsignedBigInteger('category_id');
            $table->primary(['cultural_entry_id', 'category_id']);
        });

        Schema::create('media', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('cultural_entry_id');
            $table->string('media_type', 30)->default('image');
            $table->string('file_path')->nullable();
            $table->text('external_url')->nullable();
            $table->string('caption')->nullable();
            $table->string('alt_text')->nullable();
            $table->string('creator')->nullable();
            $table->string('copyright_holder')->nullable();
            $table->string('license')->nullable();
            $table->string('permission_status', 20)->default('pending');
            $table->text('permission_notes')->nullable();
            $table->boolean('is_cover')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('sources', function (Blueprint $table): void {
            $table->id();
            $table->string('source_type', 30)->default('book');
            $table->string('title');
            $table->string('author')->nullable();
            $table->string('publisher')->nullable();
            $table->unsignedSmallInteger('publication_year')->nullable();
            $table->text('url')->nullable();
            $table->string('identifier')->nullable();
            $table->date('accessed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('cultural_entry_source', function (Blueprint $table): void {
            $table->unsignedBigInteger('cultural_entry_id');
            $table->unsignedBigInteger('source_id');
            $table->primary(['cultural_entry_id', 'source_id']);
        });
    }
}
