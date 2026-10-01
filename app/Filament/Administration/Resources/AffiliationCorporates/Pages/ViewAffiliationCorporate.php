<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\AffiliationCorporates\Pages;

use App\Filament\Administration\Resources\AffiliationCorporates\Actions\AffiliationCorporateFichaPdfActions;
use App\Filament\Administration\Resources\AffiliationCorporates\AffiliationCorporateResource;
use App\Filament\Business\Resources\AffiliationCorporates\Concerns\OptimizesAffiliationCorporateInfolistPerformance;
use App\Models\AffiliationCorporate;
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

class ViewAffiliationCorporate extends ViewRecord
{
    use OptimizesAffiliationCorporateInfolistPerformance;

    protected static string $resource = AffiliationCorporateResource::class;

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
                ->url(AffiliationCorporateResource::getUrl()),
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
                AffiliationCorporateFichaPdfActions::printCorporatePdfAction()
                    ->extraAttributes([
                        'class' => self::SUCCESS_BUTTON_CLASS,
                    ]),
                EditAction::make()
                    ->label('Editar')
                    ->icon(Heroicon::OutlinedPencil)
                    ->color('primary'),
            ]),
        ];
    }

    public function getTitle(): string|Htmlable
    {
        return 'Afiliación corporativa '.($this->getRecord()->code ?? '');
    }

    public function getHeading(): string|Htmlable
    {
        /** @var AffiliationCorporate $affiliation */
        $affiliation = $this->getRecord();

        return RecordPageHeader::render(
            eyebrow: 'Afiliación corporativa · '.($affiliation->code ?? 'Sin código'),
            title: (string) ($affiliation->name_corporate ?: 'Sin razón social'),
            status: RecordPageHeader::statusFor($affiliation->status),
            chips: [
                filled($affiliation->payment_frequency) ? RecordPageHeader::tag('Pago '.mb_strtolower((string) $affiliation->payment_frequency), RecordPageHeader::TONE_NEUTRAL) : null,
                filled($affiliation->white_company_id) ? RecordPageHeader::tag('Empresa aliada', RecordPageHeader::TONE_VIOLET) : null,
            ],
            facts: [
                'RIF' => $affiliation->rif,
                'Tarifa anual' => RecordPageHeader::money($affiliation->fee_anual, hideZero: true),
                'Monto por cuota' => RecordPageHeader::money($affiliation->total_amount, hideZero: true),
            ],
        );
    }
}
