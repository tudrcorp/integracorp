<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();
        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE user_permissions MODIFY created_by VARCHAR(255) NULL, MODIFY updated_by VARCHAR(255) NULL');
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();
        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE user_permissions MODIFY created_by VARCHAR(255) NOT NULL, MODIFY updated_by VARCHAR(255) NOT NULL');
        }
    }
};
