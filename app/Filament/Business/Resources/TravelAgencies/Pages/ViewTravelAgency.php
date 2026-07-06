<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\TravelAgencies\Pages;

use App\Filament\Business\Resources\Helpdesks\Actions\HelpdeskTicketModalActions;
use App\Filament\Business\Resources\TravelAgencies\Concerns\QueuesTravelAgencyFichaPdfSharing;
use App\Filament\Business\Resources\TravelAgencies\Schemas\TravelAgencyForm;
use App\Filament\Business\Resources\TravelAgencies\TravelAgencyResource;
use App\Models\TravelAgency;
use App\Support\BusinessTravelAgencyFichaPdfAccess;
use App\Support\Filament\FilamentIosButton;
use App\Support\SecurityAudit;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;

class ViewTravelAgency extends ViewRecord
{
    use QueuesTravelAgencyFichaPdfSharing;

    protected static string $resource = TravelAgencyResource::class;

    private const IOS_BUTTON_BASE = ' shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    private const IOS_GRAY_BUTTON_CLASS = 'ticket-btn-ios-gray'.self::IOS_BUTTON_BASE;

    private const IOS_PRIMARY_BUTTON_CLASS = 'aviso-btn-ios-primary'.self::IOS_BUTTON_BASE;

    private const IOS_SUCCESS_BUTTON_CLASS = 'aviso-btn-ios-success'.self::IOS_BUTTON_BASE;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Volver')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(TravelAgencyResource::getUrl())
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
            Action::make('travelAgencyFichaPreview')
                ->label('Ficha PDF')
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
                ->modalHeading(fn (): string => 'Ficha de agencia de viajes · '.($this->getRecord()->name ?? ''))
                ->modalDescription(fn (): string => 'Vista previa, descarga y envío por correo o WhatsApp.')
                ->modalContent(fn (): \Illuminate\Contracts\View\View => $this->resolveTravelAgencyFichaPanelView())
                ->modalSubmitAction(false)
                ->modalCancelAction(
                    fn (Action $action): Action => $action
                        ->label('Cerrar')
                        ->extraAttributes([
                            'class' => HelpdeskTicketModalActions::IOS_GRAY_BTN,
                        ]),
                )
                ->action(fn (): null => null)
                ->visible(fn (): bool => BusinessTravelAgencyFichaPdfAccess::userCanAccess($this->getRecord())),
            Action::make('addTravelAgents')
                ->label('Agregar agentes')
                ->icon('heroicon-o-user-group')
                ->color('warning')
                ->extraAttributes([
                    'class' => FilamentIosButton::extraClassForFilamentColor('warning'),
                ])
                ->modalHeading('Registrar agentes')
                ->modalDescription('Agregue uno o varios agentes asociados a esta agencia de viajes.')
                ->modalSubmitActionLabel('Guardar')
                ->modalCancelActionLabel('Cancelar')
                ->modalSubmitAction(
                    fn (Action $action) => $action
                        ->color('warning')
                        ->extraAttributes([
                            'class' => FilamentIosButton::extraClassForFilamentColor('warning'),
                        ])
                )
                ->modalCancelAction(
                    fn (Action $action) => $action
                        ->color('gray')
                        ->extraAttributes([
                            'class' => FilamentIosButton::extraClassForFilamentColor('gray'),
                        ])
                )
                ->form([
                    TravelAgencyForm::travelAgentsRepeater(useRelationship: false),
                ])
                ->action(function (array $data): void {
                    $agents = $data['travelAgents'] ?? [];

                    if ($agents === []) {
                        Notification::make()
                            ->warning()
                            ->title('Sin agentes')
                            ->body('Debe registrar al menos un agente.')
                            ->send();

                        return;
                    }

                    $createdBy = Auth::user()?->name ?? 'Analista';
                    $createdCount = 0;

                    foreach ($agents as $agentData) {
                        $this->record->travelAgents()->create([
                            'name' => $agentData['name'] ?? '',
                            'cargo' => $agentData['cargo'] ?? '',
                            'email' => $agentData['email'] ?? '',
                            'phone' => $agentData['phone'] ?? '',
                            'fechaNacimiento' => $this->formatAgentBirthDate($agentData['fechaNacimiento'] ?? null),
                            'created_by' => $createdBy,
                            'updated_by' => $createdBy,
                        ]);

                        $createdCount++;
                    }

                    $this->record->unsetRelation('travelAgents');
                    $this->record->load('travelAgents');

                    SecurityAudit::log('AUDIT_BUSINESS_TRAVEL_AGENCY_AGENTS_ADDED', 'business.travel-agencies.add-agents', [
                        'panel' => 'business',
                        'module' => 'travel_agencies',
                        'travel_agency_id' => $this->record->getKey(),
                        'travel_agency_name' => $this->record->name,
                        'created_by' => $createdBy,
                        'agents_count' => $createdCount,
                    ]);

                    Notification::make()
                        ->success()
                        ->title($createdCount > 1 ? 'Agentes guardados' : 'Agente guardado')
                        ->body($createdCount > 1
                            ? "Se registraron {$createdCount} agentes en la agencia."
                            : 'Se registró 1 agente en la agencia.')
                        ->send();
                }),
            Action::make('addObservation')
                ->label('Agregar observación')
                ->icon('heroicon-o-chat-bubble-left-right')
                ->color('info')
                ->extraAttributes([
                    'class' => FilamentIosButton::extraClassForFilamentColor('info'),
                ])
                ->modalHeading('Registrar observación')
                ->modalDescription('La observación quedará asociada a esta agencia de viajes y al analista que la registra.')
                ->modalSubmitActionLabel('Guardar')
                ->modalCancelActionLabel('Cancelar')
                ->modalSubmitAction(
                    fn (Action $action) => $action
                        ->color('info')
                        ->extraAttributes([
                            'class' => FilamentIosButton::extraClassForFilamentColor('info'),
                        ])
                )
                ->modalCancelAction(
                    fn (Action $action) => $action
                        ->color('gray')
                        ->extraAttributes([
                            'class' => FilamentIosButton::extraClassForFilamentColor('gray'),
                        ])
                )
                ->form([
                    Textarea::make('observation')
                        ->label('Texto de la observación')
                        ->placeholder('Escriba la nota o seguimiento administrativo…')
                        ->required()
                        ->minLength(2)
                        ->maxLength(5000)
                        ->rows(5),
                ])
                ->action(function (array $data): void {
                    $createdBy = Auth::user()?->name ?? 'Analista';

                    $this->record->observationCommercialStructures()->create([
                        'observation' => $data['observation'],
                        'created_by' => $createdBy,
                        'date' => now()->format('d/m/Y H:i'),
                    ]);

                    $this->record->unsetRelation('observationCommercialStructures');
                    $this->record->load('observationCommercialStructures');

                    SecurityAudit::log('AUDIT_BUSINESS_TRAVEL_AGENCY_OBSERVATION_ADDED', 'business.travel-agencies.add-observation', [
                        'panel' => 'business',
                        'module' => 'travel_agencies',
                        'travel_agency_id' => $this->record->getKey(),
                        'travel_agency_name' => $this->record->name,
                        'created_by' => $createdBy,
                        'observation' => \Illuminate\Support\Str::limit((string) ($data['observation'] ?? ''), 500),
                    ]);

                    Notification::make()
                        ->success()
                        ->title('Observación guardada')
                        ->send();
                }),
        ];
    }

    public function getTitle(): string|Htmlable
    {
        $agency = $this->getRecord();

        $identification = (string) ($agency->numberIdentification ?? 'Sin identificación');
        $name = (string) ($agency->name ?? 'Sin nombre');
        $status = strtoupper((string) ($agency->status ?? 'SIN ESTADO'));
        $email = (string) ($agency->email ?? 'Sin correo');
        $phone = (string) ($agency->phone ?? 'Sin teléfono');
        $badgeStyle = $this->badgeStyleForStatus($status);

        return new HtmlString(
            '<div style="display:flex;flex-direction:column;gap:6px;padding:10px 0;">'
            .'<span class="text-sm font-bold uppercase tracking-tight text-gray-900 dark:text-white">'
            .'Agencia de viajes: J/V/E-'.e($identification)
            .'</span>'
            .'<span class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">'
            .e($name)
            .'</span>'
            .'<div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">'
            .'<span style="background-color: '.$badgeStyle['bg'].';color:#fff;padding:5px 14px;border-radius:999px;font-size:.78rem;font-weight:700;box-shadow:'.$badgeStyle['shadow'].';">'
            .e($status)
            .'</span>'
            .'<span class="text-sm text-gray-600 dark:text-gray-300">📧 '.e($email).'</span>'
            .'<span class="text-sm text-gray-600 dark:text-gray-300">📞 '.e($phone).'</span>'
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
            'ACTIVO', 'ACTIVA', 'APROBADO', 'APROBADA' => ['bg' => '#16a34a', 'shadow' => '0 8px 20px rgba(22,163,74,.35)'],
            'PENDIENTE', 'POR REVISAR', 'EN REVISIÓN', 'EN REVISION' => ['bg' => '#f59e0b', 'shadow' => '0 8px 20px rgba(245,158,11,.35)'],
            'INACTIVO', 'INACTIVA', 'SUSPENDIDO', 'RECHAZADO' => ['bg' => '#dc2626', 'shadow' => '0 8px 20px rgba(220,38,38,.35)'],
            default => ['bg' => '#6b7280', 'shadow' => '0 8px 20px rgba(107,114,128,.35)'],
        };
    }

    private function formatAgentBirthDate(mixed $value): string
    {
        if ($value instanceof \Carbon\CarbonInterface) {
            return $value->format('d/m/Y');
        }

        return trim((string) ($value ?? ''));
    }

    private function resolveTravelAgencyFichaPanelView(): \Illuminate\Contracts\View\View
    {
        /** @var TravelAgency $travelAgency */
        $travelAgency = $this->getRecord();

        SecurityAudit::log('AUDIT_BUSINESS_TRAVEL_AGENCY_FICHA_VIEWED', 'business.travel-agencies.ficha-pdf.view-page', [
            'travel_agency_id' => $travelAgency->getKey(),
            'travel_agency_name' => $travelAgency->name,
            'source' => 'view_travel_agency_header',
        ]);

        return view('filament.business.travel-agencies.travel-agency-ficha-panel', [
            'record' => $travelAgency,
        ]);
    }
}
