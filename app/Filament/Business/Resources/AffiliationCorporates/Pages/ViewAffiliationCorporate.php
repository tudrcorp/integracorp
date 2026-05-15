<?php

namespace App\Filament\Business\Resources\AffiliationCorporates\Pages;

use App\Filament\Business\Resources\AffiliationCorporates\AffiliationCorporateResource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class ViewAffiliationCorporate extends ViewRecord
{
    protected static string $resource = AffiliationCorporateResource::class;

    protected function resolveRecord(int|string $key): Model
    {
        $record = parent::resolveRecord($key);

        $record->load([
            'agent',
            'agency',
            'country',
            'state',
            'city',
            'region',
            'corporate_quote',
            'accountManager',
            'businessUnit',
            'businessLine',
            'affiliationCorporateDocuments',
        ]);

        return $record;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Volver')
                ->icon(Heroicon::OutlinedArrowLeft)
                ->color('gray')
                ->url(AffiliationCorporateResource::getUrl()),
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
            EditAction::make(),

        ];
    }
}
