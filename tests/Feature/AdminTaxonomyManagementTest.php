<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdminTaxonomyManagementTest extends TestCase
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

    public function test_guest_and_legacy_role_cannot_access_region_or_category_management(): void
    {
        $this->getJson(route('admin.regions.index'))->assertUnauthorized();
        $this->getJson(route('admin.categories.index'))->assertUnauthorized();

        $verifikator = $this->createUser('verifikator');
        $unassigned = $this->createUser(null);

        foreach ([$verifikator, $unassigned] as $user) {
            $this->actingAs($user)
                ->getJson(route('admin.regions.index'))
                ->assertForbidden();
            $this->actingAs($user)
                ->getJson(route('admin.categories.index'))
                ->assertForbidden();
            $this->actingAs($user)
                ->postJson(route('admin.regions.store'), [
                    'name' => 'Forbidden Region',
                    'slug' => 'forbidden-region',
                ])
                ->assertForbidden();
            $this->actingAs($user)
                ->postJson(route('admin.categories.store'), [
                    'name' => 'Forbidden Category',
                    'slug' => 'forbidden-category',
                ])
                ->assertForbidden();
        }
    }

    public function test_super_admin_can_manage_both_resources(): void
    {
        $user = $this->createUser(User::ROLE_SUPER_ADMIN);

        $this->actingAs($user)
            ->getJson(route('admin.regions.index'))
            ->assertOk();
        $this->getJson(route('admin.categories.index'))
            ->assertOk();
        $this->postJson(route('admin.regions.store'), [
            'name' => 'Allowed Region Super Admin',
            'slug' => 'allowed-region-super-admin',
        ])->assertCreated();
        $this->postJson(route('admin.categories.store'), [
            'name' => 'Allowed Category Super Admin',
            'slug' => 'allowed-category-super-admin',
        ])->assertCreated();
    }

    public function test_region_search_status_pagination_and_existing_records_are_preserved(): void
    {
        foreach (range(1, 13) as $number) {
            $this->createRegion(sprintf('Test Wilayah %02d', $number), sprintf('test-wilayah-%02d', $number));
        }
        $inactiveId = $this->createRegion('Test Wilayah Nonaktif', 'test-wilayah-nonaktif', false);
        $admin = $this->createUser(User::ROLE_SUPER_ADMIN);

        $response = $this->actingAs($admin)->getJson(route('admin.regions.index', [
            'search' => 'Test Wilayah',
            'status' => 'active',
            'per_page' => 5,
            'page' => 2,
        ]));

        $response->assertOk()
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.per_page', 5)
            ->assertJsonPath('meta.total', 13)
            ->assertJsonPath('data.0.name', 'Test Wilayah 06');
        $this->assertSame(14, DB::table('regions')->count());
        $this->assertSame(0, DB::table('regions')->where('id', $inactiveId)->where('is_active', true)->count());
    }

    public function test_region_crud_validates_duplicate_slug_and_changes_status(): void
    {
        $admin = $this->createUser(User::ROLE_SUPER_ADMIN);
        $this->createRegion('Existing Province', 'existing-province');

        $this->actingAs($admin)
            ->postJson(route('admin.regions.store'), [
                'name' => 'Invalid Region',
                'slug' => 'existing-province',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['slug']);

        $this->postJson(route('admin.regions.store'), [
            'name' => '',
            'slug' => 'invalid slug',
        ])->assertUnprocessable()->assertJsonValidationErrors(['name', 'slug']);

        $created = $this->postJson(route('admin.regions.store'), [
            'name' => 'Provinsi Contoh',
            'slug' => 'provinsi-contoh',
            'code' => 'PC',
        ])->assertCreated()
            ->assertJsonPath('message', 'Wilayah berhasil ditambahkan.')
            ->assertJsonPath('data.region_type', 'province')
            ->assertJsonPath('data.is_active', true);

        $regionId = $created->json('data.id');

        $this->putJson(route('admin.regions.update', $regionId), [
            'name' => 'Provinsi Contoh Diperbarui',
            'slug' => 'provinsi-contoh',
            'region_type' => 'city',
            'is_active' => false,
            'description' => 'Deskripsi wilayah.',
        ])->assertOk()
            ->assertJsonPath('data.name', 'Provinsi Contoh Diperbarui')
            ->assertJsonPath('data.region_type', 'city')
            ->assertJsonPath('data.is_active', false);

        $this->putJson(route('admin.regions.update', $regionId), [
            'name' => 'Provinsi Contoh Diperbarui',
            'slug' => 'provinsi-contoh',
        ])->assertOk()
            ->assertJsonPath('data.region_type', 'city')
            ->assertJsonPath('data.is_active', false);

        $this->patchJson(route('admin.regions.status', $regionId), [
            'is_active' => true,
        ])->assertOk()->assertJsonPath('data.is_active', true);

        $this->assertSame(2, DB::table('regions')->count());
        $this->assertSame('Existing Province', DB::table('regions')->where('slug', 'existing-province')->value('name'));
    }

    public function test_region_cannot_be_deleted_when_it_has_children_or_soft_deleted_entries(): void
    {
        $admin = $this->createUser(User::ROLE_SUPER_ADMIN);
        $parentId = $this->createRegion('Parent', 'parent');
        $this->createRegion('Child', 'child', true, $parentId);
        $this->actingAs($admin)
            ->deleteJson(route('admin.regions.destroy', $parentId))
            ->assertConflict();

        $entryRegionId = $this->createRegion('Region with old entry', 'region-with-old-entry');
        $entryId = $this->createEntry($entryRegionId);
        DB::table('cultural_entries')->where('id', $entryId)->update(['deleted_at' => now()]);

        $this->deleteJson(route('admin.regions.destroy', $entryRegionId))
            ->assertConflict();
        $this->assertSame(1, DB::table('cultural_entries')->count());
        $this->assertSame(3, DB::table('regions')->count());

        $unusedRegionId = $this->createRegion('Unused Region', 'unused-region');
        $this->deleteJson(route('admin.regions.destroy', $unusedRegionId))
            ->assertOk()
            ->assertJsonPath('message', 'Wilayah berhasil dihapus.');
    }

    public function test_region_and_category_updates_reject_hierarchical_cycles(): void
    {
        $admin = $this->createUser(User::ROLE_SUPER_ADMIN);
        $regionParent = $this->createRegion('Region Parent', 'region-parent');
        $regionChild = $this->createRegion('Region Child', 'region-child', true, $regionParent);
        $categoryParent = $this->createCategory('Category Parent', 'category-parent');
        $categoryChild = $this->createCategory('Category Child', 'category-child', $categoryParent);

        $this->actingAs($admin)
            ->putJson(route('admin.regions.update', $regionParent), [
                'name' => 'Region Parent',
                'slug' => 'region-parent',
                'parent_id' => $regionChild,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['parent_id']);

        $this->putJson(route('admin.categories.update', $categoryParent), [
            'name' => 'Category Parent',
            'slug' => 'category-parent',
            'parent_id' => $categoryChild,
        ])->assertUnprocessable()->assertJsonValidationErrors(['parent_id']);
    }

    public function test_category_search_pagination_and_crud_preserve_existing_records(): void
    {
        foreach (range(1, 13) as $number) {
            $this->createCategory(sprintf('Test Kategori %02d', $number), sprintf('test-kategori-%02d', $number));
        }
        $admin = $this->createUser(User::ROLE_SUPER_ADMIN);

        $this->actingAs($admin)
            ->getJson(route('admin.categories.index', [
                'search' => 'Test Kategori',
                'per_page' => 5,
                'page' => 2,
            ]))
            ->assertOk()
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.total', 13)
            ->assertJsonPath('data.0.name', 'Test Kategori 06');

        $existingId = $this->createCategory('Existing Category', 'existing-category');

        $this->postJson(route('admin.categories.store'), [
            'name' => 'Invalid Category',
            'slug' => 'existing-category',
        ])->assertUnprocessable()->assertJsonValidationErrors(['slug']);

        $this->postJson(route('admin.categories.store'), [
            'name' => '',
            'slug' => 'invalid slug',
            'description' => str_repeat('x', 16001),
        ])->assertUnprocessable()->assertJsonValidationErrors(['name', 'slug', 'description']);

        $created = $this->postJson(route('admin.categories.store'), [
            'name' => 'Kategori Baru',
            'slug' => 'kategori-baru',
            'description' => 'Deskripsi kategori.',
        ])->assertCreated()
            ->assertJsonPath('message', 'Kategori berhasil ditambahkan.')
            ->assertJsonPath('data.is_active', true);

        $categoryId = $created->json('data.id');
        $this->putJson(route('admin.categories.update', $categoryId), [
            'name' => 'Kategori Diperbarui',
            'slug' => 'kategori-baru',
            'sort_order' => 9,
            'is_active' => false,
            'description' => 'Deskripsi baru.',
        ])->assertOk()
            ->assertJsonPath('data.name', 'Kategori Diperbarui')
            ->assertJsonPath('data.sort_order', 9)
            ->assertJsonPath('data.is_active', false);

        $this->putJson(route('admin.categories.update', $categoryId), [
            'name' => 'Kategori Diperbarui',
            'slug' => 'kategori-baru',
        ])->assertOk()
            ->assertJsonPath('data.sort_order', 9)
            ->assertJsonPath('data.is_active', false);

        $this->patchJson(route('admin.categories.status', $categoryId), [
            'is_active' => false,
        ])->assertOk()->assertJsonPath('data.is_active', false);

        $this->assertSame(15, DB::table('categories')->count());
        $this->assertSame('Existing Category', DB::table('categories')->where('id', $existingId)->value('name'));

        $this->deleteJson(route('admin.categories.destroy', $categoryId))
            ->assertOk()
            ->assertJsonPath('message', 'Kategori berhasil dihapus.');
        $this->assertSame(14, DB::table('categories')->count());
    }

    public function test_category_cannot_be_deleted_when_it_has_children_or_entry_pivot_rows(): void
    {
        $admin = $this->createUser(User::ROLE_SUPER_ADMIN);
        $parentId = $this->createCategory('Parent Category', 'parent-category');
        $this->createCategory('Child Category', 'child-category', $parentId);

        $this->actingAs($admin)
            ->deleteJson(route('admin.categories.destroy', $parentId))
            ->assertConflict();

        $usedCategoryId = $this->createCategory('Used Category', 'used-category');
        $regionId = $this->createRegion('Test Region', 'test-region');
        $entryId = $this->createEntry($regionId);
        DB::table('cultural_entries')->where('id', $entryId)->update(['deleted_at' => now()]);
        DB::table('category_cultural_entry')->insert([
            'cultural_entry_id' => $entryId,
            'category_id' => $usedCategoryId,
        ]);

        $this->deleteJson(route('admin.categories.destroy', $usedCategoryId))
            ->assertConflict();
        $this->assertSame(1, DB::table('category_cultural_entry')->count());
        $this->assertSame(3, DB::table('categories')->count());
    }

    private function createUser(?string $role): User
    {
        $id = DB::table('users')->insertGetId([
            'name' => 'Test '.($role ?? 'unassigned'),
            'email' => ($role ?? 'unassigned').'-'.uniqid().'@example.test',
            'password' => 'not-used-by-acting-as',
            'role' => $role,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return User::query()->findOrFail($id);
    }

    private function createRegion(string $name, string $slug, bool $active = true, ?int $parentId = null): int
    {
        return DB::table('regions')->insertGetId([
            'parent_id' => $parentId,
            'name' => $name,
            'slug' => $slug,
            'region_type' => $parentId === null ? 'province' : 'city',
            'is_active' => $active,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createCategory(string $name, string $slug, ?int $parentId = null): int
    {
        return DB::table('categories')->insertGetId([
            'parent_id' => $parentId,
            'name' => $name,
            'slug' => $slug,
            'is_active' => true,
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createEntry(int $regionId): int
    {
        return DB::table('cultural_entries')->insertGetId([
            'title' => 'Entry '.$regionId,
            'slug' => 'entry-'.$regionId,
            'origin_region_id' => $regionId,
            'publication_status' => 'draft',
            'verification_status' => 'pending',
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
            $table->unsignedBigInteger('origin_region_id')->nullable();
            $table->string('publication_status', 20)->default('draft');
            $table->string('verification_status', 20)->default('pending');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('category_cultural_entry', function (Blueprint $table): void {
            $table->unsignedBigInteger('cultural_entry_id');
            $table->unsignedBigInteger('category_id');
            $table->primary(['cultural_entry_id', 'category_id']);
        });
    }
}
