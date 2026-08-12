<?php

declare(strict_types=1);

namespace App\Support\Tdev;

use App\Models\TdevAgency;
use App\Models\TdevAgent;
use App\Support\RunReportMessageFormatter;

final class TdevRegistrationNotificationMessage
{
    public const TYPE_AGENCY_LEVEL_THREE = 'agency_level_three';

    public const TYPE_AGENT_LEVEL_THREE = 'agent_level_three';

    public const TYPE_AGENT_FREELANCE_LEVEL_TWO = 'agent_freelance_level_two';

    public static function resolveAgentType(TdevAgent $agent): string
    {
        $agency = $agent->agency;

        if ($agency?->isLevelThree()) {
            return self::TYPE_AGENT_LEVEL_THREE;
        }

        return self::TYPE_AGENT_FREELANCE_LEVEL_TWO;
    }

    public static function typeLabel(string $type): string
    {
        return match ($type) {
            self::TYPE_AGENCY_LEVEL_THREE => 'Agencia nivel 3',
            self::TYPE_AGENT_LEVEL_THREE => 'Agente de agencia nivel 3',
            self::TYPE_AGENT_FREELANCE_LEVEL_TWO => 'Agente freelance (agencia nivel 2)',
            default => 'Registro TDEV',
        };
    }

    public static function whatsappBodyForAgency(TdevAgency $agency): string
    {
        $parent = $agency->parentAgency;

        $lines = [
            '*NUEVO REGISTRO TDEV · AGENCIA NIVEL 3*',
            '',
            'Se registró una agencia asociada desde el formulario público.',
            '',
            '*Tipo*',
            '• '.self::typeLabel(self::TYPE_AGENCY_LEVEL_THREE),
            '',
            '*Agencia registrada*',
            '• Nombre: '.self::value($agency->name),
            '• Identificación: '.self::value($agency->identification_number),
            '• Correo: '.self::value($agency->email),
            '• Teléfono: '.self::value($agency->phone),
            '• Tel. adicional: '.self::value($agency->phone_additional),
            '• Representante: '.self::value($agency->representative_name),
            '• Nacimiento representante: '.($agency->representative_birth_date?->format('d/m/Y') ?? '—'),
            '• Aniversario: '.($agency->anniversary_date?->format('d/m/Y') ?? '—'),
            '• Instagram: '.(filled($agency->instagram_username) ? '@'.$agency->instagram_username : '—'),
            '• URL: '.self::value($agency->url),
            '• Dirección: '.self::value($agency->address),
            '• Ubicación: '.self::location($agency),
            '• Registrada el: '.($agency->created_at?->format('d/m/Y H:i:s') ?? '—'),
            '',
            '*Agencia principal (nivel 2)*',
            '• Nombre: '.self::value($parent?->name),
            '• Correo: '.self::value($parent?->email),
            '• Teléfono: '.self::value($parent?->phone),
            '',
            'Revise en INTEGRACORP → Estructura comercial → AGENCIAS TDEV.',
        ];

        return RunReportMessageFormatter::truncateForWhatsAppCaption(implode("\n", $lines));
    }

    public static function whatsappBodyForAgent(TdevAgent $agent): string
    {
        $type = self::resolveAgentType($agent);
        $agency = $agent->agency;
        $parent = $agency?->parentAgency;

        $lines = [
            '*NUEVO REGISTRO TDEV · AGENTE*',
            '',
            'Se registró un agente desde el formulario público.',
            '',
            '*Tipo*',
            '• '.self::typeLabel($type),
            '',
            '*Agente*',
            '• Nombre: '.self::value($agent->full_name),
            '• Cargo: '.self::value($agent->position),
            '• Correo: '.self::value($agent->email),
            '• Teléfono: '.self::value($agent->phone),
            '• Nacimiento: '.($agent->birth_date?->format('d/m/Y') ?? '—'),
            '• Registrado el: '.($agent->registered_at?->format('d/m/Y H:i:s') ?? '—'),
            '• Origen: '.self::value($agent->registration_source),
            '',
            '*Agencia*',
            '• Nombre: '.self::value($agency?->name),
            '• Nivel: '.($agency?->level !== null ? 'Nivel '.$agency->level : '—'),
            '• Correo: '.self::value($agency?->email),
            '• Teléfono: '.self::value($agency?->phone),
        ];

        if ($agency?->isLevelThree()) {
            $lines = array_merge($lines, [
                '',
                '*Agencia principal (nivel 2)*',
                '• Nombre: '.self::value($parent?->name),
                '• Correo: '.self::value($parent?->email),
            ]);
        }

        $lines[] = '';
        $lines[] = 'Revise en INTEGRACORP → Estructura comercial → AGENCIAS TDEV.';

        return RunReportMessageFormatter::truncateForWhatsAppCaption(implode("\n", $lines));
    }

    /**
     * @return array<string, mixed>
     */
    public static function emailPayloadForAgency(TdevAgency $agency): array
    {
        return [
            'registrationType' => self::TYPE_AGENCY_LEVEL_THREE,
            'registrationTypeLabel' => self::typeLabel(self::TYPE_AGENCY_LEVEL_THREE),
            'title' => 'Nueva agencia nivel 3 registrada',
            'intro' => 'Se registró una agencia asociada (nivel 3) desde el formulario público de TDEV.',
            'agency' => $agency,
            'parentAgency' => $agency->parentAgency,
            'agent' => null,
            'generatedAt' => now()->timezone((string) config('app.timezone'))->format('d/m/Y H:i'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function emailPayloadForAgent(TdevAgent $agent): array
    {
        $type = self::resolveAgentType($agent);

        return [
            'registrationType' => $type,
            'registrationTypeLabel' => self::typeLabel($type),
            'title' => 'Nuevo agente TDEV registrado',
            'intro' => 'Se registró un agente desde el formulario público de TDEV.',
            'agency' => $agent->agency,
            'parentAgency' => $agent->agency?->parentAgency,
            'agent' => $agent,
            'generatedAt' => now()->timezone((string) config('app.timezone'))->format('d/m/Y H:i'),
        ];
    }

    public static function emailSubjectForAgency(TdevAgency $agency): string
    {
        return 'Nuevo registro TDEV · Agencia nivel 3 · '.$agency->name;
    }

    public static function emailSubjectForAgent(TdevAgent $agent): string
    {
        return 'Nuevo registro TDEV · Agente · '.$agent->full_name;
    }

    private static function location(TdevAgency $agency): string
    {
        $parts = collect([
            $agency->city?->definition,
            $agency->state?->definition,
            $agency->country?->name,
        ])->filter()->implode(', ');

        return filled($parts) ? $parts : '—';
    }

    private static function value(mixed $value): string
    {
        return filled($value) ? (string) $value : '—';
    }
}
