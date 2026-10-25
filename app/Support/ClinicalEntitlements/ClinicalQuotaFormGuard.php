<?php

declare(strict_types=1);

namespace App\Support\ClinicalEntitlements;

use App\Enums\ClinicalServiceChannel;
use App\Models\TelemedicinePatient;
use App\Models\User;
use Closure;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

/**
 * Qué hace el formulario de consulta cuando el cupo del plan ya está cubierto.
 *
 * - Servicios Macro (TYPE_1): el campo se bloquea y solo se libera con una
 *   autorización OTP verificada. Adelantar el bloqueo al campo evita que el
 *   médico recorra todo el asistente y descubra el tope recién al final; como el
 *   asistente valida el paso al pulsar «Siguiente», la misma regla impide avanzar.
 * - Medicamentos, laboratorio, imagenología y especialista: no hay OTP. Se ocultan
 *   los campos de lo cubierto y la consulta sigue solo con lo no cubierto, que no
 *   consume cupo.
 */
final class ClinicalQuotaFormGuard
{
    /**
     * Solo aplica en la pantalla de creación, la que tiene el botón de
     * autorización OTP. En las demás no se bloquea ni se oculta nada: ocultar
     * un campo con datos ya guardados los haría desaparecer de la vista.
     */
    public static function isEnforced(mixed $livewire): bool
    {
        return is_object($livewire)
            && property_exists($livewire, 'verifiedClinicalOverrideIds')
            && method_exists($livewire, 'getHeaderActions');
    }

    /**
     * Canales con una autorización OTP ya verificada en esta pantalla.
     *
     * @return list<string>
     */
    public static function verifiedChannels(mixed $livewire): array
    {
        if (! self::isEnforced($livewire)) {
            return [];
        }

        $verified = $livewire->verifiedClinicalOverrideIds;

        return is_array($verified) ? array_map('strval', array_keys($verified)) : [];
    }

    /**
     * Beneficio del canal cuando el cupo del plan ya está cubierto.
     *
     * A propósito es más estricto que {@see ClinicalConsultationConsumption::assertCanSave()}:
     * el guardado deja pasar un uso extra dentro de un caso que ya contaba (alcance
     * «En casos diferentes»), pero en el formulario un cupo cubierto se trata como
     * cubierto sin importar el caso.
     */
    public static function exhaustedEntitlement(
        ClinicalServiceChannel $channel,
        ?int $serviceListId = null,
    ): ?ClinicalEntitlement {
        $snapshot = TelemedicineConsultationClinicalUi::snapshotFromSession();

        if ($snapshot === null || ! $snapshot->hasPlan || ! $snapshot->isComplete) {
            return null;
        }

        $entitlement = $channel === ClinicalServiceChannel::Type1
            ? $snapshot->forType1($serviceListId)
            : $snapshot->forChannel($channel);

        if ($entitlement === null || ! $entitlement->exhausted) {
            return null;
        }

        if (! session('patient') instanceof TelemedicinePatient) {
            return null;
        }

        return $entitlement;
    }

    /**
     * Si el campo de lo cubierto del canal debe ocultarse porque el cupo está
     * cubierto. Nunca aplica a Servicios Macro, que sigue con OTP.
     */
    public static function coveredIsHidden(mixed $livewire, ClinicalServiceChannel $channel): bool
    {
        if (! self::isEnforced($livewire) || $channel->requiresOverrideWhenExhausted()) {
            return false;
        }

        return self::exhaustedEntitlement($channel) !== null;
    }

    /**
     * Aviso informativo bajo el campo de lo no cubierto cuando lo cubierto se ocultó.
     */
    public static function uncoveredOnlyNotice(mixed $livewire, ClinicalServiceChannel $channel): ?string
    {
        if (! self::coveredIsHidden($livewire, $channel)) {
            return null;
        }

        return self::exhaustedEntitlement($channel)?->helperText();
    }

    /**
     * Solo Servicios Macro bloquea: el resto oculta lo cubierto en vez de bloquear.
     */
    public static function isBlocked(
        mixed $livewire,
        ClinicalServiceChannel $channel,
        ?int $serviceListId = null,
    ): bool {
        if (! self::isEnforced($livewire) || ! $channel->requiresOverrideWhenExhausted()) {
            return false;
        }

        if (in_array($channel->value, self::verifiedChannels($livewire), true)) {
            return false;
        }

        return self::exhaustedEntitlement($channel, $serviceListId) !== null;
    }

    /**
     * Quien no puede pedir la clave (proveedor, AMD, ATENMEDI) no tiene el botón en
     * la cabecera: a esos se les indica escalar a TDG en vez de mandarlos a un botón
     * que no verán.
     */
    public static function message(ClinicalEntitlement $entitlement): string
    {
        $user = Auth::user();

        $salida = ClinicalServiceOverrideOtp::userMayOverride($user instanceof User ? $user : null)
            ? 'solicite la clave OTP con el botón «Autorizar servicio extra (OTP)» de la cabecera para poder continuar.'
            : 'escale el caso a TDG para que un médico de TDG solicite la autorización OTP.';

        return $entitlement->helperText().' No puede cargarlo: '.$salida;
    }

    /**
     * Texto fijo bajo el campo mientras el cupo esté agotado.
     */
    public static function helperText(
        mixed $livewire,
        ClinicalServiceChannel $channel,
        ?int $serviceListId = null,
    ): ?string {
        if (! self::isBlocked($livewire, $channel, $serviceListId)) {
            return null;
        }

        $entitlement = self::exhaustedEntitlement($channel, $serviceListId);

        return $entitlement === null ? null : self::message($entitlement);
    }

    /**
     * Regla de validación del campo. Al pulsar «Siguiente» el asistente valida el
     * paso, así que esto también bloquea el avance.
     */
    public static function rule(
        mixed $livewire,
        ClinicalServiceChannel $channel,
        ?int $serviceListId = null,
    ): Closure {
        return function (string $attribute, mixed $value, Closure $fail) use ($livewire, $channel, $serviceListId): void {
            if (blank($value) || $value === []) {
                return;
            }

            // En Servicios Macro el propio valor del campo identifica el beneficio.
            if ($channel === ClinicalServiceChannel::Type1 && $serviceListId === null) {
                $serviceListId = (int) $value;
            }

            if (! self::isBlocked($livewire, $channel, $serviceListId)) {
                return;
            }

            $entitlement = self::exhaustedEntitlement($channel, $serviceListId);

            if ($entitlement !== null) {
                $fail(self::message($entitlement));
            }
        };
    }

    /**
     * Aviso inmediato al seleccionar algo sin cupo, para que no lo descubra al avanzar.
     */
    public static function notifyIfBlocked(
        mixed $livewire,
        ClinicalServiceChannel $channel,
        ?int $serviceListId = null,
    ): void {
        $entitlement = self::isBlocked($livewire, $channel, $serviceListId)
            ? self::exhaustedEntitlement($channel, $serviceListId)
            : null;

        if ($entitlement === null) {
            return;
        }

        Notification::make()
            ->title('Cupo agotado: '.$entitlement->channel->shortLabel())
            ->body(self::message($entitlement))
            ->warning()
            ->persistent()
            ->send();
    }

    /**
     * Canales de un complemento. La tilde de laboratorio/imagen cubre dos.
     *
     * @return list<ClinicalServiceChannel>
     */
    public static function complementChannels(int $complementKey): array
    {
        return match ($complementKey) {
            1 => [ClinicalServiceChannel::Medication],
            2 => [ClinicalServiceChannel::Laboratory, ClinicalServiceChannel::Imaging],
            TelemedicineConsultationClinicalUi::SPECIALIST_COMPLEMENT_KEY => [ClinicalServiceChannel::Specialist],
            default => [],
        };
    }

    /**
     * Avisos de «solo no cubiertos» de los complementos marcados.
     *
     * @param  mixed  $state  Estado del checkbox list de complementos.
     * @return list<string>
     */
    public static function uncoveredOnlyComplementNotices(mixed $livewire, mixed $state): array
    {
        $notices = [];

        foreach (array_unique(array_map('intval', (array) $state)) as $complementKey) {
            foreach (self::complementChannels($complementKey) as $channel) {
                $notice = self::uncoveredOnlyNotice($livewire, $channel);

                if ($notice !== null) {
                    $notices[] = $channel->shortLabel().': '.$notice;
                }
            }
        }

        return $notices;
    }

    /**
     * Aviso al marcar un complemento cuyo cupo ya está cubierto: puede seguir,
     * pero solo con lo no cubierto. No bloquea.
     */
    public static function notifyUncoveredOnlyComplements(mixed $livewire, mixed $state, mixed $old): void
    {
        $added = array_diff(array_map('intval', (array) $state), array_map('intval', (array) $old));

        $notices = self::uncoveredOnlyComplementNotices($livewire, $added);

        if ($notices === []) {
            return;
        }

        Notification::make()
            ->title('Cupo del plan cubierto')
            ->body(implode(' ', $notices))
            ->info()
            ->send();
    }
}
