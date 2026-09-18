<?php

declare(strict_types=1);

namespace App\Filament\Imports;

use App\Models\PlanGeneratorPopulation;
use App\Support\Imports\CorporateQuoteBirthDateParser;
use App\Support\Imports\ImportActivityLogger;
use Carbon\CarbonInterface;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Number;
use Throwable;

/**
 * Importa el padrón de la pre-afiliación corporativa de un plan generado.
 *
 * Mismas columnas y mismo parseo de fecha de nacimiento que
 * `CorporateQuoteDataImporter`, para que el analista use el mismo archivo que ya
 * usa en cotizaciones corporativas. La única diferencia es el destino: la
 * población queda colgada del plan generado hasta que se cree la afiliación.
 */
class PlanGeneratorPopulationImporter extends Importer
{
    protected static ?string $model = PlanGeneratorPopulation::class;

    /**
     * @return array<int, ImportColumn>
     */
    public static function getColumns(): array
    {
        return [
            ImportColumn::make('last_name')
                ->label('Apellido')
                ->requiredMapping()
                ->example('Garcia'),
            ImportColumn::make('first_name')
                ->label('Nombre')
                ->requiredMapping()
                ->example('Luis'),
            ImportColumn::make('nro_identificacion')
                ->label('C.I.')
                ->requiredMapping()
                ->example('12345678'),
            ImportColumn::make('birth_date')
                ->label('Fecha de Nacimiento')
                ->requiredMapping()
                ->example('21/01/2025'),
            ImportColumn::make('age')
                ->label('Edad')
                ->example('25'),
            ImportColumn::make('sex')
                ->label('Sexo')
                ->example('M'),
            ImportColumn::make('phone')
                ->label('Telefono')
                ->example('04127018390'),
            ImportColumn::make('email')
                ->label('Email')
                ->example('h7e6h@example.com'),
            ImportColumn::make('condition_medical')
                ->label('Condicion Medica')
                ->example('Sano'),
            ImportColumn::make('initial_date')
                ->label('Fecha de Ingreso')
                ->example('01/01/2025'),
            ImportColumn::make('position_company')
                ->label('Cargo')
                ->example('Desarrollador'),
            ImportColumn::make('address')
                ->label('Direccion')
                ->example('Calle 123'),
            ImportColumn::make('full_name_emergency')
                ->label('Contacto de Emergencia')
                ->example('Luis Garcia'),
            ImportColumn::make('phone_emergency')
                ->label('Telefono de Emergencia')
                ->example('04127018390'),
        ];
    }

    /**
     * Fecha de nacimiento ya validada, para calcular la edad en `beforeSave()`.
     */
    private ?CarbonInterface $parsedBirthDate = null;

    /**
     * Devuelve la fila **sin guardar**: Filament la llena después con los
     * valores del CSV (`fillRecord()`) y recién entonces la persiste. Por eso
     * acá solo van los datos que no vienen del archivo.
     *
     * El parser no se usa para normalizar sino para **validar**: si la fecha de
     * nacimiento no se puede leer, la fila se rechaza y queda en el CSV de
     * fallos. La fecha se guarda tal como viene del archivo, igual que
     * `corporate_quote_data`, porque ambas terminan en la misma columna
     * `affiliate_corporates.birth_date` y mezclar formatos ahí sería peor.
     */
    public function resolveRecord(): PlanGeneratorPopulation
    {
        $logger = app(ImportActivityLogger::class);
        $birthDateParser = app(CorporateQuoteBirthDateParser::class);

        $planGeneratorId = (int) ($this->options['plan_generator_id'] ?? 0);

        try {
            $this->parsedBirthDate = $birthDateParser->parse($this->data['birth_date'] ?? null);

            return new PlanGeneratorPopulation([
                'plan_generator_id' => $planGeneratorId,
                'import_id' => $this->import->getKey(),
            ]);
        } catch (RowImportFailedException $exception) {
            $logger->logRowFailure($this->import, $this->originalData, $exception->getMessage(), [
                'plan_generator_id' => $planGeneratorId,
                'birth_date_raw' => $this->data['birth_date'] ?? null,
            ]);

            throw $exception;
        } catch (Throwable $exception) {
            $message = 'Error al crear la fila de población del plan generado: '.$exception->getMessage();

            $logger->logRowFailure($this->import, $this->originalData, $message, [
                'plan_generator_id' => $planGeneratorId,
                'birth_date_raw' => $this->data['birth_date'] ?? null,
                'exception' => $exception::class,
            ]);

            throw new RowImportFailedException($message);
        }
    }

    /**
     * Corre después de `fillRecord()`, que es lo único que llega a la base.
     *
     * Nombres y sexo en mayúsculas para que el padrón salga uniforme, y la edad
     * **siempre** calculada desde la fecha de nacimiento: la columna «Edad» del
     * archivo del cliente suele venir desactualizada o vacía.
     */
    protected function beforeSave(): void
    {
        // `mb_strtoupper` y no `strtoupper`: el segundo trabaja por bytes y
        // dejaría «BRICEñO SILVA».
        foreach (['last_name', 'first_name', 'sex'] as $attribute) {
            $this->record->{$attribute} = mb_strtoupper(trim((string) $this->record->{$attribute}), 'UTF-8');
        }

        $this->record->nro_identificacion = trim((string) $this->record->nro_identificacion);

        if ($this->parsedBirthDate !== null) {
            $this->record->age = (string) $this->parsedBirthDate->age;
        }
    }

    public static function getCompletedNotificationTitle(Import $import): string
    {
        return 'Importación de población finalizada';
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Se importaron '.Number::format($import->successful_rows).' '.str('fila')->plural($import->successful_rows).' de población.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' '.Number::format($failedRowsCount).' '.str('fila')->plural($failedRowsCount).' fallaron. Descargue el CSV de fallos para corregirlas y vuelva a importar.';
        }

        return $body;
    }

    public function getJobBatchName(): ?string
    {
        return 'plan-generator-population-import';
    }

    /**
     * @return array<int, object>
     */
    public function getJobMiddleware(): array
    {
        // Igual que en el padrón de cotizaciones corporativas: el middleware
        // anti-solapamiento de Filament reencola jobs, consume intentos y
        // termina en MaxAttemptsExceededException.
        return [];
    }

    public function getJobRetryUntil(): ?CarbonInterface
    {
        return now()->addHours(6);
    }
}
