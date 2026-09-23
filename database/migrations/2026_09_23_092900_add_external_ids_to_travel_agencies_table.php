<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('travel_agencies')) {
            return;
        }

        if (! Schema::hasColumn('travel_agencies', 'id_agencia')) {
            Schema::table('travel_agencies', function (Blueprint $table): void {
                $table->unsignedInteger('id_agencia')->nullable()->unique();
            });
        }

        if (! Schema::hasColumn('travel_agencies', 'id_de_agente')) {
            Schema::table('travel_agencies', function (Blueprint $table): void {
                $table->string('id_de_agente', 32)->nullable()->unique();
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('travel_agencies')) {
            return;
        }

        Schema::table('travel_agencies', function (Blueprint $table): void {
            if (Schema::hasColumn('travel_agencies', 'id_agencia')) {
                $table->dropUnique(['id_agencia']);
                $table->dropColumn('id_agencia');
            }

            if (Schema::hasColumn('travel_agencies', 'id_de_agente')) {
                $table->dropUnique(['id_de_agente']);
                $table->dropColumn('id_de_agente');
            }
        });
    }
};
