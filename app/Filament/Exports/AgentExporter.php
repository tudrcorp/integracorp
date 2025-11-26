<?php

namespace App\Filament\Exports;

use App\Models\Agent;
use App\Models\State;
use Illuminate\Support\Number;
use Filament\Support\Enums\Alignment;
use Filament\Actions\Exports\Exporter;
use OpenSpout\Common\Entity\Style\Border;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Models\Export;
use BaconQrCode\Renderer\RendererStyle\Fill;

use OpenSpout\Common\Entity\Style\CellAlignment;
use OpenSpout\Common\Entity\Style\CellVerticalAlignment;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;

class AgentExporter extends Exporter
{
    protected static ?string $model = Agent::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')
                ->label('ID'),
            ExportColumn::make('owner_code')
            ->label('PERTENECE A:'),
            ExportColumn::make('name')
                ->label('NOMBRE COMPLETO'),
            ExportColumn::make('ci')
                ->label('CÉDULA'),
            ExportColumn::make('rif')
                ->label('RIF'),
            ExportColumn::make('birth_date')
                ->label('FECHA DE NACIMIENTO'),
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
            ExportColumn::make('region')
                ->label('REGIÓN'),
            ExportColumn::make('state.definition')
                ->label('ESTADO'),
            ExportColumn::make('city.definition')
                ->label('CIUDAD'),
            ExportColumn::make('sex')
                ->label('SEXO'),
            ExportColumn::make('marital_status')
                ->label('ESTADO CIVIL'),
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
            ExportColumn::make('user_tdev')
                ->label('USUARIO TDEV'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Tu exportación de agentes ha finalizado y ' . Number::format($export->successful_rows) . ' ' . str('row')->plural($export->successful_rows) . ' registrados.';

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