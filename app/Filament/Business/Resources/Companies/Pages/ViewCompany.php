<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\Companies\Pages;

use App\Filament\Business\Resources\Companies\Actions\CompanyTableActions;
use App\Filament\Business\Resources\Companies\CompanyResource;
use App\Models\Company;
use App\Support\Companies\CompanyAssociateRegistrar;
use App\Support\Companies\CompanyResponsibleDays;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;

class ViewCompany extends ViewRecord
{
    protected static string $resource = CompanyResource::class;

    private const IOS_BUTTON_BASE = ' shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    private const IOS_GRAY_BUTTON_CLASS = 'ticket-btn-ios-gray'.self::IOS_BUTTON_BASE;

    private const IOS_PRIMARY_BUTTON_CLASS = 'aviso-btn-ios-primary'.self::IOS_BUTTON_BASE;

    private const IOS_SUCCESS_BUTTON_CLASS = 'aviso-btn-ios-success'.self::IOS_BUTTON_BASE;

    private const IOS_WARNING_BUTTON_CLASS = 'aviso-btn-ios-warning'.self::IOS_BUTTON_BASE;

    private const IOS_INFO_BUTTON_CLASS = 'aviso-btn-ios-info'.self::IOS_BUTTON_BASE;

    protected function getHeaderActions(): array
    {
        return [
            CompanyTableActions::uploadPaymentVoucherAction()
                ->extraAttributes([
                    'class' => self::IOS_PRIMARY_BUTTON_CLASS,
                ]),
            CompanyTableActions::sendPublicRegistrationLinkAction()
                ->extraAttributes([
                    'class' => self::IOS_WARNING_BUTTON_CLASS,
                ]),
            Action::make('openRegistrationLink')
                ->label('Abrir enlace público')
                ->icon('heroicon-o-link')
                ->color('success')
                ->url(fn (Company $record): string => CompanyAssociateRegistrar::publicRegistrationUrl($record))
                ->openUrlInNewTab()
                ->extraAttributes([
                    'class' => self::IOS_SUCCESS_BUTTON_CLASS,
                ]),
            Action::make('addObservation')
                ->label('Agregar Notas/Observaciones')
                ->icon(Heroicon::OutlinedChatBubbleLeftRight)
                ->color('info')
                ->extraAttributes([
                    'class' => self::IOS_INFO_BUTTON_CLASS,
                ])
                ->modalHeading('Registrar nota u observación')
                ->modalDescription('La nota quedará asociada a esta empresa y al analista que la registra.')
                ->modalSubmitActionLabel('Guardar')
                ->modalCancelActionLabel('Cancelar')
                ->modalSubmitAction(
                    fn (Action $action) => $action
                        ->color('info')
                        ->extraAttributes([
                            'class' => self::IOS_INFO_BUTTON_CLASS,
                        ])
                )
                ->modalCancelAction(
                    fn (Action $action) => $action
                        ->color('gray')
                        ->extraAttributes([
                            'class' => self::IOS_GRAY_BUTTON_CLASS,
                        ])
                )
                ->form([
                    Textarea::make('description')
                        ->label('Texto de la nota u observación')
                        ->placeholder('Escriba la nota o seguimiento administrativo…')
                        ->required()
                        ->minLength(2)
                        ->maxLength(5000)
                        ->rows(5),
                ])
                ->action(function (array $data): void {
                    /** @var Company $company */
                    $company = $this->getRecord();

                    $company->companyObservations()->create([
                        'description' => $data['description'],
                        'created_by' => (string) Auth::id(),
                    ]);

                    $company->unsetRelation('companyObservations');
                    $company->load('companyObservations.createdBy:id,name,email');

                    Notification::make()
                        ->success()
                        ->title('Nota u observación guardada')
                        ->send();
                }),
            Action::make('back')
                ->label('Volver')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(CompanyResource::getUrl())
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
        ];
    }

    public function getTitle(): string|Htmlable
    {
        /** @var Company $company */
        $company = $this->getRecord();
        $name = (string) ($company->name ?? 'Sin nombre');
        $rif = (string) ($company->rif ?? '—');
        $responsiblesCount = (int) ($company->responsibles_count ?? $company->responsibles()->count());
        $population = CompanyResponsibleDays::populationTotalFor($company->planGenerator);
        $contracted = (int) ($company->responsibles_sum_contracted_days ?? 0);

        $populationLine = $population !== null
            ? e(number_format($contracted, 0, ',', '.')).' / '.e(number_format($population, 0, ',', '.')).' días contratados'
            : e((string) $responsiblesCount).' responsable(s)';

        return new HtmlString(
            '<div class="flex flex-col gap-2">'
            .'<span class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">'.e($name).'</span>'
            .'<div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">'
            .'<span style="background-color:#6b7280;color:#fff;padding:5px 14px;border-radius:999px;font-size:.78rem;font-weight:700;box-shadow:0 8px 20px rgba(107,114,128,.35);">'
            .e($rif)
            .'</span>'
            .'<span class="text-sm text-gray-600 dark:text-gray-300">'.$populationLine.'</span>'
            .'</div>'
            .'</div>'
        );
    }
}
