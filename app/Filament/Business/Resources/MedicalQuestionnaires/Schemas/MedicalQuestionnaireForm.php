<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\MedicalQuestionnaires\Schemas;

use App\Models\MedicalQuestionnaireQuestion;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class MedicalQuestionnaireForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Cuestionario')
                ->description('Cada plan tiene un solo cuestionario. Mientras esté activo, todos los formularios de afiliación de ese plan muestran sus preguntas: paneles internos, red comercial y pre-afiliaciones públicas.')
                ->icon(Heroicon::OutlinedClipboardDocumentCheck)
                ->columnSpanFull()
                ->schema([
                    Grid::make(3)->schema([
                        Select::make('plan_id')
                            ->label('Plan')
                            ->relationship('plan', 'description')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->validationMessages(['unique' => 'Ese plan ya tiene un cuestionario: edítelo en lugar de crear otro.']),
                        TextInput::make('name')
                            ->label('Nombre')
                            ->placeholder('Declaración de condiciones médicas · Plan Especial')
                            ->required()
                            ->maxLength(255),
                        Toggle::make('is_active')
                            ->label('Activo')
                            ->helperText('Si lo desactiva, los formularios de ese plan dejan de pedir el cuestionario.')
                            ->default(true)
                            ->inline(false),
                    ]),
                ]),
            Section::make('Preguntas')
                ->description('Todas se responden con Sí o No. Arrastre para cambiar el orden. Una pregunta que ya tiene respuestas no se puede borrar: desactívela y deja de preguntarse, pero las afiliaciones que la respondieron la siguen mostrando tal como se les preguntó.')
                ->icon(Heroicon::OutlinedListBullet)
                ->columnSpanFull()
                ->schema([
                    Repeater::make('questions')
                        ->hiddenLabel()
                        ->relationship('questions')
                        ->orderColumn('sort_order')
                        ->reorderableWithButtons()
                        ->grid(['default' => 1, 'lg' => 2])
                        ->collapsible()
                        ->cloneable(false)
                        ->minItems(1)
                        ->addActionLabel('Agregar pregunta')
                        ->itemLabel(fn (array $state): string => (($state['is_active'] ?? true) ? '' : '[Inactiva] ').Str::limit(trim((string) ($state['text'] ?? 'Nueva pregunta')) ?: 'Nueva pregunta', 110))
                        ->deleteAction(fn (Action $action): Action => $action
                            ->requiresConfirmation()
                            ->modalHeading('Quitar esta pregunta')
                            ->modalDescription('Se quita de forma definitiva. Solo se puede hacer con preguntas que nadie ha respondido todavía.')
                            ->visible(fn (array $arguments, Repeater $component): bool => ! self::isAnswered($component->getRawItemState($arguments['item'])['id'] ?? null)))
                        ->mutateRelationshipDataBeforeCreateUsing(fn (array $data): array => [...$data, 'created_by' => Auth::user()?->name, 'updated_by' => Auth::user()?->name])
                        ->mutateRelationshipDataBeforeSaveUsing(fn (array $data): array => [...$data, 'updated_by' => Auth::user()?->name])
                        ->schema([
                            Textarea::make('text')
                                ->label('Pregunta')
                                ->required()
                                ->maxLength(2000)
                                ->rows(2)
                                ->columnSpanFull(),
                            Grid::make(2)->schema([
                                Toggle::make('is_active')
                                    ->label('Se pregunta')
                                    ->helperText('Desactívela para dejar de preguntarla sin perder las respuestas que ya tiene.')
                                    ->default(true),
                                Placeholder::make('usage')
                                    ->label('Respuestas registradas')
                                    ->content(fn (?MedicalQuestionnaireQuestion $record): string => $record === null
                                        ? 'Pregunta nueva: todavía no se ha respondido.'
                                        : (($count = $record->answers()->count()) === 0 ? 'Nadie la ha respondido todavía.' : 'Respondida en '.$count.' afiliación(es).')),
                            ]),
                        ]),
                ]),
        ]);
    }

    private static function isAnswered(mixed $questionId): bool
    {
        return $questionId !== null && MedicalQuestionnaireQuestion::query()->whereKey($questionId)->whereHas('answers')->exists();
    }
}
