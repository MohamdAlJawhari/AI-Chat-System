<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Keep factories if you want random users
        \App\Models\User::factory(1)->create();

        // Seed admin and demo content
        $this->call([
            AdminSeeder::class,
            DemoSeeder::class,
        ]);
    }
}
