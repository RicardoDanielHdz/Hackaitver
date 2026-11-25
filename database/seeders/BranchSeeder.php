<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Branch;
use App\Support\CedisDemoData;
use Illuminate\Database\Seeder;

class BranchSeeder extends Seeder
{
    public function run(): void
    {
        foreach (CedisDemoData::branches() as $branch) {
            Branch::updateOrCreate(
                ['code' => $branch['code']],
                [
                    'number' => $branch['number'],
                    'name' => $branch['name'],
                    'zone' => $branch['zone'],
                    'distance_km' => $branch['distance_km'],
                    'rack_code' => $branch['rack_code'],
                    'max_rack_pallets' => 32,
                ]
            );
        }
    }
}
