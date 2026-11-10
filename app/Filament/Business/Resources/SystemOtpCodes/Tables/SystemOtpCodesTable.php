<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\SystemOtpCodes\Tables;

use App\Enums\SystemOtpCodeKind;
use App\Filament\Business\Resources\SystemOtpCodes\SystemOtpCodeResource;
use App\Models\SystemOtpCode;
use App\Support\SecurityAudit;
use Filament\Actions\Action;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;

class SystemOtpCodesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->heading('Claves OTP emitidas')
            ->description('La clave dura '.((int) config('clinical-entitlements.otp.ttl_minutes', 5)).' minutos para usarse; aquí se puede consultar durante '.SystemOtpCode::RETENTION_HOURS.' horas y luego se borra sola.')
            ->defaultSort('id', 'desc')
            ->poll('10s')
            ->emptyStateIcon('heroicon-o-key')
            ->emptyStateHeading('No hay claves en las últimas '.SystemOtpCode::RETENTION_HOURS.' horas')
            ->emptyStateDescription('Cuando alguien pida una clave OTP (población corporativa, servicio fuera de cupo o cupos clínicos) aparecerá aquí.')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Generada')
                    ->dateTime('d/m/Y h:i:s A')
                    ->description(fn (SystemOtpCode $record): ?string => $record->created_at?->diffForHumans())
                    ->sortable(),
                TextColumn::make('kind')
                    ->label('Para qué')
                    ->badge()
                    ->formatStateUsing(fn (SystemOtpCodeKind $state): string => $state->label())
                    ->color(fn (SystemOtpCodeKind $state): string => $state->color())
                    ->description(fn (SystemOtpCode $record): string => $record->kind->whereLabel()),
                TextColumn::make('subject')
                    ->label('Sobre qué')
                    ->wrap()
                    ->limit(80)
                    ->tooltip(fn (SystemOtpCode $record): ?string => $record->subject)
                    ->searchable(),
                TextColumn::make('requested_by_name')
                    ->label('Pedida por')
                    ->icon('heroicon-m-user')
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Estado')
                    ->state(fn (SystemOtpCode $record): string => $record->status())
                    ->badge()
                    ->color(fn (string $state): string => SystemOtpCode::statusColor($state))
                    ->tooltip(fn (string $state): string => SystemOtpCode::statusHint($state)),
                TextColumn::make('expires_at')
                    ->label('Vence')
                    ->dateTime('h:i:s A')
                    ->description(fn (SystemOtpCode $record): ?string => $record->expires_at?->isFuture() ? 'en '.$record->expires_at->diffForHumans(null, true) : 'vencida')
                    ->sortable(),
                TextColumn::make('delivery')
                    ->label('Enviada a')
                    ->state(fn (SystemOtpCode $record): string => self::deliveryLabel($record))
                    ->color(fn (SystemOtpCode $record): string => ($record->emails_sent + $record->phones_sent) === 0 ? 'danger' : 'gray'),
                TextColumn::make('masked_code')
                    ->label('Clave')
                    ->state('••• •••')
                    ->fontFamily('mono')
                    ->color('gray'),
            ])
            ->filters([
                SelectFilter::make('kind')
                    ->label('Para qué')
                    ->options(SystemOtpCodeKind::options()),
                Filter::make('only_active')
                    ->label('Solo las que todavía sirven')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => self::onlyActive($query)),
            ])
            ->recordActions([
                Action::make('reveal')
                    ->label('Ver clave')
                    ->icon('heroicon-o-eye')
                    ->color('primary')
                    ->button()
                    ->authorize(fn (): bool => SystemOtpCodeResource::userIsSuperAdmin())
                    ->modalIcon('heroicon-o-key')
                    ->modalHeading(fn (SystemOtpCode $record): string => 'Clave · '.$record->kind->label())
                    ->modalDescription('Díctela solo a quien la pidió. Que se mostró queda registrado en la auditoría.')
                    ->modalWidth(Width::Medium)
                    ->mountUsing(function (SystemOtpCode $record): void {
                        SecurityAudit::log('AUDIT_SYSTEM_OTP_CODE_REVEALED', 'business.claves-otp', [
                            'system_otp_code_id' => $record->getKey(),
                            'kind' => $record->kind->value,
                            'requested_by_user_id' => $record->requested_by_user_id,
                            'status' => $record->status(),
                        ]);
                    })
                    ->modalContent(fn (SystemOtpCode $record): View => view('filament.business.system-otp-codes.reveal', [
                        'code' => (string) $record->code,
                        'status' => $record->status(),
                        'statusHint' => SystemOtpCode::statusHint($record->status()),
                        'record' => $record,
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar'),
            ]);
    }

    public static function deliveryLabel(SystemOtpCode $record): string
    {
        if ($record->emails_sent === 0 && $record->phones_sent === 0) {
            return 'No salió';
        }

        $parts = [];

        if ($record->phones_sent > 0) {
            $parts[] = $record->phones_sent.' WhatsApp';
        }

        if ($record->emails_sent > 0) {
            $parts[] = $record->emails_sent.' '.($record->emails_sent === 1 ? 'correo' : 'correos');
        }

        return implode(' · ', $parts);
    }

    /**
     * Claves que todavía pueden usarse: entregadas, sin usar, sin vencer y sin un
     * reenvío posterior. El estado exacto (reto anulado o sin intentos) lo da la columna.
     */
    public static function onlyActive(Builder $query): Builder
    {
        $table = (new SystemOtpCode)->getTable();

        return $query
            ->whereNull($table.'.used_at')
            ->where($table.'.expires_at', '>', now())
            ->whereRaw('('.$table.'.emails_sent + '.$table.'.phones_sent) > 0')
            ->whereNotExists(function ($sub) use ($table): void {
                $sub->selectRaw('1')
                    ->from($table.' as newer')
                    ->whereColumn('newer.challenge_type', $table.'.challenge_type')
                    ->whereColumn('newer.challenge_id', $table.'.challenge_id')
                    ->whereColumn('newer.id', '>', $table.'.id');
            });
    }
}
