<?php

declare(strict_types=1);

namespace App\Support\CrmInbox;

use App\Filament\Administration\Pages\AtencionWhatsapp as AdministrationAtencionWhatsapp;
use App\Filament\Business\Pages\AtencionWhatsapp as BusinessAtencionWhatsapp;
use App\Filament\Operations\Pages\AtencionWhatsapp as OperationsAtencionWhatsapp;
use App\Models\CrmHandoffAttachment;
use App\Models\CrmHandoffEnvelope;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

/**
 * Archivos del cliente en la bandeja: cómo se nombran en el hilo, de qué conversación son y quién puede abrirlos.
 */
final class CrmHandoffMedia
{
    public const MESSAGE_KIND = 'media';

    /** @var array<string, class-string> */
    private const PAGES = [
        'business' => BusinessAtencionWhatsapp::class,
        'operations' => OperationsAtencionWhatsapp::class,
        'administration' => AdministrationAtencionWhatsapp::class,
    ];

    public static function typeLabel(string $mediaType): string
    {
        return match ($mediaType) {
            CrmHandoffAttachment::TYPE_IMAGE => 'Imagen',
            CrmHandoffAttachment::TYPE_AUDIO => 'Nota de voz',
            default => 'Documento',
        };
    }

    /**
     * Texto de la burbuja cuando el archivo llega después del mensaje de texto del bot. La nota de voz conserva
     * la transcripción; la imagen y el documento, solo la leyenda que escribió el cliente.
     */
    public static function bodyFor(string $mediaType, ?string $caption, string $existing): string
    {
        $existing = trim($existing);
        $placeholder = str_starts_with($existing, '[') && str_ends_with($existing, ']');

        if ($mediaType === CrmHandoffAttachment::TYPE_AUDIO && $existing !== '' && ! $placeholder) {
            return $existing;
        }

        return (string) ($caption ?? '');
    }

    /** Línea de la bandeja: «📎 Documento: informe.pdf». */
    public static function preview(CrmHandoffAttachment $attachment): string
    {
        $label = $attachment->media_type === CrmHandoffAttachment::TYPE_DOCUMENT
            ? 'Documento: '.$attachment->original_name
            : self::typeLabel((string) $attachment->media_type);
        $caption = trim((string) $attachment->caption);

        return ($attachment->media_type === CrmHandoffAttachment::TYPE_AUDIO ? '🎤 ' : '📎 ').$label.($caption !== '' ? ' · '.$caption : '');
    }

    /**
     * Archivos del hilo de un caso: los de su derivación y los que llegaron sin derivación desde ese teléfono
     * (la misma regla con la que el hilo muestra los mensajes).
     *
     * @return Builder<CrmHandoffAttachment>
     */
    public static function forEnvelope(CrmHandoffEnvelope $envelope): Builder
    {
        return CrmHandoffAttachment::query()->where(function (Builder $query) use ($envelope): void {
            $query->where('handoff_id', $envelope->handoff_id)
                ->orWhere(function (Builder $query) use ($envelope): void {
                    $query->whereNull('handoff_id')->where('phone', $envelope->phone);
                });
        });
    }

    /**
     * Archivos del caso que el analista todavía debe agregar a un caso de telemedicina o marcar como «sin caso».
     *
     * @return list<array{id: int, type: string, type_label: string, name: string, size: string, time: string}>
     */
    public static function pendingFor(CrmHandoffEnvelope $envelope): array
    {
        return self::forEnvelope($envelope)
            ->pendingFiling()
            ->orderBy('id')
            ->limit(50)
            ->get(['id', 'media_type', 'original_name', 'size_bytes', 'created_at'])
            ->map(fn (CrmHandoffAttachment $attachment): array => [
                'id' => (int) $attachment->id,
                'type' => (string) $attachment->media_type,
                'type_label' => self::typeLabel((string) $attachment->media_type),
                'name' => (string) $attachment->original_name,
                'size' => $attachment->sizeLabel(),
                'time' => $attachment->created_at?->timezone('America/Caracas')->format('d/m H:i') ?? '',
            ])
            ->all();
    }

    /**
     * Puede abrir el archivo quien atiende la bandeja del área del caso. Un archivo que llegó sin caso se ve
     * en Negocios, igual que los casos sin área.
     */
    public static function canView(?User $user, CrmHandoffAttachment $attachment): bool
    {
        if ($user === null) {
            return false;
        }

        $area = $attachment->handoff_id !== null
            ? CrmHandoffEnvelope::query()->where('handoff_id', $attachment->handoff_id)->value('area')
            : CrmHandoffEnvelope::query()->where('phone', $attachment->phone)->orderByDesc('id')->value('area');

        $panelId = CrmInboxAreas::panelFor(is_string($area) ? $area : null);
        $page = self::PAGES[$panelId] ?? self::PAGES['business'];

        try {
            $panel = Filament::getPanel($panelId);
        } catch (Throwable) {
            return false;
        }

        return $user->canAccessPanel($panel) && $page::canAccess();
    }
}
