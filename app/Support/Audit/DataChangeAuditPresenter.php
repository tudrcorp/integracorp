<?php

declare(strict_types=1);

namespace App\Support\Audit;

use App\Models\Agency;
use App\Models\Agent;
use App\Models\Coverage;
use App\Models\DataChangeAudit;
use App\Models\Plan;
use App\Models\UserActivityEvent;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Lectura humana de un movimiento auditado: nombres de agente, agencia, plan y cobertura en
 * lugar de ids, resumen de campos, origen legible y el contexto (misma operación, actividad
 * del usuario en ese momento, historia del registro).
 */
final class DataChangeAuditPresenter
{
    /** @var array<string, array<string, string>> */
    private static array $lookups = [];

    /**
     * Valor listo para leer: «Homefamily Care (#557)» en lugar de «557».
     */
    public static function value(string $field, ?string $value): string
    {
        if ($value === null || $value === '') {
            return '(vacío)';
        }

        $resolved = match ($field) {
            'agent_id', 'owner_agent', 'referidor_agent_id' => self::lookup('agent', $value, fn (array $ids): array => Agent::query()->whereIn('id', $ids)->pluck('name', 'id')->map(fn ($name): string => (string) $name)->all()),
            'code_agency', 'owner_code' => self::lookup('agency', $value, fn (array $codes): array => Agency::query()->whereIn('code', $codes)->pluck('name_corporative', 'code')->map(fn ($name): string => (string) $name)->all()),
            'agency_id', 'referidor_id' => self::lookup('agency_id', $value, fn (array $ids): array => Agency::query()->whereIn('id', $ids)->get(['id', 'code', 'name_corporative'])->mapWithKeys(fn (Agency $agency): array => [$agency->id => trim($agency->code.' · '.$agency->name_corporative)])->all()),
            'plan_id' => self::lookup('plan', $value, fn (array $ids): array => Plan::query()->whereIn('id', $ids)->pluck('description', 'id')->map(fn ($name): string => (string) $name)->all()),
            'coverage_id' => self::lookup('coverage', $value, fn (array $ids): array => Coverage::query()->whereIn('id', $ids)->pluck('price', 'id')->map(fn ($price): string => 'US$ '.number_format((float) $price, 0, ',', '.'))->all()),
            default => null,
        };

        return $resolved !== null && $resolved !== '' ? $resolved.($resolved === $value ? '' : ' ('.$value.')') : $value;
    }

    /**
     * «Agente, Agencia y 2 más».
     *
     * @param  array<string, mixed>  $changes
     */
    public static function fieldsSummary(array $changes, int $limit = 3): string
    {
        $labels = array_map(fn (string $field): string => DataChangeAuditRegistry::fieldLabel($field), array_keys($changes));

        if (count($labels) <= $limit) {
            return implode(', ', $labels);
        }

        return implode(', ', array_slice($labels, 0, $limit)).' y '.(count($labels) - $limit).' más';
    }

    public static function origin(DataChangeAudit $audit): string
    {
        return match ($audit->source) {
            'cola' => 'Proceso en cola · '.($audit->origin ?: 'tarea'),
            'consola' => 'Comando del sistema · '.($audit->origin ?: 'consola'),
            default => trim((self::panelLabel($audit->panel) ?? 'Web').' · '.($audit->origin ?? '')),
        };
    }

    public static function panelLabel(?string $panel): ?string
    {
        return match ($panel) {
            null, '' => null,
            'business' => 'Negocios',
            'administration' => 'Administración',
            'operations' => 'Operaciones',
            'admin' => 'Admin',
            'agents' => 'Agentes',
            'master' => 'Agencia master',
            'general' => 'Agencia general',
            'marketing' => 'Marketing',
            'telemedicina' => 'Telemedicina',
            'metrics' => 'Métricas',
            'projects' => 'Proyectos',
            default => ucfirst($panel),
        };
    }

    /**
     * Lo que hizo el usuario en la pantalla justo antes y después del cambio (botones,
     * acciones, páginas): explica con qué acción se produjo.
     *
     * @return list<array{at: string, label: string, panel: ?string}>
     */
    public static function nearbyActivity(DataChangeAudit $audit, int $seconds = 90): array
    {
        if ($audit->user_id === null || $audit->created_at === null) {
            return [];
        }

        try {
            if (! Schema::hasTable('user_activity_events')) {
                return [];
            }

            return UserActivityEvent::query()
                ->where('user_id', $audit->user_id)
                ->whereBetween('occurred_at', [$audit->created_at->subSeconds($seconds), $audit->created_at->addSeconds(20)])
                ->whereIn('type', ['page', 'action'])
                ->orderBy('occurred_at')
                ->limit(25)
                ->get(['occurred_at', 'label', 'panel'])
                ->map(fn (UserActivityEvent $event): array => [
                    'at' => (string) $event->occurred_at?->format('h:i:s A'),
                    'label' => (string) $event->label,
                    'panel' => $event->panel,
                ])
                ->all();
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @param  callable(list<string>): array<string|int, string>  $resolver
     */
    private static function lookup(string $kind, string $value, callable $resolver): ?string
    {
        if (! array_key_exists($value, self::$lookups[$kind] ?? [])) {
            try {
                $found = $resolver([$value]);
            } catch (Throwable) {
                $found = [];
            }

            self::$lookups[$kind][$value] = (string) ($found[$value] ?? $found[(int) $value] ?? '');
        }

        return self::$lookups[$kind][$value] !== '' ? self::$lookups[$kind][$value] : null;
    }
}
