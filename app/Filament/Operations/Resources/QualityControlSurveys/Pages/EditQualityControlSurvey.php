<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\QualityControlSurveys\Pages;

use App\Filament\Operations\Resources\QualityControlSurveys\QualityControlSurveyResource;
use App\Models\QualityControlSurvey;
use App\Support\Operations\QualityControl\QualityControlSurveyWriter;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

abstract class EditQualityControlSurvey extends EditRecord
{
    public function getTitle(): string|Htmlable
    {
        return 'Editar encuesta de '.$this->getRecord()->getAttribute('patient_name');
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make()->label('Ver encuesta'),
            static::deleteSurveyAction(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var class-string<QualityControlSurveyResource> $resource */
        $resource = static::getResource();
        abort_unless($record instanceof QualityControlSurvey && $resource::canEdit($record), 403);

        return QualityControlSurveyWriter::update($record, $data, Auth::id());
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Encuesta actualizada';
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }

    public static function deleteSurveyAction(): Action
    {
        return Action::make('deleteSurvey')
            ->label('Eliminar')
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->visible(fn (Model $record, Page $livewire): bool => $record instanceof QualityControlSurvey && $livewire::getResource()::canDelete($record))
            ->requiresConfirmation()
            ->modalHeading('Eliminar encuesta')
            ->modalDescription('La encuesta dejará de contar en los resultados y no volverá a entrar aunque se reimporte el mismo Excel. Queda registro en la auditoría.')
            ->modalSubmitActionLabel('Sí, eliminar')
            ->action(function (Model $record, Page $livewire): void {
                abort_unless($record instanceof QualityControlSurvey && $livewire::getResource()::canDelete($record), 403);

                QualityControlSurveyWriter::delete($record);

                Notification::make()->title('Encuesta eliminada')->success()->send();

                $livewire->redirect($livewire::getResource()::getUrl('index'), navigate: true);
            });
    }
}
