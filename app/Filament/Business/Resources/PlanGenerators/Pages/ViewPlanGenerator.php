<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\PlanGenerators\Pages;

use App\Filament\Business\Resources\Helpdesks\Actions\HelpdeskTicketModalActions;
use App\Filament\Business\Resources\PlanGenerators\PlanGeneratorResource;
use App\Models\PlanGenerator;
use App\Support\PlanGeneratorPdfAccess;
use App\Support\SecurityAudit;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

class ViewPlanGenerator extends ViewRecord
{
    protected static string $resource = PlanGeneratorResource::class;

    private const IOS_BUTTON_BASE = ' shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    private const IOS_GRAY_BUTTON_CLASS = 'ticket-btn-ios-gray'.self::IOS_BUTTON_BASE;

    private const IOS_PRIMARY_BUTTON_CLASS = 'aviso-btn-ios-primary'.self::IOS_BUTTON_BASE;

    private const IOS_SUCCESS_BUTTON_CLASS = 'aviso-btn-ios-success'.self::IOS_BUTTON_BASE;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('approveQuote')
                ->label('Aprobar cotización')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->extraAttributes([
                    'class' => self::IOS_SUCCESS_BUTTON_CLASS,
                ])
                ->requiresConfirmation()
                ->modalHeading('Aprobar cotización')
                ->modalDescription('Se marcará la cotización como APROBADA y continuarás con el registro de la empresa.')
                ->modalSubmitActionLabel('Aprobar y continuar')
                ->action(function (): void {
                    $this->approveQuote();
                })
                ->visible(fn (): bool => strtoupper((string) ($this->getRecord()->status ?? '')) === 'PRE-APROBADO'),
            Action::make('back')
                ->label('Volver')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(PlanGeneratorResource::getUrl())
                ->extraAttributes([
                    'class' => self::IOS_GRAY_BUTTON_CLASS,
                ]),
            EditAction::make()
                ->label('Editar')
                ->icon('heroicon-o-pencil')
                ->color('primary')
                ->extraAttributes([
                    'class' => self::IOS_PRIMARY_BUTTON_CLASS,
                ]),
            Action::make('planPdfPreview')
                ->label('Vista previa PDF')
                ->icon('heroicon-o-document-text')
                ->color('success')
                ->extraAttributes([
                    'class' => self::IOS_SUCCESS_BUTTON_CLASS,
                ])
                ->slideOver()
                ->formWrapper(false)
                ->modalWidth(Width::FiveExtraLarge)
                ->extraModalWindowAttributes([
                    'class' => 'fi-agency-command-center-window',
                ])
                ->modalHeading(fn (): string => 'Plan generado · '.($this->getRecord()->name ?? ''))
                ->modalDescription(fn (): string => 'Vista previa y descarga del PDF con la matriz de beneficios.')
                ->modalContent(fn (): \Illuminate\Contracts\View\View => $this->resolvePlanPdfPanelView())
                ->modalSubmitAction(false)
                ->modalCancelAction(
                    fn (Action $action): Action => $action
                        ->label('Cerrar')
                        ->extraAttributes([
                            'class' => HelpdeskTicketModalActions::IOS_GRAY_BTN,
                        ]),
                )
                ->action(fn (): null => null)
                ->visible(fn (): bool => PlanGeneratorPdfAccess::userCanAccess()),
        ];
    }

    public function approveQuote(): void
    {
        /** @var PlanGenerator $plan */
        $plan = $this->getRecord();

        $plan->update(['status' => 'APROBADA']);

        SecurityAudit::log('AUDIT_BUSINESS_PLAN_GENERATOR_APPROVED', 'business.plan-generators.approve-quote', [
            'plan_generator_id' => $plan->getKey(),
            'plan_name' => $plan->name,
        ]);

        Notification::make()
            ->title('Cotización aprobada')
            ->body('Continúa con el registro de la empresa.')
            ->success()
            ->send();

        $this->redirect(PlanGeneratorResource::getUrl('register-company', ['record' => $plan->getKey()]));
    }

    public function getTitle(): string|Htmlable
    {
        $plan = $this->getRecord();
        $name = (string) ($plan->name ?? 'Sin nombre');
        $status = strtoupper((string) ($plan->status ?? 'SIN ESTADO'));
        $badgeStyle = $this->badgeStyleForStatus($status);

        return new HtmlString(
            '<div class="flex flex-col gap-2">'
            .'<span class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">'.e($name).'</span>'
            .'<div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">'
            .'<span style="background-color: '.$badgeStyle['bg'].';color:#fff;padding:5px 14px;border-radius:999px;font-size:.78rem;font-weight:700;box-shadow:'.$badgeStyle['shadow'].';">'
            .e($status)
            .'</span>'
            .'<span class="text-sm text-gray-600 dark:text-gray-300">'.e((string) ($plan->columns_count ?? $plan->columns()->count())).' columnas</span>'
            .'<span class="text-sm text-gray-600 dark:text-gray-300">'.e((string) ($plan->rows_count ?? $plan->rows()->count())).' beneficios</span>'
            .'</div>'
            .'</div>'
        );
    }

    /**
     * @return array{bg: string, shadow: string}
     */
    private function badgeStyleForStatus(string $status): array
    {
        return match ($status) {
            'ACTIVO', 'ACTIVA' => ['bg' => '#16a34a', 'shadow' => '0 8px 20px rgba(22,163,74,.35)'],
            'APROBADA', 'APROBADO' => ['bg' => '#16a34a', 'shadow' => '0 8px 20px rgba(22,163,74,.35)'],
            'PRE-APROBADO' => ['bg' => '#d97706', 'shadow' => '0 8px 20px rgba(217,119,6,.35)'],
            'INACTIVO', 'INACTIVA' => ['bg' => '#6b7280', 'shadow' => '0 8px 20px rgba(107,114,128,.35)'],
            default => ['bg' => '#6b7280', 'shadow' => '0 8px 20px rgba(107,114,128,.35)'],
        };
    }

    private function resolvePlanPdfPanelView(): \Illuminate\Contracts\View\View
    {
        /** @var PlanGenerator $planGenerator */
        $planGenerator = $this->getRecord();

        SecurityAudit::log('AUDIT_BUSINESS_PLAN_GENERATOR_PDF_VIEWED', 'business.plan-generators.pdf.view-page', [
            'plan_generator_id' => $planGenerator->getKey(),
            'plan_name' => $planGenerator->name,
            'source' => 'view_plan_generator_header',
        ]);

        return view('filament.business.plan-generators.plan-pdf-panel', [
            'record' => $planGenerator,
        ]);
    }
}
