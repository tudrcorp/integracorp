<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('permission_rol')) {
            Schema::create('permission_rol', function (Blueprint $table) {
                $table->id();
                $table->integer('permission_id');
                $table->integer('rol_id');
                $table->timestamps();
            });
        }

        Schema::table('permissions', function (Blueprint $table) {
            if (Schema::hasColumn('permissions', 'rol_id')) {
                $table->dropColumn('rol_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('permissions', function (Blueprint $table) {
            if (! Schema::hasColumn('permissions', 'rol_id')) {
                $table->unsignedBigInteger('rol_id')->nullable()->after('id');
            }
        });

        Schema::dropIfExists('permission_rol');
    }
};
