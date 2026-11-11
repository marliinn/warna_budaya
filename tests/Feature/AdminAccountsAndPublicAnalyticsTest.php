<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdminAccountsAndPublicAnalyticsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'session.driver' => 'database',
            'analytics.enabled' => true,
            'analytics.retention_days' => 90,
            'analytics.max_range_days' => 90,
            'analytics.dedupe_seconds' => 5,
        ]);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
        $this->createSchema();

        Route::middleware('web')->get('/analytics-test-page', fn () => response(
            '<!doctype html><title>Test</title>',
            200,
            ['Content-Type' => 'text/html; charset=UTF-8']
        ));
        Route::middleware('web')->get('/analytics-test.css', fn () => response(
            'body{}',
            200,
            ['Content-Type' => 'text/css']
        ));
        Route::middleware('web')->get('/admin/analytics-test', fn () => response(
            '<!doctype html><title>Admin</title>',
            200,
            ['Content-Type' => 'text/html']
        ));
        Route::middleware('web')->get('/api/analytics-test-page', fn () => response(
            '<!doctype html><title>API</title>',
            200,
            ['Content-Type' => 'text/html']
        ));
        Route::middleware('web')->get('/assets/analytics-test.png', fn () => response(
            '<!doctype html><title>Asset</title>',
            200,
            ['Content-Type' => 'text/html']
        ));
    }

    public function test_only_super_admin_can_access_account_and_analytics_apis(): void
    {
        $this->getJson(route('admin.api.users.index'))->assertUnauthorized();
        $this->getJson(route('admin.api.analytics.summary'))->assertUnauthorized();

        foreach (['admin', 'verifikator'] as $role) {
            $this->actingAs($this->user($role))
                ->getJson(route('admin.api.users.index'))
                ->assertForbidden();
            $this->getJson(route('admin.api.analytics.summary'))->assertForbidden();
        }

        $this->actingAs($this->user(User::ROLE_SUPER_ADMIN))
            ->getJson(route('admin.api.users.index'))
            ->assertOk();
        $this->getJson(route('admin.api.analytics.summary'))->assertOk();
    }

    public function test_user_list_includes_only_existing_super_admin_accounts(): void
    {
        $superAdminOne = $this->user(User::ROLE_SUPER_ADMIN);
        $superAdminTwo = $this->user(User::ROLE_SUPER_ADMIN);

        $response = $this->actingAs($superAdminOne)
            ->getJson(route('admin.api.users.index'))
            ->assertOk()
            ->assertJsonPath('meta.total', 2);

        $this->assertEqualsCanonicalizing(
            [$superAdminOne->id, $superAdminTwo->id],
            collect($response->json('data'))->pluck('id')->all()
        );
    }

    public function test_user_management_page_has_no_account_creation_or_legacy_role_choices(): void
    {
        $superAdmin = $this->user(User::ROLE_SUPER_ADMIN);

        $this->actingAs($superAdmin)
            ->get(route('admin.users.page'))
            ->assertOk()
            ->assertSee('Edit akun')
            ->assertDontSee('Tambah akun')
            ->assertDontSee('data-user-create', false)
            ->assertDontSee('value="admin"', false)
            ->assertDontSee('value="verifikator"', false);
    }

    public function test_account_creation_is_disabled_and_super_admin_role_cannot_be_changed(): void
    {
        $superAdmin = $this->user(User::ROLE_SUPER_ADMIN);
        $this->user(User::ROLE_SUPER_ADMIN);
        $before = DB::table('users')->count();

        foreach (['admin', 'verifikator', User::ROLE_SUPER_ADMIN] as $role) {
            $this->actingAs($superAdmin)
                ->postJson('/admin/api/users', [
                    'name' => 'Akun Tidak Boleh Dibuat',
                    'email' => 'tidak-boleh@example.test',
                    'role' => $role,
                    'password' => 'long-secure-password',
                    'password_confirmation' => 'long-secure-password',
                ])
                ->assertStatus(405);
        }

        $this->putJson(route('admin.api.users.update', $superAdmin->id), [
            'role' => 'admin',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['role']);

        $this->putJson(route('admin.api.users.update', $superAdmin->id), [
            'name' => 'Super Admin Tetap',
            'email' => 'super-admin-tetap@example.test',
        ])->assertOk()
            ->assertJsonPath('data.email', 'super-admin-tetap@example.test')
            ->assertJsonPath('data.role', User::ROLE_SUPER_ADMIN);

        $this->assertSame(
            User::ROLE_SUPER_ADMIN,
            DB::table('users')->where('id', $superAdmin->id)->value('role')
        );
        $this->assertSame($before, DB::table('users')->count());
    }

    public function test_only_super_admin_accounts_can_be_loaded_and_updated_without_account_status_column(): void
    {
        $superAdmin = $this->user(User::ROLE_SUPER_ADMIN);
        $secondSuperAdmin = $this->user(User::ROLE_SUPER_ADMIN);
        $legacyAccount = $this->user('admin');
        $superAdminPassword = $superAdmin->password;
        $secondPassword = $secondSuperAdmin->password;

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('is_active');
        });

        $this->actingAs($superAdmin)
            ->getJson(route('admin.api.users.show', $secondSuperAdmin->id))
            ->assertOk()
            ->assertJsonPath('data.id', $secondSuperAdmin->id)
            ->assertJsonPath('data.role', User::ROLE_SUPER_ADMIN)
            ->assertJsonPath('data.status', 'unavailable')
            ->assertJsonMissingPath('data.password');

        $this->getJson(route('admin.api.users.show', $legacyAccount->id))
            ->assertNotFound();

        $this->putJson(route('admin.api.users.update', $secondSuperAdmin->id), [
            'name' => 'Super Admin Diperbarui',
            'email' => 'super-admin.diperbarui@example.test',
        ])->assertOk()
            ->assertJsonPath('data.name', 'Super Admin Diperbarui')
            ->assertJsonPath('data.email', 'super-admin.diperbarui@example.test');

        $this->putJson(route('admin.api.users.update', $secondSuperAdmin->id), [
            'role' => 'verifikator',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['role']);

        $this->putJson(route('admin.api.users.update', $superAdmin->id), [
            'email' => 'super-admin.diperbarui@example.test',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);

        $this->assertSame($superAdminPassword, DB::table('users')->where('id', $superAdmin->id)->value('password'));
        $this->assertSame($secondPassword, DB::table('users')->where('id', $secondSuperAdmin->id)->value('password'));
        $this->assertSame(2, DB::table('users')->where('role', User::ROLE_SUPER_ADMIN)->count());
    }

    public function test_super_admin_accounts_cannot_be_disabled_and_password_reset_is_private(): void
    {
        $superAdmin = $this->user(User::ROLE_SUPER_ADMIN);
        $secondSuperAdmin = $this->user(User::ROLE_SUPER_ADMIN);
        DB::table('sessions')->insert([
            'id' => 'second-super-admin-session',
            'user_id' => $secondSuperAdmin->id,
            'payload' => '',
            'last_activity' => now()->timestamp,
        ]);

        $this->actingAs($superAdmin)
            ->patchJson(route('admin.api.users.status', $superAdmin->id), ['is_active' => false])
            ->assertStatus(409);
        $this->patchJson(route('admin.api.users.status', $superAdmin->id), ['is_active' => true])
            ->assertOk();

        $this->patchJson(route('admin.api.users.status', $secondSuperAdmin->id), ['is_active' => false])
            ->assertStatus(409);
        $this->assertDatabaseHas('sessions', ['id' => 'second-super-admin-session']);

        $this->putJson(route('admin.api.users.password', $secondSuperAdmin->id), [
            'password' => 'new-long-secure-password',
            'password_confirmation' => 'new-long-secure-password',
        ])->assertOk()
            ->assertJsonMissingPath('data.password')
            ->assertJsonMissingPath('data.password_hash');
        $this->assertTrue(Hash::check(
            'new-long-secure-password',
            DB::table('users')->where('id', $secondSuperAdmin->id)->value('password')
        ));
    }

    public function test_account_search_filters_by_login_status_and_paginates(): void
    {
        $superAdmin = $this->user(User::ROLE_SUPER_ADMIN);
        $secondSuperAdmin = $this->user(User::ROLE_SUPER_ADMIN);
        $this->databaseSession($secondSuperAdmin);

        $this->actingAs($superAdmin)
            ->getJson(route('admin.api.users.index', [
                'search' => $secondSuperAdmin->email,
                'status' => 'active',
                'per_page' => 1,
            ]))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $secondSuperAdmin->id)
            ->assertJsonPath('data.0.login_active', true)
            ->assertJsonPath('data.0.status', 'active');

        $this->getJson(route('admin.api.users.show', $superAdmin->id))
            ->assertOk()
            ->assertJsonPath('data.status', 'active');
    }

    public function test_user_list_includes_super_admins_and_marks_status_unavailable_without_status_column(): void
    {
        $superAdmin = $this->user(User::ROLE_SUPER_ADMIN);
        $secondSuperAdmin = $this->user(User::ROLE_SUPER_ADMIN);
        $legacyRole = $this->user('admin');

        config(['session.driver' => 'file']);

        $response = $this->actingAs($superAdmin)
            ->getJson(route('admin.api.users.index'))
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.status_available', false)
            ->assertJsonPath('data.0.status', 'unavailable')
            ->assertJsonPath('data.0.login_active', null);

        $listedIds = collect($response->json('data'))->pluck('id');
        $this->assertTrue($listedIds->contains($superAdmin->id));
        $this->assertTrue($listedIds->contains($secondSuperAdmin->id));
        $this->assertFalse($listedIds->contains($legacyRole->id));

        $this->getJson(route('admin.api.users.index', ['status' => 'active']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    public function test_login_status_uses_unexpired_database_sessions_for_super_admin_accounts(): void
    {
        $superAdmin = $this->user(User::ROLE_SUPER_ADMIN);
        $secondSuperAdmin = $this->user(User::ROLE_SUPER_ADMIN);

        $sessionId = $this->databaseSession($secondSuperAdmin);
        $this->databaseSession($secondSuperAdmin, now()->subMinutes(config('session.lifetime') + 1));

        $response = $this->actingAs($superAdmin)
            ->getJson(route('admin.api.users.index'))
            ->assertOk()
            ->assertJsonPath('meta.status_available', true)
            ->assertDontSee($sessionId);

        $users = collect($response->json('data'))->keyBy('id');
        $this->assertSame(2, $users->count());
        $this->assertFalse($users[$superAdmin->id]['login_active']);
        $this->assertSame('inactive', $users[$superAdmin->id]['status']);
        $this->assertFalse($users[$secondSuperAdmin->id]['login_active']);
        $this->assertSame('inactive', $users[$secondSuperAdmin->id]['status']);

        $this->getJson(route('admin.api.users.index', ['status' => 'active']))
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
        $this->getJson(route('admin.api.users.index', ['status' => 'inactive']))
            ->assertOk()
            ->assertJsonPath('meta.total', 2);

        DB::table('sessions')->where('id', $sessionId)->delete();
        $afterLogout = $this->getJson(route('admin.api.users.index'))->assertOk();
        $this->assertFalse(collect($afterLogout->json('data'))->keyBy('id')[$secondSuperAdmin->id]['login_active']);

        $this->databaseSession($secondSuperAdmin);
        $afterLogin = $this->getJson(route('admin.api.users.index'))->assertOk();
        $this->assertTrue(collect($afterLogin->json('data'))->keyBy('id')[$secondSuperAdmin->id]['login_active']);
    }

    public function test_public_page_views_are_recorded_and_assets_admin_and_json_are_excluded(): void
    {
        $response = $this->get('/analytics-test-page?private=value');

        $response->assertOk();
        $this->assertSame(1, DB::table('public_page_views')->count());
        $this->assertSame('/analytics-test-page', DB::table('public_page_views')->value('path'));
        $this->assertSame(1, DB::table('analytics_visitors')->count());
        $this->assertCount(1, $response->headers->getCookies());

        $this->get('/analytics-test.css')->assertOk();
        $this->get('/admin/analytics-test')->assertOk();
        $this->get('/api/analytics-test-page')->assertOk();
        $this->get('/assets/analytics-test.png')->assertOk();

        $this->assertSame(1, DB::table('public_page_views')->count());
        $this->assertFalse(Schema::hasColumn('public_page_views', 'ip_address'));
        $this->assertFalse(Schema::hasColumn('public_page_views', 'user_agent'));
    }

    public function test_refresh_of_same_public_path_within_dedupe_window_is_not_counted_twice(): void
    {
        $firstResponse = $this->get('/analytics-test-page');
        $cookies = collect($firstResponse->headers->getCookies())
            ->mapWithKeys(fn ($cookie): array => [$cookie->getName() => $cookie->getValue()])
            ->all();

        $this->withCookie(config('analytics.cookie_name'), $cookies[config('analytics.cookie_name')])
            ->withCookie(config('session.cookie'), $cookies[config('session.cookie')])
            ->get('/analytics-test-page')
            ->assertOk();

        $this->assertSame(1, DB::table('public_page_views')->count());
    }

    public function test_analytics_api_returns_aggregates_and_validates_date_range(): void
    {
        $admin = $this->user(User::ROLE_SUPER_ADMIN);
        $firstSeen = now()->subDays(2);
        DB::table('analytics_visitors')->insert([
            'visitor_hash' => str_repeat('a', 64),
            'first_seen_at' => $firstSeen,
            'last_seen_at' => now(),
            'expires_at' => now()->addDays(90),
        ]);
        DB::table('analytics_visitors')->insert([
            'visitor_hash' => str_repeat('b', 64),
            'first_seen_at' => now()->subDays(10),
            'last_seen_at' => now()->subDay(),
            'expires_at' => now()->addDays(80),
        ]);
        foreach ([
            [str_repeat('a', 64), str_repeat('1', 64), '/jelajah-nusantara', now()->subDay()],
            [str_repeat('b', 64), str_repeat('2', 64), '/arsip-budaya', now()->subDay()],
            [str_repeat('a', 64), str_repeat('1', 64), '/jelajah-nusantara', now()->subDay()->addSeconds(20)],
        ] as [$visitor, $session, $path, $viewedAt]) {
            DB::table('public_page_views')->insert([
                'visitor_hash' => $visitor,
                'session_hash' => $session,
                'path' => $path,
                'device_type' => 'desktop',
                'browser' => 'Chrome',
                'operating_system' => 'Windows',
                'viewed_at' => $viewedAt,
            ]);
        }

        $this->actingAs($admin)
            ->getJson(route('admin.api.analytics.summary', [
                'date_from' => now()->subDays(3)->toDateString(),
                'date_to' => now()->toDateString(),
            ]))
            ->assertOk()
            ->assertJsonPath('data.unique_visitors', 2)
            ->assertJsonPath('data.sessions', 2)
            ->assertJsonPath('data.page_views', 3)
            ->assertJsonPath('data.new_visitors', 1)
            ->assertJsonPath('data.returning_visitors', 1);

        $this->getJson(route('admin.api.analytics.traffic', [
            'date_from' => now()->subDays(3)->toDateString(),
            'date_to' => now()->toDateString(),
            'period' => 'weekly',
        ]))->assertOk()
            ->assertJsonPath('meta.period', 'weekly');

        $this->getJson(route('admin.api.analytics.summary', [
            'date_from' => now()->subDays(100)->toDateString(),
            'date_to' => now()->toDateString(),
        ]))->assertUnprocessable()
            ->assertJsonValidationErrors(['date_from']);

        $this->getJson(route('admin.api.analytics.geography', [
            'date_from' => now()->subDays(3)->toDateString(),
            'date_to' => now()->toDateString(),
        ]))->assertOk()
            ->assertJsonPath('data.available', false)
            ->assertJsonPath('data.countries', []);
    }

    private function user(string $role, bool $isActive = true): User
    {
        $user = new User;
        $user->forceFill([
            'name' => 'Test '.$role,
            'email' => uniqid('account-', true).'@example.test',
            'password' => Hash::make('test-password-123'),
            'role' => $role,
            'is_active' => $isActive,
        ]);
        $user->save();

        return $user;
    }

    private function databaseSession(User $user, ?Carbon $lastActivity = null): string
    {
        $sessionId = uniqid('session-', true);
        DB::table('sessions')->insert([
            'id' => $sessionId,
            'user_id' => $user->id,
            'ip_address' => null,
            'user_agent' => null,
            'payload' => '',
            'last_activity' => ($lastActivity ?? now())->timestamp,
        ]);

        return $sessionId;
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
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('sessions', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        Schema::create('analytics_visitors', function (Blueprint $table): void {
            $table->char('visitor_hash', 64)->primary();
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
            $table->timestamp('expires_at')->index();
            $table->index('first_seen_at');
        });

        Schema::create('public_page_views', function (Blueprint $table): void {
            $table->id();
            $table->char('visitor_hash', 64);
            $table->char('session_hash', 64);
            $table->string('path', 255);
            $table->string('device_type', 20);
            $table->string('browser', 50)->nullable();
            $table->string('operating_system', 50)->nullable();
            $table->char('country_code', 2)->nullable();
            $table->string('province', 100)->nullable();
            $table->string('city', 100)->nullable();
            $table->timestamp('viewed_at')->index();
            $table->index(['visitor_hash', 'viewed_at']);
            $table->index(['session_hash', 'path', 'viewed_at']);
        });
    }
}
