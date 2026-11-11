<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminCulturalCurationTest extends TestCase
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

    public function test_admin_dashboard_includes_draft_pending_and_soft_deleted_entries(): void
    {
        $this->createEntry('Arsip Draft Pending');
        $this->createEntry('Arsip Verified', 'draft', 'verified');
        $this->createEntry('Arsip Soft Deleted', 'draft', 'pending', true);
        $admin = $this->createUser(User::ROLE_SUPER_ADMIN);

        $response = $this->actingAs($admin)->get(route('admin.home'));

        $response
            ->assertOk()
            ->assertSee('Dashboard Kurasi')
            ->assertSee('Arsip Draft Pending')
            ->assertSee('Arsip Verified')
            ->assertSee('Arsip Soft Deleted');

        $this->assertSame(3, $response->viewData('stats')['total']);
        $this->assertSame(2, $response->viewData('stats')['pending']);
        $this->assertSame(1, $response->viewData('stats')['verified']);
        $this->assertSame(1, $response->viewData('stats')['deleted']);
    }

    public function test_guests_and_users_without_admin_roles_cannot_access_curation(): void
    {
        $this->get(route('admin.home'))->assertRedirect(route('admin.login'));

        $user = $this->createUser(null);
        $this->actingAs($user)->get(route('admin.home'))->assertForbidden();
    }

    public function test_verification_decisions_are_logged_without_changing_publication_status(): void
    {
        $entryId = $this->createEntry('Arsip untuk Ditinjau');
        $superAdmin = $this->createUser(User::ROLE_SUPER_ADMIN);

        $this->actingAs($superAdmin)
            ->post(route('admin.curation.entries.verification', $entryId), [
                'decision' => 'approve',
                'notes' => 'Diperiksa dengan sumber yang tersedia.',
            ])
            ->assertRedirect(route('admin.curation.entries.show', $entryId));

        $entry = DB::table('cultural_entries')->find($entryId);
        $this->assertSame('verified', $entry->verification_status);
        $this->assertSame('draft', $entry->publication_status);
        $this->assertNotNull($entry->verified_at);

        $log = DB::table('verification_logs')->where('cultural_entry_id', $entryId)->first();
        $this->assertSame($superAdmin->id, $log->reviewer_id);
        $this->assertSame('verified', $log->decision);
        $this->assertStringContainsString('pending → verified', $log->notes);
    }

    public function test_rejected_verification_does_not_revoke_publication_approval(): void
    {
        $entryId = $this->createEntry('Arsip Perlu Perbaikan', 'published', 'verified');
        DB::table('cultural_entries')->where('id', $entryId)->update(['verified_at' => now()]);
        $superAdmin = $this->createUser(User::ROLE_SUPER_ADMIN);

        $this->actingAs($superAdmin)
            ->post(route('admin.curation.entries.verification', $entryId), [
                'decision' => 'reject',
                'notes' => 'Sumber belum cukup untuk mendukung klaim.',
            ])
            ->assertRedirect(route('admin.curation.entries.show', $entryId));

        $entry = DB::table('cultural_entries')->find($entryId);
        $this->assertSame('pending', $entry->verification_status);
        $this->assertSame('published', $entry->publication_status);
        $this->assertNull($entry->verified_at);
        $this->assertNotNull($entry->published_at);
        $this->assertSame(1, DB::table('cultural_entries')->where('id', $entryId)->where('publication_status', 'published')->count());

        $log = DB::table('verification_logs')->where('cultural_entry_id', $entryId)->first();
        $this->assertSame('rejected', $log->decision);
        $this->assertSame($superAdmin->id, $log->reviewer_id);
        $this->assertStringContainsString('Sumber belum cukup', $log->notes);
    }

    public function test_rejection_requires_a_reason_and_does_not_create_a_log_without_one(): void
    {
        $entryId = $this->createEntry('Arsip Tanpa Catatan');
        $superAdmin = $this->createUser(User::ROLE_SUPER_ADMIN);

        $this->actingAs($superAdmin)
            ->from(route('admin.curation.entries.show', $entryId))
            ->post(route('admin.curation.entries.verification', $entryId), [
                'decision' => 'reject',
            ])
            ->assertSessionHasErrors('notes');

        $this->assertSame(0, DB::table('verification_logs')->count());
        $this->assertSame('pending', DB::table('cultural_entries')->find($entryId)->verification_status);
    }

    public function test_super_admin_can_approve_pending_entry_for_publication_without_media_permission(): void
    {
        $regionId = $this->createRegion('Jawa Barat');
        $entryId = $this->createEntry(
            'Angklung',
            'draft',
            'pending',
            false,
            $regionId,
            'Alat musik bambu tradisional yang dimainkan dengan cara digoyangkan.'
        );
        $this->createMedia($entryId, 'pending', 'foto-menunggu.jpg');
        $admin = $this->createUser(User::ROLE_SUPER_ADMIN);

        $this->actingAs($admin)
            ->post(route('admin.curation.entries.publication', $entryId), [
                'action' => 'publish',
                'notes' => 'Diperiksa dan disetujui untuk publikasi.',
                'return_to' => 'manage',
            ])
            ->assertRedirect(route('admin.cultural-entries.manage'));

        $entry = DB::table('cultural_entries')->find($entryId);
        $this->assertSame('published', $entry->publication_status);
        $this->assertSame('pending', $entry->verification_status);
        $this->assertNotNull($entry->published_at);
        $this->assertSame('pending', DB::table('media')->where('cultural_entry_id', $entryId)->value('permission_status'));

        $publicResponse = $this->get(route('cultural-entries.index'));
        $publicResponse->assertOk()->assertSee('Angklung');
        $publicEntries = $publicResponse->viewData('entries');
        $this->assertSame([], $publicEntries->first()->media->all());

        $log = DB::table('verification_logs')->where('cultural_entry_id', $entryId)->first();
        $this->assertSame('published', $log->decision);
        $this->assertStringContainsString('draft → published', $log->notes);
    }

    public function test_legacy_verifikator_cannot_approve_publication(): void
    {
        $entryId = $this->createEntry('Belum Terverifikasi');
        $verifikator = $this->createUser('verifikator');

        $this->actingAs($verifikator)
            ->from(route('admin.curation.entries.show', $entryId))
            ->post(route('admin.curation.entries.publication', $entryId), [
                'action' => 'publish',
            ])
            ->assertForbidden();

        $this->assertSame('draft', DB::table('cultural_entries')->find($entryId)->publication_status);
        $this->assertSame(0, DB::table('verification_logs')->count());
    }

    public function test_super_admin_can_open_data_budaya_with_direct_publication_control(): void
    {
        $superAdmin = $this->createUser(User::ROLE_SUPER_ADMIN);

        $this->actingAs($superAdmin)
            ->get(route('admin.cultural-entries.manage'))
            ->assertOk()
            ->assertSee('data-publication-template', false)
            ->assertSee('data-csrf-token', false)
            ->assertSee('Publikasikan semua yang layak')
            ->assertSee(route('admin.curation.entries.publication.bulk'));
    }

    public function test_bulk_publication_publishes_only_eligible_drafts_and_logs_each_decision(): void
    {
        $regionId = $this->createRegion('Jawa Barat');
        $eligibleId = $this->createEntry(
            'Entri Layak',
            'draft',
            'pending',
            false,
            $regionId,
            'Deskripsi tersedia.'
        );
        $missingRegionId = $this->createEntry(
            'Entri Tanpa Wilayah',
            'draft',
            'pending',
            false,
            null,
            'Deskripsi tersedia.'
        );
        $deletedId = $this->createEntry(
            'Entri Terhapus',
            'draft',
            'pending',
            true,
            $regionId,
            'Deskripsi tersedia.'
        );
        $superAdmin = $this->createUser(User::ROLE_SUPER_ADMIN);

        $this->actingAs($superAdmin)
            ->post(route('admin.curation.entries.publication.bulk'), [
                'confirm_bulk_publish' => '1',
            ])
            ->assertRedirect(route('admin.cultural-entries.manage'))
            ->assertSessionHas('status', '1 entri berhasil dipublikasikan; 1 entri dilewati karena syarat publikasi belum terpenuhi.')
            ->assertSessionHas('publication_bulk_skipped');

        $this->assertSame('published', DB::table('cultural_entries')->find($eligibleId)->publication_status);
        $this->assertSame('pending', DB::table('cultural_entries')->find($eligibleId)->verification_status);
        $this->assertSame('draft', DB::table('cultural_entries')->find($missingRegionId)->publication_status);
        $this->assertSame('draft', DB::table('cultural_entries')->withTrashed()->find($deletedId)->publication_status);
        $this->assertSame(1, DB::table('verification_logs')->where('cultural_entry_id', $eligibleId)->where('decision', 'published')->count());
    }

    public function test_explicit_publication_decisions_are_logged_and_repeated_approval_is_audited(): void
    {
        $regionId = $this->createRegion('Jawa Barat');
        $entryId = $this->createEntry(
            'Publikasi Pending',
            'draft',
            'pending',
            false,
            $regionId,
            'Deskripsi arsip budaya.'
        );
        $admin = $this->createUser(User::ROLE_SUPER_ADMIN);

        $this->actingAs($admin)
            ->post(route('admin.curation.entries.publication', $entryId), [
                'action' => 'publish',
                'notes' => 'Disetujui untuk Arsip.',
            ])
            ->assertRedirect(route('admin.curation.entries.show', $entryId));

        $publishedAt = DB::table('cultural_entries')->find($entryId)->published_at;
        $this->actingAs($admin)
            ->post(route('admin.curation.entries.publication', $entryId), [
                'action' => 'publish',
            ])
            ->assertRedirect(route('admin.curation.entries.show', $entryId));

        $this->assertSame($publishedAt, DB::table('cultural_entries')->find($entryId)->published_at);
        $this->assertSame(2, DB::table('verification_logs')->where('cultural_entry_id', $entryId)->count());
        $this->assertSame(
            ['published', 'published'],
            DB::table('verification_logs')->where('cultural_entry_id', $entryId)->orderBy('id')->pluck('decision')->all()
        );
    }

    public function test_publishing_requires_a_valid_region_and_description_but_not_verification_or_media(): void
    {
        $entryId = $this->createEntry('Informasi Belum Lengkap', 'draft', 'verified');
        $admin = $this->createUser(User::ROLE_SUPER_ADMIN);

        $this->actingAs($admin)
            ->from(route('admin.curation.entries.show', $entryId))
            ->post(route('admin.curation.entries.publication', $entryId), [
                'action' => 'publish',
            ])
            ->assertRedirect(route('admin.curation.entries.show', $entryId))
            ->assertSessionHas('publication_blockers');

        $this->assertSame('draft', DB::table('cultural_entries')->find($entryId)->publication_status);
        $this->assertSame(0, DB::table('verification_logs')->count());
    }

    public function test_admin_can_inspect_full_entry_media_sources_and_existing_history(): void
    {
        $regionId = $this->createRegion('Papua Barat');
        $entryId = $this->createEntry(
            'Rumah Kaki Seribu',
            'draft',
            'pending',
            false,
            $regionId,
            'Rumah tradisional masyarakat Arfak.'
        );
        $mediaId = $this->createMedia($entryId, 'pending', 'images/arsip/rumah-arfak.jpg');
        DB::table('media')->where('id', $mediaId)->update([
            'creator' => 'Dokumentator Budaya',
            'copyright_holder' => 'Komunitas Arfak',
            'license' => 'Perlu izin',
            'permission_notes' => 'Menunggu surat persetujuan.',
        ]);
        $sourceId = DB::table('sources')->insertGetId([
            'source_type' => 'book',
            'title' => 'Arsitektur Tradisional Arfak',
            'author' => 'Peneliti Budaya',
            'publisher' => 'Penerbit Nusantara',
            'publication_year' => 2020,
            'url' => 'https://example.test/arfak',
            'identifier' => 'ISBN-TEST-2020',
            'accessed_at' => '2026-10-31',
            'notes' => 'Catatan bibliografi untuk pemeriksaan.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('cultural_entry_source')->insert([
            'cultural_entry_id' => $entryId,
            'source_id' => $sourceId,
            'reference_note' => 'Bab mengenai rumah tinggal.',
        ]);
        DB::table('verification_logs')->insert([
            'cultural_entry_id' => $entryId,
            'reviewer_id' => null,
            'decision' => 'rejected',
            'notes' => 'Perlu verifikasi komunitas.',
            'reviewed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $admin = $this->createUser(User::ROLE_SUPER_ADMIN);

        $response = $this->actingAs($admin)->get(route('admin.curation.entries.show', $entryId));

        $response
            ->assertOk()
            ->assertSee('Rumah tradisional masyarakat Arfak.')
            ->assertSee('Menunggu surat persetujuan.')
            ->assertSee('Dokumentator Budaya')
            ->assertSee('Arsitektur Tradisional Arfak')
            ->assertSee('ISBN-TEST-2020')
            ->assertSee('Catatan bibliografi untuk pemeriksaan.')
            ->assertSee('Perlu verifikasi komunitas.')
            ->assertSee('https://example.test/arfak');
    }

    public function test_admin_can_reject_media_without_granting_it_and_history_is_recorded(): void
    {
        $entryId = $this->createEntry('Arsip Foto');
        $mediaId = $this->createMedia($entryId, 'granted', 'foto-bermasalah.jpg');
        $admin = $this->createUser(User::ROLE_SUPER_ADMIN);

        $this->actingAs($admin)
            ->post(route('admin.curation.media.permission', [$entryId, $mediaId]), [
                'decision' => 'reject',
                'notes' => 'Pemegang hak belum memberikan izin.',
            ])
            ->assertRedirect(route('admin.curation.entries.show', $entryId));

        $media = DB::table('media')->find($mediaId);
        $this->assertSame('pending', $media->permission_status);
        $this->assertStringContainsString('Pemegang hak belum memberikan izin.', $media->permission_notes);

        $log = DB::table('verification_logs')->where('cultural_entry_id', $entryId)->first();
        $this->assertSame('media_rejected', $log->decision);
        $this->assertSame($admin->id, $log->reviewer_id);
        $this->assertStringContainsString('granted → pending', $log->notes);
    }

    public function test_media_permission_can_be_granted_separately_with_evidence_note(): void
    {
        $entryId = $this->createEntry('Arsip dengan Izin');
        $mediaId = $this->createMedia($entryId, 'pending', 'foto-berizin.jpg');
        $admin = $this->createUser(User::ROLE_SUPER_ADMIN);

        $this->actingAs($admin)
            ->post(route('admin.curation.media.permission', [$entryId, $mediaId]), [
                'decision' => 'grant',
                'notes' => 'Surat izin pemegang hak diperiksa.',
            ])
            ->assertRedirect(route('admin.curation.entries.show', $entryId));

        $this->assertSame('granted', DB::table('media')->find($mediaId)->permission_status);
        $this->assertSame('draft', DB::table('cultural_entries')->find($entryId)->publication_status);
        $log = DB::table('verification_logs')->where('cultural_entry_id', $entryId)->first();
        $this->assertSame('media_granted', $log->decision);
        $this->assertStringContainsString('Surat izin pemegang hak diperiksa.', $log->notes);
    }

    public function test_admin_dashboard_shows_pending_media_preview_source_and_license(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('cultural/angklung.jpg', 'image-content');

        $entryId = $this->createEntry('Angklung');
        $mediaId = $this->createMedia($entryId, 'pending', 'cultural/angklung.jpg');
        DB::table('media')->where('id', $mediaId)->update([
            'external_url' => 'https://commons.wikimedia.org/wiki/File:Angklung.jpg',
            'creator' => 'Dokumentator Budaya',
            'license' => 'CC BY-SA 4.0',
            'alt_text' => 'Angklung bambu dalam sebuah pertunjukan.',
        ]);
        $sourceId = DB::table('sources')->insertGetId([
            'source_type' => 'website',
            'title' => 'Dokumentasi Angklung',
            'url' => 'https://example.test/angklung',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('cultural_entry_source')->insert([
            'cultural_entry_id' => $entryId,
            'source_id' => $sourceId,
        ]);

        $admin = $this->createUser(User::ROLE_SUPER_ADMIN);
        $response = $this->actingAs($admin)->get(route('admin.home'));

        $response
            ->assertOk()
            ->assertSee('Media menunggu persetujuan')
            ->assertSee('Angklung bambu dalam sebuah pertunjukan.')
            ->assertSee('https://commons.wikimedia.org/wiki/File:Angklung.jpg')
            ->assertSee('CC BY-SA 4.0')
            ->assertSee('https://example.test/angklung')
            ->assertSee('Setujui semua 1 media yang memenuhi syarat');
    }

    public function test_bulk_media_approval_grants_only_eligible_pending_media_and_logs_each_decision(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('cultural/angklung.jpg', 'image-content');

        $entryId = $this->createEntry('Angklung');
        $eligibleMediaId = $this->createMedia($entryId, 'pending', 'cultural/angklung.jpg');
        DB::table('media')->where('id', $eligibleMediaId)->update([
            'external_url' => 'https://commons.wikimedia.org/wiki/File:Angklung.jpg',
            'creator' => 'Dokumentator Budaya',
            'license' => 'CC BY-SA 4.0',
            'alt_text' => 'Angklung bambu dalam sebuah pertunjukan.',
        ]);

        $ineligibleMediaId = $this->createMedia($entryId, 'pending', 'cultural/angklung.jpg');
        DB::table('media')->where('id', $ineligibleMediaId)->update([
            'external_url' => 'https://commons.wikimedia.org/wiki/File:Angklung.jpg',
            'creator' => 'Dokumentator Budaya',
            'license' => 'Lisensi tidak diketahui',
            'alt_text' => 'Foto tanpa lisensi yang dikenali.',
        ]);

        $admin = $this->createUser(User::ROLE_SUPER_ADMIN);
        $this->actingAs($admin)
            ->post(route('admin.curation.media.permission.bulk'), [
                'return_to' => 'media-sources',
            ])
            ->assertRedirect(route('admin.media-sources.page'))
            ->assertSessionHas('status', '1 media pending disetujui; 1 media dilewati karena belum memenuhi syarat.')
            ->assertSessionHas('media_bulk_skipped')
            ->assertSessionHas('media_bulk_granted_count', 1);

        $this->assertSame('granted', DB::table('media')->find($eligibleMediaId)->permission_status);
        $this->assertSame('pending', DB::table('media')->find($ineligibleMediaId)->permission_status);
        $this->assertSame('draft', DB::table('cultural_entries')->find($entryId)->publication_status);
        $this->assertSame(1, DB::table('verification_logs')->where('decision', 'media_granted')->count());
        $this->assertSame($admin->id, DB::table('verification_logs')->where('decision', 'media_granted')->value('reviewer_id'));
    }

    public function test_legacy_verifikator_cannot_bulk_grant_pending_media(): void
    {
        $entryId = $this->createEntry('Media dibatasi');
        $mediaId = $this->createMedia($entryId, 'pending', 'cultural/angklung.jpg');
        $verifikator = $this->createUser('verifikator');

        $this->actingAs($verifikator)
            ->post(route('admin.curation.media.permission.bulk'))
            ->assertForbidden();

        $this->assertSame('pending', DB::table('media')->find($mediaId)->permission_status);
        $this->assertSame(0, DB::table('verification_logs')->count());
    }

    public function test_admin_dashboard_filters_and_paginates_without_hiding_pending_entries(): void
    {
        $regionId = $this->createRegion('Bali');
        $categoryId = $this->createCategory('Seni Pertunjukan');
        foreach (range(1, 16) as $number) {
            $entryId = $this->createEntry(sprintf('Tari Bali %02d', $number), 'draft', 'pending', false, $regionId);
            DB::table('category_cultural_entry')->insert([
                'cultural_entry_id' => $entryId,
                'category_id' => $categoryId,
            ]);
        }
        $this->createEntry('Wayang Jawa');
        $admin = $this->createUser(User::ROLE_SUPER_ADMIN);

        $response = $this->actingAs($admin)->get(route('admin.home', [
            'search' => 'Tari',
            'region_id' => $regionId,
            'category_id' => $categoryId,
            'verification_status' => 'pending',
            'publication_status' => 'draft',
        ]));

        $response->assertOk()->assertSee('Tari Bali 01')->assertDontSee('Wayang Jawa');
        $entries = $response->viewData('entries');
        $this->assertSame(16, $entries->total());
        $this->assertCount(15, $entries->items());
        $this->assertStringContainsString('verification_status=pending', $entries->url(2));
    }

    public function test_legacy_roles_cannot_verify_publish_or_manage_media(): void
    {
        $entryId = $this->createEntry('Arsip Dibatasi');
        $mediaId = $this->createMedia($entryId, 'pending');
        $legacyVerifier = $this->createUser('verifikator');
        $legacyAdmin = $this->createUser('admin');

        $this->post(route('admin.curation.entries.verification', $entryId), [
            'decision' => 'approve',
        ])->assertRedirect(route('admin.login'));

        $this->actingAs($legacyAdmin)
            ->post(route('admin.curation.entries.verification', $entryId), [
                'decision' => 'approve',
            ])
            ->assertForbidden();

        $this->actingAs($legacyVerifier)
            ->post(route('admin.curation.entries.publication', $entryId), ['action' => 'publish'])
            ->assertForbidden();

        $this->actingAs($legacyVerifier)
            ->post(route('admin.curation.media.permission', [$entryId, $mediaId]), [
                'decision' => 'grant',
                'notes' => 'Lisensi sudah diperiksa.',
            ])
            ->assertForbidden();
    }

    private function createUser(?string $role): User
    {
        $user = new User;
        $user->forceFill([
            'name' => 'Kurator '.($role ?? 'tanpa-role'),
            'email' => uniqid('kurator-', true).'@example.test',
            'password' => Hash::make('password-sementara'),
            'role' => $role,
        ]);
        $user->save();

        return $user;
    }

    private function createEntry(
        string $title,
        string $publicationStatus = 'draft',
        string $verificationStatus = 'pending',
        bool $deleted = false,
        ?int $regionId = null,
        ?string $description = null
    ): int {
        return DB::table('cultural_entries')->insertGetId([
            'title' => $title,
            'slug' => str($title)->slug().'-'.uniqid(),
            'short_description' => $description,
            'description' => $description,
            'origin_region_id' => $regionId,
            'publication_status' => $publicationStatus,
            'verification_status' => $verificationStatus,
            'verified_at' => $verificationStatus === 'verified' ? now() : null,
            'published_at' => $publicationStatus === 'published' ? now() : null,
            'created_at' => now(),
            'updated_at' => now(),
            'deleted_at' => $deleted ? now() : null,
        ]);
    }

    private function createRegion(string $name): int
    {
        return DB::table('regions')->insertGetId([
            'name' => $name,
            'slug' => str($name)->slug(),
            'region_type' => 'province',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createCategory(string $name): int
    {
        return DB::table('categories')->insertGetId([
            'name' => $name,
            'slug' => str($name)->slug(),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createMedia(int $entryId, string $permissionStatus, ?string $filePath = null): int
    {
        return DB::table('media')->insertGetId([
            'cultural_entry_id' => $entryId,
            'media_type' => 'image',
            'file_path' => $filePath,
            'caption' => $filePath,
            'permission_status' => $permissionStatus,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createSchema(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->string('role', 20)->nullable();
            $table->timestamps();
        });

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
            $table->text('reference_note')->nullable();
            $table->primary(['cultural_entry_id', 'source_id']);
        });

        Schema::create('verification_logs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('cultural_entry_id');
            $table->unsignedBigInteger('reviewer_id')->nullable();
            $table->string('decision', 20);
            $table->text('notes')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
    }
}
