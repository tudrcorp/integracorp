<?php

declare(strict_types=1);

namespace App\Services\PublicAiAgent;

class PublicPlanBenefitsService
{
    /**
     * @return array<int, string>
     */
    public function planTitles(): array
    {
        return [
            1 => 'Plan Inicial',
            2 => 'Plan Ideal',
            3 => 'Plan Especial',
        ];
    }

    public function supportsPlanId(int $planId): bool
    {
        return array_key_exists($planId, $this->planTitles());
    }

    public function benefitCategoryTitle(int $planId): string
    {
        return match ($planId) {
            1 => 'Asistencia Médica en Sitio',
            2 => 'Asistencia Médica por Accidente',
            3 => 'Asistencia Médica por Emergencias',
            default => 'Beneficios del plan',
        };
    }

    /**
     * @return list<string>
     */
    public function benefitBulletLines(int $planId): array
    {
        if (! $this->supportsPlanId($planId)) {
            return [];
        }

        return match ($planId) {
            1 => [
                'Orientación médica telefónica (Telemedicina).',
                'Entrega de tratamiento médico en domicilio',
                'Monitoreo telefónico evolutivo.',
                'Atención Médica Domiciliaria con tratamiento de unidosis incluida.',
                'Laboratorios a domicilio con fines diagnósticos.',
                'Imagenología a domicilio con fines diagnósticos.',
                'Seguimiento e interpretación de resultados.',
                'Traslado en ambulancia urbano en caso de emergencia.',
            ],
            2 => [
                'Orientación médica telefónica (Telemedicina).',
                'Entrega de tratamiento médico en domicilio',
                'Monitoreo telefónico evolutivo.',
                'Atención Médica Domiciliaria con tratamiento de unidosis incluida.',
                'Laboratorios a domicilio con fines diagnósticos.',
                'Imagenología a domicilio con fines diagnósticos.',
                'Seguimiento e interpretación de resultados.',
                'Traslado en ambulancia urbano en caso de emergencia.',
                'Consulta online o presencial con médicos especialistas.',
                'Urgencias menores en domicilio o en sitio.',
                'Asistencia médica por accidente.',
            ],
            default => [
                'Orientación médica telefónica (Telemedicina).',
                'Entrega de tratamiento médico en domicilio',
                'Monitoreo telefónico evolutivo.',
                'Atención Médica Domiciliaria con tratamiento de unidosis incluida.',
                'Laboratorios a domicilio con fines diagnósticos.',
                'Imagenología a domicilio con fines diagnósticos.',
                'Seguimiento e interpretación de resultados.',
                'Traslado en ambulancia urbano en caso de emergencia.',
                'Consulta online o presencial con médicos especialistas.',
                'Urgencias menores en domicilio o en sitio.',
                'Asistencia médica por accidente.',
                'Asistencia médica por emergencia. (Patologías listadas).',
            ],
        };
    }

    public function buildBenefitsMessage(int $planId): string
    {
        $title = $this->planTitles()[$planId] ?? null;

        if ($title === null) {
            return 'No encontré beneficios para ese plan. Usa el ID 1, 2 o 3 seguido de la palabra beneficios (ejemplo: 1 beneficios).';
        }

        $lines = [
            $this->benefitCategoryTitle($planId),
            '',
            ...collect($this->benefitBulletLines($planId))
                ->map(fn (string $line): string => '• '.$line)
                ->all(),
        ];

        return sprintf(
            "%d .- %s\n\n%s",
            $planId,
            $title,
            implode("\n", $lines),
        );
    }

    public function benefitsReminderMessage(): string
    {
        return <<<'TEXT'
Recuerda que para cotizar debes escribir la palabra «cotizar».
TEXT;
    }
}
