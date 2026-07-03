<?php

declare(strict_types=1);

namespace App\Support\Exports;

use App\Models\Plan;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Http\RedirectResponse;

final class CorporateAffiliationsReportExportAction
{
    public static function make(
        string $name = 'exportCorporateAffiliations',
        string $auditEvent = 'AUDIT_BUSINESS_CORPORATE_AFFILIATIONS_EXPORT',
        string $auditRoute = 'business.affiliation-corporates.export-report',
        string $modalHeading = 'Exportar afiliaciones corporativas',
        string $modalDescription = 'Descargue un reporte con datos de afiliación corporativa, planes y afiliados. Los filtros son opcionales.',
        string $planHelperText = 'Filtra por plan de contrato o plan del afiliado corporativo.',
    ): Action {
        return Action::make($name)
            ->label('Exportar Afiliados')
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->color('success')
            ->button()
            ->modalHeading($modalHeading)
            ->modalDescription($modalDescription)
            ->modalIcon(Heroicon::OutlinedDocumentChartBar)
            ->modalIconColor('success')
            ->modalWidth(Width::Large)
            ->form(self::formSchema($planHelperText))
            ->modalSubmitActionLabel('Descargar')
            ->modalSubmitAction(fn (Action $submitAction): Action => $submitAction
                ->icon(Heroicon::OutlinedArrowDownTray))
            ->modalCancelActionLabel('Cancelar')
            ->successNotification(null)
            ->action(function (array $data) use ($auditRoute): RedirectResponse {
                return redirect()->route($auditRoute, self::exportQueryParameters($data));
            });
    }

    /**
     * @param  array{plan_id?: int|string|null, affiliate_status?: string|null, format: string}  $data
     * @return array<string, int|string>
     */
    private static function exportQueryParameters(array $data): array
    {
        $parameters = [
            'format' => $data['format'],
        ];

        if (filled($data['plan_id'] ?? null)) {
            $parameters['plan_id'] = (int) $data['plan_id'];
        }

        if (filled($data['affiliate_status'] ?? null)) {
            $parameters['affiliate_status'] = (string) $data['affiliate_status'];
        }

        return $parameters;
    }

    /**
     * @return array<int, Select>
     */
    private static function formSchema(string $planHelperText): array
    {
        return [
            Select::make('plan_id')
                ->label('Tipo de plan')
                ->placeholder('Todos los planes')
                ->options(fn (): array => Plan::query()
                    ->orderBy('description')
                    ->pluck('description', 'id')
                    ->all())
                ->searchable()
                ->helperText($planHelperText),
            Select::make('affiliate_status')
                ->label('Estatus de afiliado')
                ->placeholder('Todos los estatus')
                ->options(CorporateAffiliationsExportService::affiliateStatusOptions())
                ->helperText('Filtra las filas del reporte según el estatus del afiliado corporativo.'),
            Select::make('format')
                ->label('Formato')
                ->options([
                    'xlsx' => 'Excel (.xlsx)',
                    'csv' => 'CSV',
                ])
                ->default('xlsx')
                ->required(),
        ];
    }
}
