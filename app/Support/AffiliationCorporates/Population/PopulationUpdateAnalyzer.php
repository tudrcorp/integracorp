<?php

declare(strict_types=1);

namespace App\Support\AffiliationCorporates\Population;

use App\Enums\PersonIdentitySource;
use App\Models\AffiliateCorporate;
use App\Models\AffiliationCorporate;
use App\Models\CorporatePopulationUpdate;
use App\Models\CorporatePopulationUpdateItem as Item;
use App\Models\User;
use App\Support\AffiliationCorporates\CorporateAffiliatePlanSynchronizer;
use App\Support\Affiliations\PersonIdentity\DuplicatePersonIdentityException;
use App\Support\Affiliations\PersonIdentity\PersonIdentityGuard;
use App\Support\Affiliations\PersonIdentity\PersonIdentityRecord;
use App\Support\SecurityAudit;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Analiza una carga: lee el archivo, cruza con la afiliación y guarda la
 * propuesta fila por fila. No cambia ningún afiliado.
 */
final class PopulationUpdateAnalyzer
{
    /** Por encima de esto, aprobar exige una confirmación explícita. */
    public const MASS_DEACTIVATION_RATIO = 0.15;

    public const MASS_DEACTIVATION_MIN = 20;

    public static function analyze(CorporatePopulationUpdate $update): CorporatePopulationUpdate
    {
        try {
            $owner = AffiliationCorporate::query()->findOrFail($update->affiliation_corporate_id);
            $path = Storage::disk($update->file_disk)->path($update->file_path);
            $file = PopulationFileReader::read($path, $update->file_format);
            $mapping = PopulationColumnMapping::validate((array) $update->mapping, $file['headers']);

            $persons = array_map(
                static fn (array $row): array => PopulationPerson::fromRow((int) $row['row'], $row['values'], $mapping),
                $file['rows'],
            );

            $affiliates = AffiliateCorporate::query()
                ->where('affiliation_corporate_id', $owner->getKey())
                ->get(['id', 'first_name', 'last_name', 'nro_identificacion', 'birth_date', 'age', 'status', 'plan_id', 'coverage_id', 'fee', 'updated_at']);

            $items = PopulationMatcher::match($persons, $affiliates);
            $items = self::resolvePlans($owner, $items);
            $items = self::blockOpenActivity($owner, $items, $affiliates);
            $items = self::blockPeopleActiveElsewhere($owner, $items);
            $items = self::observe($items, $affiliates->keyBy('id'));

            $summary = self::summary($items, $file['rows'], $affiliates);

            DB::transaction(static function () use ($update, $items, $summary): void {
                Item::query()->where('corporate_population_update_id', $update->getKey())->delete();

                $now = now();
                foreach (array_chunk($items, 500) as $chunk) {
                    Item::query()->insert(array_map(static fn (array $item): array => [
                        'corporate_population_update_id' => $update->getKey(),
                        'action' => $item['action'],
                        'selectable' => $item['selectable'],
                        'selected' => $item['selected'],
                        'file_row' => $item['file_row'],
                        'file_data' => $item['file_data'] === null ? null : json_encode($item['file_data'], JSON_UNESCAPED_UNICODE),
                        'affiliate_corporate_id' => $item['affiliate_corporate_id'],
                        'match_via' => $item['match_via'],
                        'reason' => $item['reason'],
                        'details' => $item['details'] === [] ? null : json_encode($item['details'], JSON_UNESCAPED_UNICODE),
                        'apply_status' => Item::APPLY_PENDING,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ], $chunk));
                }

                $update->forceFill([
                    'status' => CorporatePopulationUpdate::STATUS_ANALYZED,
                    'summary' => $summary,
                    'error' => null,
                    'analyzed_at' => now(),
                ])->save();
            });

            SecurityAudit::log('AUDIT_CORPORATE_POPULATION_ANALYZED', 'business.corporate-population.analyze', [
                'update_id' => $update->getKey(),
                'affiliation_code' => $owner->code,
                'file' => $update->file_name,
                'counts' => $summary['counts'],
            ], self::analyst($update));

            self::notifyAnalyst($update->fresh(), $owner);
        } catch (Throwable $exception) {
            report($exception);

            $update->forceFill([
                'status' => CorporatePopulationUpdate::STATUS_FAILED,
                'error' => $exception instanceof \RuntimeException || $exception instanceof \InvalidArgumentException
                    ? $exception->getMessage()
                    : 'Error inesperado al analizar el archivo. No se cambió nada.',
            ])->save();

            $analyst = self::analyst($update);
            if ($analyst !== null) {
                Notification::make()
                    ->title('No se pudo analizar el archivo de población')
                    ->body((string) $update->error)
                    ->danger()
                    ->sendToDatabase($analyst);
            }
        }

        return $update->fresh() ?? $update;
    }

    /**
     * Plan, cobertura y tarifa de cada alta, por edad. Si no se puede asignar
     * con certeza, la persona pasa a Revisar.
     *
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private static function resolvePlans(AffiliationCorporate $owner, array $items): array
    {
        $planRows = CorporateAffiliatePlanSynchronizer::planRowsFor($owner);

        foreach ($items as $i => $item) {
            if ($item['action'] !== Item::ACTION_ADD) {
                continue;
            }

            $age = PopulationPerson::ageAt($item['file_data']['birth_date']);
            $probe = new AffiliateCorporate;
            $probe->age = $age;

            $resolution = CorporateAffiliatePlanSynchronizer::resolvePlanRowForAffiliate($owner, $probe, $planRows);
            $row = $resolution['row'];

            if ($row === null && $age === null && $planRows->count() === 1) {
                $row = $planRows->first();
            }

            if ($row === null) {
                $items[$i]['action'] = Item::ACTION_REVIEW;
                $items[$i]['selectable'] = false;
                $items[$i]['selected'] = false;
                $items[$i]['reason'] = 'No existe en la afiliación, pero no se le puede asignar plan: '
                    .($planRows->isEmpty() ? 'la afiliación no tiene planes contratados.' : CorporateAffiliatePlanSynchronizer::reasonLabel((string) $resolution['reason']).'.');

                continue;
            }

            $items[$i]['details'] += [
                'plan_row_id' => (int) $row->getKey(),
                'plan_id' => (int) $row->plan_id,
                'coverage_id' => $row->coverage_id !== null ? (int) $row->coverage_id : null,
                'fee' => round((float) $row->fee, 2),
                'age' => $age,
            ];
        }

        return $items;
    }

    /**
     * Bajas de afiliados con actividad abierta: no se proponen, se bloquean.
     *
     * @param  list<array<string, mixed>>  $items
     * @param  \Illuminate\Support\Collection<int, AffiliateCorporate>  $affiliates
     * @return list<array<string, mixed>>
     */
    private static function blockOpenActivity(AffiliationCorporate $owner, array $items, $affiliates): array
    {
        $ids = [];
        foreach ($items as $item) {
            if (in_array($item['action'], [Item::ACTION_DEACTIVATE, Item::ACTION_DUPLICATE_BASE], true) && $item['affiliate_corporate_id'] !== null) {
                $ids[] = (int) $item['affiliate_corporate_id'];
            }
        }

        if ($ids === []) {
            return $items;
        }

        $open = PopulationActivityInspector::openActivityFor($owner, $affiliates->whereIn('id', $ids)->values());

        foreach ($items as $i => $item) {
            $id = (int) ($item['affiliate_corporate_id'] ?? 0);
            if (! isset($open[$id]) || ! in_array($item['action'], [Item::ACTION_DEACTIVATE, Item::ACTION_DUPLICATE_BASE], true)) {
                continue;
            }

            $items[$i]['details']['proposed_action'] = $item['action'];
            $items[$i]['details']['open_activity'] = $open[$id];
            $items[$i]['action'] = Item::ACTION_BLOCKED_OPEN_CASE;
            $items[$i]['selectable'] = false;
            $items[$i]['selected'] = false;
            $items[$i]['reason'] = 'No se da de baja: tiene actividad abierta. Debe revisarlo el equipo de Operaciones.';
        }

        return $items;
    }

    /**
     * Altas de personas que ya están vigentes en otra afiliación (individual, corporativa o Nuevos
     * Negocios) o con el nombre cargado dos veces: no se proponen, pasan a Revisar con el motivo.
     * Las personas de esta misma afiliación que el archivo da de baja no cuentan: al aplicar, las
     * bajas van primero.
     *
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private static function blockPeopleActiveElsewhere(AffiliationCorporate $owner, array $items): array
    {
        $leaving = [];
        foreach ($items as $item) {
            if (in_array($item['action'], [Item::ACTION_DEACTIVATE, Item::ACTION_DUPLICATE_BASE], true) && $item['affiliate_corporate_id'] !== null) {
                $leaving[(int) $item['affiliate_corporate_id']] = true;
            }
        }

        foreach ($items as $i => $item) {
            if ($item['action'] !== Item::ACTION_ADD || ! is_array($item['file_data'])) {
                continue;
            }

            $person = $item['file_data'];
            $probe = (new AffiliateCorporate)->forceFill([
                'affiliation_corporate_id' => $owner->getKey(),
                'first_name' => (string) ($person['first_name'] ?? ''),
                'last_name' => (string) ($person['last_name'] ?? ''),
                'nro_identificacion' => PopulationPerson::storedDocument((string) ($person['document'] ?? '')),
                'birth_date' => PopulationPerson::toStoredBirthDate($person['birth_date'] ?? null),
                'status' => 'ACTIVO',
            ]);
            $record = PersonIdentityRecord::fromModel($probe, $owner);

            $conflicts = array_values(array_filter(
                PersonIdentityGuard::conflictsFor($record),
                static fn (array $conflict): bool => ! ($conflict['existing']->source === PersonIdentitySource::Corporate
                    && isset($leaving[(int) $conflict['existing']->id])),
            ));

            $reason = match (true) {
                PersonIdentityGuard::hasRepeatedName($probe) => DuplicatePersonIdentityException::repeatedName($record)->getMessage(),
                $conflicts !== [] => (new DuplicatePersonIdentityException($conflicts))->getMessage(),
                default => null,
            };

            if ($reason === null) {
                continue;
            }

            $items[$i]['action'] = Item::ACTION_REVIEW;
            $items[$i]['selectable'] = false;
            $items[$i]['selected'] = false;
            $items[$i]['reason'] = $reason;
        }

        return $items;
    }

    /**
     * Estado de cada afiliado al analizar: al aplicar se compara, y si alguien lo
     * cambió en medio, no se aplica sobre datos viejos.
     *
     * @param  list<array<string, mixed>>  $items
     * @param  \Illuminate\Support\Collection<int, AffiliateCorporate>  $byId
     * @return list<array<string, mixed>>
     */
    private static function observe(array $items, $byId): array
    {
        foreach ($items as $i => $item) {
            $affiliate = $item['affiliate_corporate_id'] !== null ? $byId->get((int) $item['affiliate_corporate_id']) : null;
            if ($affiliate instanceof AffiliateCorporate) {
                $items[$i]['details']['observed'] = self::fingerprint($affiliate);
                $items[$i]['details']['base_name'] = trim($affiliate->first_name.' '.$affiliate->last_name);
                $items[$i]['details']['base_document'] = (string) $affiliate->nro_identificacion;
                $items[$i]['details']['base_status'] = (string) $affiliate->status;
                $items[$i]['details']['base_birth_date'] = (string) $affiliate->birth_date;
            }
        }

        return $items;
    }

    /**
     * @return array{status: string, document: string}
     */
    public static function fingerprint(AffiliateCorporate $affiliate): array
    {
        return [
            'status' => strtoupper(trim((string) $affiliate->status)),
            'document' => (string) $affiliate->nro_identificacion,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @param  list<array<string, mixed>>  $rows
     * @param  \Illuminate\Support\Collection<int, AffiliateCorporate>  $affiliates
     * @return array<string, mixed>
     */
    private static function summary(array $items, array $rows, $affiliates): array
    {
        $counts = [];
        $selected = [];

        foreach ($items as $item) {
            $counts[$item['action']] = ($counts[$item['action']] ?? 0) + 1;
            if ($item['selected']) {
                $selected[$item['action']] = ($selected[$item['action']] ?? 0) + 1;
            }
        }

        $countable = $affiliates->filter(static fn (AffiliateCorporate $a): bool => in_array(strtoupper((string) $a->status), PopulationMatcher::COUNTABLE_STATUSES, true))->count();

        return [
            'file_rows' => count($rows),
            'base_total' => $affiliates->count(),
            'base_countable' => $countable,
            'counts' => $counts,
            'selected' => $selected,
        ];
    }

    /**
     * Bajas seleccionadas que exigen confirmación explícita por su volumen.
     */
    public static function isMassDeactivation(int $deactivations, int $countable): bool
    {
        return $deactivations >= self::MASS_DEACTIVATION_MIN
            && $deactivations > (int) ceil($countable * self::MASS_DEACTIVATION_RATIO);
    }

    private static function analyst(CorporatePopulationUpdate $update): ?User
    {
        return $update->analyzed_by ? User::query()->find($update->analyzed_by) : null;
    }

    private static function notifyAnalyst(CorporatePopulationUpdate $update, AffiliationCorporate $owner): void
    {
        $analyst = self::analyst($update);
        if ($analyst === null) {
            return;
        }

        $counts = (array) ($update->summary['counts'] ?? []);
        $url = PopulationUpdateUrls::detail($update);

        Notification::make()
            ->title('Análisis listo: '.$owner->code)
            ->body(sprintf(
                'Agregar %d · Reactivar %d · Dar de baja %d · Corregir cédula %d · Revisar %d. Nada se aplicó: revise y apruebe.',
                $counts[Item::ACTION_ADD] ?? 0,
                $counts[Item::ACTION_REACTIVATE] ?? 0,
                $counts[Item::ACTION_DEACTIVATE] ?? 0,
                $counts[Item::ACTION_FIX_DOCUMENT] ?? 0,
                ($counts[Item::ACTION_REVIEW] ?? 0) + ($counts[Item::ACTION_DUPLICATE_BASE] ?? 0),
            ))
            ->success()
            ->actions($url !== null ? [Action::make('open')->label('Revisar')->url($url)->button()] : [])
            ->sendToDatabase($analyst);

        $blocked = Item::query()
            ->where('corporate_population_update_id', $update->getKey())
            ->where('action', Item::ACTION_BLOCKED_OPEN_CASE)
            ->get(['details']);

        if ($blocked->isEmpty()) {
            return;
        }

        $names = $blocked->take(8)->map(static fn (Item $item): string => (string) ($item->details['base_name'] ?? '—'))->implode(', ');

        Notification::make()
            ->title($blocked->count().' afiliado(s) con casos abiertos no se darán de baja')
            ->body('No están en el archivo de '.$owner->code.', pero tienen actividad abierta en telemedicina u operaciones: '.$names.($blocked->count() > 8 ? '…' : '').'. Deben ser revisados por el equipo de Operaciones antes de darlos de baja.')
            ->warning()
            ->persistent()
            ->actions($url !== null ? [Action::make('open')->label('Ver detalle')->url($url)->button()] : [])
            ->sendToDatabase($analyst);
    }
}
