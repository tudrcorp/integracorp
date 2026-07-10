<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\CompanyAssociates\Tables;

use App\Filament\Business\Resources\CompanyAssociates\Actions\CompanyAssociatesTableActions;
use App\Models\CompanyAssociate;
use App\Support\Companies\CompanyAssociatesGroupPalette;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ColumnGroup;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CompanyAssociatesTable
{
    /**
     * @param  array{scopedResponsible?: bool, scopedCompany?: bool}  $context
     */
    public static function configure(Table $table, array $context = []): Table
    {
        $scopedResponsible = (bool) ($context['scopedResponsible'] ?? false);
        $scopedCompany = (bool) ($context['scopedCompany'] ?? false);

        return $table
            ->heading($scopedResponsible ? 'Detalle de asociados' : 'Asociados / Clientes')
            ->description($scopedResponsible
                ? 'Vista enfocada en los usuarios registrados bajo el responsable seleccionado.'
                : 'Usuarios registrados públicamente bajo responsables y empresas de nuevos negocios.')
            ->defaultSort('registered_at', 'desc')
            ->deferFilters(false)
            ->recordTitleAttribute('full_name')
            ->emptyStateHeading($scopedResponsible ? 'Sin asociados para este responsable' : 'Sin asociados registrados')
            ->emptyStateDescription($scopedResponsible
                ? 'Este responsable aún no tiene usuarios registrados con el enlace público.'
                : 'Comparta el enlace público de la empresa para que los responsables registren a sus asociados.')
            ->emptyStateIcon(Heroicon::OutlinedUserGroup)
            ->groups([
                Group::make('company_responsible_id')
                    ->label('Responsable')
                    ->collapsible()
                    ->titlePrefixedWithLabel(false)
                    ->getKeyFromRecordUsing(fn (CompanyAssociate $record): string => (string) ($record->company_responsible_id ?? 'none'))
                    ->getTitleFromRecordUsing(fn (CompanyAssociate $record): string => CompanyAssociatesGroupPalette::groupTitleLabel($record))
                    ->getDescriptionFromRecordUsing(fn (CompanyAssociate $record): string => CompanyAssociatesGroupPalette::groupDescriptionLabel($record)),
            ])
            ->defaultGroup('company_responsible_id')
            ->recordClasses(fn (CompanyAssociate $record): array => CompanyAssociatesGroupPalette::recordRowClasses($record))
            ->columns([
                ColumnGroup::make('Asociado', [
                    TextColumn::make('full_name')
                        ->label('Nombre y Apellido')
                        ->icon(Heroicon::OutlinedUser)
                        ->weight('semibold')
                        ->searchable()
                        ->sortable(),
                    TextColumn::make('identity_card')
                        ->label('Cédula')
                        ->badge()
                        ->color('gray')
                        ->searchable()
                        ->copyable(),
                    TextColumn::make('age')
                        ->label('Edad')
                        ->suffix(' años')
                        ->alignCenter()
                        ->sortable(),
                    TextColumn::make('sex')
                        ->label('Sexo')
                        ->badge()
                        ->color(fn (?string $state): string => match (strtoupper((string) $state)) {
                            'MASCULINO' => 'info',
                            'FEMENINO' => 'danger',
                            default => 'gray',
                        }),
                ]),
                ColumnGroup::make('Relaciones', [
                    TextColumn::make('company.name')
                        ->label('Empresa')
                        ->icon(Heroicon::OutlinedBuildingOffice2)
                        ->searchable()
                        ->sortable()
                        ->limit(32)
                        ->hidden($scopedCompany),
                    TextColumn::make('responsible.full_name')
                        ->label('Responsable')
                        ->icon(Heroicon::OutlinedUserCircle)
                        ->searchable()
                        ->limit(28)
                        ->hidden($scopedResponsible),
                ]),
                ColumnGroup::make('ILS', [
                    TextColumn::make('vaucher_ils')
                        ->label('Código voucher')
                        ->icon(Heroicon::OutlinedTicket)
                        ->badge()
                        ->color('info')
                        ->placeholder('—')
                        ->toggleable(),
                    TextColumn::make('date_init')
                        ->label('Vigencia desde')
                        ->placeholder('—')
                        ->toggleable(isToggledHiddenByDefault: true),
                    TextColumn::make('date_end')
                        ->label('Vigencia hasta')
                        ->placeholder('—')
                        ->toggleable(isToggledHiddenByDefault: true),
                    TextColumn::make('ils_status')
                        ->label('ILS')
                        ->badge()
                        ->state(fn (CompanyAssociate $record): string => $record->hasVoucherIls() ? 'Cargado' : 'Pendiente')
                        ->color(fn (CompanyAssociate $record): string => $record->hasVoucherIls() ? 'success' : 'warning'),
                ]),
                ColumnGroup::make('Contacto', [
                    TextColumn::make('email')
                        ->label('Correo')
                        ->icon(Heroicon::OutlinedEnvelope)
                        ->searchable()
                        ->copyable()
                        ->placeholder('—')
                        ->toggleable(),
                    TextColumn::make('phone')
                        ->label('Teléfono')
                        ->icon(Heroicon::OutlinedPhone)
                        ->copyable()
                        ->placeholder('—')
                        ->toggleable(),
                    TextColumn::make('contact_full_name')
                        ->label('Contacto emergencia')
                        ->icon(Heroicon::OutlinedPhoneArrowUpRight)
                        ->limit(24)
                        ->toggleable(isToggledHiddenByDefault: true),
                ]),
                ColumnGroup::make('Registro', [
                    TextColumn::make('registered_at')
                        ->label('Registrado el')
                        ->icon(Heroicon::OutlinedClock)
                        ->dateTime('d/m/Y H:i:s')
                        ->sortable(),
                    ImageColumn::make('identity_document')
                        ->label('Documento')
                        ->disk('public')
                        ->square()
                        ->toggleable(isToggledHiddenByDefault: true),
                    ImageColumn::make('document_ils')
                        ->label('Voucher ILS')
                        ->disk('public')
                        ->square()
                        ->toggleable(isToggledHiddenByDefault: true),
                ]),
            ])
            ->filters([
                SelectFilter::make('company_id')
                    ->label('Empresa')
                    ->relationship('company', 'name')
                    ->searchable()
                    ->preload()
                    ->native(false)
                    ->hidden($scopedCompany),
                SelectFilter::make('company_responsible_id')
                    ->label('Responsable')
                    ->relationship('responsible', 'full_name')
                    ->searchable()
                    ->preload()
                    ->native(false)
                    ->hidden($scopedResponsible),
                TernaryFilter::make('has_voucher_ils')
                    ->label('Voucher ILS')
                    ->placeholder('Todos')
                    ->trueLabel('Cargado')
                    ->falseLabel('Pendiente')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->where(function (Builder $query): void {
                            $query->whereNotNull('vaucher_ils')
                                ->orWhereNotNull('document_ils');
                        }),
                        false: fn (Builder $query): Builder => $query->where(function (Builder $query): void {
                            $query->whereNull('vaucher_ils')
                                ->whereNull('document_ils')
                                ->whereNull('date_init')
                                ->whereNull('date_end');
                        }),
                        blank: fn (Builder $query): Builder => $query,
                    ),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make()
                        ->label('Ver asociado'),
                    CompanyAssociatesTableActions::uploadVoucherIlsAction(),
                    CompanyAssociatesTableActions::generateCarnetAction(),
                    CompanyAssociatesTableActions::previewInclusionQrAction(),
                    CompanyAssociatesTableActions::openCarnetAction(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    CompanyAssociatesTableActions::sendDocumentsBulkAction(),
                ]),
            ]);
    }
}
