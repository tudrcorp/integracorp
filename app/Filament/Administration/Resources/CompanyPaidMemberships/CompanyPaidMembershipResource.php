<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\CompanyPaidMemberships;

use App\Filament\Administration\Resources\CompanyPaidMemberships\Pages\ManageCompanyPaidMemberships;
use App\Filament\Concerns\AuthorizesDepartmentNavigation;
use App\Models\CompanyPaidMembership;
use App\Support\Companies\CompanyPaidMembershipApprovalService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class CompanyPaidMembershipResource extends Resource
{
    use AuthorizesDepartmentNavigation;

    protected static ?string $model = CompanyPaidMembership::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentCurrencyDollar;

    protected static string|UnitEnum|null $navigationGroup = 'ADMINISTRACIÓN';

    protected static ?string $navigationLabel = 'Comprobantes Nuevos Negocios';

    protected static ?string $modelLabel = 'comprobante nuevos negocios';

    protected static ?string $pluralModelLabel = 'comprobantes nuevos negocios';

    protected static ?int $navigationSort = 15;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->heading('COMPROBANTES · NUEVOS NEGOCIOS')
            ->description('Validación de pagos cargados desde Nuevos Negocios. Al aprobar se genera una venta tipada NUEVOS NEGOCIOS.')
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['company', 'planGenerator']))
            ->columns([
                TextColumn::make('company.name')
                    ->label('Empresa')
                    ->searchable()
                    ->sortable()
                    ->wrap(),
                TextColumn::make('company.rif')
                    ->label('RIF')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('planGenerator.name')
                    ->label('Cotización')
                    ->toggleable()
                    ->placeholder('—'),
                TextColumn::make('total_amount')
                    ->label('Total')
                    ->numeric(decimalPlaces: 2)
                    ->suffix(' US$')
                    ->sortable(),
                TextColumn::make('payment_method')
                    ->label('Método')
                    ->badge()
                    ->searchable(),
                TextColumn::make('reference_payment_usd')
                    ->label('Referencia')
                    ->prefix('US$: ')
                    ->description(fn (CompanyPaidMembership $record): string => $record->reference_payment_ves !== 'N/A'
                        ? 'VES: '.$record->reference_payment_ves
                        : 'VES: N/A')
                    ->toggleable(),
                IconColumn::make('document_usd')
                    ->label('Comp. US$')
                    ->alignment(Alignment::Center)
                    ->icon(fn (CompanyPaidMembership $record): string => filled($record->document_usd) && $record->document_usd !== 'N/A'
                        ? 'heroicon-o-check-circle'
                        : 'heroicon-o-x-circle')
                    ->color(fn (CompanyPaidMembership $record): string => filled($record->document_usd) && $record->document_usd !== 'N/A'
                        ? 'success'
                        : 'danger')
                    ->url(fn (CompanyPaidMembership $record): ?string => filled($record->document_usd) && $record->document_usd !== 'N/A'
                        ? asset('storage/'.$record->document_usd)
                        : null)
                    ->openUrlInNewTab(),
                IconColumn::make('document_ves')
                    ->label('Comp. VES')
                    ->alignment(Alignment::Center)
                    ->icon(fn (CompanyPaidMembership $record): string => filled($record->document_ves) && $record->document_ves !== 'N/A'
                        ? 'heroicon-o-check-circle'
                        : 'heroicon-o-x-circle')
                    ->color(fn (CompanyPaidMembership $record): string => filled($record->document_ves) && $record->document_ves !== 'N/A'
                        ? 'success'
                        : 'danger')
                    ->url(fn (CompanyPaidMembership $record): ?string => filled($record->document_ves) && $record->document_ves !== 'N/A'
                        ? asset('storage/'.$record->document_ves)
                        : null)
                    ->openUrlInNewTab(),
                TextColumn::make('pay_amount_usd')
                    ->label('Pagado')
                    ->suffix(' US$')
                    ->description(fn (CompanyPaidMembership $record): string => number_format((float) $record->pay_amount_ves, 2).' VES'),
                TextColumn::make('date_payment_voucher')
                    ->label('Fecha comprobante')
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label('Cargado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Estatus')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        CompanyPaidMembershipApprovalService::STATUS_APPROVED => 'success',
                        CompanyPaidMembershipApprovalService::STATUS_REJECTED => 'danger',
                        default => 'warning',
                    }),
                TextColumn::make('invoice_number')
                    ->label('Factura')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('aproved_by')
                    ->label('Validado por')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Estatus')
                    ->options([
                        CompanyPaidMembershipApprovalService::STATUS_PENDING => 'Pendiente',
                        CompanyPaidMembershipApprovalService::STATUS_APPROVED => 'Aprobado',
                        CompanyPaidMembershipApprovalService::STATUS_REJECTED => 'Rechazado',
                    ])
                    ->default(CompanyPaidMembershipApprovalService::STATUS_PENDING),
            ])
            ->recordActions([
                Action::make('approve')
                    ->label('Aprobar')
                    ->icon('heroicon-s-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Aprobar comprobante de nuevos negocios')
                    ->modalDescription('Se creará una venta con tipo NUEVOS NEGOCIOS y se marcará el comprobante como APROBADO.')
                    ->modalWidth(Width::Large)
                    ->visible(fn (CompanyPaidMembership $record): bool => $record->status === CompanyPaidMembershipApprovalService::STATUS_PENDING)
                    ->action(function (CompanyPaidMembership $record): void {
                        try {
                            $result = CompanyPaidMembershipApprovalService::approve($record);

                            Notification::make()
                                ->title('Pago aprobado')
                                ->body('Venta '.$result['sale']->invoice_number.' creada (NUEVOS NEGOCIOS).')
                                ->success()
                                ->send();
                        } catch (\Throwable $throwable) {
                            Notification::make()
                                ->title('No se pudo aprobar')
                                ->body($throwable->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                Action::make('reject')
                    ->label('Rechazar')
                    ->icon('heroicon-s-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Rechazar comprobante')
                    ->modalWidth(Width::Large)
                    ->visible(fn (CompanyPaidMembership $record): bool => $record->status === CompanyPaidMembershipApprovalService::STATUS_PENDING)
                    ->form([
                        Textarea::make('reason')
                            ->label('Motivo del rechazo')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function (CompanyPaidMembership $record, array $data): void {
                        try {
                            CompanyPaidMembershipApprovalService::reject(
                                $record,
                                isset($data['reason']) ? (string) $data['reason'] : null,
                            );

                            Notification::make()
                                ->title('Comprobante rechazado')
                                ->success()
                                ->send();
                        } catch (\Throwable $throwable) {
                            Notification::make()
                                ->title('No se pudo rechazar')
                                ->body($throwable->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageCompanyPaidMemberships::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
