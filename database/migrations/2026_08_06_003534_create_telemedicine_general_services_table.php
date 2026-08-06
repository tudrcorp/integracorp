<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @var list<string>
     */
    private const DEFAULT_SERVICES = [
        'NEBULIZACIÓN',
        'OXIGENOTERAPIA',
        'SUTURA DE HERIDA',
        'CURA DE HERIDA',
        'ADMINISTRACION DE TRATAMIENTO',
        'LAVADO OTICO-OCULAR',
        'INMOBILIZACION',
    ];

    public function up(): void
    {
        Schema::create('telemedicine_general_services', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('description')->nullable();
            $table->string('status')->default('ACTIVO');
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->timestamps();

            $table->unique('name');
        });

        $now = now();

        foreach (self::DEFAULT_SERVICES as $name) {
            DB::table('telemedicine_general_services')->insert([
                'name' => $name,
                'description' => $name,
                'status' => 'ACTIVO',
                'created_by' => 'system',
                'updated_by' => 'system',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('telemedicine_general_services');
    }
};
