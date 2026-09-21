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
 * Impide cargar en el formulario un servicio cuyo cupo del plan ya está cubierto:
 * solo se libera con una autorización OTP verificada.
 *
 * Adelantar el bloqueo al campo evita que el médico recorra todo el asistente y
 * descubra el tope recién al final. Como el asistente valida el paso al pulsar
 * «Siguiente», la misma regla impide avanzar.
 */
final class ClinicalQuotaFormGuard
{
    /**
     * Solo aplica donde existe el botón de autorización OTP. Sin ese botón el
     * bloqueo dejaría al médico sin salida.
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
     * «En casos diferentes»), pero en el formulario un cupo cubierto siempre exige
     * autorización OTP. Es la regla de negocio pedida: si el límite está cubierto,
     * el médico no lo carga sin clave, sin importar el caso.
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

    public static function isBlocked(
        mixed $livewire,
        ClinicalServiceChannel $channel,
        ?int $serviceListId = null,
    ): bool {
        if (! self::isEnforced($livewire)) {
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
     * Los complementos son un solo campo con tres canales: solo la tilde de
     * medicamentos consume cupo por sí sola.
     */
    public static function complementsRule(mixed $livewire): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($livewire): void {
            $entitlement = self::blockedComplementChannel($livewire, $value);

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
     * Complementos marcados que consumen cupo por sí solos (medicamentos). El resto
     * se valida en su propio select, porque la tilde de laboratorio/imagen cubre dos
     * canales y bloquearla entera taparía el que sí tiene cupo.
     *
     * @param  mixed  $state  Estado del checkbox list de complementos.
     */
    public static function blockedComplementChannel(mixed $livewire, mixed $state): ?ClinicalEntitlement
    {
        $selected = array_map('intval', (array) $state);

        if (! in_array(1, $selected, true)) {
            return null;
        }

        if (! self::isBlocked($livewire, ClinicalServiceChannel::Medication)) {
            return null;
        }

        return self::exhaustedEntitlement(ClinicalServiceChannel::Medication);
    }
}
