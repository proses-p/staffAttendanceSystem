<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class CompanyStaffTestingSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException(
                'This seeder is for local testing only.'
            );
        }

        $staffMembers = [
            [
                'company_slug' => 'tanzmed',
                'name' => 'TanzMed Test Staff',
                'email' => 'tanzmed@example.com',
                'employee_id' => 'TM-TEST-001',
            ],
            [
                'company_slug' => 'dudumizi',
                'name' => 'Dudumizi Test Staff',
                'email' => 'dudumizi@example.com',
                'employee_id' => 'DD-TEST-001',
            ],
        ];

        foreach ($staffMembers as $data) {
            $company = Company::where(
                'slug',
                $data['company_slug']
            )->first();

            if (! $company) {
                throw new RuntimeException(
                    "Company {$data['company_slug']} was not found."
                );
            }

            $user = User::where('email', $data['email'])->first();

            if ($user) {
                if (
                    $user->role !== 'staff' ||
                    (int) $user->company_id !== (int) $company->id
                ) {
                    throw new RuntimeException(
                        "Existing account {$data['email']} has a different assignment."
                    );
                }

                $this->command->info(
                    "Staff already exists: {$data['email']}"
                );

                continue;
            }

            $user = new User();
            $user->name = $data['name'];
            $user->email = $data['email'];
            $user->employee_id = $data['employee_id'];
            $user->password = Hash::make('123');
            $user->role = 'staff';
            $user->company_id = $company->id;
            $user->is_active = true;
            $user->email_verified_at = now();
            $user->save();

            $this->command->info(
                "Created {$data['email']} for {$company->name}"
            );
        }
    }
}
