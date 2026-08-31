<?php

declare(strict_types=1);

namespace App\Support\ClinicalEntitlements;

use App\Enums\ClinicalServiceChannel;
use App\Models\TelemedicinePatient;
use App\Models\TelemedicineServiceList;
use Throwable;

final class TelemedicineConsultationClinicalUi
{
    public const SPECIALIST_COMPLEMENT_KEY = 3;

    public const SPECIALIST_NOT_CONTEMPLATED_MESSAGE = 'Consulta con especialista no está contemplada en el uso clínico de este plan. Puede continuar: la interconsulta se registra, pero no consume cupo de especialista.';

    private static ?ClinicalEntitlementSnapshot $cached = null;

    private static ?int $cachedPatientId = null;

    /**
     * @var array<int, string>|null
     */
    private static ?array $cachedFollowUpOptions = null;

    public static function snapshotFromSession(): ?ClinicalEntitlementSnapshot
    {
        $patient = session('patient');
        if (! $patient instanceof TelemedicinePatient || $patient->id === null) {
            return null;
        }

        if (self::$cached !== null && self::$cachedPatientId === (int) $patient->id) {
            return self::$cached;
        }

        self::$cachedPatientId = (int) $patient->id;
        self::$cached = AffiliateClinicalEntitlementResolver::forPatient($patient);

        return self::$cached;
    }

    public static function flush(): void
    {
        self::$cached = null;
        self::$cachedPatientId = null;
        self::$cachedFollowUpOptions = null;
    }

    public static function isFollowUpType1Service(?string $name): bool
    {
        $normalized = mb_strtoupper(trim((string) $name));
        $normalized = strtr($normalized, [
            'Á' => 'A',
            'É' => 'E',
            'Í' => 'I',
            'Ó' => 'O',
            'Ú' => 'U',
        ]);

        return str_contains($normalized, 'SEGUIMIENTO MEDICO');
    }

    public static function isFollowUpServiceListId(?int $serviceListId): bool
    {
        if ($serviceListId === null || $serviceListId < 1) {
            return false;
        }

        $options = self::type1Options();
        if (isset($options[$serviceListId])) {
            return self::isFollowUpType1Service($options[$serviceListId]);
        }

        foreach (self::catalogFollowUpType1Options() as $id => $name) {
            if ($id === $serviceListId) {
                return self::isFollowUpType1Service($name);
            }
        }

        return false;
    }

    /**
     * Un seguimiento puede derivar a otro seguimiento: ese servicio no se quita
     * aunque ya esté elegido en «Tipo de servicio».
     *
     * @param  array<int, string>  $type1Options
     * @param  array<int, string>  $followUpOptions
     * @return array<int, string>
     */
    public static function driftOptionsFromType1(
        array $type1Options,
        ?int $exceptServiceListId = null,
        array $followUpOptions = [],
    ): array {
        foreach ($followUpOptions as $id => $name) {
            $type1Options[(int) $id] = $name;
        }

        if (
            $exceptServiceListId !== null
            && ! self::isFollowUpType1Service($type1Options[$exceptServiceListId] ?? null)
        ) {
            unset($type1Options[$exceptServiceListId]);
        }

        return $type1Options;
    }

    /**
     * @return array<int, string>
     */
    public static function type1Options(): array
    {
        $snapshot = self::snapshotFromSession();
        if ($snapshot === null) {
            return [];
        }

        if (! $snapshot->hasPlan) {
            return TelemedicineServiceList::query()
                ->where('level', 1)
                ->orderBy('name')
                ->pluck('name', 'id')
                ->all();
        }

        if (! $snapshot->isComplete) {
            return [];
        }

        return $snapshot->type1Options();
    }

    /**
     * @return array<int, string>
     */
    public static function type1DriftOptions(?int $exceptServiceListId = null): array
    {
        $options = self::type1Options();
        $hasFollowUp = false;

        foreach ($options as $name) {
            if (self::isFollowUpType1Service((string) $name)) {
                $hasFollowUp = true;
                break;
            }
        }

        return self::driftOptionsFromType1(
            $options,
            $exceptServiceListId,
            $hasFollowUp ? [] : self::catalogFollowUpType1Options(),
        );
    }

    /**
     * @return array<int, string>
     */
    public static function catalogFollowUpType1Options(): array
    {
        if (self::$cachedFollowUpOptions !== null) {
            return self::$cachedFollowUpOptions;
        }

        try {
            $out = [];

            foreach (TelemedicineServiceList::query()->where('level', 1)->orderBy('name')->get(['id', 'name']) as $service) {
                if (self::isFollowUpType1Service($service->name)) {
                    $out[(int) $service->id] = (string) $service->name;
                }
            }

            self::$cachedFollowUpOptions = $out;
        } catch (Throwable) {
            self::$cachedFollowUpOptions = [];
        }

        return self::$cachedFollowUpOptions;
    }

    public static function bannerMessage(): ?string
    {
        $snapshot = self::snapshotFromSession();
        if ($snapshot === null) {
            return 'No hay un paciente en sesión. Vuelva al caso para iniciar la consulta.';
        }

        if (! $snapshot->hasPlan) {
            return 'Paciente sin plan de afiliación: se listan los servicios operativos habituales. El cupo clínico no aplica hasta que Operaciones vincule el plan.';
        }

        if ($snapshot->blockingMessage !== '') {
            return $snapshot->blockingMessage;
        }

        return null;
    }

    public static function type1Helper(?int $serviceListId): ?string
    {
        $snapshot = self::snapshotFromSession();
        if ($snapshot === null || ! $snapshot->isComplete) {
            return self::bannerMessage();
        }

        return $snapshot->forType1($serviceListId)?->helperText();
    }

    public static function complementIsEnabled(int $complementKey): bool
    {
        $snapshot = self::snapshotFromSession();
        if ($snapshot === null) {
            return false;
        }

        if (! $snapshot->hasPlan) {
            return true;
        }

        if (! $snapshot->isComplete) {
            return false;
        }

        return match ($complementKey) {
            1 => $snapshot->channelIsAvailable(ClinicalServiceChannel::Medication),
            2 => $snapshot->channelIsAvailable(ClinicalServiceChannel::Laboratory)
                || $snapshot->channelIsAvailable(ClinicalServiceChannel::Imaging),
            self::SPECIALIST_COMPLEMENT_KEY => $snapshot->channelIsAvailable(ClinicalServiceChannel::Specialist),
            default => false,
        };
    }

    /**
     * @return array<int, string>
     */
    public static function complementOptions(): array
    {
        $all = [
            1 => 'Asignación de Medicamentos',
            2 => 'Indicación de Laboratorios o Estudios de Imagenología',
            self::SPECIALIST_COMPLEMENT_KEY => 'Consulta con Especialista',
        ];

        $out = [];
        foreach ($all as $key => $label) {
            if ($key === self::SPECIALIST_COMPLEMENT_KEY || self::complementIsEnabled($key)) {
                $out[$key] = $label;
            }
        }

        return $out;
    }

    public static function specialistIsContemplatedIn(?ClinicalEntitlementSnapshot $snapshot): bool
    {
        if ($snapshot === null || ! $snapshot->hasPlan) {
            return true;
        }

        if (! $snapshot->isComplete) {
            return false;
        }

        return $snapshot->channelIsAvailable(ClinicalServiceChannel::Specialist);
    }

    public static function specialistIsContemplated(): bool
    {
        return self::specialistIsContemplatedIn(self::snapshotFromSession());
    }

    public static function specialistComplementSelected(mixed $complements): bool
    {
        return in_array(self::SPECIALIST_COMPLEMENT_KEY, array_map('intval', (array) $complements), true);
    }

    public static function shouldNotifySpecialistNotContemplated(mixed $state, mixed $old): bool
    {
        return self::specialistComplementSelected($state)
            && ! self::specialistComplementSelected($old)
            && ! self::specialistIsContemplated();
    }

    /**
     * @return array<int, string>
     */
    public static function complementOptionDescriptions(): array
    {
        if (self::specialistIsContemplated()) {
            $helper = self::channelHelper(ClinicalServiceChannel::Specialist);

            return $helper !== null ? [self::SPECIALIST_COMPLEMENT_KEY => $helper] : [];
        }

        return [
            self::SPECIALIST_COMPLEMENT_KEY => 'No está contemplada en el uso clínico de este plan. Si la marca, podrá continuar.',
        ];
    }

    public static function complementsHelperText(mixed $complements): ?string
    {
        $parts = [];
        $banner = self::bannerMessage();
        if ($banner !== null) {
            $parts[] = $banner;
        }

        if (self::specialistComplementSelected($complements) && ! self::specialistIsContemplated()) {
            $parts[] = self::SPECIALIST_NOT_CONTEMPLATED_MESSAGE;
        } elseif (self::specialistComplementSelected($complements)) {
            $quota = self::channelHelper(ClinicalServiceChannel::Specialist);
            if ($quota !== null) {
                $parts[] = $quota;
            }
        }

        if ($parts !== []) {
            return implode(' ', array_unique($parts));
        }

        return 'Medicamentos y laboratorios o estudios aparecen solo si el plan los tiene en uso clínico. Consulta con especialista siempre está disponible.';
    }

    public static function specialistNotContemplatedHint(mixed $complements): ?string
    {
        if (! self::specialistComplementSelected($complements) || self::specialistIsContemplated()) {
            return null;
        }

        return 'No contemplada en el uso clínico';
    }

    public static function channelHelper(ClinicalServiceChannel $channel): ?string
    {
        return self::snapshotFromSession()?->forChannel($channel)?->helperText();
    }
}
