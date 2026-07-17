<?php

namespace App\Filament\Imports;

use App\Models\CorporateQuoteData;
use App\Support\Imports\CorporateQuoteBirthDateParser;
use App\Support\Imports\ImportActivityLogger;
use Carbon\CarbonInterface;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Number;
use Throwable;

class CorporateQuoteDataImporter extends Importer
{
    protected static ?string $model = CorporateQuoteData::class;

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
                ->requiredMapping()
                ->numeric()
                ->example('25'),
            ImportColumn::make('sex')
                ->label('Sexo')
                ->requiredMapping()
                ->example('M'),
            ImportColumn::make('phone')
                ->label('Telefono')
                ->requiredMapping()
                ->example('04127018390'),
            ImportColumn::make('email')
                ->label('Email')
                ->requiredMapping()
                ->example('h7e6h@example.com'),
            ImportColumn::make('condition_medical')
                ->label('Condicion Medica')
                ->requiredMapping()
                ->example('Sano'),
            ImportColumn::make('initial_date')
                ->label('Fecha de Ingreso')
                ->requiredMapping()
                ->example('01/01/2025'),
            ImportColumn::make('position_company')
                ->label('Cargo')
                ->requiredMapping()
                ->example('Desarrollador'),
            ImportColumn::make('address')
                ->label('Direccion')
                ->requiredMapping()
                ->example('Calle 123'),
            ImportColumn::make('full_name_emergency')
                ->label('Contacto de Emergencia')
                ->requiredMapping()
                ->example('Luis Garcia'),
            ImportColumn::make('phone_emergency')
                ->label('Telefono de Emergencia')
                ->requiredMapping()
                ->example('04127018390'),
        ];
    }

    public function resolveRecord(): CorporateQuoteData
    {
        $logger = app(ImportActivityLogger::class);
        $birthDateParser = app(CorporateQuoteBirthDateParser::class);

        try {
            $birthDate = $birthDateParser->parse($this->data['birth_date'] ?? null);

            return CorporateQuoteData::create([
                'last_name' => $this->data['last_name'],
                'first_name' => $this->data['first_name'],
                'nro_identificacion' => $this->data['nro_identificacion'],
                'birth_date' => $birthDate->format('Y-m-d'),
                'age' => $birthDate->age,
                'sex' => $this->data['sex'],
                'phone' => $this->data['phone'],
                'email' => $this->data['email'],
                'condition_medical' => $this->data['condition_medical'],
                'initial_date' => $this->data['initial_date'],
                'position_company' => $this->data['position_company'],
                'address' => $this->data['address'],
                'full_name_emergency' => $this->data['full_name_emergency'],
                'phone_emergency' => $this->data['phone_emergency'],
                'corporate_quote_id' => $this->options['corporate_quote_id'],
            ]);
        } catch (RowImportFailedException $exception) {
            $logger->logRowFailure($this->import, $this->originalData, $exception->getMessage(), [
                'corporate_quote_id' => $this->options['corporate_quote_id'] ?? null,
                'birth_date_raw' => $this->data['birth_date'] ?? null,
            ]);

            throw $exception;
        } catch (Throwable $exception) {
            $message = 'Error al crear la fila de población: '.$exception->getMessage();

            $logger->logRowFailure($this->import, $this->originalData, $message, [
                'corporate_quote_id' => $this->options['corporate_quote_id'] ?? null,
                'birth_date_raw' => $this->data['birth_date'] ?? null,
                'exception' => $exception::class,
            ]);

            throw new RowImportFailedException($message);
        }
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'La importación de población finalizó: '.Number::format($import->successful_rows).' '.str('fila')->plural($import->successful_rows).' importada(s).';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' '.Number::format($failedRowsCount).' '.str('fila')->plural($failedRowsCount).' fallaron o no se procesaron. Revise storage/logs/imports.log y descargue el CSV de fallos.';
        }

        return $body;
    }

    /**
     * @return array<int, object>
     */
    public function getJobMiddleware(): array
    {
        return [
            (new WithoutOverlapping("import{$this->import->getKey()}"))
                ->releaseAfter(60)
                ->expireAfter(7200),
        ];
    }

    public function getJobRetryUntil(): ?CarbonInterface
    {
        // Los chunks corren en serie (WithoutOverlapping). Con archivos grandes
        // un límite corto deja filas en remaining_rows sin procesar.
        return now()->addHours(6);
    }
}
