<?php

declare(strict_types=1);

namespace App\Filament\Master\Pages;

use App\Filament\Shared\CommercialStructure\CommercialHierarchyFlowchart;
use App\Filament\Shared\CommercialStructure\Concerns\DownloadsCommercialHierarchyStructure;
use App\Models\Agency;
use App\Services\CommercialHierarchyExportService;
use App\Support\SecurityAudit;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class ViewMyHierarchy extends Page
{
    use DownloadsCommercialHierarchyStructure;

    protected static ?string $navigationLabel = 'Ver Jerarquía';

    protected static ?string $title = 'Mi jerarquía comercial';

    protected static ?string $slug = 'ver-jerarquia';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected string $view = 'filament.shared.pages.view-my-hierarchy';

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function canAccess(): bool
    {
        return filled(Auth::user()?->code_agency);
    }

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('export_hierarchy_pdf')
                ->label('Exportar PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->color('danger')
                ->tooltip('Descarga la jerarquía completa en PDF')
                ->action(fn (): ?BinaryFileResponse => $this->downloadHierarchy('pdf')),

            Action::make('export_hierarchy_excel')
                ->label('Exportar Excel')
                ->icon('heroicon-o-table-cells')
                ->color('success')
                ->tooltip('Descarga la jerarquía completa en Excel')
                ->action(fn (): ?BinaryFileResponse => $this->downloadHierarchy('xlsx')),
        ];
    }

    public function getHierarchyDiagram(): HtmlString
    {
        $agency = $this->resolveAgency();

        if (! $agency instanceof Agency) {
            return new HtmlString(
                '<div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-500/30 dark:bg-amber-950/40 dark:text-amber-100">'
                .'No se encontró la agencia asociada a tu usuario.'
                .'</div>'
            );
        }

        return CommercialHierarchyFlowchart::renderForAgency($agency);
    }

    /**
     * La agencia se resuelve siempre desde el usuario autenticado, nunca desde la petición:
     * la exportación no debe poder apuntar a la red de otra agencia.
     */
    private function resolveAgency(): ?Agency
    {
        $agencyCode = trim((string) (Auth::user()?->code_agency ?? ''));

        return $agencyCode !== ''
            ? Agency::query()->whereRaw('UPPER(TRIM(code)) = ?', [strtoupper($agencyCode)])->first()
            : null;
    }

    private function downloadHierarchy(string $format): ?BinaryFileResponse
    {
        $agency = $this->resolveAgency();

        if (! $agency instanceof Agency) {
            Notification::make()
                ->title('No se pudo exportar')
                ->body('No se encontró la agencia asociada a tu usuario.')
                ->danger()
                ->send();

            return null;
        }

        try {
            $path = $format === 'pdf'
                ? CommercialHierarchyExportService::toPdf($agency)
                : CommercialHierarchyExportService::toXlsx($agency);
        } catch (Throwable $exception) {
            SecurityAudit::log('AUDIT_MASTER_HIERARCHY_EXPORT_FAILED', 'master.hierarchy.export', [
                'agency_code' => $agency->code,
                'format' => $format,
                'error' => $exception->getMessage(),
            ]);

            Notification::make()
                ->title('No se pudo generar el reporte')
                ->body('Ocurrió un problema al armar el archivo. Intenta nuevamente o avisa a soporte.')
                ->danger()
                ->send();

            return null;
        }

        SecurityAudit::log('AUDIT_MASTER_HIERARCHY_EXPORTED', 'master.hierarchy.export', [
            'agency_code' => $agency->code,
            'format' => $format,
        ]);

        return response()
            ->download($path, CommercialHierarchyExportService::filenameFor($agency, $format))
            ->deleteFileAfterSend(true);
    }
}
