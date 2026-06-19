<?php

namespace App\Filament\Business\Resources\Agents\Pages;

use App\Filament\Business\Resources\Agents\AgentResource;
use App\Filament\Business\Resources\Agents\Concerns\QueuesAgentFichaPdfEmail;
use App\Filament\Business\Resources\Helpdesks\Actions\HelpdeskTicketModalActions;
use App\Models\Agent;
use App\Support\BusinessAgentFichaPdfAccess;
use App\Support\Filament\FilamentIosButton;
use App\Support\SecurityAudit;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;

class ViewAgent extends ViewRecord
{
    use QueuesAgentFichaPdfEmail;

    protected static string $resource = AgentResource::class;

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
                ->url(AgentResource::getUrl())
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
            Action::make('agentFichaPreview')
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
                ->modalHeading(fn (): string => 'Ficha de agente · '.($this->getRecord()->name ?? ''))
                ->modalDescription(fn (): string => 'Vista previa, descarga y envío por correo o WhatsApp.')
                ->modalContent(fn (): \Illuminate\Contracts\View\View => $this->resolveAgentFichaPanelView())
                ->modalSubmitAction(false)
                ->modalCancelAction(
                    fn (Action $action): Action => $action
                        ->label('Cerrar')
                        ->extraAttributes([
                            'class' => HelpdeskTicketModalActions::IOS_GRAY_BTN,
                        ]),
                )
                ->action(fn (): null => null)
                ->visible(fn (): bool => BusinessAgentFichaPdfAccess::userCanAccess($this->getRecord())),
            Action::make('addObservation')
                ->label('Agregar observación')
                ->icon('heroicon-o-chat-bubble-left-right')
                ->color('info')
                ->extraAttributes([
                    'class' => FilamentIosButton::extraClassForFilamentColor('info'),
                ])
                ->modalHeading('Registrar observación')
                ->modalDescription('La observación quedará asociada a este agente y al analista que la registra.')
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
                    $this->record->observationCommercialStructures()->create([
                        'observation' => $data['observation'],
                        'created_by' => Auth::user()?->name ?? 'Analista',
                        'date' => now()->format('d/m/Y H:i'),
                    ]);

                    $this->record->unsetRelation('observationCommercialStructures');
                    $this->record->load('observationCommercialStructures');

                    Notification::make()
                        ->success()
                        ->title('Observación guardada')
                        ->send();
                }),
            Action::make('audit')
                ->label('Auditoría')
                ->icon('heroicon-o-shield-check')
                ->color('warning')
                ->extraAttributes([
                    'class' => FilamentIosButton::extraClassForFilamentColor('warning'),
                ])
                ->visible(fn (): bool => $this->pendingAuditItems() !== [])
                ->modalHeading('Auditoría del agente de corretaje')
                ->modalDescription('Seleccione uno o varios puntos auditados. Se registrará una observación automática en la bitácora a nombre de INTEGRACORP-AUDITORIA y los puntos auditados se retirarán de la lista.')
                ->modalSubmitActionLabel('Registrar auditoría')
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
                    CheckboxList::make('items')
                        ->label('Puntos a auditar')
                        ->options(fn (): array => $this->pendingAuditItemOptions())
                        ->descriptions(fn (): array => $this->pendingAuditItemDescriptions())
                        ->required()
                        ->bulkToggleable()
                        ->columns(1),
                ])
                ->action(function (array $data): void {
                    $this->registerAudit($data['items'] ?? []);
                }),
        ];
    }

    /**
     * Catálogo de puntos auditables: clave => [label, detail].
     *
     * @return array<string, array{label: string, detail: string}>
     */
    public static function auditItemsCatalog(): array
    {
        return [
            'main_info' => [
                'label' => 'Información principal del Agente',
                'detail' => 'Información principal del agente (nombre y apellido, dirección local o extranjera, correo electrónico válido, cédula de identidad o RIF válido, porcentaje de comisiones correctos e información bancaria).',
            ],
            'hierarchy' => [
                'label' => 'Jerarquía Correcta',
                'detail' => 'Jerarquía correcta de la estructura comercial.',
            ],
            'commissions' => [
                'label' => 'Comisiones Correctas y Actualizadas',
                'detail' => 'Comisiones correctas y actualizadas.',
            ],
            'bank_national' => [
                'label' => 'Información Bancaria Nacional',
                'detail' => 'Información bancaria nacional.',
            ],
            'bank_foreign' => [
                'label' => 'Información Bancaria Extranjera',
                'detail' => 'Información bancaria extranjera.',
            ],
            'documents' => [
                'label' => 'Documentos del Agente',
                'detail' => 'Documentos del agente.',
            ],
        ];
    }

    /**
     * Claves ya auditadas para este agente.
     *
     * @return array<int, string>
     */
    private function auditedItemKeys(): array
    {
        return array_values(array_filter(
            (array) ($this->record->audit_items ?? []),
            fn (mixed $key): bool => is_string($key) && array_key_exists($key, self::auditItemsCatalog()),
        ));
    }

    /**
     * Ítems del catálogo que aún no han sido auditados.
     *
     * @return array<string, array{label: string, detail: string}>
     */
    private function pendingAuditItems(): array
    {
        return array_diff_key(self::auditItemsCatalog(), array_flip($this->auditedItemKeys()));
    }

    /**
     * @return array<string, string>
     */
    private function pendingAuditItemOptions(): array
    {
        return array_map(fn (array $item): string => $item['label'], $this->pendingAuditItems());
    }

    /**
     * @return array<string, string>
     */
    private function pendingAuditItemDescriptions(): array
    {
        return array_map(fn (array $item): string => $item['detail'], $this->pendingAuditItems());
    }

    /**
     * @param  array<int, string>  $selectedKeys
     */
    private function registerAudit(array $selectedKeys): void
    {
        $catalog = self::auditItemsCatalog();

        $validKeys = array_values(array_filter(
            $selectedKeys,
            fn (mixed $key): bool => is_string($key) && array_key_exists($key, $catalog) && ! in_array($key, $this->auditedItemKeys(), true),
        ));

        if ($validKeys === []) {
            return;
        }

        $analyst = Auth::user()?->name ?? 'Analista';
        $auditedAt = now()->format('d/m/Y H:i');

        $lines = array_map(
            fn (string $key): string => '• '.$catalog[$key]['detail'],
            $validKeys,
        );

        $description = 'Auditoría registrada por el analista '.$analyst.' el '.$auditedAt.'.'.PHP_EOL
            .'Puntos auditados:'.PHP_EOL
            .implode(PHP_EOL, $lines);

        $this->record->observationCommercialStructures()->create([
            'observation' => $description,
            'created_by' => 'INTEGRACORP-AUDITORIA',
            'date' => $auditedAt,
        ]);

        $this->record->audit_items = array_values(array_unique([
            ...$this->auditedItemKeys(),
            ...$validKeys,
        ]));
        $this->record->save();

        $this->record->unsetRelation('observationCommercialStructures');
        $this->record->load('observationCommercialStructures');

        Notification::make()
            ->success()
            ->title('Auditoría registrada')
            ->body('Se registró la observación de auditoría en la bitácora con los puntos seleccionados.')
            ->send();
    }

    public function getTitle(): string|Htmlable
    {
        $agent = $this->getRecord();

        $code = (string) ($agent->code_agent ?? ('AGT-000'.$agent->id));
        $name = (string) ($agent->name ?? 'Sin nombre');
        $status = strtoupper((string) ($agent->status ?? 'SIN ESTADO'));
        $email = (string) ($agent->email ?? 'Sin correo');
        $phone = (string) ($agent->phone ?? 'Sin teléfono');
        $badgeStyle = $this->badgeStyleForStatus($status);

        return new HtmlString(
            '<div style="display:flex;flex-direction:column;gap:6px;padding:10px 0;">'
            .'<span class="text-sm font-bold uppercase tracking-tight text-gray-900 dark:text-white">'
            .'Agente: '.e($code)
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
            'ACTIVO' => ['bg' => '#16a34a', 'shadow' => '0 8px 20px rgba(22,163,74,.35)'],
            'PENDIENTE' => ['bg' => '#f59e0b', 'shadow' => '0 8px 20px rgba(245,158,11,.35)'],
            'INACTIVO' => ['bg' => '#dc2626', 'shadow' => '0 8px 20px rgba(220,38,38,.35)'],
            default => ['bg' => '#6b7280', 'shadow' => '0 8px 20px rgba(107,114,128,.35)'],
        };
    }

    private function resolveAgentFichaPanelView(): \Illuminate\Contracts\View\View
    {
        /** @var Agent $agent */
        $agent = $this->getRecord();
        $agent->loadMissing(['typeAgent']);

        SecurityAudit::log('AUDIT_BUSINESS_AGENT_FICHA_VIEWED', 'business.agents.ficha-pdf.view-page', [
            'agent_id' => $agent->getKey(),
            'agent_name' => $agent->name,
            'source' => 'view_agent_header',
        ]);

        return view('filament.business.agents.agent-ficha-panel', [
            'record' => $agent,
        ]);
    }
}
