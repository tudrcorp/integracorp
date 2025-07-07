<?php

namespace App\Filament\Imports;

use App\Models\AffiliateCorporate;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Number;

class AffiliateCorporateImporter extends Importer
{
    protected static ?string $model = AffiliateCorporate::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('last_name')
                ->label('Apellido')
                ->requiredMapping()
                ->example('Garcia')
                ->fillRecordUsing(function (AffiliateCorporate $record, string $state): void {
                    $record->last_name = strtoupper($state);
                }),
            ImportColumn::make('first_name')
                ->label('Nombre')
                ->requiredMapping()
                ->example('Luis')
                ->fillRecordUsing(function (AffiliateCorporate $record, string $state): void {
                    $record->first_name = strtoupper($state);
                }),
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
                ->example('25')
                ->rules(['integer']),
            ImportColumn::make('sex')
                ->label('Sexo')
                ->requiredMapping()
                ->example('M')
                ->fillRecordUsing(function (AffiliateCorporate $record, string $state): void {
                    $record->sex = strtoupper($state);
                }),
            ImportColumn::make('phone')
                ->label('Telefono')
                ->requiredMapping()
                ->example('04127018390'),
            ImportColumn::make('email')
                ->label('Email')
                ->requiredMapping()
                ->example('h7e6h@example.com')
                ->rules(['email', 'max:255']),
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
                ->example('Luis Garcia')
                ->fillRecordUsing(function (AffiliateCorporate $record, string $state): void {
                    $record->full_name_emergency = strtoupper($state);
                }),
            ImportColumn::make('phone_emergency')
                ->label('Telefono de Emergencia')
                ->requiredMapping()
                ->example('04127018390'),
        ];
    }

    public function resolveRecord(): AffiliateCorporate
    {
        return AffiliateCorporate::create([
            // Update existing records, matching them by `$this->data['column_name']`
            'last_name' => $this->data['last_name'],
            'first_name' => $this->data['first_name'],
            'nro_identificacion' => $this->data['nro_identificacion'],
            'birth_date' => $this->data['birth_date'],
            'age' => $this->data['age'],
            'sex' => $this->data['sex'],
            'phone' => $this->data['phone'],
            'email' => $this->data['email'],
            'condition_medical' => $this->data['condition_medical'],
            'initial_date' => $this->data['initial_date'],
            'position_company' => $this->data['position_company'],
            'address' => $this->data['address'],
            'full_name_emergency' => $this->data['full_name_emergency'],
            'phone_emergency' => $this->data['phone_emergency'],
            'affiliation_corporate_id' => $this->options['affiliation_corporate_id'],
        ]);
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your affiliate corporate import has completed and ' . Number::format($import->successful_rows) . ' ' . str('row')->plural($import->successful_rows) . ' imported.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' ' . Number::format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to import.';
        }

        return $body;
    }

    public function getJobBatchName(): ?string
    {
        return 'affiliate-corporate-import';
    }
}