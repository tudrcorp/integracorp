<?php

namespace App\Filament\Exports;

use App\Models\Affiliate;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Number;

use OpenSpout\Common\Entity\Style\CellAlignment;
use OpenSpout\Common\Entity\Style\CellVerticalAlignment;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;

class AffiliateExporter extends Exporter
{
    protected static ?string $model = Affiliate::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('affiliation_id')
                ->label('Afiliación'),
            ExportColumn::make('full_name')
                ->label('Nombre Completo'),
            ExportColumn::make('nro_identificacion')
                ->label('Nro Identificación'),
            ExportColumn::make('email')
                ->label('Correo Electrónico'),
            ExportColumn::make('sex')
                ->label('Sexo'),
            ExportColumn::make('stature')
                ->label('Estatura'),
            ExportColumn::make('birth_date')
                ->label('Fecha de Nacimiento'),
            ExportColumn::make('age')
                ->label('Edad'),
            ExportColumn::make('weight')
                ->label('Peso'),
            ExportColumn::make('relationship')
                ->label('Relación'),
            ExportColumn::make('created_at')
                ->label('Fecha de Creación'),
            ExportColumn::make('updated_at')
                ->label('Fecha de Actualización'),
            ExportColumn::make('status')
                ->label('Estado'),
            ExportColumn::make('address')
                ->label('Dirección'),
            ExportColumn::make('phone')
                ->label('Teléfono'),
            ExportColumn::make('country.name')
                ->label('País'),
            ExportColumn::make('state.definition')
                ->label('Estado'),
            ExportColumn::make('city.definition')
                ->label('Ciudad'),
            ExportColumn::make('region')
                ->label('Región'),
            ExportColumn::make('plan.description')
                ->label('Plan'),
            ExportColumn::make('coverage.price')
                ->label('Cobertura'),
            ExportColumn::make('vaucherIls')
                ->label('Vaucher Ils'),
            ExportColumn::make('dateInit')
                ->label('Fecha de Inicio'),
            ExportColumn::make('dateEnd')
                ->label('Fecha de Fin'),
            ExportColumn::make('numberDays')
                ->label('Número de Días'),
            ExportColumn::make('document_ils')
                ->label('Documento Ils'),
            ExportColumn::make('fee')
                ->label('Tarifa'),
            ExportColumn::make('total_amount')
                ->label('Monto Total'),
            ExportColumn::make('created_by')
                ->label('Creado por'),
            
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your affiliate export has completed and ' . Number::format($export->successful_rows) . ' ' . str('row')->plural($export->successful_rows) . ' exported.';

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
