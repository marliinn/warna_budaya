<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UserManagementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $sessionTable = (string) config('session.table');
        $sessionConnection = config('session.connection');
        $sessionSchema = Schema::connection($sessionConnection);
        $loginStatusAvailable = config('session.driver') === 'database'
            && ($sessionConnection === null || $sessionConnection === config('database.default'))
            && $sessionSchema->hasTable($sessionTable)
            && $sessionSchema->hasColumn($sessionTable, 'user_id')
            && $sessionSchema->hasColumn($sessionTable, 'last_activity');
        $accountStatusAvailable = Schema::hasColumn('users', 'is_active');
        $sessionCutoff = now()->subMinutes((int) config('session.lifetime'))->timestamp;
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        if (! $loginStatusAvailable && isset($filters['status'])) {
            return response()->json([
                'message' => 'Filter sesi login belum tersedia pada konfigurasi penyimpanan sesi saat ini.',
                'errors' => [
                    'status' => ['Filter sesi login belum tersedia pada konfigurasi penyimpanan sesi saat ini.'],
                ],
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $users = User::query()
            ->where('role', User::ROLE_SUPER_ADMIN)
            ->when($loginStatusAvailable && isset($filters['status']), function (Builder $query) use (
                $filters,
                $sessionTable,
                $sessionCutoff
            ): void {
                $sessionCondition = function (QueryBuilder $query) use ($sessionTable, $sessionCutoff): void {
                    $query->selectRaw('1')
                        ->from($sessionTable)
                        ->whereColumn($sessionTable.'.user_id', 'users.id')
                        ->where($sessionTable.'.last_activity', '>=', $sessionCutoff);
                };

                if ($filters['status'] === 'active') {
                    $query->whereExists($sessionCondition);
                } else {
                    $query->whereNotExists($sessionCondition);
                }
            })
            ->when(trim($filters['search'] ?? '') !== '', function (Builder $query) use ($filters): void {
                $search = trim($filters['search']);
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->orderBy('id')
            ->paginate($filters['per_page'] ?? 15)
            ->withQueryString();

        $activeUserIds = [];
        if ($loginStatusAvailable && $users->getCollection()->isNotEmpty()) {
            $activeUserIds = DB::connection($sessionConnection)->table($sessionTable)
                ->whereIn('user_id', $users->getCollection()->modelKeys())
                ->where('last_activity', '>=', $sessionCutoff)
                ->distinct()
                ->pluck('user_id')
                ->mapWithKeys(fn ($userId): array => [(int) $userId => true])
                ->all();
        }

        return response()->json([
            'data' => $users->getCollection()
                ->map(function (User $user) use (
                    $accountStatusAvailable,
                    $loginStatusAvailable,
                    $activeUserIds
                ): array {
                    $data = $this->userData($user, $accountStatusAvailable);
                    $isLoginActive = $loginStatusAvailable && isset($activeUserIds[(int) $user->getKey()]);
                    $data['login_active'] = $loginStatusAvailable ? $isLoginActive : null;
                    $data['status'] = $loginStatusAvailable
                        ? ($isLoginActive ? 'active' : 'inactive')
                        : 'unavailable';

                    return $data;
                })
                ->all(),
            'meta' => [
                'current_page' => $users->currentPage(),
                'per_page' => $users->perPage(),
                'last_page' => $users->lastPage(),
                'total' => $users->total(),
                'from' => $users->firstItem(),
                'to' => $users->lastItem(),
                'status_available' => $loginStatusAvailable,
            ],
            'links' => [
                'first' => $users->url(1),
                'last' => $users->url($users->lastPage()),
                'prev' => $users->previousPageUrl(),
                'next' => $users->nextPageUrl(),
            ],
        ]);
    }

    public function show(int $user): JsonResponse
    {
        return response()->json([
            'data' => $this->userData(
                User::query()->where('role', User::ROLE_SUPER_ADMIN)->findOrFail($user),
                Schema::hasColumn('users', 'is_active')
            ),
        ]);
    }

    public function update(Request $request, int $user): JsonResponse
    {
        $account = User::query()->where('role', User::ROLE_SUPER_ADMIN)->findOrFail($user);
        $rules = [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($account->id),
            ],
            'role' => ['prohibited'],
        ];
        $data = $request->validate($rules);

        try {
            $account = DB::transaction(function () use ($account, $data): User {
                $account = User::query()->lockForUpdate()->findOrFail($account->id);

                $attributes = collect($data)->only(['name', 'email'])->all();
                if (isset($attributes['email'])) {
                    $attributes['email'] = Str::lower($attributes['email']);
                }
                $account->fill($attributes)->save();

                return $account;
            });
        } catch (UniqueConstraintViolationException) {
            return response()->json([
                'message' => 'Email sudah digunakan.',
                'errors' => ['email' => ['Email sudah digunakan.']],
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json([
            'message' => 'Informasi akun pengelola berhasil diperbarui.',
            'data' => $this->userData($account, Schema::hasColumn('users', 'is_active')),
        ]);
    }

    public function setStatus(Request $request, int $user): JsonResponse
    {
        $this->ensureStatusColumnExists();
        $data = $request->validate(['is_active' => ['required', 'boolean']]);
        $account = User::query()->where('role', User::ROLE_SUPER_ADMIN)->findOrFail($user);

        if (! $data['is_active'] && $account->role === User::ROLE_SUPER_ADMIN) {
            return response()->json([
                'message' => 'Akun Super Admin tidak dapat dinonaktifkan melalui endpoint ini.',
            ], JsonResponse::HTTP_CONFLICT);
        }

        if (! $data['is_active'] && $account->id === Auth::id()) {
            return response()->json([
                'message' => 'Akun yang sedang digunakan tidak dapat dinonaktifkan.',
            ], JsonResponse::HTTP_CONFLICT);
        }

        $account = DB::transaction(function () use ($account, $data): User {
            $account = User::query()->lockForUpdate()->findOrFail($account->id);

            if (! $data['is_active'] && $account->role === User::ROLE_SUPER_ADMIN) {
                abort(JsonResponse::HTTP_CONFLICT, 'Akun Super Admin tidak dapat dinonaktifkan melalui endpoint ini.');
            }

            $account->forceFill(['is_active' => (bool) $data['is_active']])->save();

            if (! $data['is_active']) {
                $this->revokeSessions($account->id);
            }

            return $account;
        });

        return response()->json([
            'message' => $account->is_active ? 'Akun berhasil diaktifkan.' : 'Akun berhasil dinonaktifkan.',
            'data' => $this->userData($account),
        ]);
    }

    public function resetPassword(Request $request, int $user): JsonResponse
    {
        $this->ensureStatusColumnExists();
        $data = $request->validate([
            'password' => ['required', 'string', 'min:12', 'confirmed'],
        ]);
        $account = User::query()->where('role', User::ROLE_SUPER_ADMIN)->findOrFail($user);

        DB::transaction(function () use ($account, $data): void {
            $account = User::query()->lockForUpdate()->findOrFail($account->id);
            $account->forceFill(['password' => Hash::make($data['password'])])->save();
            $this->revokeSessions($account->id, $account->id === Auth::id()
                ? request()->session()->getId()
                : null);
        });

        return response()->json(['message' => 'Password berhasil direset.']);
    }

    private function revokeSessions(int $userId, ?string $exceptSessionId = null): void
    {
        if (config('session.driver') !== 'database') {
            return;
        }

        $table = (string) config('session.table', 'sessions');
        $connectionName = config('session.connection');
        $connection = DB::connection($connectionName);

        if (! Schema::connection($connection->getName())->hasTable($table)) {
            return;
        }

        $query = $connection->table($table)->where('user_id', $userId);
        if ($exceptSessionId !== null) {
            $query->where('id', '<>', $exceptSessionId);
        }
        $query->delete();
    }

    private function userData(User $user, bool $statusAvailable = true): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'is_active' => $statusAvailable ? (bool) $user->is_active : null,
            'status' => $statusAvailable
                ? ($user->is_active ? 'active' : 'inactive')
                : 'unavailable',
            'email_verified_at' => $user->email_verified_at,
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
        ];
    }

    private function ensureStatusColumnExists(): void
    {
        if (! Schema::hasColumn('users', 'is_active')) {
            abort(JsonResponse::HTTP_SERVICE_UNAVAILABLE, 'Migration status akun belum diterapkan.');
        }
    }
}
