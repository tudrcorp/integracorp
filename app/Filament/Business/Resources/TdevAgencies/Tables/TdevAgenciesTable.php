<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\TdevAgencies\Tables;

use App\Models\TdevAgency;
use App\Support\Tdev\TdevAgencyRegistrar;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class TdevAgenciesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->with(['country', 'state', 'city', 'parentAgency'])
                ->withCount(['agents', 'childAgencies']))
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->deferFilters(false)
            ->paginationPageOptions([10, 25, 50, 100])
            ->heading(new HtmlString(
                <<<'HTML'
                <div class="flex flex-wrap items-center gap-3">
                    <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-cyan-500/15 text-cyan-700 dark:bg-cyan-400/15 dark:text-cyan-300">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-5 w-5" aria-hidden="true">
                            <path d="M11.47 3.841a.75.75 0 0 1 1.06 0l8.69 8.69a.75.75 0 1 0 1.06-1.061l-8.689-8.69a2.25 2.25 0 0 0-3.182 0l-8.69 8.69a.75.75 0 1 0 1.061 1.06l8.69-8.689Z"/>
                            <path d="m12 5.432 8.159 8.159c.03.03.06.058.091.086v6.198c0 1.035-.84 1.875-1.875 1.875H15a.75.75 0 0 1-.75-.75v-4.5a.75.75 0 0 0-.75-.75h-3a.75.75 0 0 0-.75.75V21a.75.75 0 0 1-.75.75H5.625a1.875 1.875 0 0 1-1.875-1.875v-6.198a2.29 2.29 0 0 0 .091-.086L12 5.432Z"/>
                        </svg>
                    </span>
                    <div>
                        <div class="text-base font-semibold tracking-tight text-gray-950 dark:text-white">Directorio TDEV</div>
                        <div class="text-xs font-medium text-gray-500 dark:text-gray-400">Filtra por nivel, copia enlaces públicos y gestiona la red comercial</div>
                    </div>
                </div>
                HTML
            ))
            ->description('Agencias nivel 2 y asociadas nivel 3 · páginas web, registro de agentes y estructura comercial.')
            ->emptyStateHeading('Sin agencias TDEV')
            ->emptyStateDescription('Crea la primera agencia nivel 2 para generar su página web y URLs de registro.')
            ->emptyStateIcon(Heroicon::OutlinedBuildingStorefront)
            ->columns([
                ImageColumn::make('logo')
                    ->label('Logo')
                    ->disk('public')
                    ->visibility('public')
                    ->circular()
                    ->imageWidth(48)
                    ->imageHeight(48)
                    ->defaultImageUrl(fn (TdevAgency $record): string => 'https://ui-avatars.com/api/?name='.urlencode(substr($record->name, 0, 2)).'&background=2299A4&color=fff&size=128')
                    ->toggleable()
                    ->extraImgAttributes([
                        'class' => 'object-cover ring-2 ring-cyan-500/20 dark:ring-cyan-400/25',
                    ]),
                TextColumn::make('name')
                    ->label('Agencia')
                    ->searchable()
                    ->sortable()
                    ->weight('font-semibold')
                    ->wrap()
                    ->grow()
                    ->icon(Heroicon::OutlinedBuildingStorefront)
                    ->iconColor('primary')
                    ->description(function (TdevAgency $record): ?string {
                        if ($record->isLevelThree() && $record->parentAgency) {
                            return 'Principal: '.$record->parentAgency->name;
                        }

                        return filled($record->identification_number)
                            ? 'ID: '.$record->identification_number
                            : null;
                    }),
                TextColumn::make('level')
                    ->label('Nivel')
                    ->badge()
                    ->alignCenter()
                    ->formatStateUsing(fn (int|string|null $state): string => 'Nivel '.(string) $state)
                    ->color(fn (int|string|null $state): string => (int) $state === TdevAgency::LEVEL_TWO ? 'info' : 'warning')
                    ->icon(fn (int|string|null $state): Heroicon => (int) $state === TdevAgency::LEVEL_TWO
                        ? Heroicon::OutlinedStar
                        : Heroicon::OutlinedLink)
                    ->sortable(),
                TextColumn::make('email')
                    ->label('Correo')
                    ->icon(Heroicon::OutlinedEnvelope)
                    ->iconColor('gray')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Correo copiado')
                    ->placeholder('—')
                    ->limit(32)
                    ->tooltip(fn (TdevAgency $record): ?string => $record->email)
                    ->toggleable(),
                TextColumn::make('phone')
                    ->label('Teléfono')
                    ->icon(Heroicon::OutlinedPhone)
                    ->iconColor('gray')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Teléfono copiado')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('city.definition')
                    ->label('Ciudad')
                    ->icon(Heroicon::OutlinedMapPin)
                    ->iconColor('gray')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('agents_count')
                    ->label('Agentes')
                    ->badge()
                    ->alignCenter()
                    ->color('success')
                    ->icon(Heroicon::OutlinedUserGroup)
                    ->sortable(),
                TextColumn::make('child_agencies_count')
                    ->label('Nivel 3')
                    ->badge()
                    ->alignCenter()
                    ->color('warning')
                    ->icon(Heroicon::OutlinedBuildingOffice2)
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label('Creada')
                    ->dateTime('d/m/Y H:i')
                    ->description(fn (TdevAgency $record): string => $record->created_at?->diffForHumans() ?? '')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('level')
                    ->label('Nivel')
                    ->options([
                        (string) TdevAgency::LEVEL_TWO => 'Nivel 2',
                        (string) TdevAgency::LEVEL_THREE => 'Nivel 3',
                    ])
                    ->default((string) TdevAgency::LEVEL_TWO),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('openLandingLink')
                        ->label('Página web')
                        ->icon(Heroicon::OutlinedGlobeAlt)
                        ->color('info')
                        ->visible(fn (TdevAgency $record): bool => $record->isLevelTwo())
                        ->url(fn (TdevAgency $record): string => TdevAgencyRegistrar::publicLandingUrl($record))
                        ->openUrlInNewTab(),
                    Action::make('openRegistrationLink')
                        ->label('URL agentes')
                        ->icon(Heroicon::OutlinedLink)
                        ->color('success')
                        ->url(fn (TdevAgency $record): string => TdevAgencyRegistrar::publicAgentRegistrationUrl($record))
                        ->openUrlInNewTab(),
                    Action::make('openAgencyRegistrationLink')
                        ->label('URL agencias N3')
                        ->icon(Heroicon::OutlinedBuildingStorefront)
                        ->color('warning')
                        ->visible(fn (TdevAgency $record): bool => $record->isLevelTwo())
                        ->url(fn (TdevAgency $record): string => TdevAgencyRegistrar::publicLevelThreeAgencyRegistrationUrl($record))
                        ->openUrlInNewTab(),
                    ViewAction::make()
                        ->label('Ver ficha'),
                    EditAction::make()
                        ->label('Editar'),
                ])
                    ->label('Acciones')
                    ->icon(Heroicon::OutlinedEllipsisVertical)
                    ->button()
                    ->color('gray'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
