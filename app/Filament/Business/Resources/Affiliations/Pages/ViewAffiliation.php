<?php

namespace App\Filament\Business\Resources\Affiliations\Pages;

use App\Filament\Business\Resources\Affiliations\AffiliationResource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class ViewAffiliation extends ViewRecord
{
    protected static string $resource = AffiliationResource::class;

    public function getRelationManagers(): array
    {
        return [];
    }

    protected function resolveRecord(int|string $key): Model
    {
        $record = parent::resolveRecord($key);

        $record->load([
            'affiliationDocuments',
            'renovationHistories.plan',
            'renovationHistories.previousPlan',
            'renovationHistories.coverage',
            'renovationHistories.affiliate',
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
                ->url(AffiliationResource::getUrl()),
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
            EditAction::make(),
        ];
    }

    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable
    {
        $affiliate = $this->getRecord();

        $fullName = $affiliate->full_name_ti ?? 'Sin Nombre';
        $status = strtoupper((string) ($affiliate->status ?? ''));
        $badgeStyle = $this->badgeStyleForStatus($status);

        return new \Illuminate\Support\HtmlString(
            '<div style="display: flex; flex-direction: column; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif; gap: 2px; padding: 12px 0;">'
            .'<span class="text-sm font-bold uppercase tracking-tight text-gray-900 dark:text-white mb-2">'
            .'Afiliación Nro: '.e($affiliate->code)
            .'</span>'
            .'<span class="text-3xl font-bold tracking-tight text-gray-900 dark:text-white mb-2">'
            .'Titular: '.e($fullName)
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
