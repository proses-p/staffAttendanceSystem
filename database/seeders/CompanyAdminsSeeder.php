<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class CompanyAdminsSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException(
                'This testing seeder can only run locally or in testing.'
            );
        }

        $admins = [
            [
                'company_id' => 1,
                'name' => 'TanzMed Admin',
                'email' => 'tanzmed@gmail.com',
                'password' => 'tanz123',
            ],
            [
                'company_id' => 2,
                'name' => 'Dudumizi Admin',
                'email' => 'dudumizi@gmail.com',
                'password' => 'dudu123',
            ],
        ];

        foreach ($admins as $data) {
            $company = Company::find($data['company_id']);

            if (! $company) {
                throw new RuntimeException(
                    "Company ID {$data['company_id']} does not exist."
                );
            }

            $existingUser = User::where(
                'email',
                $data['email']
            )->first();

            if ($existingUser) {
                if (
                    $existingUser->role !== 'admin' ||
                    (int) $existingUser->company_id !==
                        (int) $company->id
                ) {
                    throw new RuntimeException(
                        "The email {$data['email']} already belongs "
                        . 'to an account with different access.'
                    );
                }

                $this->command->warn(
                    "{$data['email']} already exists. "
                    . 'Its password was not changed.'
                );

                continue;
            }

            $user = new User();

            $user->name = $data['name'];
            $user->email = $data['email'];
            $user->password = Hash::make($data['password']);
            $user->role = 'admin';
            $user->company_id = $company->id;
            $user->is_active = true;
            $user->email_verified_at = now();

            $user->save();

            $this->command->info(
                "Created admin for {$company->name}: {$data['email']}"
            );

            $this->command->line(
                "Temporary test password: {$data['password']}"
            );
        }
    }
}
