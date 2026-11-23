<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ReferenceDataSeeder extends Seeder
{
    public function run(): void
    {
        $legacy = (string) file_get_contents(database_path('seed/legacy_data.sql'));

        DB::unprepared(str_replace('INSERT INTO', 'INSERT IGNORE INTO', $legacy));
    }
}
