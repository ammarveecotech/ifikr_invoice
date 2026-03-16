<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Company;

class CompanySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Company::firstOrCreate(
            ['id' => 1],
            [
                'name' => 'System Company',
                'tin' => env('DEFAULT_TIN', '000000000000'),
            ]
        );
    }
}
