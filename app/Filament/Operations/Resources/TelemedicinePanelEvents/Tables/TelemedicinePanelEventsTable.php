<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\TelemedicinePanelEvents\Tables;

use App\Enums\TelemedicinePanelEventCategory as Category;
use App\Enums\TelemedicinePanelEventLevel as Level;
use App\Filament\Operations\Resources\TelemedicinePanelEvents\TelemedicinePanelEventResource;
use App\Models\TelemedicinePanelEvent as Event;
use App\Models\User;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class TelemedicinePanelEventsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort(fn (Builder $query): Builder => $query->orderByDesc('occurred_at')->orderByDesc('id'))
            ->poll('10s')
            ->striped()
            ->defaultPaginationPageOption(50)
            ->paginationPageOptions([25, 50, 100])
            ->recordUrl(fn (Event $record): string => TelemedicinePanelEventResource::getUrl('view', ['record' => $record]))
            ->emptyStateHeading('Nada que mostrar en esta vista')
            ->emptyStateDescription('Cuando un médico tenga un problema en el panel de Telemedicina aparecerá aquí, con el caso, el paso y el mensaje que vio.')
            ->emptyStateIcon(Heroicon::OutlinedCheckBadge)
            ->columns([
                TextColumn::make('occurred_at')
                    ->label('Hora')
                    ->dateTime('d/m/Y H:i:s')
                    ->description(fn (Event $record): ?string => $record->occurred_at?->diffForHumans())
                    ->sortable(),
                TextColumn::make('level')
                    ->label('Nivel')
                    ->badge()
                    ->formatStateUsing(fn (Event $record): string => $record->levelEnum()?->label() ?? (string) $record->level)
                    ->color(fn (Event $record): string => $record->levelEnum()?->color() ?? 'gray'),
                TextColumn::make('title')
                    ->label('Qué pasó')
                    ->weight(fn (Event $record): string => $record->isProblem() ? 'bold' : 'normal')
                    ->description(fn (Event $record): ?string => filled($record->message) ? Str::limit((string) $record->message, 180) : null)
                    ->wrap()
                    ->searchable(['title', 'message']),
                TextColumn::make('category')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (Event $record): string => $record->categoryEnum()?->label() ?? (string) $record->category)
                    ->color(fn (Event $record): string => $record->categoryEnum()?->color() ?? 'gray')
                    ->toggleable(),
                TextColumn::make('user_name')
                    ->label('Médico / usuario')
                    ->placeholder('Sin sesión')
                    ->searchable(),
                TextColumn::make('telemedicine_case_code')
                    ->label('Caso')
                    ->placeholder('—')
                    ->copyable()
                    ->searchable(),
                TextColumn::make('screen')
                    ->label('Pantalla')
                    ->placeholder(fn (Event $record): string => (string) $record->url)
                    ->description(fn (Event $record): ?string => collect([
                        filled($record->step) ? 'Paso: '.$record->step : null,
                        $record->telemedicineServiceList?->name !== null ? 'Servicio: '.$record->telemedicineServiceList->name : null,
                    ])->filter()->implode(' · ') ?: null)
                    ->wrap()
                    ->toggleable(),
            ])
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('telemedicineServiceList:id,name'))
            ->filters([
                SelectFilter::make('level')
                    ->label('Nivel')
                    ->multiple()
                    ->options(Level::options())
                    ->native(false),
                SelectFilter::make('category')
                    ->label('Tipo')
                    ->multiple()
                    ->options(Category::options())
                    ->native(false),
                SelectFilter::make('user_id')
                    ->label('Médico / usuario')
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search): array => User::query()
                        ->whereIn('id', Event::query()->select('user_id')->whereNotNull('user_id')->distinct())
                        ->where('name', 'like', '%'.$search.'%')
                        ->orderBy('name')
                        ->limit(30)
                        ->pluck('name', 'id')
                        ->all())
                    ->getOptionLabelUsing(fn (mixed $value): ?string => User::query()->whereKey($value)->value('name')),
                Filter::make('case')
                    ->label('Caso')
                    ->schema([
                        TextInput::make('code')
                            ->label('Código del caso')
                            ->placeholder('Ej.: 72085-01269'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        filled($data['code'] ?? null),
                        fn (Builder $q): Builder => $q->where('telemedicine_case_code', trim((string) $data['code'])),
                    ))
                    ->indicateUsing(fn (array $data): ?string => filled($data['code'] ?? null) ? 'Caso '.$data['code'] : null),
                Filter::make('occurred_at')
                    ->label('Fecha')
                    ->schema([
                        DateTimePicker::make('from')->label('Desde')->seconds(false)->native(false),
                        DateTimePicker::make('until')->label('Hasta')->seconds(false)->native(false),
                    ])
                    ->columns(2)
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when(filled($data['from'] ?? null), fn (Builder $q): Builder => $q->where('occurred_at', '>=', $data['from']))
                        ->when(filled($data['until'] ?? null), fn (Builder $q): Builder => $q->where('occurred_at', '<=', $data['until'])))
                    ->indicateUsing(fn (array $data): ?string => match (true) {
                        filled($data['from'] ?? null) && filled($data['until'] ?? null) => 'Del '.$data['from'].' al '.$data['until'],
                        filled($data['from'] ?? null) => 'Desde '.$data['from'],
                        filled($data['until'] ?? null) => 'Hasta '.$data['until'],
                        default => null,
                    }),
            ])
            ->filtersFormColumns(2)
            ->recordActions([
                ViewAction::make()->label('Ver secuencia'),
            ]);
    }
}
