<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Claves OTP emitidas, consultables por SUPERADMIN en Negocios → CONFIGURACIÓN
     * cuando el WhatsApp o el correo no llegan. La clave va cifrada con la llave
     * de la aplicación y cada fila se borra a las 24 horas (model:prune).
     *
     * Es solo consulta: la validación sigue contra el hash de cada tabla de retos.
     */
    public function up(): void
    {
        if (Schema::hasTable('system_otp_codes')) {
            return;
        }

        Schema::create('system_otp_codes', function (Blueprint $table): void {
            $table->id();
            $table->string('kind', 40);
            $table->string('challenge_type', 150);
            $table->unsignedBigInteger('challenge_id');
            $table->text('code');
            $table->unsignedBigInteger('requested_by_user_id')->nullable();
            $table->string('requested_by_name', 150)->nullable();
            $table->string('subject', 255)->nullable();
            $table->json('details')->nullable();
            $table->unsignedSmallInteger('emails_sent')->default(0);
            $table->unsignedSmallInteger('phones_sent')->default(0);
            $table->dateTime('expires_at');
            $table->dateTime('used_at')->nullable();
            $table->timestamps();

            $table->index('created_at', 'soc_created_at_idx');
            $table->index(['challenge_type', 'challenge_id'], 'soc_challenge_idx');
            $table->index(['kind', 'created_at'], 'soc_kind_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_otp_codes');
    }
};
