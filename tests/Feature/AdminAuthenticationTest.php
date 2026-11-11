<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdminAuthenticationTest extends TestCase
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

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('role', 20)->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('regions', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('cultural_entries', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->string('publication_status')->default('draft');
            $table->string('verification_status')->default('pending');
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('media', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('cultural_entry_id');
            $table->string('permission_status')->default('pending');
            $table->timestamps();
        });
    }

    public function test_admin_login_page_is_available_without_public_registration(): void
    {
        $this->get(route('admin.login'))
            ->assertOk()
            ->assertSee('Login Pengelola')
            ->assertSee('name="_token"', false);

        $this->get('/register')->assertNotFound();
    }

    public function test_admin_home_redirects_guests_to_the_admin_login(): void
    {
        $this->get(route('admin.home'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_super_admin_entry_sends_guests_to_login_and_returns_super_admin_to_dashboard(): void
    {
        $superAdmin = $this->createUser(User::ROLE_SUPER_ADMIN, 'super@example.test', 'correct-password');

        $this->get(route('admin.super-entry'))
            ->assertRedirect(route('admin.login'))
            ->assertSessionHas('url.intended', route('admin.super-entry'));

        $this->post(route('admin.login.store'), [
            'email' => 'super@example.test',
            'password' => 'correct-password',
        ])->assertRedirect(route('admin.super-entry'));

        $this->assertAuthenticatedAs($superAdmin);
        $this->get(route('admin.super-entry'))
            ->assertRedirect(route('admin.home'));
    }

    public function test_both_existing_super_admin_accounts_can_authenticate(): void
    {
        $accounts = [
            $this->createUser(User::ROLE_SUPER_ADMIN, 'super-one@example.test', 'correct-password'),
            $this->createUser(User::ROLE_SUPER_ADMIN, 'super-two@example.test', 'correct-password'),
        ];

        foreach ($accounts as $account) {
            $this->post(route('admin.login.store'), [
                'email' => $account->email,
                'password' => 'correct-password',
            ])->assertRedirect(route('admin.home'));
            $this->assertAuthenticatedAs($account);
            $this->post(route('admin.logout'))->assertRedirect(route('admin.login'));
        }
    }

    public function test_legacy_roles_cannot_access_the_admin_dashboard(): void
    {
        foreach (['admin', 'verifikator'] as $role) {
            $user = $this->createUser($role, $role.'@example.test', 'correct-password');

            $this->actingAs($user)
                ->get(route('admin.super-entry'))
                ->assertForbidden();

            $this->get(route('admin.home'))->assertForbidden();
        }
    }

    public function test_guests_are_redirected_to_admin_login_from_taxonomy_pages_and_intended_url_is_saved(): void
    {
        foreach (['admin.regions.page', 'admin.categories.page'] as $routeName) {
            $response = $this->get(route($routeName));

            $response->assertRedirect(route('admin.login'))
                ->assertSessionHas('url.intended', route($routeName));
        }
    }

    public function test_successful_login_returns_to_a_safe_intended_admin_page(): void
    {
        $this->createUser(User::ROLE_SUPER_ADMIN, 'super@example.test', 'correct-password');

        $this->withSession([
            'url.intended' => route('admin.regions.page').'?search=Jawa',
        ])->post(route('admin.login.store'), [
            'email' => 'super@example.test',
            'password' => 'correct-password',
        ])->assertRedirect(route('admin.regions.page').'?search=Jawa');

        $this->assertAuthenticated();
    }

    public function test_login_rejects_an_external_intended_url(): void
    {
        $this->createUser(User::ROLE_SUPER_ADMIN, 'super@example.test', 'correct-password');

        $this->withSession([
            'url.intended' => 'https://attacker.example/admin/regions/manage',
        ])->post(route('admin.login.store'), [
            'email' => 'super@example.test',
            'password' => 'correct-password',
        ])->assertRedirect(route('admin.home'));
    }

    public function test_admin_home_denies_users_without_super_admin_role(): void
    {
        $unassigned = new User;
        $this->actingAs($unassigned)
            ->get(route('admin.home'))
            ->assertForbidden();

        $this->get(route('admin.login'))->assertForbidden();

        foreach (['admin', 'verifikator'] as $role) {
            $legacyUser = new User;
            $legacyUser->role = $role;
            $this->actingAs($legacyUser)->get(route('admin.home'))->assertForbidden();
        }

        $superAdmin = new User;
        $superAdmin->role = User::ROLE_SUPER_ADMIN;
        $this->actingAs($superAdmin)
            ->get(route('admin.home'))
            ->assertOk()
            ->assertSee('Dashboard Kurasi');
    }

    public function test_taxonomy_pages_allow_super_admin_and_deny_legacy_or_unassigned_users(): void
    {
        $superAdmin = new User;
        $superAdmin->role = User::ROLE_SUPER_ADMIN;
        $legacyUsers = [];
        foreach (['admin', 'verifikator'] as $role) {
            $user = new User;
            $user->role = $role;
            $legacyUsers[] = $user;
        }
        $unassigned = new User;

        foreach (['admin.regions.page', 'admin.categories.page'] as $routeName) {
            $this->actingAs($superAdmin)
                ->get(route($routeName))
                ->assertOk();

            foreach ($legacyUsers as $legacyUser) {
                $this->actingAs($legacyUser)->get(route($routeName))->assertForbidden();
            }
            $this->actingAs($unassigned)
                ->get(route($routeName))
                ->assertForbidden();
        }
    }

    public function test_admin_login_validates_inputs_and_applies_rate_limits(): void
    {
        $this->post(route('admin.login.store'), [
            'email' => ['bukan-email'],
            'password' => [],
        ])->assertSessionHasErrors(['email', 'password']);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post(route('admin.login.store'), [
                'email' => 'pengelola',
                'password' => 'salah',
            ])->assertSessionHasErrors('email');
        }

        $this->post(route('admin.login.store'), [
            'email' => 'pengelola',
            'password' => 'salah',
        ])->assertTooManyRequests();
    }

    public function test_public_routes_are_not_protected_by_admin_access_middleware(): void
    {
        foreach (['home', 'regions.index', 'regions.show', 'cultural-entries.index'] as $name) {
            $route = app('router')->getRoutes()->getByName($name);

            $this->assertNotNull($route);
            $this->assertNotContains('admin.access', $route->gatherMiddleware());
        }
    }

    public function test_only_super_admin_receives_management_and_verification_permissions(): void
    {
        $superAdmin = new User;
        $superAdmin->role = User::ROLE_SUPER_ADMIN;
        $legacyAdmin = new User;
        $legacyAdmin->role = 'admin';
        $legacyVerifier = new User;
        $legacyVerifier->role = 'verifikator';
        $unassigned = new User;

        $this->assertTrue(Gate::forUser($superAdmin)->allows('admin-access'));
        $this->assertTrue(Gate::forUser($superAdmin)->allows('manage-admin-accounts'));
        $this->assertTrue(Gate::forUser($superAdmin)->allows('manage-cultural-content'));
        $this->assertTrue(Gate::forUser($superAdmin)->allows('manage-media'));
        $this->assertTrue(Gate::forUser($superAdmin)->allows('manage-sources'));
        $this->assertTrue(Gate::forUser($superAdmin)->allows('manage-regions'));
        $this->assertTrue(Gate::forUser($superAdmin)->allows('manage-categories'));
        $this->assertTrue(Gate::forUser($superAdmin)->allows('verify-content'));
        foreach ([$legacyAdmin, $legacyVerifier, $unassigned] as $user) {
            $this->assertFalse(Gate::forUser($user)->allows('admin-access'));
            $this->assertFalse(Gate::forUser($user)->allows('manage-cultural-content'));
            $this->assertFalse(Gate::forUser($user)->allows('verify-content'));
        }
        $this->assertFalse(Gate::forUser($unassigned)->allows('admin-access'));
    }

    private function createUser(string $role, string $email, string $password): User
    {
        $id = DB::table('users')->insertGetId([
            'name' => 'Test Admin',
            'email' => $email,
            'password' => Hash::make($password),
            'role' => $role,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return User::query()->findOrFail($id);
    }
}
