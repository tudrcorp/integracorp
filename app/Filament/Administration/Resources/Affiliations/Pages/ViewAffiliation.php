<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\Affiliations\Pages;

use App\Filament\Administration\Resources\Affiliations\Actions\AffiliationFichaPdfActions;
use App\Filament\Administration\Resources\Affiliations\AffiliationResource;
use App\Filament\Business\Resources\Affiliations\Concerns\OptimizesAffiliationInfolistPerformance;
use App\Filament\Shared\Affiliations\MergeFamilyGroupAction;
use App\Models\Affiliation;
use App\Support\Filament\FilamentIosActionsMenu;
use App\Support\Filament\RecordPageHeader;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Storage;

class ViewAffiliation extends ViewRecord
{
    use OptimizesAffiliationInfolistPerformance;

    protected static string $resource = AffiliationResource::class;

    private const IOS_BUTTON_BASE = 'shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    private const WARNING_BUTTON_CLASS = 'aviso-btn-ios-warning '.self::IOS_BUTTON_BASE;

    private const SUCCESS_BUTTON_CLASS = 'aviso-btn-ios-success '.self::IOS_BUTTON_BASE;

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
                ->color('warning')
                ->extraAttributes([
                    'class' => self::WARNING_BUTTON_CLASS,
                ])
                ->url(AffiliationResource::getUrl()),
            FilamentIosActionsMenu::make([
                Action::make('attachDocuments')
                    ->label('Adjuntar documentos')
                    ->icon(Heroicon::OutlinedPaperClip)
                    ->color('primary')
                    ->modalHeading('Adjuntar documentos al expediente')
                    ->modalDescription('Puedes cargar uno o varios archivos en PDF o imagen.')
                    ->form([
                        FileUpload::make('documents')
                            ->label('Documentos')
                            ->disk('public')
                            ->directory('affiliations/expedientes')
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

                        $this->record->affiliationDocuments()->createMany(
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

                        $this->record->load('affiliationDocuments');

                        Notification::make()
                            ->success()
                            ->title('Documentos adjuntados')
                            ->body('El expediente se actualizó correctamente.')
                            ->send();
                    }),
                MergeFamilyGroupAction::make(),
                EditAction::make()
                    ->label('Compensar Pago')
                    ->icon(Heroicon::OutlinedCreditCard)
                    ->color('primary'),
                AffiliationFichaPdfActions::printIndividualPdfAction(),
                // ->extraAttributes([
                //     'class' => self::SUCCESS_BUTTON_CLASS,
                // ]),
            ]),
        ];
    }

    public function getTitle(): string|Htmlable
    {
        return 'Afiliación '.($this->getRecord()->code ?? '');
    }

    public function getHeading(): string|Htmlable
    {
        /** @var Affiliation $affiliation */
        $affiliation = $this->getRecord();

        return RecordPageHeader::render(
            eyebrow: 'Afiliación individual · '.($affiliation->code ?? 'Sin código'),
            title: (string) ($affiliation->full_name_ti ?: 'Sin nombre de titular'),
            status: RecordPageHeader::statusFor($affiliation->status),
            chips: [
                filled($affiliation->plan?->description) ? RecordPageHeader::tag((string) $affiliation->plan->description) : null,
                filled($affiliation->payment_frequency) ? RecordPageHeader::tag('Pago '.mb_strtolower((string) $affiliation->payment_frequency), RecordPageHeader::TONE_NEUTRAL) : null,
                filled($affiliation->white_company_id) ? RecordPageHeader::tag('Empresa aliada', RecordPageHeader::TONE_VIOLET) : null,
            ],
            facts: [
                'Cédula titular' => $affiliation->nro_identificacion_ti,
                'Tarifa anual' => RecordPageHeader::money($affiliation->fee_anual, hideZero: true),
                'Monto por cuota' => RecordPageHeader::money($affiliation->total_amount, hideZero: true),
            ],
        );
    }
}
