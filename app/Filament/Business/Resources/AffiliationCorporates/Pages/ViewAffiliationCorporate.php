<?php

namespace App\Filament\Business\Resources\AffiliationCorporates\Pages;

use App\Filament\Business\Resources\AffiliationCorporates\AffiliationCorporateResource;
use App\Filament\Business\Resources\AffiliationCorporates\Concerns\OptimizesAffiliationCorporateInfolistPerformance;
use App\Support\Filament\FilamentIosButton;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ViewAffiliationCorporate extends ViewRecord
{
    use OptimizesAffiliationCorporateInfolistPerformance;

    protected static string $resource = AffiliationCorporateResource::class;

    public function getRelationManagers(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Volver')
                ->icon(Heroicon::OutlinedArrowLeft)
                ->color('gray')
                ->url(AffiliationCorporateResource::getUrl())
                ->extraAttributes([
                    'class' => FilamentIosButton::extraClassForFilamentColor('gray'),
                ]),
            Action::make('attachDocuments')
                ->label('Adjuntar documentos')
                ->icon(Heroicon::OutlinedPaperClip)
                ->color('primary')
                ->extraAttributes([
                    'class' => FilamentIosButton::extraClassForFilamentColor('primary'),
                ])
                ->modalHeading('Adjuntar documentos al expediente')
                ->modalDescription('Puedes cargar uno o varios archivos en PDF o imagen.')
                ->modalSubmitActionLabel('Adjuntar')
                ->modalCancelActionLabel('Cancelar')
                ->modalSubmitAction(
                    fn (Action $action) => $action
                        ->color('primary')
                        ->extraAttributes([
                            'class' => FilamentIosButton::extraClassForFilamentColor('primary'),
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
                    FileUpload::make('documents')
                        ->label('Documentos')
                        ->disk('public')
                        ->directory('affiliation-corporates/expedientes')
                        ->preserveFilenames()
                        ->multiple()
                        ->acceptedFileTypes([
                            'application/pdf',
                            'image/jpeg',
                            'image/png',
                            'image/webp',
                        ])
                        ->maxFiles(15)
                        ->maxSize(10240)
                        ->downloadable()
                        ->openable()
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $files = collect($data['documents'] ?? [])
                        ->filter(fn (mixed $path): bool => is_string($path) && $path !== '')
                        ->values();

                    if ($files->isEmpty()) {
                        return;
                    }

                    $userId = auth()->id();

                    $this->record->affiliationCorporateDocuments()->createMany(
                        $files
                            ->map(function (string $path) use ($userId): array {
                                return [
                                    'file_path' => $path,
                                    'original_name' => basename($path),
                                    'mime_type' => Storage::disk('public')->mimeType($path) ?: null,
                                    'file_size' => Storage::disk('public')->size($path) ?: null,
                                    'uploaded_by' => $userId,
                                ];
                            })
                            ->all(),
                    );

                    $this->record->load('affiliationCorporateDocuments');

                    Notification::make()
                        ->success()
                        ->title('Documentos adjuntados')
                        ->body('El expediente se actualizó correctamente.')
                        ->send();
                }),
            Action::make('addObservation')
                ->label('Agregar observación')
                ->icon(Heroicon::OutlinedChatBubbleLeftRight)
                ->color('info')
                ->extraAttributes([
                    'class' => FilamentIosButton::extraClassForFilamentColor('info'),
                ])
                ->modalHeading('Registrar observación')
                ->modalDescription('La observación quedará asociada a esta afiliación corporativa y al analista que la registra.')
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
                    Textarea::make('description')
                        ->label('Texto de la observación')
                        ->placeholder('Escriba la nota o seguimiento administrativo…')
                        ->required()
                        ->minLength(2)
                        ->maxLength(5000)
                        ->rows(5),
                ])
                ->action(function (array $data): void {
                    $this->record->affiliationCorporateObservations()->create([
                        'description' => $data['description'],
                        'created_by' => (string) Auth::id(),
                    ]);

                    $this->record->unsetRelation('affiliationCorporateObservations');
                    $this->record->load('affiliationCorporateObservations.createdBy:id,name,email');

                    Notification::make()
                        ->success()
                        ->title('Observación guardada')
                        ->send();
                }),
            Action::make('audit')
                ->label('Auditoría')
                ->icon(Heroicon::OutlinedShieldCheck)
                ->color('warning')
                ->extraAttributes([
                    'class' => FilamentIosButton::extraClassForFilamentColor('warning'),
                ])
                ->visible(fn (): bool => $this->pendingAuditItems() !== [])
                ->modalHeading('Auditoría de la afiliación corporativa')
                ->modalDescription('Seleccione uno o varios puntos auditados. Se registrará una observación automática a nombre de INTEGRACORP-AUDITORIA y los puntos auditados se retirarán de la lista.')
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
            EditAction::make()
                ->extraAttributes([
                    'class' => FilamentIosButton::extraClassForFilamentColor('primary'),
                ]),
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
            'affiliation_info' => [
                'label' => 'Información de la Afiliación Corporativa',
                'detail' => 'Información de la afiliación corporativa (razón social, RIF, correo electrónico y número de teléfono).',
            ],
            'payer_info' => [
                'label' => 'Información completa del Pagador',
                'detail' => 'Información completa del pagador (responsable de pago).',
            ],
            'affiliates_info' => [
                'label' => 'Información del/los Afiliado(s)',
                'detail' => 'Información de los afiliados (nombre y apellido, cédula de identidad o RIF, correo electrónico y número de teléfono).',
            ],
            'medical_record' => [
                'label' => 'Ficha Médica (Afiliación Plan Especial)',
                'detail' => 'Ficha médica de la afiliación (declaración del plan especial).',
            ],
            'main_documents' => [
                'label' => 'Documento principal de la Afiliación y documentos de los Afiliados',
                'detail' => 'Documento principal de la afiliación corporativa y los documentos de los afiliados.',
            ],
            'ils_document' => [
                'label' => 'Documento de ILS',
                'detail' => 'Documento de ILS.',
            ],
        ];
    }

    /**
     * Claves ya auditadas para esta afiliación corporativa.
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

        $this->record->affiliationCorporateObservations()->create([
            'description' => $description,
            'created_by' => 'INTEGRACORP-AUDITORIA',
        ]);

        $this->record->audit_items = array_values(array_unique([
            ...$this->auditedItemKeys(),
            ...$validKeys,
        ]));
        $this->record->save();

        $this->record->unsetRelation('affiliationCorporateObservations');
        $this->record->load('affiliationCorporateObservations.createdBy:id,name,email');

        Notification::make()
            ->success()
            ->title('Auditoría registrada')
            ->body('Se registró la observación de auditoría con los puntos seleccionados.')
            ->send();
    }

    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable
    {
        $affiliationCorporate = $this->getRecord();

        $corporateName = $affiliationCorporate->name_corporate ?? 'Sin Nombre';
        $status = strtoupper((string) ($affiliationCorporate->status ?? ''));
        $badgeStyle = $this->badgeStyleForStatus($status);

        return new \Illuminate\Support\HtmlString(
            '<div style="display: flex; flex-direction: column; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif; gap: 2px; padding: 12px 0;">'
            .'<span class="text-sm font-bold uppercase tracking-tight text-gray-900 dark:text-white mb-2">'
            .'Afiliación Corporativa Nro: '.e($affiliationCorporate->code)
            .'</span>'
            .'<span class="text-3xl font-bold tracking-tight text-gray-900 dark:text-white mb-2">'
            .'Empresa: '.e($corporateName)
            .'</span>'
            .'<div style="display: flex; align-items: center; margin-top: 8px;">'
            .'<span style="'
            .'background-color: '.$badgeStyle['bg'].'; '
            .'color: #ffffff; '
            .'padding: 6px 16px; '
            .'border-radius: 50px; '
            .'font-size: 0.8rem; '
            .'font-weight: 700; '
            .'display: inline-flex; '
            .'align-items: center; '
            .'gap: 6px; '
            .'box-shadow: '.$badgeStyle['shadow'].'; '
            .'border: 1px solid rgba(255, 255, 255, 0.2);">'
            .'<span style="font-size: 10px;">●</span> '.e($status ?: 'Sin estado')
            .'</span>'
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
            'ACTIVA', 'ACTIVO' => [
                'bg' => '#28cd41',
                'shadow' => '0 4px 12px rgba(40, 205, 65, 0.35)',
            ],
            'EXCLUIDO', 'EXCLUIDA' => [
                'bg' => '#ff3b30',
                'shadow' => '0 4px 12px rgba(255, 59, 48, 0.35)',
            ],
            default => [
                'bg' => '#8e8e93',
                'shadow' => '0 4px 12px rgba(142, 142, 147, 0.35)',
            ],
        };
    }
}
