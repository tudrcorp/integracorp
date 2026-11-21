<?php

declare(strict_types=1);

namespace App\Support\NotificationCenter;

use App\Enums\NotificationAudience;
use App\Enums\OutboundChannel;
use App\Models\NotificationCatalogEntry;
use App\Models\NotificationChannelSetting;
use App\Models\SystemNotificationRecipientSetting;
use App\Support\SecurityAudit;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Escrituras del Centro de notificaciones: cada cambio queda auditado y la cache
 * del catálogo se invalida en el acto (los workers lo ven en ≤ 30 s).
 */
final class NotificationCenterSettings
{
    public const ENTRY_FLAGS = ['is_active', 'whatsapp_enabled', 'mail_enabled'];

    /**
     * Enciende o apaga un tipo o uno de sus canales. En un aviso interno del Centro,
     * «activa» es el mismo interruptor de su configuración de destinatarios.
     */
    public static function setEntryFlag(NotificationCatalogEntry $entry, string $flag, bool $value, bool $confirmedCritical = false): void
    {
        if (! in_array($flag, self::ENTRY_FLAGS, true)) {
            throw new InvalidArgumentException('Interruptor desconocido.');
        }

        if ($entry->is_critical && ! $value && ! $confirmedCritical) {
            throw new InvalidArgumentException('Este tipo es crítico: para pausarlo use «Pausar tipo crítico» y confirme.');
        }

        if ($entry->is_critical && ! NotificationCenterAccess::canManageCritical()) {
            throw new InvalidArgumentException('Solo un SUPERADMIN puede cambiar un tipo crítico.');
        }

        $actor = NotificationCenterAccess::actorName();
        $systemKey = $entry->systemKey();

        DB::transaction(function () use ($entry, $flag, $value, $actor, $systemKey): void {
            if ($systemKey !== null) {
                if ($flag !== 'is_active') {
                    throw new InvalidArgumentException('Los canales de un aviso interno se eligen con sus destinatarios: abra «Configurar destinatarios».');
                }

                SystemNotificationRecipientSetting::for($systemKey)->update(['is_active' => $value, 'updated_by' => $actor]);
            }

            $entry->forceFill([$flag => $value, 'updated_by' => $actor])->save();
        });

        SecurityAudit::log('AUDIT_NOTIFICATION_CENTER_ENTRY_TOGGLED', 'business.notification-center.catalog', [
            'catalog_key' => $entry->key,
            'flag' => $flag,
            'value' => $value,
            'critical' => $entry->is_critical,
            'updated_by' => $actor,
        ]);

        NotificationCatalog::flush();
    }

    /**
     * @param  array{name: string, area: string, audience: string, description?: string|null}  $data
     */
    public static function updateEntry(NotificationCatalogEntry $entry, array $data): void
    {
        $name = trim($data['name']);
        $area = array_key_exists($data['area'], NotificationCatalogDefaults::AREAS) ? $data['area'] : 'sin-clasificar';
        $audience = NotificationAudience::tryFrom($data['audience']) ?? NotificationAudience::External;

        if ($name === '') {
            throw new InvalidArgumentException('El nombre es obligatorio.');
        }

        $before = ['name' => $entry->name, 'area' => $entry->area, 'audience' => $entry->audience->value];

        $entry->forceFill([
            'name' => mb_substr($name, 0, 160),
            'area' => $area,
            'audience' => $entry->isSystemNotice() ? NotificationAudience::Internal : $audience,
            'description' => filled($data['description'] ?? null) ? mb_substr(trim((string) $data['description']), 0, 500) : null,
            'is_discovered' => false,
            'updated_by' => NotificationCenterAccess::actorName(),
        ])->save();

        SecurityAudit::log('AUDIT_NOTIFICATION_CENTER_ENTRY_UPDATED', 'business.notification-center.catalog', [
            'catalog_key' => $entry->key,
            'before' => $before,
            'after' => ['name' => $entry->name, 'area' => $entry->area, 'audience' => $entry->audience->value],
            'updated_by' => NotificationCenterAccess::actorName(),
        ]);

        NotificationCatalog::flush();
    }

    public static function setChannel(OutboundChannel $channel, bool $enabled): void
    {
        if (! NotificationCenterAccess::canManageChannels()) {
            throw new InvalidArgumentException('Solo un SUPERADMIN puede apagar o encender un canal en todo el sistema.');
        }

        NotificationChannelSetting::query()->updateOrCreate(
            ['channel' => $channel->value],
            ['is_enabled' => $enabled, 'updated_by' => NotificationCenterAccess::actorName()],
        );

        SecurityAudit::log('AUDIT_NOTIFICATION_CENTER_CHANNEL_TOGGLED', 'business.notification-center.channels', [
            'channel' => $channel->value,
            'enabled' => $enabled,
            'updated_by' => NotificationCenterAccess::actorName(),
        ]);

        NotificationCatalog::flush();
    }
}
