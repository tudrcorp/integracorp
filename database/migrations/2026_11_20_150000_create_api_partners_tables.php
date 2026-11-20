<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aliados externos que consultan datos de gestión de IntegraCorp por API.
 *
 * - `api_partners`: un aliado, su credencial (solo el hash del token), permisos,
 *   IPs permitidas, límite de peticiones y vigencia.
 * - `api_partner_access_logs`: bitácora de cada petición (también los intentos
 *   rechazados), para auditar quién consultó datos de pacientes.
 *
 * Aditiva e idempotente: se puede aplicar con `--path` aunque ya exista.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('api_partners')) {
            Schema::create('api_partners', function (Blueprint $table): void {
                $table->id();
                $table->string('name', 150);
                $table->string('contact_name', 150)->nullable();
                $table->string('contact_email', 150)->nullable();
                $table->string('token_prefix', 32)->nullable()->unique();
                $table->string('token_hash', 64)->nullable();
                $table->timestamp('token_issued_at')->nullable();
                $table->unsignedBigInteger('token_issued_by')->nullable();
                $table->timestamp('token_revoked_at')->nullable();
                $table->json('abilities')->nullable();
                $table->json('allowed_ips')->nullable();
                $table->unsignedSmallInteger('rate_limit_per_minute')->default(60);
                $table->boolean('is_active')->default(true);
                $table->timestamp('expires_at')->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->string('last_used_ip', 45)->nullable();
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('api_partner_access_logs')) {
            Schema::create('api_partner_access_logs', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('api_partner_id')->nullable();
                $table->string('method', 10);
                $table->string('path', 255);
                $table->string('ip', 45)->nullable();
                $table->string('user_agent', 255)->nullable();
                $table->unsignedSmallInteger('status_code');
                $table->string('outcome', 40);
                $table->json('query')->nullable();
                $table->unsignedInteger('records_count')->nullable();
                $table->unsignedInteger('duration_ms')->nullable();
                $table->timestamp('created_at')->nullable();

                $table->index(['api_partner_id', 'created_at']);
                $table->index('created_at');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('api_partner_access_logs');
        Schema::dropIfExists('api_partners');
    }
};
