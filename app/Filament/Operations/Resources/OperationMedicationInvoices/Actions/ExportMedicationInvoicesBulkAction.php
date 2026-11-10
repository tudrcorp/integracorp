<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\OperationMedicationInvoices\Actions;

use App\Enums\OperationReportFormat;
use App\Enums\OperationReportType;
use App\Jobs\GenerateOperationReportJob;
use App\Support\Operations\Reports\MedicationInvoiceReportBuilder;
use App\Support\Operations\Reports\OperationReportFilters;
use App\Support\Operations\Reports\OperationReportGenerator;
use App\Support\SecurityAudit;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

/**
 * Reporte de las facturas seleccionadas en PDF, Excel o CSV, con la totalización al pie.
 *
 * Es el mismo reporte del Generador de reportes (tipo «Facturas de medicamentos») y exige el
 * mismo permiso. No carga los modelos seleccionados: trabaja con sus ids. Lo normal se descarga
 * al instante; una selección muy grande va a cola y avisa en la campana.
 */
final class ExportMedicationInvoicesBulkAction
{
    public static function make(): BulkAction
    {
        return BulkAction::make('export_medication_invoices_report')
            ->label('Generar reporte')
            ->icon(Heroicon::OutlinedDocumentChartBar)
            ->color('primary')
            ->visible(fn (): bool => MedicationInvoiceReportBuilder::allows())
            ->fetchSelectedRecords(false)
            ->modalHeading('Reporte de facturas seleccionadas')
            ->modalDescription('Una fila por factura y los totales al pie: por cobrar, cobradas y total facturado. Las anuladas se listan pero no suman.')
            ->modalIcon(Heroicon::OutlinedDocumentChartBar)
            ->modalWidth(Width::Large)
            ->modalSubmitActionLabel('Generar')
            ->schema([
                ToggleButtons::make('format')
                    ->label('Formato')
                    ->options(collect(OperationReportFormat::cases())->mapWithKeys(fn (OperationReportFormat $format): array => [$format->value => $format->label()])->all())
                    ->icons(collect(OperationReportFormat::cases())->mapWithKeys(fn (OperationReportFormat $format): array => [$format->value => $format->icon()])->all())
                    ->default(OperationReportFormat::Excel->value)
                    ->inline()
                    ->required(),
            ])
            ->deselectRecordsAfterCompletion()
            ->action(function (BulkAction $action, array $data): ?BinaryFileResponse {
                $user = Auth::user();

                if ($user === null || ! MedicationInvoiceReportBuilder::allows()) {
                    Notification::make()->title('Sin permiso')->body('No tiene permiso para generar el reporte de facturas a pacientes.')->danger()->send();

                    return null;
                }

                $ids = $action->getSelectedRecordsQuery()
                    ->limit(MedicationInvoiceReportBuilder::MAX_SELECTED + 1)
                    ->pluck('operation_medication_invoices.id')
                    ->map(fn (mixed $id): int => (int) $id)
                    ->all();

                if ($ids === []) {
                    Notification::make()->title('Sin facturas')->body('Seleccione al menos una factura.')->warning()->send();

                    return null;
                }

                if (count($ids) > MedicationInvoiceReportBuilder::MAX_SELECTED) {
                    Notification::make()
                        ->title('Selección demasiado grande')
                        ->body('Seleccione hasta '.number_format(MedicationInvoiceReportBuilder::MAX_SELECTED, 0, ',', '.').' facturas o use el Generador de reportes con un periodo.')
                        ->warning()
                        ->send();

                    return null;
                }

                $type = OperationReportType::MedicationInvoices;
                $format = OperationReportFormat::tryFrom((string) ($data['format'] ?? '')) ?? OperationReportFormat::Excel;
                $filters = OperationReportFilters::fromArray(['invoice_ids' => $ids]);
                $filename = OperationReportGenerator::filename($type, $format);

                SecurityAudit::log('AUDIT_OPERATIONS_MEDICATION_INVOICES_REPORT', 'operations.medication-invoices.report', [
                    'format' => $format->value,
                    'selected' => count($ids),
                ]);

                if (OperationReportGenerator::shouldQueue($type, $format, count($ids))) {
                    GenerateOperationReportJob::dispatch($type->value, $format->value, $filters->toArray(), (int) $user->getAuthIdentifier(), $filename);

                    Notification::make()
                        ->title('Estamos generando su reporte')
                        ->body(number_format(count($ids), 0, ',', '.').' facturas. Le avisaremos en la campana; también quedará en el Generador de reportes, en «Mis reportes recientes».')
                        ->info()
                        ->send();

                    return null;
                }

                try {
                    OperationReportGenerator::store($type, $format, $filters, (int) $user->getAuthIdentifier(), $filename);
                } catch (Throwable $exception) {
                    report($exception);
                    Notification::make()->title('No se pudo generar el reporte')->body('Intente nuevamente. Si el problema continúa, avise a soporte.')->danger()->send();

                    return null;
                }

                $path = OperationReportGenerator::absolutePathFor((int) $user->getAuthIdentifier(), $filename);

                return $path === null ? null : response()->download($path, $filename, ['Content-Type' => $format->mimeType()]);
            });
    }
}
