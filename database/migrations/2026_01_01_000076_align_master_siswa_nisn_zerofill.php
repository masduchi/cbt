<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE master_siswa MODIFY nisn INT(10) UNSIGNED ZEROFILL NOT NULL');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE master_siswa MODIFY nisn INT(10) UNSIGNED NOT NULL');
        }
    }
};
