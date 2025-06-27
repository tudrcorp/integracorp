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
            ImportColumn::make('affiliation_corporate_id')
                ->requiredMapping()
                ->numeric()
                ->rules(['required', 'integer']),
            ImportColumn::make('last_name')
                ->requiredMapping()
                ->rules(['required', 'max:255']),
            ImportColumn::make('first_name')
                ->requiredMapping()
                ->rules(['required', 'max:255']),
            ImportColumn::make('nro_identificacion')
                ->requiredMapping()
                ->rules(['required', 'max:255']),
            ImportColumn::make('birth_date')
                ->requiredMapping()
                ->rules(['required', 'max:255']),
            ImportColumn::make('age')
                ->requiredMapping()
                ->rules(['required', 'max:255']),
            ImportColumn::make('sex')
                ->requiredMapping()
                ->rules(['required', 'max:255']),
            ImportColumn::make('phone')
                ->requiredMapping()
                ->rules(['required', 'max:255']),
            ImportColumn::make('email')
                ->requiredMapping()
                ->rules(['required', 'email', 'max:255']),
            ImportColumn::make('condition_medical')
                ->requiredMapping()
                ->rules(['required']),
            ImportColumn::make('initial_date')
                ->requiredMapping()
                ->rules(['required', 'max:255']),
            ImportColumn::make('position_company')
                ->requiredMapping()
                ->rules(['required', 'max:255']),
            ImportColumn::make('address'),
            ImportColumn::make('full_name_emergency')
                ->requiredMapping()
                ->rules(['required', 'max:255']),
            ImportColumn::make('phone_emergency')
                ->requiredMapping()
                ->rules(['required', 'max:255']),
        ];
    }

    public function resolveRecord(): AffiliateCorporate
    {
        return AffiliateCorporate::firstOrNew([
            'affiliation_corporate_id' => $this->data['affiliation_corporate_id'],
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
}
