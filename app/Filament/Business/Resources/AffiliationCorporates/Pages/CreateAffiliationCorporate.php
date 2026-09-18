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
use App\Support\PlanGenerators\PlanGeneratorCatalogPublisher;
use App\Support\PlanGenerators\PlanGeneratorPopulationStatus;
use App\Support\PlanGenerators\PlanGeneratorPreAffiliationOptions;
use App\Support\PlanGenerators\PlanGeneratorPreAffiliationSession;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

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
     * importándose: `afterCreateFromPlanGenerator()` copia la población a
     * `affiliate_corporates` y hacerlo a medio importar dejaría afiliados fuera
     * sin que nadie se entere. La página de población ya deshabilita el botón;
     * esta guarda cubre a quien entre por URL directa.
     */
    protected function beforeCreate(): void
    {
        $plan = $this->planGeneratorFromSession();

        if ($plan === null) {
            return;
        }

        $reason = PlanGeneratorPopulationStatus::blockedReason($plan);

        if ($reason === null) {
            return;
        }

        Notification::make()
            ->title('La población todavía no está lista')
            ->body($reason)
            ->warning()
            ->persistent()
            ->send();

        $this->halt();
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
        try {

            $record = $this->getRecord();

            if (PlanGeneratorPreAffiliationSession::isActive()) {
                $this->afterCreateFromPlanGenerator($record);

                return;
            }

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
        $payload = PlanGeneratorPreAffiliationSession::get();
        $dataRecords = is_array($payload['data_records'] ?? null) ? $payload['data_records'] : [];
        $catalog = $this->planGeneratorCatalog;

        foreach ($dataRecords as $dataRecord) {
            if (! is_array($dataRecord)) {
                continue;
            }

            // Con el plan publicado en el catálogo, cada cobertura elegida se
            // abre en una fila por rango etario, que es lo que muestra la tabla
            // «Plan(es) Afiliado(s)» y lo que hace el flujo de cotizaciones
            // corporativas de Negocios. Sin catálogo se guarda la fila agregada
            // de siempre, para no perder la afiliación.
            $rows = $catalog === null
                ? [$dataRecord]
                : PlanGeneratorPreAffiliationOptions::corporatePlanRowsForColumn(
                    $catalog['plan'],
                    $dataRecord,
                    $catalog,
                );

            foreach ($rows as $row) {
                AfilliationCorporatePlan::create([
                    'affiliation_corporate_id' => $record->id,
                    'code_affiliation' => $record->code,
                    'plan_id' => $row['plan_id'] ?? 0,
                    'coverage_id' => $row['coverage_id'] ?? null,
                    'age_range_id' => $row['age_range_id'] ?? 0,
                    'total_persons' => $row['total_persons'] ?? 0,
                    'payment_frequency' => $record->payment_frequency,
                    'fee' => $row['fee'] ?? 0,
                    'subtotal_anual' => $row['subtotal_anual'] ?? 0,
                    'subtotal_quarterly' => $row['subtotal_quarterly'] ?? 0,
                    'subtotal_biannual' => $row['subtotal_biannual'] ?? 0,
                    'subtotal_monthly' => $row['subtotal_monthly'] ?? 0,
                    'status' => 'PRE-AFILIADO',
                    'created_by' => Auth::user()->name,
                ]);
            }
        }

        $plan = $this->planGeneratorFromSession();
        $imported = $plan === null ? 0 : $this->copyPlanGeneratorPopulation($plan, $record);

        $record->poblation = $imported > 0
            ? $imported
            : (int) ($payload['total_persons'] ?? 0);
        $record->save();

        PlanGeneratorPreAffiliationSession::forget();

        Notification::make()
            ->title('Pre-afiliación corporativa creada')
            ->body($imported > 0
                ? 'Se vincularon los planes del generador y se cargaron '.$imported.' afiliado(s) desde la población importada.'
                : 'Los planes del generador fueron vinculados. Agregue los afiliados corporativos manualmente.')
            ->success()
            ->send();
    }

    /**
     * Copia el padrón importado del plan generado a los afiliados corporativos,
     * igual que el flujo de cotizaciones corporativas copia
     * `corporate_quote_data`.
     *
     * Se inserta por lotes: un padrón corporativo pasa de las mil filas con
     * facilidad y un `save()` por fila cuelga el request.
     */
    private function copyPlanGeneratorPopulation(PlanGenerator $plan, AffiliationCorporate $record): int
    {
        $now = now();
        $inserted = 0;

        $plan->populations()
            ->getQuery()
            ->orderBy('id')
            ->chunkById(500, function ($populations) use ($record, $now, &$inserted): void {
                $rows = $populations->map(fn (PlanGeneratorPopulation $population): array => [
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
                    'status' => 'PRE-APROBADA',
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all();

                if ($rows === []) {
                    return;
                }

                AffiliateCorporate::query()->insert($rows);
                $inserted += count($rows);
            });

        return $inserted;
    }
}
