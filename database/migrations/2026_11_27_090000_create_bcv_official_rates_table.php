<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tasa oficial BCV (Bs. por US$) por fecha, desde el histórico público de ve.dolarapi.com.
 * Una tasa pasada no cambia: se guarda para no depender de la API y dejar constancia de la tasa
 * usada en libros fiscales y conversiones. Solo existen los días que el BCV publicó; un fin de
 * semana o feriado toma la última tasa publicada antes de esa fecha.
 *
 * Aditiva e idempotente (aplicar con --path).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('bcv_official_rates')) {
            return;
        }

        Schema::create('bcv_official_rates', function (Blueprint $table): void {
            $table->id();
            $table->date('rate_date')->unique();
            $table->decimal('rate', 18, 8);
            $table->string('source', 40)->default('dolarapi');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bcv_official_rates');
    }
};
