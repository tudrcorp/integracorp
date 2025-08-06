<?php

namespace App\Filament\Exports;

use App\Models\IndividualQuote;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Number;

class IndividualQuoteExporter extends Exporter
{
    protected static ?string $model = IndividualQuote::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')
                ->label('ID'),
            ExportColumn::make('owner_code'),
            ExportColumn::make('code_agency'),
            ExportColumn::make('code'),
            ExportColumn::make('agent.name'),
            ExportColumn::make('state.id'),
            ExportColumn::make('count_days'),
            ExportColumn::make('region'),
            ExportColumn::make('full_name'),
            ExportColumn::make('birth_date'),
            ExportColumn::make('email'),
            ExportColumn::make('phone'),
            ExportColumn::make('status'),
            ExportColumn::make('created_by'),
            ExportColumn::make('created_at'),
            ExportColumn::make('updated_at'),
            ExportColumn::make('owner_agent'),
            ExportColumn::make('plan'),
            ExportColumn::make('type'),
            ExportColumn::make('assignment_status'),
            ExportColumn::make('age'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your individual quote export has completed and ' . Number::format($export->successful_rows) . ' ' . str('row')->plural($export->successful_rows) . ' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . Number::format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to export.';
        }

        return $body;
    }
}
