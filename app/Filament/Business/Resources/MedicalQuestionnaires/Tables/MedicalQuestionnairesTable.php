<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\MedicalQuestionnaires\Tables;

use App\Models\MedicalQuestionnaire;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MedicalQuestionnairesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('plan:id,description'))
            ->emptyStateHeading('Todavía no hay cuestionarios médicos')
            ->emptyStateDescription('Cree uno y elija su plan: sus preguntas aparecerán en todos los formularios de afiliación de ese plan.')
            ->emptyStateIcon(Heroicon::OutlinedClipboardDocumentCheck)
            ->columns([
                TextColumn::make('name')
                    ->label('Cuestionario')
                    ->weight('bold')
                    ->searchable()
                    ->wrap(),
                TextColumn::make('plan.description')
                    ->label('Plan')
                    ->badge(),
                TextColumn::make('active_questions_count')
                    ->label('Preguntas que se hacen')
                    ->counts(['questions as active_questions_count' => fn (Builder $query): Builder => $query->where('is_active', true)])
                    ->alignCenter(),
                TextColumn::make('inactive_questions_count')
                    ->label('Desactivadas')
                    ->counts(['questions as inactive_questions_count' => fn (Builder $query): Builder => $query->where('is_active', false)])
                    ->alignCenter()
                    ->color('gray'),
                IconColumn::make('is_active')
                    ->label('Activo')
                    ->boolean(),
                TextColumn::make('updated_at')
                    ->label('Última modificación')
                    ->dateTime('d/m/Y h:i a')
                    ->description(fn (MedicalQuestionnaire $record): ?string => filled($record->updated_by) ? 'por '.$record->updated_by : null),
            ])
            ->recordActions([
                Action::make('pdf')
                    ->label('PDF de preguntas')
                    ->icon(Heroicon::OutlinedDocumentArrowDown)
                    ->color('gray')
                    ->url(fn (MedicalQuestionnaire $record): string => route('business.medical-questionnaires.pdf', $record))
                    ->openUrlInNewTab(),
                EditAction::make()->label('Editar preguntas'),
            ]);
    }
}
