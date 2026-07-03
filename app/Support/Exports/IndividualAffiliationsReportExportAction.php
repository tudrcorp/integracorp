<?php

declare(strict_types=1);

namespace App\Support\Exports;

use App\Models\Plan;
use App\Support\SecurityAudit;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class IndividualAffiliationsReportExportAction
{
    public static function make(
        string $name = 'exportIndividualAffiliations',
        string $auditEvent = 'AUDIT_BUSINESS_INDIVIDUAL_AFFILIATIONS_EXPORT',
        string $auditRoute = 'business.affiliations.export-report',
        string $modalHeading = 'Exportar afiliaciones individuales',
        string $modalDescription = 'Descargue un reporte con datos de afiliación y afiliados. Los filtros son opcionales.',
        string $planHelperText = 'Filtra por el plan de la afiliación o del afiliado.',
    ): Action {
        return Action::make($name)
            ->label('Exportar reporte')
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->color('success')
            ->modalHeading($modalHeading)
            ->modalDescription($modalDescription)
            ->modalIcon(Heroicon::OutlinedDocumentChartBar)
            ->modalIconColor('success')
            ->modalSubmitActionLabel('Descargar')
            ->modalCancelActionLabel('Cancelar')
            ->modalWidth(Width::Large)
            ->form([
                Select::make('plan_id')
                    ->label('Tipo de plan')
                    ->searchable()
                    ->preload()
                    ->options(fn (): array => Plan::query()
                        ->orderBy('description')
                        ->pluck('description', 'id')
                        ->all())
                    ->placeholder('Todos los planes')
                    ->helperText($planHelperText),
                Select::make('affiliate_status')
                    ->label('Estatus de afiliado')
                    ->options(IndividualAffiliationsExportService::affiliateStatusOptions())
                    ->placeholder('Todos los estatus')
                    ->helperText('Filtra las filas del reporte según el estatus del afiliado.'),
                Select::make('format')
                    ->label('Formato')
                    ->options([
                        'xlsx' => 'Excel (.xlsx)',
                        'csv' => 'CSV',
                    ])
                    ->default('xlsx')
                    ->required()
                    ->native(false),
            ])
            ->action(function (array $data) use ($auditEvent, $auditRoute): StreamedResponse|BinaryFileResponse {
                $filters = [
                    'plan_id' => $data['plan_id'] ?? null,
                    'affiliate_status' => $data['affiliate_status'] ?? null,
                ];

                SecurityAudit::log($auditEvent, $auditRoute, [
                    'plan_id' => $filters['plan_id'],
                    'affiliate_status' => $filters['affiliate_status'],
                    'format' => $data['format'] ?? 'xlsx',
                    'exported_by_user_id' => Auth::id(),
                ]);

                $service = app(IndividualAffiliationsExportService::class);

                return ($data['format'] ?? 'xlsx') === 'csv'
                    ? $service->streamCsv($filters)
                    : $service->downloadXlsx($filters);
            });
    }
}
