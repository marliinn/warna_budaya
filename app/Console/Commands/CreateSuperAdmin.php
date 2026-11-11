<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CreateSuperAdmin extends Command
{
    protected $signature = 'app:create-super-admin';

    protected $description = 'Create the first WARNA BUDAYA super administrator';

    public function handle(): int
    {
        $name = trim((string) $this->ask('Nama Super Admin'));
        $email = Str::lower(trim((string) $this->ask('Email Super Admin')));

        $validator = Validator::make(
            ['name' => $name, 'email' => $email],
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'string', 'email', 'max:255'],
            ]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $password = $this->secret('Password (minimal 12 karakter)');
        $passwordConfirmation = $this->secret('Konfirmasi password');
        $passwordValidator = Validator::make(
            [
                'password' => $password,
                'password_confirmation' => $passwordConfirmation,
            ],
            [
                'password' => ['required', 'string', 'min:12', 'confirmed'],
            ]
        );

        if ($passwordValidator->fails()) {
            foreach ($passwordValidator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $connection = DB::connection();
        $lockName = 'warna_budaya_create_super_admin';
        $lock = $connection->selectOne('SELECT GET_LOCK(?, 10) AS acquired', [$lockName]);

        if ((int) ($lock->acquired ?? 0) !== 1) {
            $this->error('Tidak dapat memperoleh lock provisioning. Coba lagi.');

            return self::FAILURE;
        }

        try {
            $result = $connection->transaction(function () use ($name, $email, $password): string {
                if (User::query()->where('role', User::ROLE_SUPER_ADMIN)->count() >= 2) {
                    return 'limit';
                }

                if (User::query()->where('email', $email)->exists()) {
                    return 'duplicate';
                }

                $user = new User;
                $user->forceFill([
                    'name' => $name,
                    'email' => $email,
                    'password' => Hash::make($password),
                    'role' => User::ROLE_SUPER_ADMIN,
                ]);
                $user->save();

                return 'created';
            });
        } catch (UniqueConstraintViolationException) {
            $this->error('Email sudah terdaftar. Akun existing tidak diubah.');

            return self::FAILURE;
        } finally {
            $connection->selectOne('SELECT RELEASE_LOCK(?) AS released', [$lockName]);
        }

        if ($result === 'limit') {
            $this->error('Maksimal dua akun Super Admin sudah tercapai. Tidak ada perubahan yang dilakukan.');

            return self::FAILURE;
        }

        if ($result === 'duplicate') {
            $this->error('Email sudah terdaftar. Akun existing tidak diubah.');

            return self::FAILURE;
        }

        $this->info('Super Admin berhasil dibuat.');

        return self::SUCCESS;
    }
}
