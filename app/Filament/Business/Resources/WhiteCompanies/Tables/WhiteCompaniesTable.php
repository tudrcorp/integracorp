<?php

namespace App\Filament\Business\Resources\WhiteCompanies\Tables;

use App\Models\Country;
use App\Models\WhiteCompany;
use App\Support\SecurityAudit;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class WhiteCompaniesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with([
                'country',
                'state',
                'city',
            ]))
            ->defaultSort('name')
            ->paginationPageOptions([10, 25, 50, 100])
            ->heading('Empresas aliadas')
            ->description('Listado de empresas white-label: use la búsqueda global o los filtros; las columnas de ubicación se pueden ocultar desde el selector de columnas.')
            ->emptyStateHeading('Sin empresas aliadas')
            ->emptyStateDescription('Cree la primera empresa desde el botón «Crear Empresa» para comenzar a asociar agencias y usuarios.')
            ->emptyStateIcon(Heroicon::OutlinedBuildingLibrary)
            ->columns([
                ImageColumn::make('logo')
                    ->label('Logo')
                    ->disk('public')
                    ->visibility('public')
                    ->circular()
                    ->imageWidth(44)
                    ->imageHeight(44)
                    ->defaultImageUrl(fn (): string => 'https://ui-avatars.com/api/?name=W&background=e2e8f0&color=64748b&size=128')
                    ->toggleable()
                    ->extraImgAttributes([
                        'class' => 'ring-1 ring-gray-200/80 dark:ring-white/10 object-cover',
                    ]),

                TextColumn::make('name')
                    ->label('Razón social')
                    ->description(fn (WhiteCompany $record): ?string => $record->rif ? 'RIF: '.$record->rif : null)
                    ->searchable()
                    ->sortable()
                    ->weight('font-semibold')
                    ->icon(Heroicon::OutlinedBuildingOffice2)
                    ->iconColor('primary')
                    ->wrap()
                    ->grow(),

                TextColumn::make('email')
                    ->label('Correo')
                    ->icon(Heroicon::OutlinedEnvelope)
                    ->iconColor('gray')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('Correo copiado')
                    ->placeholder('—')
                    ->limit(36)
                    ->tooltip(fn (WhiteCompany $record): ?string => $record->email),

                TextColumn::make('phone')
                    ->label('Teléfono')
                    ->icon(Heroicon::OutlinedPhone)
                    ->iconColor('gray')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('Teléfono copiado')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('country.name')
                    ->label('País')
                    ->badge()
                    ->color('info')
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('state.definition')
                    ->label('Estado')
                    ->badge()
                    ->color('gray')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('city.definition')
                    ->label('Ciudad')
                    ->badge()
                    ->color('gray')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('address')
                    ->label('Dirección')
                    ->icon(Heroicon::OutlinedMapPin)
                    ->iconColor('gray')
                    ->searchable()
                    ->wrap()
                    ->lineClamp(2)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Alta')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->tooltip(fn (WhiteCompany $record): ?string => $record->created_at?->timezone(config('app.timezone'))->isoFormat('dddd D [de] MMMM [de] YYYY, HH:mm')),
            ])
            ->filters([
                SelectFilter::make('country_id')
                    ->label('País')
                    ->options(fn (): array => Country::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    ->preload()
                    ->indicator('País'),
                TernaryFilter::make('logo')
                    ->label('Logotipo')
                    ->placeholder('Todas')
                    ->trueLabel('Con logo')
                    ->falseLabel('Sin logo')
                    ->queries(
                        true: fn (Builder $query, array $data): Builder => $query->whereNotNull('logo')->where('logo', '!=', ''),
                        false: fn (Builder $query, array $data): Builder => $query->where(function (Builder $q): void {
                            $q->whereNull('logo')->orWhere('logo', '');
                        }),
                        blank: fn (Builder $query, array $data): Builder => $query,
                    )
                    ->indicator('Logo'),
            ])
            ->recordActions([
                EditAction::make()
                    ->label('Editar')
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->after(function (WhiteCompany $record): void {
                        self::audit('AUDIT_BUSINESS_WHITE_COMPANY_UPDATED', 'business.white-companies.edit', [
                            'white_company_id' => $record->id,
                            'name' => $record->name,
                            'rif' => $record->rif,
                            'email' => $record->email,
                        ]);
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('Eliminar seleccionadas')
                        ->before(function (Collection $records): void {
                            self::audit('AUDIT_BUSINESS_WHITE_COMPANIES_BULK_DELETED', 'business.white-companies.bulk-delete', [
                                'record_ids' => $records->pluck('id')->values()->all(),
                                'total' => $records->count(),
                            ]);
                        }),
                ]),
            ])
            ->striped();
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private static function audit(string $event, string $route, array $context = []): void
    {
        SecurityAudit::log($event, $route, [
            'panel' => 'business',
            'module' => 'white_companies',
            ...$context,
        ]);
    }
}
