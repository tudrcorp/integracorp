<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\AffiliationDateInconsistencies\Tables;

use App\Enums\AffiliationDateInconsistencyKind as Kind;
use App\Models\AffiliationDateInconsistency as Inconsistency;
use App\Models\User;
use App\Support\Affiliations\DateCorrection\DateCorrectionSubject;
use App\Support\Affiliations\DateInconsistencies\DateInconsistencyResolver;
use App\Support\Filament\BusinessFilamentActionAccess;
use App\Support\Filament\BusinessFilamentActionPermissionRegistry;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use RuntimeException;

class AffiliationDateInconsistenciesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort(fn (Builder $query): Builder => $query
                ->orderByRaw("CASE severity WHEN 'ALTA' THEN 0 WHEN 'MEDIA' THEN 1 ELSE 2 END")
                ->orderBy('first_detected_at'))
            ->striped()
            ->defaultPaginationPageOption(25)
            ->paginationPageOptions([10, 25, 50, 100])
            ->emptyStateHeading('Sin inconsistencias en esta vista')
            ->emptyStateDescription('El sistema revisa las fechas de todas las afiliaciones activas cada día a las 6:45. Lo que encuentre aparece aquí, explicado.')
            ->emptyStateIcon(Heroicon::OutlinedCheckBadge)
            ->columns([
                TextColumn::make('severity')
                    ->label('Gravedad')
                    ->badge()
                    ->color(fn (?string $state): string => Kind::severityColor($state))
                    ->sortable(),
                TextColumn::make('kind')
                    ->label('Inconsistencia')
                    ->formatStateUsing(fn (Inconsistency $record): string => $record->kindEnum()?->label() ?? (string) $record->kind)
                    ->description(fn (Inconsistency $record): string => Str::limit((string) ($record->details['explanation'] ?? ''), 140))
                    ->wrap(),
                TextColumn::make('affiliation_code')
                    ->label('Afiliación')
                    ->weight('bold')
                    ->description(fn (Inconsistency $record): string => $record->affiliation_type === DateCorrectionSubject::TYPE_CORPORATE ? 'Corporativa' : 'Individual')
                    ->searchable()
                    ->copyable(),
                TextColumn::make('repair_mode')
                    ->label('Cómo se resuelve')
                    ->badge()
                    ->state(fn (Inconsistency $record): string => match ($record->details['repair_mode'] ?? Kind::REPAIR_MANUAL) {
                        Kind::REPAIR_AUTO => 'Reparación guiada',
                        Kind::REPAIR_DATE => 'El analista elige la fecha',
                        default => 'Manual (fuera de aquí)',
                    })
                    ->color(fn (Inconsistency $record): string => ($record->details['repair_mode'] ?? Kind::REPAIR_MANUAL) === Kind::REPAIR_MANUAL ? 'gray' : 'info'),
                TextColumn::make('first_detected_at')
                    ->label('Detectada')
                    ->dateTime('d/m/Y')
                    ->description(fn (Inconsistency $record): ?string => $record->first_detected_at?->diffForHumans())
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (Inconsistency $record): string => $record->statusLabel())
                    ->color(fn (?string $state): string => match ($state) {
                        Inconsistency::STATUS_OPEN => 'danger',
                        Inconsistency::STATUS_REPAIRED => 'success',
                        Inconsistency::STATUS_DISMISSED => 'gray',
                        default => 'info',
                    })
                    ->description(fn (Inconsistency $record): ?string => $record->resolved_by_name !== null ? 'por '.$record->resolved_by_name : null),
            ])
            ->filters([
                SelectFilter::make('kind')
                    ->label('Inconsistencia')
                    ->options(Kind::options())
                    ->native(false),
                SelectFilter::make('severity')
                    ->label('Gravedad')
                    ->options([
                        Kind::SEVERITY_HIGH => 'Alta',
                        Kind::SEVERITY_MEDIUM => 'Media',
                        Kind::SEVERITY_LOW => 'Baja',
                    ])
                    ->native(false),
                SelectFilter::make('affiliation_type')
                    ->label('Tipo de afiliación')
                    ->options([
                        DateCorrectionSubject::TYPE_INDIVIDUAL => 'Individual',
                        DateCorrectionSubject::TYPE_CORPORATE => 'Corporativa',
                    ])
                    ->native(false),
            ])
            ->recordActions([
                ViewAction::make()->label('Revisar'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('dismissMany')
                        ->label('No son inconsistencias')
                        ->icon(Heroicon::OutlinedHandThumbUp)
                        ->color('gray')
                        ->modalHeading('Marcar como «no es una inconsistencia»')
                        ->modalDescription('Salen de la lista de abiertas mientras sus datos no cambien. La misma nota queda en cada una y se puede reabrir cualquiera después.')
                        ->modalSubmitActionLabel('Marcar')
                        ->visible(fn (): bool => BusinessFilamentActionAccess::userCan(BusinessFilamentActionPermissionRegistry::REPAIR_DATE_INCONSISTENCIES))
                        ->schema([
                            Textarea::make('note')
                                ->label('Nota explicativa')
                                ->required()
                                ->minLength(DateInconsistencyResolver::NOTE_MIN_LENGTH)
                                ->maxLength(2000)
                                ->rows(3)
                                ->placeholder('Ejemplo: Negocios confirma que estas pólizas siguen cobrando por continuidad; se renuevan al cierre del año.'),
                            Checkbox::make('confirmed')
                                ->label('Revisé cada una y confirmo que no son inconsistencias.')
                                ->accepted()
                                ->required(),
                        ])
                        ->action(function (EloquentCollection $records, array $data): void {
                            $user = Auth::user();

                            try {
                                $count = DateInconsistencyResolver::dismissMany(
                                    $records->map(fn (Inconsistency $record): int => (int) $record->getKey())->values()->all(),
                                    (string) ($data['note'] ?? ''),
                                    $user instanceof User ? $user : throw new RuntimeException('Sesión no válida.'),
                                );
                            } catch (RuntimeException $exception) {
                                Notification::make()->title('No se marcó ninguna')->body($exception->getMessage())->danger()->persistent()->send();

                                return;
                            }

                            Notification::make()->title($count.' marcada(s) como no inconsistencia')->body('Se pueden reabrir desde la pestaña «No son inconsistencia».')->success()->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }
}
