<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CompanyUserSeeder extends Seeder
{
    public function run(): void
    {
        $companyIds = Company::active()->pluck('id');

        if ($companyIds->isEmpty()) {
            return;
        }

        User::query()
            ->where('status', 1)
            ->select('id')
            ->chunkById(200, function ($users) use ($companyIds) {
                $now = now();
                $memberships = [];

                foreach ($users as $user) {
                    foreach ($companyIds as $companyId) {
                        $memberships[] = [
                            'company_id' => $companyId,
                            'user_id' => $user->id,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                }

                DB::table('company_user')->insertOrIgnore($memberships);
            });
    }
}
