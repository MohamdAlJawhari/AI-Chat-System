<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Keep factories if you want random users
        \App\Models\User::factory(1)->create();

        // Add your DemoSeeder
        $this->call([
            DemoSeeder::class,
        ]);
    }
}
