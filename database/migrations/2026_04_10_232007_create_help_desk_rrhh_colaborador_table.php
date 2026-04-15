<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('help_desk_rrhh_colaborador', function (Blueprint $table) {
            $table->id();
            $table->foreignId('help_desk_id')->constrained('help_desks')->cascadeOnDelete();
            $table->foreignId('rrhh_colaborador_id')->constrained('rrhh_colaboradors')->cascadeOnDelete();
            $table->unique(['help_desk_id', 'rrhh_colaborador_id']);
            $table->timestamps();
        });

        if (Schema::hasColumn('help_desks', 'rrhh_colaborador_id')) {
            DB::table('help_desks')
                ->whereNotNull('rrhh_colaborador_id')
                ->orderBy('id')
                ->chunkById(100, function ($rows): void {
                    foreach ($rows as $row) {
                        DB::table('help_desk_rrhh_colaborador')->updateOrInsert(
                            [
                                'help_desk_id' => $row->id,
                                'rrhh_colaborador_id' => $row->rrhh_colaborador_id,
                            ],
                            [
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]
                        );
                    }
                });

            Schema::table('help_desks', function (Blueprint $table): void {
                try {
                    $table->dropForeign(['rrhh_colaborador_id']);
                } catch (\Throwable) {
                    //
                }
            });

            Schema::table('help_desks', function (Blueprint $table): void {
                if (Schema::hasColumn('help_desks', 'rrhh_colaborador_id')) {
                    $table->dropColumn('rrhh_colaborador_id');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('help_desk_rrhh_colaborador');

        if (! Schema::hasColumn('help_desks', 'rrhh_colaborador_id')) {
            Schema::table('help_desks', function (Blueprint $table): void {
                $table->foreignId('rrhh_colaborador_id')->nullable()->constrained('rrhh_colaboradors')->nullOnDelete();
            });
        }
    }
};
