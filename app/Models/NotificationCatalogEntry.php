<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\NotificationAudience;
use App\Enums\SystemNotificationKey;
use Illuminate\Database\Eloquent\Model;

/**
 * Un tipo de mensaje del sistema en el Centro de notificaciones.
 *
 * - Los avisos internos del Centro (SystemNotificationKey) tienen system_key: su
 *   estado activo/pausado vive en SystemNotificationRecipientSetting, y sus canales
 *   se deciden con sus destinatarios.
 * - Los demás (externos, internos sueltos, masivos) se pausan y se les apaga cada
 *   canal desde aquí; el guardián lo aplica antes de enviar.
 *
 * @property NotificationAudience $audience
 * @property list<string>|null $origins
 * @property list<string>|null $channels
 */
class NotificationCatalogEntry extends Model
{
    protected $fillable = [
        'key',
        'name',
        'description',
        'area',
        'audience',
        'system_key',
        'origins',
        'channels',
        'is_active',
        'whatsapp_enabled',
        'mail_enabled',
        'is_critical',
        'is_bulk',
        'is_discovered',
        'last_seen_at',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'audience' => NotificationAudience::class,
            'origins' => 'array',
            'channels' => 'array',
            'is_active' => 'boolean',
            'whatsapp_enabled' => 'boolean',
            'mail_enabled' => 'boolean',
            'is_critical' => 'boolean',
            'is_bulk' => 'boolean',
            'is_discovered' => 'boolean',
            'last_seen_at' => 'datetime',
        ];
    }

    public function systemKey(): ?SystemNotificationKey
    {
        return filled($this->system_key) ? SystemNotificationKey::tryFrom((string) $this->system_key) : null;
    }

    public function isSystemNotice(): bool
    {
        return $this->systemKey() !== null;
    }
}
