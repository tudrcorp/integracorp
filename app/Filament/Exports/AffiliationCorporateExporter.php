<?php

namespace App\Filament\Exports;

use App\Models\AffiliationCorporate;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Number;

class AffiliationCorporateExporter extends Exporter
{
    protected static ?string $model = AffiliationCorporate::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')
                ->label('ID'),
            ExportColumn::make('corporate_quote_id'),
            ExportColumn::make('owner_code'),
            ExportColumn::make('code'),
            ExportColumn::make('code_agency'),
            ExportColumn::make('agent_id'),
            ExportColumn::make('name_corporate'),
            ExportColumn::make('rif'),
            ExportColumn::make('address'),
            ExportColumn::make('city_id'),
            ExportColumn::make('country_id'),
            ExportColumn::make('region_id'),
            ExportColumn::make('phone'),
            ExportColumn::make('email'),
            ExportColumn::make('full_name_contact'),
            ExportColumn::make('nro_identificacion_contact'),
            ExportColumn::make('phone_contact'),
            ExportColumn::make('email_contact'),
            ExportColumn::make('date_affiliation'),
            ExportColumn::make('created_by'),
            ExportColumn::make('status'),
            ExportColumn::make('document'),
            ExportColumn::make('observations'),
            ExportColumn::make('payment_frequency'),
            ExportColumn::make('fee_anual'),
            ExportColumn::make('total_amount'),
            ExportColumn::make('vaucher_ils'),
            ExportColumn::make('date_payment_initial_ils'),
            ExportColumn::make('date_payment_final_ils'),
            ExportColumn::make('document_ils'),
            ExportColumn::make('created_at'),
            ExportColumn::make('updated_at'),
            ExportColumn::make('state_id'),
            ExportColumn::make('poblation'),
            ExportColumn::make('activated_at'),
            ExportColumn::make('ownerAccountManagers'),
            ExportColumn::make('business_unit_id'),
            ExportColumn::make('business_line_id'),
            ExportColumn::make('service_providers'),
            ExportColumn::make('effective_date'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your affiliation corporate export has completed and ' . Number::format($export->successful_rows) . ' ' . str('row')->plural($export->successful_rows) . ' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . Number::format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to export.';
        }

        return $body;
    }
}
