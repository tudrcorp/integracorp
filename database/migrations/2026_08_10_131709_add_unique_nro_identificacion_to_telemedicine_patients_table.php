<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Normaliza cédulas existentes antes del índice único.
        DB::table('telemedicine_patients')
            ->orderBy('id')
            ->lazyById()
            ->each(function (object $patient): void {
                $raw = (string) ($patient->nro_identificacion ?? '');
                $normalized = mb_strtoupper(trim($raw));
                $normalized = str_replace([' ', '.', '_'], '', $normalized);

                if ($normalized === '' || $normalized === $raw) {
                    return;
                }

                DB::table('telemedicine_patients')
                    ->where('id', $patient->id)
                    ->update(['nro_identificacion' => $normalized]);
            });

        Schema::table('telemedicine_patients', function (Blueprint $table): void {
            // Familias corporativas comparten email; la identidad estable es la cédula.
            $table->dropUnique('telemedicine_patients_email_unique');
        });

        Schema::table('telemedicine_patients', function (Blueprint $table): void {
            $table->unique('nro_identificacion', 'telemedicine_patients_nro_identificacion_unique');
            $table->index('email', 'telemedicine_patients_email_index');
        });
    }

    public function down(): void
    {
        Schema::table('telemedicine_patients', function (Blueprint $table): void {
            $table->dropUnique('telemedicine_patients_nro_identificacion_unique');
            $table->dropIndex('telemedicine_patients_email_index');
        });

        Schema::table('telemedicine_patients', function (Blueprint $table): void {
            $table->unique('email', 'telemedicine_patients_email_unique');
        });
    }
};
