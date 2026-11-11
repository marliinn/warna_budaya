<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdminCulturalEntryManagementTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'session.driver' => 'array',
        ]);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');

        $this->createSchema();
    }

    public function test_guest_is_denied_and_only_content_managers_can_access_the_api(): void
    {
        $this->getJson(route('admin.cultural-entries.api.index'))->assertUnauthorized();

        foreach (['verifikator', null] as $role) {
            $user = $this->createUser($role);

            $this->actingAs($user)
                ->getJson(route('admin.cultural-entries.api.index'))
                ->assertForbidden();
            $this->actingAs($user)
                ->postJson(route('admin.cultural-entries.api.store'), [
                    'title' => 'Tidak Diizinkan',
                    'slug' => 'tidak-diizinkan',
                ])
                ->assertForbidden();
        }

        foreach ([User::ROLE_SUPER_ADMIN] as $role) {
            $user = $this->createUser($role);
            $this->actingAs($user)
                ->getJson(route('admin.cultural-entries.api.index'))
                ->assertOk();
            $this->postJson(route('admin.cultural-entries.api.store'), [
                'title' => 'Allowed '.$role,
                'slug' => 'allowed-'.str_replace('_', '-', $role),
            ])->assertCreated();
        }
    }

    public function test_create_validates_slug_and_relations_and_keeps_workflow_statuses_managed_separately(): void
    {
        $admin = $this->createUser(User::ROLE_SUPER_ADMIN);
        $regionId = $this->createRegion('Jawa Barat', 'jawa-barat');
        $categoryId = $this->createCategory('Musik', 'musik');
        $this->createEntry('Existing Entry', 'existing-entry');

        $this->actingAs($admin)
            ->postJson(route('admin.cultural-entries.api.store'), [
                'title' => 'Duplikat',
                'slug' => 'existing-entry',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['slug']);

        $this->postJson(route('admin.cultural-entries.api.store'), [
            'title' => '',
            'slug' => 'invalid slug',
            'origin_region_id' => 999,
            'category_ids' => [999],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'slug', 'origin_region_id', 'category_ids.0']);

        $this->postJson(route('admin.cultural-entries.api.store'), [
            'title' => 'Status Tidak Diizinkan',
            'slug' => 'status-tidak-diizinkan',
            'publication_status' => 'published',
            'verification_status' => 'verified',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['publication_status', 'verification_status']);

        $response = $this->postJson(route('admin.cultural-entries.api.store'), [
            'title' => 'Angklung',
            'slug' => 'angklung',
            'short_description' => 'Alat musik bambu.',
            'description' => 'Deskripsi panjang budaya.',
            'keywords' => ['musik', 'bambu'],
            'origin_region_id' => $regionId,
            'category_ids' => [$categoryId],
        ])->assertCreated()
            ->assertJsonPath('data.publication_status', 'draft')
            ->assertJsonPath('data.verification_status', 'pending')
            ->assertJsonPath('data.region.name', 'Jawa Barat')
            ->assertJsonPath('data.categories.0.slug', 'musik')
            ->assertJsonPath('data.keywords.0', 'musik');

        $entryId = $response->json('data.id');
        $this->assertSame($admin->id, DB::table('cultural_entries')->where('id', $entryId)->value('created_by'));
        $this->assertSame(1, DB::table('category_cultural_entry')->where('cultural_entry_id', $entryId)->count());
    }

    public function test_create_entry_with_media_is_atomic_and_sets_workflow_statuses_on_the_server(): void
    {
        $admin = $this->createUser(User::ROLE_SUPER_ADMIN);

        $response = $this->actingAs($admin)
            ->postJson(route('admin.cultural-entries.api.store-with-media'), [
                'entry' => [
                    'title' => 'Tradisi Anyaman Bambu',
                    'slug' => 'tradisi-anyaman-bambu',
                    'short_description' => 'Tradisi kerajinan bambu.',
                    'keywords' => ['anyaman', 'bambu'],
                ],
                'media' => [
                    'media_type' => 'image',
                    'file_path' => 'media/anyaman-bambu.jpg',
                    'caption' => 'Pengrajin anyaman bambu',
                    'is_cover' => true,
                ],
            ])->assertCreated()
            ->assertJsonPath('data.cultural_entry.title', 'Tradisi Anyaman Bambu')
            ->assertJsonPath('data.cultural_entry.publication_status', 'draft')
            ->assertJsonPath('data.cultural_entry.verification_status', 'pending')
            ->assertJsonPath('data.cultural_entry.keywords.0', 'anyaman')
            ->assertJsonPath('data.media.permission_status', 'pending')
            ->assertJsonPath('data.media.cultural_entry.title', 'Tradisi Anyaman Bambu');

        $entryId = $response->json('data.cultural_entry.id');
        $mediaId = $response->json('data.media.id');
        $this->assertDatabaseHas('cultural_entries', [
            'id' => $entryId,
            'slug' => 'tradisi-anyaman-bambu',
            'publication_status' => 'draft',
            'verification_status' => 'pending',
        ]);
        $this->assertDatabaseHas('media', [
            'id' => $mediaId,
            'cultural_entry_id' => $entryId,
            'permission_status' => 'pending',
            'file_path' => 'media/anyaman-bambu.jpg',
        ]);
    }

    public function test_create_entry_with_media_rolls_back_entry_when_media_insert_fails(): void
    {
        DB::statement("
            CREATE TRIGGER reject_media_insert
            BEFORE INSERT ON media
            BEGIN
                SELECT RAISE(FAIL, 'Simulated media persistence failure');
            END
        ");

        $this->actingAs($this->createUser(User::ROLE_SUPER_ADMIN))
            ->postJson(route('admin.cultural-entries.api.store-with-media'), [
                'entry' => [
                    'title' => 'Entri yang Harus Rollback',
                    'slug' => 'entri-harus-rollback',
                ],
                'media' => [
                    'file_path' => 'media/gagal.jpg',
                    'is_cover' => false,
                ],
            ])->assertInternalServerError()
            ->assertExactJson([
                'message' => 'Terjadi kesalahan pada layanan. Silakan coba kembali.',
            ])
            ->assertDontSee('Simulated media persistence failure');

        $this->assertDatabaseMissing('cultural_entries', ['slug' => 'entri-harus-rollback']);
        $this->assertDatabaseCount('media', 0);
    }

    public function test_create_entry_with_media_validates_both_payloads_and_prohibits_client_statuses(): void
    {
        $this->actingAs($this->createUser(User::ROLE_SUPER_ADMIN))
            ->postJson(route('admin.cultural-entries.api.store-with-media'), [
                'entry' => [
                    'title' => '',
                    'slug' => 'slug tidak valid',
                    'publication_status' => 'published',
                    'verification_status' => 'verified',
                ],
                'media' => [
                    'file_path' => '',
                    'is_cover' => 'not-a-boolean',
                    'permission_status' => 'granted',
                ],
            ])->assertUnprocessable()
            ->assertJsonValidationErrors([
                'entry.title',
                'entry.slug',
                'entry.publication_status',
                'entry.verification_status',
                'media.file_path',
                'media.external_url',
                'media.is_cover',
                'media.permission_status',
            ]);

        $this->postJson(route('admin.cultural-entries.api.store-with-media'), [
            'entry' => ['title' => 'Entri status', 'slug' => 'entri-status'],
            'media' => ['file_path' => 'media/entri-status.jpg', 'is_cover' => false],
            'publication_status' => 'published',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['publication_status']);
    }

    public function test_create_entry_with_media_obeys_admin_and_media_permissions(): void
    {
        $route = route('admin.cultural-entries.api.store-with-media');
        $payload = [
            'entry' => ['title' => 'Akses Dilarang', 'slug' => 'akses-dilarang'],
            'media' => ['file_path' => 'media/akses.jpg', 'is_cover' => false],
        ];

        $this->postJson($route, $payload)->assertUnauthorized();

        $verifikator = $this->createUser('verifikator');
        $this->actingAs($verifikator)->postJson($route, $payload)->assertForbidden();

        foreach ([User::ROLE_SUPER_ADMIN] as $role) {
            $user = $this->createUser($role);
            $this->actingAs($user)
                ->postJson($route, [
                    'entry' => [
                        'title' => 'Entri '.$role,
                        'slug' => 'entri-'.str_replace('_', '-', $role),
                    ],
                    'media' => ['file_path' => 'media/'.str_replace('_', '-', $role).'.jpg', 'is_cover' => false],
                ])
                ->assertCreated();
        }
    }

    public function test_update_syncs_categories_and_resets_published_verified_content_with_audit_log(): void
    {
        $admin = $this->createUser(User::ROLE_SUPER_ADMIN);
        $regionId = $this->createRegion('Bali', 'bali');
        $firstCategoryId = $this->createCategory('Tari', 'tari');
        $secondCategoryId = $this->createCategory('Upacara', 'upacara');
        $entryId = $this->createEntry('Tari Pendet', 'tari-pendet', $regionId, 'published', 'verified');
        DB::table('cultural_entries')->where('id', $entryId)->update([
            'verified_at' => now(),
            'published_at' => now(),
        ]);
        DB::table('category_cultural_entry')->insert([
            'cultural_entry_id' => $entryId,
            'category_id' => $firstCategoryId,
        ]);

        $this->actingAs($admin)
            ->putJson(route('admin.cultural-entries.api.update', $entryId), [
                'title' => 'Tari Pendet Diperbarui',
                'slug' => 'tari-pendet',
                'origin_region_id' => $regionId,
                'category_ids' => [(string) $secondCategoryId],
            ])->assertOk()
            ->assertJsonPath('data.publication_status', 'draft')
            ->assertJsonPath('data.verification_status', 'pending')
            ->assertJsonPath('data.verified_at', null)
            ->assertJsonPath('data.published_at', null)
            ->assertJsonPath('data.categories.0.id', $secondCategoryId);

        $this->assertSame(1, DB::table('category_cultural_entry')
            ->where('cultural_entry_id', $entryId)
            ->where('category_id', $secondCategoryId)
            ->count());
        $this->assertSame('content_changed', DB::table('verification_logs')
            ->where('cultural_entry_id', $entryId)
            ->value('decision'));
    }

    public function test_update_without_content_changes_does_not_reset_workflow_statuses(): void
    {
        $admin = $this->createUser(User::ROLE_SUPER_ADMIN);
        $entryId = $this->createEntry('Published Entry', 'published-entry', null, 'published', 'verified');
        DB::table('cultural_entries')->where('id', $entryId)->update([
            'verified_at' => now(),
            'published_at' => now(),
        ]);

        $this->actingAs($admin)
            ->putJson(route('admin.cultural-entries.api.update', $entryId), [
                'title' => 'Published Entry',
                'slug' => 'published-entry',
            ])->assertOk()
            ->assertJsonPath('data.publication_status', 'published')
            ->assertJsonPath('data.verification_status', 'verified');
        $this->assertSame(0, DB::table('verification_logs')->count());
    }

    public function test_index_search_filter_pagination_and_soft_delete_preserve_entry_relations(): void
    {
        $admin = $this->createUser(User::ROLE_SUPER_ADMIN);
        $regionId = $this->createRegion('Sumatera Barat', 'sumatera-barat');
        $categoryId = $this->createCategory('Kuliner', 'kuliner');
        foreach (range(1, 3) as $number) {
            $entryId = $this->createEntry("Rendang Test {$number}", "rendang-test-{$number}", $regionId);
            DB::table('category_cultural_entry')->insert([
                'cultural_entry_id' => $entryId,
                'category_id' => $categoryId,
            ]);
        }

        $response = $this->actingAs($admin)
            ->getJson(route('admin.cultural-entries.api.index', [
                'search' => 'Rendang Test',
                'region_id' => $regionId,
                'category_id' => $categoryId,
                'per_page' => 2,
                'page' => 2,
            ]));

        $response->assertOk()
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('data.0.title', 'Rendang Test 3');
        $this->assertStringContainsString('category_id='.$categoryId, $response->json('links.prev'));

        $entryId = $response->json('data.0.id');
        DB::table('media')->insert([
            'cultural_entry_id' => $entryId,
            'permission_status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->deleteJson(route('admin.cultural-entries.api.destroy', $entryId))
            ->assertOk()
            ->assertJsonPath('data.id', $entryId);

        $this->assertSame(0, DB::table('cultural_entries')->where('id', $entryId)->count());
        $this->assertSame(1, DB::table('cultural_entries')->where('id', $entryId)->whereNotNull('deleted_at')->count());
        $this->assertSame(1, DB::table('media')->where('cultural_entry_id', $entryId)->count());
        $this->assertSame(1, DB::table('category_cultural_entry')->where('cultural_entry_id', $entryId)->count());

        $this->getJson(route('admin.cultural-entries.api.index', ['trashed' => 'only']))
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
        $showResponse = $this->getJson(route('admin.cultural-entries.api.show', $entryId));
        $showResponse->assertOk();
        $this->assertNotNull($showResponse->json('data.deleted_at'));
    }

    private function createUser(?string $role): User
    {
        $id = DB::table('users')->insertGetId([
            'name' => 'Test '.($role ?? 'unassigned'),
            'email' => ($role ?? 'unassigned').'-'.uniqid().'@example.test',
            'password' => Hash::make('test-password'),
            'role' => $role,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return User::query()->findOrFail($id);
    }

    private function createRegion(string $name, string $slug): int
    {
        return DB::table('regions')->insertGetId([
            'name' => $name,
            'slug' => $slug,
            'region_type' => 'province',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createCategory(string $name, string $slug): int
    {
        return DB::table('categories')->insertGetId([
            'name' => $name,
            'slug' => $slug,
            'is_active' => true,
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createEntry(
        string $title,
        string $slug,
        ?int $regionId = null,
        string $publicationStatus = 'draft',
        string $verificationStatus = 'pending'
    ): int {
        return DB::table('cultural_entries')->insertGetId([
            'title' => $title,
            'slug' => $slug,
            'origin_region_id' => $regionId,
            'publication_status' => $publicationStatus,
            'verification_status' => $verificationStatus,
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
            $table->string('password');
            $table->string('role', 20)->nullable();
            $table->timestamps();
        });

        Schema::create('regions', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('region_type', 30)->default('province');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
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
            $table->string('publication_status', 50)->default('draft');
            $table->string('verification_status', 50)->default('pending');
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
            $table->string('media_type', 30)->nullable();
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

        Schema::create('verification_logs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('cultural_entry_id');
            $table->unsignedBigInteger('reviewer_id')->nullable();
            $table->string('decision', 100);
            $table->text('notes')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
    }
}
