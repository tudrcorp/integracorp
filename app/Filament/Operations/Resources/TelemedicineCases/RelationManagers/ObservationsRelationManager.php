<?php

namespace App\Filament\Operations\Resources\TelemedicineCases\RelationManagers;

use App\Models\ObservationCase;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class ObservationsRelationManager extends RelationManager
{
    protected static string $relationship = 'observations';

    protected static ?string $title = 'Observaciones';

    protected static string|BackedEnum|null $icon = Heroicon::OutlinedChatBubbleLeftRight;

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->description('Las observaciones quedan asociadas a este caso y al usuario que las registra.')
                    ->schema([
                        Textarea::make('description')
                            ->label('Texto de la observación')
                            ->placeholder('Escriba la nota o seguimiento administrativo…')
                            ->required()
                            ->minLength(2)
                            ->maxLength(5000)
                            ->rows(5)
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Observaciones del caso')
            ->description('Historial completo: de la más reciente a la más antigua. Use el formulario para añadir una nueva nota.')
            ->emptyStateHeading('Sin observaciones')
            ->emptyStateDescription('Aún no se han registrado notas para este caso. Use «Crear» para añadir la primera.')
            ->emptyStateIcon(Heroicon::OutlinedChatBubbleLeftEllipsis)
            ->defaultSort('created_at', 'desc')
            ->paginationPageOptions([10, 25, 50])
            ->columns([
                TextColumn::make('created_at')
                    ->label('Registro')
                    ->dateTime('d/m/Y H:i')
                    ->description(fn (ObservationCase $record): string => $record->created_at?->diffForHumans() ?? '—')
                    ->sortable()
                    ->icon(Heroicon::OutlinedCalendar)
                    ->iconColor('gray'),
                TextColumn::make('description')
                    ->label('Observación')
                    ->wrap()
                    ->searchable()
                    ->formatStateUsing(function (?string $state): string {
                        $text = trim((string) ($state ?? ''));

                        return Str::limit($text, 280);
                    })
                    ->tooltip(function (ObservationCase $record): ?string {
                        $text = trim((string) $record->description);

                        return strlen($text) > 200 ? $text : null;
                    }),
                TextColumn::make('createdBy.name')
                    ->label('Registrado por')
                    ->placeholder('—')
                    ->weight(FontWeight::Medium)
                    ->icon(Heroicon::OutlinedUser)
                    ->iconColor('primary')
                    ->description(fn (ObservationCase $record): ?string => $record->createdBy?->email)
                    ->searchable(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Nueva observación')
                    ->icon(Heroicon::OutlinedPlus)
                    ->modalHeading('Registrar observación')
                    ->modalSubmitActionLabel('Guardar')
                    ->mutateFormDataUsing(function (array $data): array {
                        $userId = Auth::id();
                        if ($userId !== null) {
                            $data['created_by'] = (string) $userId;
                        }

                        return $data;
                    })
                    ->successNotificationTitle('Observación guardada'),
            ])
            ->recordActions([
                EditAction::make()
                    ->label('Editar')
                    ->modalHeading('Editar observación')
                    ->successNotificationTitle('Observación actualizada'),
                DeleteAction::make()
                    ->label('Eliminar')
                    ->requiresConfirmation()
                    ->modalHeading('Eliminar observación')
                    ->modalDescription('Esta acción no se puede deshacer.')
                    ->successNotificationTitle('Observación eliminada'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('Eliminar seleccionadas'),
                ]),
            ]);
    }

    public static function getModelLabel(): string
    {
        return 'observación';
    }

    public static function getPluralModelLabel(): string
    {
        return 'observaciones';
    }

    public function isReadOnly(): bool
    {
        return false;
    }
}
