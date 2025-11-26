<?php

namespace App\Filament\Exports;

use App\Models\Agency;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Number;

use OpenSpout\Common\Entity\Style\CellAlignment;
use OpenSpout\Common\Entity\Style\CellVerticalAlignment;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;

class AgencyExporter extends Exporter
{
    protected static ?string $model = Agency::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')
                ->label('ID'),
            ExportColumn::make('owner_code')
                ->label('PERTENECE A:'),
            ExportColumn::make('code')
                ->label('CÓDIGO AGENCIA'),
            ExportColumn::make('typeAgency.definition')
                ->label('TIPO DE AGENCIA'),
            ExportColumn::make('rif')
                ->label('RIF'),    
            ExportColumn::make('name_corporative')
                ->label('NOMBRE CORPORATIVO'),
            ExportColumn::make('ci_responsable')
                ->label('CÉDULA RESPONSABLE'),
            ExportColumn::make('address')
                ->label('DIRECCIÓN'),
            ExportColumn::make('email')
                ->label('CORREO ELECTRÓNICO'),
            ExportColumn::make('phone')
                ->label('TELÉFONO PRINCIPAL'),
            ExportColumn::make('user_instagram')
                ->label('INSTAGRAM'),
            ExportColumn::make('country.name')
                ->label('PAÍS'),
            ExportColumn::make('state.definition')
                ->label('ESTADO'),
            ExportColumn::make('city.definition')
                ->label('CIUDAD'),
            ExportColumn::make('region')
                ->label('REGIÓN'),
            ExportColumn::make('name_contact_2')
                ->label('NOMBRE CONTACTO'),
            ExportColumn::make('email_contact_2')
                ->label('CORREO CONTACTO'),
            ExportColumn::make('phone_contact_2')
                ->label('TELÉFONO CONTACTO'),
            
            ExportColumn::make('status')
                ->label('ESTATUS'),
            ExportColumn::make('created_by')
                ->label('CREADO POR'),
            ExportColumn::make('created_at')
                ->label('FECHA DE CREACIÓN'),
            ExportColumn::make('updated_at')
                ->label('FECHA DE ACTUALIZACIÓN'),
            
            ExportColumn::make('comments')
                ->label('COMENTARIOS'),
            
            ExportColumn::make('user_tdev')
                ->label('USUARIO TDEV'),
            
            ExportColumn::make('name_representative')
                ->label('NOMBRE REPRESENTANTE LEGAL'),
            
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Tu exportación de agencias ha finalizado y ' . Number::format($export->successful_rows) . ' ' . str('row')->plural($export->successful_rows) . ' registrados.';

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