<?php

namespace Database\Seeders;

use App\Actions\Access\SyncPermissionCatalogue;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * Production installs use `php artisan parishdesk:install` instead, which
     * creates no roles other than Super Admin.
     */
    public function run(SyncPermissionCatalogue $sync): void
    {
        $sync->handle();

        if (! app()->isProduction()) {
            $this->call(UserSeeder::class);
        }
    }
}
