<?php

declare(strict_types=1);

namespace App\Support\PlanGenerators;

use App\Models\Agency;
use App\Models\Agent;
use Filament\Forms\Components\Select;
use Illuminate\Database\Eloquent\Builder;

/**
 * Buscador del campo Agente de una cotización. Una sola caja consulta agentes
 * y agencias activos, sin precargar los listados, y el plan guarda el nombre.
 * Si el nombre no existe, se puede usar tal cual: no se inserta en esas tablas.
 */
final class PlanGeneratorAgentLookup
{
    public const MIN_SEARCH_LENGTH = 2;

    public const RESULT_LIMIT = 15;

    private const PER_SOURCE_WHEN_BOTH_MATCH = 8;

    private const SEARCH_DEBOUNCE_MS = 350;

    public static function field(): Select
    {
        return Select::make('agent_name')
            ->label('Agente')
            ->required()
            ->native(false)
            ->searchable()
            ->preload(false)
            ->optionsLimit(self::RESULT_LIMIT + 1)
            ->searchDebounce(self::SEARCH_DEBOUNCE_MS)
            ->searchPrompt('Escribe al menos 2 letras')
            ->searchingMessage('Buscando…')
            ->loadingMessage('Buscando…')
            ->noSearchResultsMessage('No hay agentes ni agencias con ese dato')
            ->placeholder('Nombre o código')
            ->helperText('Busca un agente o una agencia. Si no aparece, elige «Usar». Ese nombre queda solo en la cotización.')
            ->getSearchResultsUsing(fn (?string $search): array => self::search((string) $search))
            ->getOptionLabelUsing(fn (?string $value): ?string => self::labelForState($value))
            ->dehydrateStateUsing(fn (?string $state): ?string => self::nameForStorage($state));
    }

    /**
     * @return array<string, string>
     */
    public static function search(string $term): array
    {
        $term = self::normalizeTerm($term);

        if (mb_strlen($term) < self::MIN_SEARCH_LENGTH) {
            return [];
        }

        $like = '%'.self::escapeLike($term).'%';

        $agents = self::matchingAgents($like);
        $agencies = self::matchingAgencies($like);

        if ($agents !== [] && $agencies !== []) {
            $agents = array_slice($agents, 0, self::PER_SOURCE_WHEN_BOTH_MATCH, true);
        }

        $options = $agents;

        foreach ($agencies as $key => $label) {
            if (count($options) >= self::RESULT_LIMIT) {
                break;
            }

            $options[$key] = $label;
        }

        $manualName = mb_strtoupper($term);
        $options[self::manualKey($manualName)] = 'Usar «'.$manualName.'»';

        return $options;
    }

    public static function labelForState(?string $state): ?string
    {
        $state = trim((string) $state);

        if ($state === '') {
            return null;
        }

        $manualName = self::nameFromManualKey($state);

        if ($manualName !== null) {
            return $manualName;
        }

        if (self::isManualKey($state)) {
            return null;
        }

        $record = self::recordFromKey($state);

        if ($record === null) {
            return self::isRecordKey($state) ? null : $state;
        }

        return self::formatOption($record['kind'], $record['name'], $record['code']);
    }

    public static function nameForStorage(?string $state): ?string
    {
        $state = trim((string) $state);

        if ($state === '') {
            return null;
        }

        $manualName = self::nameFromManualKey($state);

        if ($manualName !== null) {
            return $manualName;
        }

        if (self::isManualKey($state)) {
            return null;
        }

        if (! self::isRecordKey($state)) {
            return mb_strtoupper($state);
        }

        $record = self::recordFromKey($state);

        if ($record === null) {
            return null;
        }

        return mb_strtoupper($record['name']);
    }

    /**
     * @return array<string, string>
     */
    private static function matchingAgents(string $like): array
    {
        $options = [];

        $agents = Agent::query()
            ->where('status', 'ACTIVO')
            ->where('name', '!=', '')
            ->where(function (Builder $query) use ($like): void {
                $query->where('name', 'like', $like)
                    ->orWhere('code_agent', 'like', $like)
                    ->orWhere('rif', 'like', $like);
            })
            ->orderBy('name')
            ->limit(self::RESULT_LIMIT)
            ->get(['id', 'name', 'code_agent']);

        foreach ($agents as $agent) {
            $name = trim((string) $agent->name);

            if ($name === '') {
                continue;
            }

            $options['agent:'.$agent->getKey()] = self::formatOption('Agente', $name, $agent->code_agent);
        }

        return $options;
    }

    /**
     * @return array<string, string>
     */
    private static function matchingAgencies(string $like): array
    {
        $options = [];

        $agencies = Agency::query()
            ->where('status', 'ACTIVO')
            ->where('name_corporative', '!=', '')
            ->where(function (Builder $query) use ($like): void {
                $query->where('name_corporative', 'like', $like)
                    ->orWhere('code', 'like', $like)
                    ->orWhere('rif', 'like', $like);
            })
            ->orderBy('name_corporative')
            ->limit(self::RESULT_LIMIT)
            ->get(['id', 'name_corporative', 'code']);

        foreach ($agencies as $agency) {
            $name = trim((string) $agency->name_corporative);

            if ($name === '') {
                continue;
            }

            $code = $agency->code;
            $options['agency:'.$agency->getKey()] = self::formatOption('Agencia', $name, $code);
        }

        return $options;
    }

    /**
     * @return array{kind: string, name: string, code: ?string}|null
     */
    private static function recordFromKey(string $state): ?array
    {
        if (preg_match('/^(agent|agency):(\d+)$/', $state, $matches) !== 1) {
            return null;
        }

        $id = (int) $matches[2];

        if ($id < 1) {
            return null;
        }

        if ($matches[1] === 'agent') {
            $agent = Agent::query()->whereKey($id)->first(['id', 'name', 'code_agent']);

            if ($agent === null || trim((string) $agent->name) === '') {
                return null;
            }

            return [
                'kind' => 'Agente',
                'name' => trim((string) $agent->name),
                'code' => filled($agent->code_agent) ? (string) $agent->code_agent : null,
            ];
        }

        $agency = Agency::query()->whereKey($id)->first(['id', 'name_corporative', 'code']);

        if ($agency === null || trim((string) $agency->name_corporative) === '') {
            return null;
        }

        return [
            'kind' => 'Agencia',
            'name' => trim((string) $agency->name_corporative),
            'code' => filled($agency->code) ? (string) $agency->code : null,
        ];
    }

    private static function isRecordKey(string $state): bool
    {
        return preg_match('/^(agent|agency):\d+$/', $state) === 1;
    }

    private static function manualKey(string $name): string
    {
        return 'manual:'.rawurlencode($name);
    }

    private static function isManualKey(string $state): bool
    {
        return str_starts_with($state, 'manual:');
    }

    private static function nameFromManualKey(string $state): ?string
    {
        if (! self::isManualKey($state)) {
            return null;
        }

        $name = self::normalizeTerm(rawurldecode(substr($state, strlen('manual:'))));

        if ($name === '') {
            return null;
        }

        return mb_strtoupper($name);
    }

    private static function formatOption(string $kind, string $name, mixed $code): string
    {
        $label = mb_strtoupper(trim($name)).' · '.$kind;
        $code = trim((string) $code);

        if ($code === '') {
            return $label;
        }

        return $label.' · '.mb_strtoupper($code);
    }

    private static function normalizeTerm(string $term): string
    {
        $term = trim(preg_replace('/\s+/u', ' ', $term) ?? '');

        return mb_substr($term, 0, 80);
    }

    private static function escapeLike(string $term): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term);
    }
}
