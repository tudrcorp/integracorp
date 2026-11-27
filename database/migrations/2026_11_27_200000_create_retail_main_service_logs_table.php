<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bitácora de los servicios principales RETAIL: quién agregó, reactivó o quitó cada servicio, cuándo y por qué
 * (nota obligatoria). Solo se escribe: los registros no se editan ni se borran.
 */
return new class extends Migration
{
    private const TABLE = 'retail_main_service_logs';

    public function up(): void
    {
        if (Schema::hasTable(self::TABLE)) {
            return;
        }

        Schema::create(self::TABLE, function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('retail_main_service_id')->index();
            $table->string('service_name', 120);
            $table->string('service_label', 120);
            $table->string('action', 20);
            $table->string('note', 500);
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('user_name', 120);
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(self::TABLE);
    }
};
