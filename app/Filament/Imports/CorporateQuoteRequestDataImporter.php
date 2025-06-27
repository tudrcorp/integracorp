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
            ImportColumn::make('full_name')
                ->requiredMapping()
                ->example('Gustavo Camacho')
                ->rules(['required', 'max:255']),
            ImportColumn::make('birth_date')
                ->example('01-01-2025')
                ->rules(['max:255']),
            ImportColumn::make('age')
                ->example('45')
                ->rules(['max:255']),
        ];
    }

    public function resolveRecord(): CorporateQuoteRequestData
    {
        // return CorporateQuoteRequestData::firstOrNew([
        //     'corporate_quote_request_id' => $this->data['corporate_quote_request_id'],
        // ]);
        return CorporateQuoteRequestData::create([
            // Update existing records, matching them by `$this->data['column_name']`
            'full_name' => $this->data['full_name'],
            'birth_date' => $this->data['birth_date'],
            'age' => $this->data['age'],
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