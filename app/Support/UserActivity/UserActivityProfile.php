<?php

declare(strict_types=1);

namespace App\Support\UserActivity;

use App\Models\User;
use App\Support\AffiliationCorporates\CorporatePaymentFrequencyChangeRecipients;

/**
 * Qué tipo de usuario es, para agrupar y filtrar: interno, agente, agencia,
 * proveedor o médico. Sale de los indicadores del propio usuario.
 */
final class UserActivityProfile
{
    /** @var array<string, string> */
    public const TYPES = [
        'internal' => 'Interno',
        'agent' => 'Agente',
        'agency' => 'Agencia',
        'provider' => 'Proveedor',
        'doctor' => 'Médico',
        'other' => 'Otro',
    ];

    /** Columnas de `users` que hacen falta para clasificar (sin traer la fila entera). */
    public const COLUMNS = ['id', 'name', 'email', 'status', 'departament', 'is_agent', 'is_subagent', 'is_agency', 'agency_type', 'code_agency', 'is_doctor', 'supplier_id', 'is_proveedor_amd'];

    /**
     * @return array{type: string, label: string, detail: string}
     */
    public static function classify(?User $user): array
    {
        if ($user === null) {
            return ['type' => 'other', 'label' => self::TYPES['other'], 'detail' => ''];
        }

        $departments = CorporatePaymentFrequencyChangeRecipients::departmentsOf($user);

        $type = match (true) {
            (bool) $user->is_agency || filled($user->agency_type) => 'agency',
            (bool) $user->is_agent || (bool) $user->is_subagent => 'agent',
            filled($user->supplier_id) || (bool) $user->is_proveedor_amd => 'provider',
            (bool) $user->is_doctor => 'doctor',
            $departments !== [] || str_ends_with(mb_strtolower((string) $user->email), '@tudrencasa.com') => 'internal',
            default => 'other',
        };

        $detail = match ($type) {
            'internal' => implode(' · ', array_map(static fn (string $department): string => mb_convert_case($department, MB_CASE_TITLE, 'UTF-8'), $departments)),
            'agency' => trim(($user->agency_type ? 'Agencia '.mb_convert_case((string) $user->agency_type, MB_CASE_TITLE, 'UTF-8') : '').($user->code_agency ? ' · '.$user->code_agency : ''), ' ·'),
            'agent' => (string) ($user->code_agency ?? ''),
            default => '',
        };

        return ['type' => $type, 'label' => self::TYPES[$type], 'detail' => $detail];
    }
}
