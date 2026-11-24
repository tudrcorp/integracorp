<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * WhatsApp al paciente cuando nace la orden de servicio de un servicio no cubierto
 * (medicamentos, laboratorios, imagenología y especialista), con su respuesta SI/NO.
 *
 * - `service_order_patient_notifications`: una fila por orden con el envío y la respuesta.
 * - `whatsapp_inbound_messages`: bitácora de cada mensaje que UltraMsg avisa por el webhook
 *   (el id del mensaje es único: un aviso repetido no se procesa dos veces).
 * - `service_order_patient_whatsapp_settings`: fila única con el interruptor (apagado por defecto)
 *   y las horas para responder; se maneja en Operaciones → «WhatsApp al paciente».
 * - Mensajes de prueba: `is_test` y `sample_order_id` (la orden que se usa de muestra); una
 *   prueba no tiene orden propia, por eso `operation_service_order_id` admite nulo.
 * - Permisos, sin asignarlos a nadie: la pantalla «WhatsApp al paciente» (activar y enviar
 *   pruebas) y «Reenviar WhatsApp al paciente de la orden».
 * - Tipo nuevo en el Centro de notificaciones (carga aditiva del catálogo).
 *
 * Idempotente: se puede aplicar con --path aunque ya exista una parte.
 */
return new class extends Migration
{
    private const PERMISSION_MODULE = 'OPERACIONES';

    /** @var array<string, string> slug => nombre */
    private const PERMISSIONS = [
        'reenviar-whatsapp-paciente-orden' => 'Reenviar WhatsApp al paciente de la orden de servicio (no cubiertos)',
        'whatsapp-paciente-ordenes' => 'WhatsApp al paciente de las órdenes de servicio: activar, desactivar y enviar pruebas',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('service_order_patient_notifications')) {
            Schema::create('service_order_patient_notifications', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('operation_service_order_id')->nullable();
                $table->boolean('is_test')->default(false);
                $table->unsignedBigInteger('sample_order_id')->nullable();
                $table->unsignedBigInteger('operation_coordination_service_id')->nullable();
                $table->unsignedBigInteger('telemedicine_patient_id')->nullable();
                $table->unsignedBigInteger('telemedicine_case_id')->nullable();
                $table->string('order_number', 100)->nullable();
                $table->string('service_type', 40)->nullable();
                $table->string('patient_name')->nullable();
                $table->string('recipient_name')->nullable();
                $table->string('recipient_kind', 20)->nullable()->comment('PACIENTE o REPRESENTANTE');
                $table->string('phone', 20)->nullable()->comment('Solo dígitos con código de país (584121234567)');
                $table->string('status', 20);
                $table->text('message')->nullable();
                $table->string('ultramsg_message_id', 100)->nullable();
                $table->unsignedSmallInteger('attempts')->default(0);
                $table->timestamp('queued_at')->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->string('answer', 2)->nullable()->comment('SI o NO');
                $table->string('answer_raw', 500)->nullable();
                $table->timestamp('answered_at')->nullable();
                $table->unsignedSmallInteger('invalid_replies')->default(0);
                $table->text('error')->nullable();
                $table->string('created_by')->nullable();
                $table->string('updated_by')->nullable();
                $table->timestamps();
            });
        }

        $this->ensureTestColumns();

        if (! Schema::hasTable('service_order_patient_whatsapp_settings')) {
            Schema::create('service_order_patient_whatsapp_settings', function (Blueprint $table): void {
                $table->id();
                $table->boolean('enabled')->default(false);
                $table->unsignedSmallInteger('response_hours')->default(72);
                $table->string('updated_by')->nullable();
                $table->timestamps();
            });
        }

        if (DB::table('service_order_patient_whatsapp_settings')->doesntExist()) {
            DB::table('service_order_patient_whatsapp_settings')->insert([
                'enabled' => false,
                'response_hours' => 72,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->ensureIndexes('service_order_patient_notifications', [
            'sopn_order_unique' => ['unique', ['operation_service_order_id']],
            'sopn_coordination_index' => ['index', ['operation_coordination_service_id']],
            'sopn_patient_index' => ['index', ['telemedicine_patient_id']],
            'sopn_case_index' => ['index', ['telemedicine_case_id']],
            'sopn_phone_status_index' => ['index', ['phone', 'status']],
            'sopn_status_expires_index' => ['index', ['status', 'expires_at']],
        ]);

        if (! Schema::hasTable('whatsapp_inbound_messages')) {
            Schema::create('whatsapp_inbound_messages', function (Blueprint $table): void {
                $table->id();
                $table->string('provider', 20)->default('ULTRAMSG');
                $table->string('message_id', 191);
                $table->string('event_type', 40)->nullable();
                $table->string('phone', 20)->nullable();
                $table->string('message_type', 20)->nullable();
                $table->text('body')->nullable();
                $table->string('result', 30)->nullable()->comment('Qué se hizo con el mensaje');
                $table->unsignedBigInteger('service_order_patient_notification_id')->nullable();
                $table->json('payload')->nullable();
                $table->timestamp('received_at')->nullable();
                $table->timestamp('processed_at')->nullable();
                $table->timestamps();
            });
        }

        $this->ensureIndexes('whatsapp_inbound_messages', [
            'wim_message_unique' => ['unique', ['message_id']],
            'wim_phone_index' => ['index', ['phone']],
            'wim_notification_index' => ['index', ['service_order_patient_notification_id']],
        ]);

        foreach (self::PERMISSIONS as $slug => $name) {
            if (Schema::hasTable('permissions')
                && ! DB::table('permissions')->where('slug', $slug)->where('module', self::PERMISSION_MODULE)->exists()) {
                DB::table('permissions')->insert([
                    'name' => $name,
                    'slug' => $slug,
                    'module' => self::PERMISSION_MODULE,
                    'created_by' => 'system',
                    'updated_by' => 'system',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        /** Suma el tipo «Orden de servicio no cubierta al paciente» al Centro de notificaciones (aditivo). */
        try {
            \App\Support\NotificationCenter\NotificationCatalog::seed();
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    /**
     * Columnas de los mensajes de prueba, por si la tabla se creó antes que ellas.
     */
    private function ensureTestColumns(): void
    {
        $table = 'service_order_patient_notifications';

        if (! Schema::hasColumn($table, 'is_test')) {
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->boolean('is_test')->default(false)->after('operation_service_order_id'));
        }

        if (! Schema::hasColumn($table, 'sample_order_id')) {
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->unsignedBigInteger('sample_order_id')->nullable()->after('is_test'));
        }

        $nullable = collect(Schema::getColumns($table))->firstWhere('name', 'operation_service_order_id')['nullable'] ?? true;

        if (! $nullable) {
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->unsignedBigInteger('operation_service_order_id')->nullable()->change());
        }
    }

    /**
     * Índices con nombre corto (MySQL admite 64 caracteres), creados solo si faltan: la
     * migración se puede repetir sobre una tabla que ya existe.
     *
     * @param  array<string, array{0: 'unique'|'index', 1: list<string>}>  $indexes
     */
    private function ensureIndexes(string $tableName, array $indexes): void
    {
        foreach ($indexes as $name => [$type, $columns]) {
            if (Schema::hasIndex($tableName, $name)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($type, $columns, $name): void {
                $type === 'unique' ? $table->unique($columns, $name) : $table->index($columns, $name);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('permissions')) {
            $permissionIds = DB::table('permissions')->whereIn('slug', array_keys(self::PERMISSIONS))->where('module', self::PERMISSION_MODULE)->pluck('id');

            if ($permissionIds->isNotEmpty()) {
                if (Schema::hasTable('user_permissions')) {
                    DB::table('user_permissions')->whereIn('permission_id', $permissionIds)->delete();
                }

                DB::table('permissions')->whereIn('id', $permissionIds)->delete();
            }
        }

        Schema::dropIfExists('service_order_patient_whatsapp_settings');
        Schema::dropIfExists('whatsapp_inbound_messages');
        Schema::dropIfExists('service_order_patient_notifications');
    }
};
