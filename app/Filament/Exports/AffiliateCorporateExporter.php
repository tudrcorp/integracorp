<?php

namespace App\Filament\Exports;

use App\Models\AffiliateCorporate;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Number;

use OpenSpout\Common\Entity\Style\CellAlignment;
use OpenSpout\Common\Entity\Style\CellVerticalAlignment;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;

class AffiliateCorporateExporter extends Exporter
{
    protected static ?string $model = AffiliateCorporate::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')
                ->label('ID'),
            ExportColumn::make('affiliation_corporate_id'),
            ExportColumn::make('first_name'),
            ExportColumn::make('last_name'),
            ExportColumn::make('nro_identificacion'),
            ExportColumn::make('birth_date'),
            ExportColumn::make('age'),
            ExportColumn::make('sex'),
            ExportColumn::make('phone'),
            ExportColumn::make('email'),
            ExportColumn::make('condition_medical'),
            ExportColumn::make('initial_date'),
            ExportColumn::make('position_company'),
            ExportColumn::make('address'),
            ExportColumn::make('full_name_emergency'),
            ExportColumn::make('phone_emergency'),
            ExportColumn::make('created_at'),
            ExportColumn::make('updated_at'),
            ExportColumn::make('plan_id'),
            ExportColumn::make('coverage_id'),
            ExportColumn::make('payment_frequency'),
            ExportColumn::make('fee'),
            ExportColumn::make('subtotal_anual'),
            ExportColumn::make('subtotal_payment_frequency'),
            ExportColumn::make('subtotal_daily'),
            ExportColumn::make('status'),
            ExportColumn::make('created_by'),
            ExportColumn::make('vaucherIls'),
            ExportColumn::make('dateInit'),
            ExportColumn::make('dateEnd'),
            ExportColumn::make('numberDays'),
            ExportColumn::make('document_ils'),
            ExportColumn::make('corporate_quote_id'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your affiliate corporate export has completed and ' . Number::format($export->successful_rows) . ' ' . str('row')->plural($export->successful_rows) . ' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . Number::format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to export.';
        }

        return $body;
    }

    public function getXlsxHeaderCellStyle(): ?Style
    {
        return (new Style())
            ->setFontBold()
            ->setFontItalic()
            ->setFontSize(11)
            ->setFontName('Helvetica')
            ->setFontColor(Color::rgb(252, 254, 253))
            ->setBackgroundColor(Color::rgb(33, 65, 56))
            ->setCellAlignment(CellAlignment::CENTER)
            ->setCellVerticalAlignment(CellVerticalAlignment::CENTER);
    }

    public function getXlsxCellStyle(): ?Style
    {
        return (new Style())
            ->setFontSize(10)
            ->setFontName('Helvetica')
            ->setFontColor(Color::rgb(0, 0, 0))
            ->setCellAlignment(CellAlignment::LEFT)
            ->setCellVerticalAlignment(CellVerticalAlignment::TOP);
    }
}
