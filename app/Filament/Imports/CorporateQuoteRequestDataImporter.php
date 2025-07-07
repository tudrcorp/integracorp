<?php

namespace App\Filament\Imports;

use Carbon\CarbonInterface;
use Illuminate\Support\Number;
use Filament\Actions\Imports\Importer;
use App\Models\CorporateQuoteRequestData;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Models\Import;

class CorporateQuoteRequestDataImporter extends Importer
{
    protected static ?string $model = CorporateQuoteRequestData::class;

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
                ->numeric()
                ->example('12345678'),
            ImportColumn::make('birth_date')
                ->label('Fecha de Nacimiento')
                ->requiredMapping()
                ->example('21-01-2025'),
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
                ->example('01-01-2025'),
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

    public function resolveRecord(): CorporateQuoteRequestData
    {
        // return CorporateQuoteRequestData::firstOrNew([
        //     'corporate_quote_request_id' => $this->data['corporate_quote_request_id'],
        // ]);
        return CorporateQuoteRequestData::create([
            // Update existing records, matching them by `$this->data['column_name']`
            'last_name'             => $this->data['last_name'],
            'first_name'            => $this->data['first_name'],
            'nro_identificacion'    => $this->data['nro_identificacion'],
            'birth_date'            => $this->data['birth_date'],
            'age'                   => $this->data['age'],
            'sex'                   => $this->data['sex'],
            'phone'                 => $this->data['phone'],
            'email'                 => $this->data['email'],
            'condition_medical'     => $this->data['condition_medical'],
            'initial_date'          => $this->data['initial_date'],
            'position_company'      => $this->data['position_company'],
            'address'               => $this->data['address'],
            'full_name_emergency'   => $this->data['full_name_emergency'],
            'phone_emergency'       => $this->data['phone_emergency'],
            'corporate_quote_request_id' => $this->options['corporate_quote_request_id'],
        ]);
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your corporate quote request data import has completed and ' . Number::format($import->successful_rows) . ' ' . str('row')->plural($import->successful_rows) . ' imported.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' ' . Number::format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to import.';
        }

        return $body;
    }

    public function getJobRetryUntil(): ?CarbonInterface
    {
        return now()->addMinute(1);
    }
}