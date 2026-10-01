<?php

namespace App\Filament\Business\Resources\AffiliationCorporates\Pages;

use App\Filament\Business\Resources\AffiliationCorporates\AffiliationCorporateResource;
use App\Models\AffiliateCorporate;
use App\Models\AffiliationCorporate;
use App\Models\AfilliationCorporatePlan;
use App\Models\Agency;
use App\Models\CorporateQuoteData;
use App\Models\DetailCorporateQuote;
use App\Models\PlanGenerator;
use App\Models\PlanGeneratorPopulation;
use App\Services\CorporateAffiliateRemovalService;
use App\Support\AffiliationAffiliateBusinessContextSynchronizer;
use App\Support\PlanGenerators\PlanGeneratorCatalogPublisher;
use App\Support\PlanGenerators\PlanGeneratorCoverageAssignment;
use App\Support\PlanGenerators\PlanGeneratorPopulationStatus;
use App\Support\PlanGenerators\PlanGeneratorPreAffiliationSession;
use App\Support\SecurityAudit;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class CreateAffiliationCorporate extends CreateRecord
{
    protected static string $resource = AffiliationCorporateResource::class;

    protected function getFormActions(): array
    {
        return [
            $this->getCreateFormAction()
                ->label('Crear Pre-Afiliación'),
            $this->getCancelFormAction(),
        ];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data = $this->publishPlanGeneratorCatalogPlan($data);

        $data['code_agency'] = $data['code_agency'] == null ? 'TDG-100' : $data['code_agency'];
        $data['agent_id'] = $data['agent_id'] == null ? null : $data['agent_id'];

        if ($data['code_agency'] != 'TDG-100') {
            $data['owner_code'] = Agency::where('code', $data['code_agency'])->first()->owner_code;
        } else {
            $data['owner_code'] = 'TDG-100';
        }

        return $data;
    }

    /**
     * No se crea la afiliación mientras el padrón del plan generado siga
     * importándose o tenga a alguien fuera de una cobertura que le corresponda:
     * `afterCreateFromPlanGenerator()` copia la población a
     * `affiliate_corporates` con su plan y su tarifa, y hacerlo a medias dejaría
     * afiliados fuera o mal cobrados sin que nadie se entere. La página de
     * población ya deshabilita el botón; esta guarda cubre a quien entre por
     * URL directa o cambie asignaciones en otra pestaña.
     *
     * Se revierte la transacción: el catálogo ya se publicó en
     * `mutateFormDataBeforeCreate()` y no debe quedar a medias.
     */
    protected function beforeCreate(): void
    {
        $plan = $this->planGeneratorFromSession();

        if ($plan === null) {
            return;
        }

        $reason = PlanGeneratorPopulationStatus::blockedReason($plan);

        // Los totales del formulario salen de la foto que se tomó al pulsar
        // «Continuar». Si las asignaciones cambiaron después, esos montos ya no
        // son los de la población real.
        if ($reason === null
            && PlanGeneratorPreAffiliationSession::assignmentSignature() !== PlanGeneratorCoverageAssignment::report($plan)['signature']) {
            $reason = 'Las coberturas del padrón cambiaron después de continuar. Vuelva a «Población de la pre-afiliación» y pulse «Continuar a la pre-afiliación» para recalcular los montos.';
        }

        if ($reason === null) {
            return;
        }

        Notification::make()
            ->title(str_starts_with($reason, PlanGeneratorCoverageAssignment::PLAN_FAILURE_PREFIX)
                ? 'Falla en la creación del plan'
                : 'La población todavía no está lista')
            ->body($reason)
            ->warning()
            ->persistent()
            ->send();

        $this->halt(shouldRollbackDatabaseTransaction: true);
    }

    /**
     * Publica la matriz del plan generado en el catálogo antes de crear la
     * afiliación.
     *
     * Sin esto las filas de `afilliation_corporate_plans` quedaban con
     * `plan_id`, `coverage_id` y `age_range_id` en cero, y las columnas Plan,
     * Cobertura y Rango de Edad salían vacías: el plan que el analista armó en
     * la cotización no existía en `plans`.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function publishPlanGeneratorCatalogPlan(array $data): array
    {
        $plan = $this->planGeneratorFromSession();

        if ($plan === null) {
            return $data;
        }

        // `affiliation_corporates` no tiene `plan_id` ni `coverage_id`: el plan,
        // la cobertura y el rango viven en `afilliation_corporate_plans`, que es
        // la tabla de «Plan(es) Afiliado(s)». Acá solo se publica el catálogo y
        // se guardan las referencias para `afterCreateFromPlanGenerator()`.
        $this->planGeneratorCatalog = PlanGeneratorCatalogPublisher::publish($plan, Auth::user()?->name);

        return $data;
    }

    /**
     * Referencias del catálogo publicadas en `mutateFormDataBeforeCreate()`.
     *
     * @var array{plan: \App\Models\Plan, coverage_ids: array<string, int>, age_range_ids: array<string, int>}|null
     */
    private ?array $planGeneratorCatalog = null;

    private function planGeneratorFromSession(): ?PlanGenerator
    {
        $payload = PlanGeneratorPreAffiliationSession::get();

        if (! is_array($payload) || ($payload['type'] ?? null) !== PlanGeneratorPreAffiliationSession::TYPE_CORPORATE) {
            return null;
        }

        return PlanGenerator::query()->find($payload['plan_generator_id'] ?? null);
    }

    protected function afterCreate(): void
    {
        // Fuera del try de abajo: ese bloque registra el error y deja la
        // afiliación creada sin afiliados. El generador revierte todo.
        if (PlanGeneratorPreAffiliationSession::isActive()) {
            $this->afterCreateFromPlanGenerator($this->getRecord());

            return;
        }

        try {

            $record = $this->getRecord();

            /**
             * Actualizacion de la cotizacion
             * Se cambia el estatus de la cobertura de la cotizacion que selecciono el cliente
             * ----------------------------------------------------------------------------------------------------
             */
            $quote = DetailCorporateQuote::select('coverage_id', 'status', 'id')->where('coverage_id', $record->coverage_id)->firstOrFail();
            $quote->status = 'APROBADA';
            $quote->save();

            /**
             * Recupero los planes de afiliados seleccionados por el agente o la agencia
             * en la vista de cotizaciones corporativas
             * ----------------------------------------------------------------------------------------------------
             */
            $data_records = session()->get('data_records');

            for ($i = 0; $i < count($data_records); $i++) {

                /** Guardar los datos en la tabla de afiliados */
                $detailsAfiliationPlans = AfilliationCorporatePlan::create([
                    'affiliation_corporate_id' => $record->id,
                    'code_affiliation' => $record->code,
                    'plan_id' => $data_records[$i]['plan_id'],
                    'coverage_id' => $data_records[$i]['coverage_id'],
                    'age_range_id' => $data_records[$i]['age_range_id'],
                    'total_persons' => $data_records[$i]['total_persons'],
                    'payment_frequency' => $record->payment_frequency,
                    'fee' => $data_records[$i]['fee'],
                    'subtotal_anual' => $data_records[$i]['subtotal_anual'],
                    'subtotal_quarterly' => $data_records[$i]['subtotal_quarterly'],
                    'subtotal_biannual' => $data_records[$i]['subtotal_biannual'],
                    'subtotal_monthly' => $data_records[$i]['subtotal_monthly'],
                    'status' => 'PRE-AFILIADO',
                    'created_by' => Auth::user()->name,
                ]);
            }

            // elimino la variable de sesion para evitar sobrecargar dicho contenedor
            session()->forget('data_records');

            // Cargamos los afiliados que son los que se importaron al momento de realizar la cotizacion
            $data_afiliados = CorporateQuoteData::where('corporate_quote_id', $record->corporate_quote_id)->get()->toArray();
            // dd($data_afiliados);
            for ($i = 0; $i < count($data_afiliados); $i++) {
                $afiliados_corporativos = new AffiliateCorporate;
                $afiliados_corporativos->last_name = $data_afiliados[$i]['last_name'];
                $afiliados_corporativos->first_name = $data_afiliados[$i]['first_name'];
                $afiliados_corporativos->nro_identificacion = $data_afiliados[$i]['nro_identificacion'];
                $afiliados_corporativos->birth_date = $data_afiliados[$i]['birth_date'];
                $afiliados_corporativos->age = $data_afiliados[$i]['age'];
                $afiliados_corporativos->sex = $data_afiliados[$i]['sex'];
                $afiliados_corporativos->phone = $data_afiliados[$i]['phone'];
                $afiliados_corporativos->email = $data_afiliados[$i]['email'];
                $afiliados_corporativos->condition_medical = $data_afiliados[$i]['condition_medical'];
                $afiliados_corporativos->initial_date = $data_afiliados[$i]['initial_date'];
                $afiliados_corporativos->position_company = $data_afiliados[$i]['position_company'];
                $afiliados_corporativos->address = $data_afiliados[$i]['address'];
                $afiliados_corporativos->full_name_emergency = $data_afiliados[$i]['full_name_emergency'];
                $afiliados_corporativos->phone_emergency = $data_afiliados[$i]['phone_emergency'];
                $afiliados_corporativos->affiliation_corporate_id = $record->id;
                $afiliados_corporativos->status = 'PRE-APROBADA';
                $afiliados_corporativos->save();

            }

            // Actualizamos la cantidad de personas afiliadas
            $record->poblation = count($data_afiliados);
            $record->save();

        } catch (\Throwable $th) {
            Log::error($th->getMessage());
            Notification::make()
                ->title('Error al crear la afiliación corporativa')
                ->body($th->getMessage())
                ->danger()
                ->send();
        }
    }

    private function afterCreateFromPlanGenerator(AffiliationCorporate $record): void
    {
        $plan = $this->planGeneratorFromSession();
        $catalog = $this->planGeneratorCatalog;

        if ($plan === null || $catalog === null) {
            $this->createAggregatedPlanGeneratorRows($record);

            return;
        }

        try {
            $placements = PlanGeneratorCoverageAssignment::affiliationPlacements($plan, $catalog);

            foreach ($placements['plan_rows'] as $row) {
                AfilliationCorporatePlan::create([
                    'affiliation_corporate_id' => $record->id,
                    'code_affiliation' => $record->code,
                    'plan_id' => $row['plan_id'],
                    'coverage_id' => $row['coverage_id'],
                    'age_range_id' => $row['age_range_id'],
                    'total_persons' => $row['total_persons'],
                    'payment_frequency' => $record->payment_frequency,
                    'fee' => $row['fee'],
                    'subtotal_anual' => $row['subtotal_anual'],
                    'subtotal_quarterly' => $row['subtotal_quarterly'],
                    'subtotal_biannual' => $row['subtotal_biannual'],
                    'subtotal_monthly' => $row['subtotal_monthly'],
                    'status' => 'PRE-AFILIADO',
                    'created_by' => Auth::user()->name,
                ]);
            }

            $imported = $this->copyPlanGeneratorPopulation($plan, $record, $placements['affiliates']);

            if ($imported !== $placements['total_persons']) {
                throw new RuntimeException('Se copiaron '.$imported.' afiliado(s) de '.$placements['total_persons']
                    .' ubicados en el padrón. El padrón cambió mientras se creaba la afiliación.');
            }

            // Montos de la población real ubicada, no los que llegan del
            // formulario: así la cabecera cuadra con «Plan(es) Afiliado(s)».
            $record->poblation = $imported;
            $record->fee_anual = $placements['annual'];
            $record->total_amount = round(CorporateAffiliateRemovalService::annualFeeToPerPeriodAmount(
                $placements['annual'],
                $record->payment_frequency,
            ), 2);
            $record->save();
        } catch (Throwable $exception) {
            Log::error('Pre-afiliación corporativa desde el generador revertida', [
                'plan_generator_id' => $plan->getKey(),
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            $isPlanFailure = str_starts_with($exception->getMessage(), PlanGeneratorCoverageAssignment::PLAN_FAILURE_PREFIX);

            Notification::make()
                ->title($isPlanFailure ? 'Falla en la creación del plan' : 'No se creó la pre-afiliación')
                ->body(($exception instanceof RuntimeException ? $exception->getMessage() : 'Ocurrió un error inesperado.')
                    .' No se guardó nada: la afiliación, sus planes y sus afiliados se revirtieron.')
                ->danger()
                ->persistent()
                ->send();

            throw (new Halt)->rollBackDatabaseTransaction();
        }

        SecurityAudit::log('AUDIT_BUSINESS_PLAN_GENERATOR_CORPORATE_AFFILIATION_CREATED', 'business.affiliation-corporates.create-from-plan-generator', [
            'plan_generator_id' => $plan->getKey(),
            'affiliation_corporate_id' => $record->getKey(),
            'affiliates' => $imported,
            'plan_rows' => count($placements['plan_rows']),
            'fee_anual' => $placements['annual'],
            'assignment_signature' => $placements['signature'],
        ]);

        PlanGeneratorPreAffiliationSession::forget();

        Notification::make()
            ->title('Pre-afiliación corporativa creada')
            ->body('Se cargaron '.$imported.' afiliado(s), cada uno en la cobertura y el rango de edad que le asignó.')
            ->success()
            ->send();
    }

    /**
     * Camino previo para una sesión del generador sin plan corporativo
     * resoluble: guarda la fila agregada de cada cobertura, sin padrón.
     */
    private function createAggregatedPlanGeneratorRows(AffiliationCorporate $record): void
    {
        $payload = PlanGeneratorPreAffiliationSession::get();
        $dataRecords = is_array($payload['data_records'] ?? null) ? $payload['data_records'] : [];

        foreach ($dataRecords as $dataRecord) {
            if (! is_array($dataRecord)) {
                continue;
            }

            AfilliationCorporatePlan::create([
                'affiliation_corporate_id' => $record->id,
                'code_affiliation' => $record->code,
                'plan_id' => $dataRecord['plan_id'] ?? 0,
                'coverage_id' => $dataRecord['coverage_id'] ?? null,
                'age_range_id' => $dataRecord['age_range_id'] ?? 0,
                'total_persons' => $dataRecord['total_persons'] ?? 0,
                'payment_frequency' => $record->payment_frequency,
                'fee' => $dataRecord['fee'] ?? 0,
                'subtotal_anual' => $dataRecord['subtotal_anual'] ?? 0,
                'subtotal_quarterly' => $dataRecord['subtotal_quarterly'] ?? 0,
                'subtotal_biannual' => $dataRecord['subtotal_biannual'] ?? 0,
                'subtotal_monthly' => $dataRecord['subtotal_monthly'] ?? 0,
                'status' => 'PRE-AFILIADO',
                'created_by' => Auth::user()->name,
            ]);
        }

        $record->poblation = (int) ($payload['total_persons'] ?? 0);
        $record->save();

        PlanGeneratorPreAffiliationSession::forget();

        Notification::make()
            ->title('Pre-afiliación corporativa creada')
            ->body('Los planes del generador fueron vinculados. Agregue los afiliados corporativos manualmente.')
            ->success()
            ->send();
    }

    /**
     * Copia el padrón importado del plan generado a los afiliados corporativos,
     * cada uno con el plan, la cobertura y la tarifa anual del rango de edad en
     * que el analista lo ubicó.
     *
     * Se inserta por lotes: un padrón corporativo pasa de las mil filas con
     * facilidad y un `save()` por fila cuelga el request. Una fila que no esté
     * en `$placements` no se copia, y el llamador lo detecta por el conteo.
     *
     * @param  array<int, array{plan_id: int, coverage_id: int, fee: float}>  $placements
     */
    private function copyPlanGeneratorPopulation(PlanGenerator $plan, AffiliationCorporate $record, array $placements): int
    {
        $now = now();
        $inserted = 0;
        $frequency = (string) $record->payment_frequency;
        // `affiliate_corporates.created_by` es entero: guarda el id del usuario.
        $createdBy = Auth::id();
        $specificBusinessUnit = AffiliationAffiliateBusinessContextSynchronizer::normalizeSpecificBusinessUnit($record->specific_business_unit);

        $plan->populations()
            ->getQuery()
            ->reorder()
            ->orderBy('id')
            ->chunkById(500, function ($populations) use ($record, $now, $placements, $frequency, $createdBy, $specificBusinessUnit, &$inserted): void {
                $rows = [];

                foreach ($populations as $population) {
                    /** @var PlanGeneratorPopulation $population */
                    $placement = $placements[(int) $population->getKey()] ?? null;

                    if ($placement === null) {
                        continue;
                    }

                    $fee = $placement['fee'];

                    $rows[] = [
                        'affiliation_corporate_id' => $record->id,
                        'last_name' => $population->last_name,
                        'first_name' => $population->first_name,
                        'nro_identificacion' => $population->nro_identificacion,
                        'birth_date' => $population->birth_date,
                        'age' => $population->age,
                        'sex' => $population->sex,
                        'phone' => $population->phone,
                        'email' => $population->email,
                        'condition_medical' => $population->condition_medical,
                        'initial_date' => $population->initial_date,
                        'position_company' => $population->position_company,
                        'address' => $population->address,
                        'full_name_emergency' => $population->full_name_emergency,
                        'phone_emergency' => $population->phone_emergency,
                        'plan_id' => $placement['plan_id'],
                        'coverage_id' => $placement['coverage_id'],
                        'fee' => $fee,
                        'subtotal_anual' => $fee,
                        'payment_frequency' => $frequency,
                        'subtotal_payment_frequency' => round(CorporateAffiliateRemovalService::annualFeeToPerPeriodAmount($fee, $frequency), 2),
                        'subtotal_daily' => round($fee / 30, 2),
                        'business_unit_id' => $record->business_unit_id,
                        'specific_business_unit' => $specificBusinessUnit,
                        'business_line_id' => $record->business_line_id,
                        'status' => 'PRE-APROBADA',
                        'created_by' => $createdBy,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                if ($rows === []) {
                    return;
                }

                AffiliateCorporate::query()->insert($rows);
                $inserted += count($rows);
            });

        return $inserted;
    }
}
