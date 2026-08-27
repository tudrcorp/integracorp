<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Affiliate;
use App\Models\Affiliation;
use App\Support\Affiliations\MergeIndividualAffiliationsException;
use App\Support\Affiliations\MergeIndividualAffiliationsRequest;
use App\Support\Affiliations\MergeIndividualAffiliationsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;

class MergeIndividualAffiliationsCommand extends Command
{
    protected $signature = 'affiliations:merge-individual
                            {target : Código de la afiliación que sobrevive}
                            {sources* : Códigos de las afiliaciones a unir (pasan a EXCLUIDO)}
                            {--titular= : Cédula del titular resultante}
                            {--map=* : Cédula:PARENTESCO (ej. 12345678:ESPOSA)}
                            {--reason= : Motivo obligatorio para ejecutar}
                            {--execute : Sin este flag solo muestra la vista previa}';

    protected $description = 'Unifica afiliaciones individuales en un solo grupo familiar. Por defecto solo hace dry-run.';

    public function handle(MergeIndividualAffiliationsService $service): int
    {
        $targetCode = trim((string) $this->argument('target'));
        $sourceCodes = array_values(array_filter(array_map(
            static fn (mixed $code): string => trim((string) $code),
            (array) $this->argument('sources'),
        )));

        $target = Affiliation::query()->where('code', $targetCode)->first();

        if (! $target instanceof Affiliation) {
            $this->error('No existe la afiliación destino '.$targetCode.'.');

            return self::FAILURE;
        }

        $sources = Affiliation::query()->whereIn('code', $sourceCodes)->get();

        if ($sources->count() !== count($sourceCodes)) {
            $found = $sources->pluck('code')->all();
            $missing = array_diff($sourceCodes, $found);
            $this->error('No se encontraron: '.implode(', ', $missing));

            return self::FAILURE;
        }

        $affiliates = Affiliate::query()
            ->whereIn('affiliation_id', [$target->id, ...$sources->modelKeys()])
            ->get();

        $maps = $this->parsedMaps();
        $titularCi = MergeIndividualAffiliationsService::normalizeIdentification((string) $this->option('titular'));
        $titular = $this->resolveTitular($affiliates, $target, $titularCi);

        if (! $titular instanceof Affiliate) {
            $this->error('Indique --titular= con la cédula de quien será el titular del grupo.');

            return self::FAILURE;
        }

        $relationships = $this->relationshipsFor($affiliates, $titular, $maps);

        $user = Auth::user();
        $request = new MergeIndividualAffiliationsRequest(
            targetAffiliationId: (int) $target->id,
            sourceAffiliationIds: $sources->modelKeys(),
            titularAffiliateId: (int) $titular->id,
            relationships: $relationships,
            reason: (string) ($this->option('reason') ?: 'Vista previa por consola'),
            actorName: (string) ($user?->name ?? 'SISTEMA'),
            actorUserId: $user?->id !== null ? (int) $user->id : null,
        );

        try {
            $preview = $service->preview($request);
        } catch (MergeIndividualAffiliationsException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->renderPreview($preview);

        if ($preview->blockers !== []) {
            $this->error('Hay bloqueos. No se escribió nada.');

            return self::FAILURE;
        }

        if (! $this->option('execute')) {
            $this->info('Dry-run: no se escribió nada. Para ejecutar agregue --execute --reason="..."');

            return self::SUCCESS;
        }

        $reason = trim((string) $this->option('reason'));
        if ($reason === '') {
            $this->error('Para ejecutar debe indicar --reason="..."');

            return self::FAILURE;
        }

        try {
            $result = $service->execute(new MergeIndividualAffiliationsRequest(
                targetAffiliationId: $request->targetAffiliationId,
                sourceAffiliationIds: $request->sourceAffiliationIds,
                titularAffiliateId: $request->titularAffiliateId,
                relationships: $request->relationships,
                reason: $reason,
                actorName: $request->actorName,
                actorUserId: $request->actorUserId,
            ));
        } catch (MergeIndividualAffiliationsException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Unificado en '.$result->targetCode.' con '.$result->newFamilyMembers.' persona(s).');
        $this->info('Excluidas: '.implode(', ', $result->excludedCodes));
        $this->info('Cuotas canceladas: '.$result->cancelledCollections.'. Carnets en cola.');

        return self::SUCCESS;
    }

    /**
     * @return array<string, string>
     */
    private function parsedMaps(): array
    {
        $maps = [];

        foreach ((array) $this->option('map') as $item) {
            $parts = explode(':', (string) $item, 2);

            if (count($parts) !== 2) {
                continue;
            }

            $ci = MergeIndividualAffiliationsService::normalizeIdentification($parts[0]);
            $relationship = MergeIndividualAffiliationsService::normalizeRelationship($parts[1]);

            if ($ci !== '' && $relationship !== '') {
                $maps[$ci] = $relationship;
            }
        }

        return $maps;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Affiliate>  $affiliates
     */
    private function resolveTitular($affiliates, Affiliation $target, string $titularCi): ?Affiliate
    {
        if ($titularCi !== '') {
            return $affiliates->first(
                fn (Affiliate $affiliate): bool => MergeIndividualAffiliationsService::normalizeIdentification((string) $affiliate->nro_identificacion) === $titularCi
            );
        }

        return $affiliates->first(
            function (Affiliate $affiliate) use ($target): bool {
                return (int) $affiliate->affiliation_id === (int) $target->id
                    && MergeIndividualAffiliationsService::normalizeRelationship((string) $affiliate->relationship) === 'TITULAR';
            }
        ) ?? $affiliates->first(
            fn (Affiliate $affiliate): bool => MergeIndividualAffiliationsService::normalizeRelationship((string) $affiliate->relationship) === 'TITULAR'
        );
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Affiliate>  $affiliates
     * @param  array<string, string>  $maps
     * @return array<int, string>
     */
    private function relationshipsFor($affiliates, Affiliate $titular, array $maps): array
    {
        $relationships = [];

        foreach ($affiliates as $affiliate) {
            $ci = MergeIndividualAffiliationsService::normalizeIdentification((string) $affiliate->nro_identificacion);

            if ((int) $affiliate->id === (int) $titular->id) {
                $relationships[(int) $affiliate->id] = 'TITULAR';

                continue;
            }

            if (isset($maps[$ci])) {
                $relationships[(int) $affiliate->id] = $maps[$ci];

                continue;
            }

            $current = MergeIndividualAffiliationsService::normalizeRelationship((string) $affiliate->relationship);
            $relationships[(int) $affiliate->id] = $current === 'TITULAR' ? 'OTRO' : ($current !== '' ? $current : 'OTRO');
        }

        return $relationships;
    }

    private function renderPreview(\App\Support\Affiliations\MergeIndividualAffiliationsPreview $preview): void
    {
        if ($preview->blockers !== []) {
            $this->error('Bloqueos:');
            foreach ($preview->blockers as $blocker) {
                $this->line(' - '.$blocker);
            }
        }

        if ($preview->warnings !== []) {
            $this->warn('Advertencias:');
            foreach ($preview->warnings as $warning) {
                $this->line(' - '.$warning);
            }
        }

        $this->table(
            ['Destino', 'Titular', 'Estatus', 'Tarifa actual', 'Tarifa unificada', 'Personas'],
            [[
                $preview->target['code'],
                $preview->target['titular'],
                $preview->target['status'],
                number_format($preview->target['fee_anual'], 2, ',', '.'),
                number_format($preview->newFeeAnual, 2, ',', '.'),
                (string) $preview->newFamilyMembers,
            ]],
        );

        $this->table(
            ['Persona', 'Cédula', 'Origen', 'Parentesco', 'Tarifa'],
            array_map(static fn (array $member): array => [
                $member['name'],
                $member['identification'],
                $member['from_code'],
                $member['old_relationship'].' → '.$member['new_relationship'],
                $member['fee_after'] === null ? '—' : number_format((float) $member['fee_after'], 2, ',', '.'),
            ], $preview->members),
        );
    }
}
