<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\ProspectAgents;

final class ProspectAgentLabels
{
    /**
     * @return array<string, string>
     */
    public static function typeOptions(): array
    {
        return [
            'agencia-corretaje' => 'Agencia (corretaje)',
            'agente-corretaje' => 'Agente (Corretaje)',
            'agencia-viajes' => 'Agencia de Viajes',
            'mayorista-viajes' => 'Mayorista de Viajes',
            'freelance' => 'Freelance',
            'asesor-exclusivo' => 'Asesor exclusivo',
            'cliente-individual' => 'Cliente Individual',
            'cliente-corporativo' => 'Cliente Corporativo',
            'ejecutivo' => 'Ejecutivo',
            'otro' => 'Otro',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function statusOptions(): array
    {
        return [
            'captación' => 'Captación',
            'contacto-inicial' => 'Contacto inicial',
            'prospecto' => 'Prospecto',
            'aliado-activo' => 'Aliado Activo',
            'inactivo' => 'Inactivo',
            'en-proceso' => 'En proceso',
            'en-negociación' => 'En negociación',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function referenceOptions(): array
    {
        return [
            'directiva-TDG' => 'Directiva TDG',
            'gerencia-de-negocios' => 'Gerencia de Negocios',
            'whatsapp-comercial' => 'Whatsapp Comercial',
            'redes-sociales' => 'Redes sociales',
            'tercero' => 'Tercero',
            'otro' => 'Otro',
        ];
    }

    public static function typeLabel(?string $key): string
    {
        if ($key === null || $key === '') {
            return '—';
        }

        return self::typeOptions()[$key] ?? $key;
    }

    public static function statusLabel(?string $key): string
    {
        if ($key === null || $key === '') {
            return '—';
        }

        return self::statusOptions()[$key] ?? $key;
    }

    public static function referenceLabel(?string $key): string
    {
        if ($key === null || $key === '') {
            return '—';
        }

        return self::referenceOptions()[$key] ?? $key;
    }

    public static function statusColor(?string $state): string
    {
        return match ($state) {
            'captación' => 'gray',
            'contacto-inicial' => 'info',
            'prospecto' => 'warning',
            'aliado-activo' => 'success',
            'inactivo' => 'danger',
            'en-proceso' => 'primary',
            'en-negociación' => 'warning',
            default => 'gray',
        };
    }
}
