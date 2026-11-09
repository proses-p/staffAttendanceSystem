<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\LocalDatabaseGuard;
use DomainException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class BootstrapSuperAdmin extends Command
{
    protected $signature = 'app:bootstrap-super-admin {--operator= : Change ticket or operator identifier}';

    protected $description = 'Create the first platform super-admin after explicit local operator confirmation';

    public function handle(LocalDatabaseGuard $databaseGuard): int
    {
        $operator = trim((string) $this->option('operator'));
        if ($operator === '' || mb_strlen($operator) > 128) {
            $this->error('Provide a change ticket or operator identifier with --operator.');

            return self::FAILURE;
        }

        try {
            $databaseGuard->assertLocal();
        } catch (DomainException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if (User::query()->where('role', 'super_admin')->exists()) {
            $this->error('A super-admin account already exists; refusing duplicate bootstrap.');

            return self::FAILURE;
        }

        $name = $this->ask('Name for the authorized platform super-admin');
        $email = $this->ask('Email for the authorized platform super-admin');
        $password = $this->secret('Choose a unique password (at least 12 characters)');
        $passwordConfirmation = $this->secret('Confirm the password');

        $validator = Validator::make([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'password_confirmation' => $passwordConfirmation,
        ], [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:12', 'confirmed'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $confirmation = 'CREATE INITIAL SUPER ADMIN';
        if ($this->ask("Type exactly \"{$confirmation}\" to authorize this account") !== $confirmation) {
            $this->error('Confirmation did not match. No account was created.');

            return self::FAILURE;
        }

        $lock = Cache::lock('app:bootstrap-super-admin', 120);
        if (! $lock->get()) {
            $this->error('Another super-admin bootstrap is in progress; no account was created.');

            return self::FAILURE;
        }

        try {
            try {
                $user = DB::transaction(function () use ($email, $name, $password): User {
                    DB::table('users')->orderBy('id')->lockForUpdate()->get(['id']);

                    if (User::query()->where('role', 'super_admin')->lockForUpdate()->exists()) {
                        throw new DomainException('A super-admin account already exists; refusing duplicate bootstrap.');
                    }

                    $user = new User;
                    $user->name = $name;
                    $user->email = $email;
                    $user->password = Hash::make($password);
                    $user->role = 'super_admin';
                    $user->is_active = true;
                    $user->save();

                    return $user;
                });
            } catch (DomainException $exception) {
                $this->error($exception->getMessage());

                return self::FAILURE;
            }
        } finally {
            $lock->release();
        }

        Log::notice('Initial platform super-admin created by explicit local bootstrap.', [
            'operator' => $operator,
            'user_id' => $user->id,
        ]);

        $this->info('Initial super-admin created. The password was not written to application logs.');

        return self::SUCCESS;
    }
}
