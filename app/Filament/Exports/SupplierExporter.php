<?php

namespace App\Filament\Exports;

use App\Models\Supplier;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Number;
use OpenSpout\Common\Entity\Style\CellAlignment;
use OpenSpout\Common\Entity\Style\CellVerticalAlignment;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use Throwable;

class SupplierExporter extends Exporter
{
    protected static ?string $model = Supplier::class;

    /**
     * @param  Builder<Supplier>  $query
     * @return Builder<Supplier>
     */
    public static function modifyQuery(Builder $query): Builder
    {
        return $query->with(['supplierContactPrincipals', 'state', 'city', 'SupplierClasificacion']);
    }

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('state_definition')
                ->label('Estado')
                ->state(fn (Supplier $record): string => $record->state?->definition ?? ''),
            ExportColumn::make('city_definition')
                ->label('Ciudad')
                ->state(fn (Supplier $record): string => $record->city?->definition ?? ''),
            ExportColumn::make('state_services')
                ->label('Zona de Cobertura')
                ->state(function (Supplier $record): string {
                    $value = $record->state_services;

                    if (! is_array($value)) {
                        return (string) $value;
                    }

                    $parts = [];

                    foreach ($value as $item) {
                        if (is_string($item)) {
                            $item = trim($item);

                            if ($item !== '') {
                                $parts[] = $item;
                            }

                            continue;
                        }

                        if (is_scalar($item)) {
                            $parts[] = (string) $item;
                        } else {
                            $parts[] = json_encode($item);
                        }
                    }

                    return implode('; ', array_values(array_unique($parts)));
                }),
            ExportColumn::make('supplier_clasificacion_description')
                ->label('Clasificacion del Proveedor')
                ->state(fn (Supplier $record): string => $record->SupplierClasificacion?->description ?? ''),
            ExportColumn::make('tipo_clinica')->label('Tipo de Clinica'),
            ExportColumn::make('horario')->label('Horario de Atencion'),
            ExportColumn::make('status_convenio')->label('Estatus del Convenio'),
            ExportColumn::make('status_sistema')->label('Estatus del Sistema'),
            ExportColumn::make('name')->label('Nombre del Proveedor'),
            ExportColumn::make('rif')->label('RIF'),
            ExportColumn::make('razon_social')->label('Razon Social'),
            ExportColumn::make('personal_phone')->label('Teléfono Celular'),
            ExportColumn::make('local_phone')->label('Teléfono Local'),
            ExportColumn::make('principal_contact_emails')
                ->label('Correo Principal')
                ->state(function (Supplier $record): string {
                    try {
                        $contacts = $record->relationLoaded('supplierContactPrincipals')
                            ? $record->supplierContactPrincipals
                            : $record->supplierContactPrincipals()->get();

                        $emails = $contacts
                            ->pluck('email')
                            ->map(fn ($email): string => is_string($email) ? trim($email) : '')
                            ->filter(fn (string $email): bool => $email !== '')
                            ->unique()
                            ->values();

                        return $emails->implode('; ');
                    } catch (Throwable $e) {
                        Log::warning('SupplierExporter: fallo en columna principal_contact_emails', [
                            'supplier_id' => $record->getKey(),
                            'message' => $e->getMessage(),
                        ]);

                        return '';
                    }
                }),
            ExportColumn::make('afiliacion_proveedor')->label('Afiliación Proveedor'),
            ExportColumn::make('ubicacion_principal')->label('Ubicación Principal'),
            ExportColumn::make('convenio_pago')->label('Convenio de Pago'),
            ExportColumn::make('tiempo_credito')->label('Tiempo de Credito'),
            ExportColumn::make('created_by')->label('Creado por'),
            ExportColumn::make('updated_by')->label('Actualizado por'),

            // ExportColumn::make('type_service')->label('Tipo de Servicio'),
            // ExportColumn::make('tipo_servicio')->label('Tipo de Servicio'),
            // ExportColumn::make('promedio_costo_proveedor')->label('Promedio Costo Proveedor'),
            // ExportColumn::make('densitometria_osea')->label('Densitómetro'),
            // ExportColumn::make('dialisis')->label('Equipo de Dialisis'),
            // ExportColumn::make('electrocardiograma_centro')->label('Electrocardiógrafo'),
            // ExportColumn::make('equipos_especiales_oftalmologia'),
            // ExportColumn::make('mamografia')->label('Mamógrafo'),
            // ExportColumn::make('quirofanos'),
            // ExportColumn::make('radioterapia_intraoperatoria'),
            // ExportColumn::make('resonancia')->label('Resonador'),
            // ExportColumn::make('tomografo')->label('Tomógrafo'),
            // ExportColumn::make('uci_pediatrica')->label('UCI Pediatrica(Unidad de Cuidados Intensivos)'),
            // ExportColumn::make('uci_adulto')->label('UCI Adulto(Unidad de Cuidados Intensivos)'),
            // ExportColumn::make('estacionamiento_propio'),
            // ExportColumn::make('ascensor')->label('Ascensor Operativo'),
            // ExportColumn::make('robotica')->label('Equipo de  Cirugía Robótica'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your supplier export has completed and '.Number::format($export->successful_rows).' '.str('row')->plural($export->successful_rows).' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.Number::format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to export.';
        }

        return $body;
    }

    public function getXlsxHeaderCellStyle(): ?Style
    {
        return (new Style)
            ->setFontBold()
            ->setFontItalic()
            ->setFontSize(8)
            ->setFontName('Helvetica')
            ->setFontColor(Color::rgb(252, 254, 253))
            ->setBackgroundColor(Color::rgb(33, 65, 56))
            ->setCellAlignment(CellAlignment::CENTER)
            ->setCellVerticalAlignment(CellVerticalAlignment::CENTER);
    }

    public function getXlsxCellStyle(): ?Style
    {
        return (new Style)
            ->setFontSize(8)
            ->setFontName('Helvetica')
            ->setFontColor(Color::rgb(0, 0, 0))
            ->setCellAlignment(CellAlignment::LEFT)
            ->setCellVerticalAlignment(CellVerticalAlignment::TOP);
    }

    /**
     * @return int | array<int> | null
     */
    public function getJobBackoff(): int|array|null
    {
        return [30, 60, 120, 300];
    }
}
