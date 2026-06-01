<?php

declare(strict_types=1);

namespace App\Filament\Telemedicina\Resources\TelemedicineDoctors\Tables;

use App\Models\TelemedicineDoctor;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class TelemedicineDoctorsTable
{
    private static function statusBadgeColor(?string $status): string
    {
        return match (mb_strtoupper((string) $status)) {
            'ACTIVO', 'ACTIVA' => 'success',
            'INACTIVO', 'INACTIVA' => 'danger',
            default => 'gray',
        };
    }

    private static function managedByBadgeColor(?string $managedBy): string
    {
        return match (mb_strtoupper((string) $managedBy)) {
            'ATENMEDI' => 'info',
            'TDG' => 'primary',
            default => 'gray',
        };
    }

    /**
     * @return list<string>
     */
    private static function profileSummaryLines(TelemedicineDoctor $record): array
    {
        $lines = [
            filled($record->specialty) ? (string) $record->specialty : null,
            filled($record->managed_by) ? 'Pertenece a: '.mb_strtoupper((string) $record->managed_by) : null,
            filled($record->status) ? 'Estado: '.mb_strtoupper((string) $record->status) : null,
        ];

        return array_values(array_filter($lines));
    }

    public static function configure(Table $table): Table
    {
        return $table
            ->query(TelemedicineDoctor::query()->where('id', Auth::user()?->doctor_id))
            ->heading('Mi perfil médico')
            ->description('Resumen de su ficha profesional. Use «Ver perfil» o «Editar» para actualizar datos, credenciales o firma digital.')
            ->defaultSort('full_name', 'asc')
            ->emptyStateHeading('Perfil no vinculado')
            ->emptyStateDescription('Su usuario aún no tiene un médico asociado. Contacte a operaciones para completar el registro.')
            ->emptyStateIcon(Heroicon::OutlinedUserCircle)
            ->recordActionsColumnLabel('')
            ->extraAttributes([
                'class' => 'telemedicine-case-table-ios telemedicine-doctor-profile-table',
            ])
            ->columns([
                ImageColumn::make('image')
                    ->label('')
                    ->circular()
                    ->size(48)
                    ->defaultImageUrl(fn (TelemedicineDoctor $record): string => 'https://ui-avatars.com/api/?name='.urlencode(Str::limit($record->full_name ?? 'M', 40)).'&color=FFFFFF&background=0ea5e9')
                    ->extraImgAttributes([
                        'class' => 'ring-2 ring-sky-200/80 dark:ring-sky-500/40',
                    ])
                    ->extraCellAttributes(['class' => 'py-3 w-16 shrink-0']),
                TextColumn::make('full_name')
                    ->label('Médico')
                    ->icon(Heroicon::OutlinedUserCircle)
                    ->weight(FontWeight::SemiBold)
                    ->formatStateUsing(fn (?string $state): ?string => filled($state) ? mb_strtoupper($state) : null)
                    ->description(fn (TelemedicineDoctor $record): string => 'V-'.($record->nro_identificacion ?? '—'))
                    ->searchable()
                    ->wrap()
                    ->extraCellAttributes(['class' => 'py-3 min-w-[10rem] max-w-[16rem]']),
                TextColumn::make('profile_summary')
                    ->label('Resumen')
                    ->icon(Heroicon::OutlinedAcademicCap)
                    ->state(fn (TelemedicineDoctor $record): array => self::profileSummaryLines($record))
                    ->listWithLineBreaks()
                    ->placeholder('—')
                    ->wrap()
                    ->extraCellAttributes(['class' => 'py-3 min-w-[9rem] max-w-[14rem]']),
                TextColumn::make('specialty')
                    ->label('Especialidad')
                    ->icon(Heroicon::OutlinedAcademicCap)
                    ->badge()
                    ->color('info')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->extraCellAttributes(['class' => 'py-3']),
                TextColumn::make('managed_by')
                    ->label('Pertenece a')
                    ->icon(Heroicon::OutlinedBuildingOffice2)
                    ->badge()
                    ->color(fn (?string $state): string => self::managedByBadgeColor($state))
                    ->formatStateUsing(fn (?string $state): string => filled($state) ? mb_strtoupper($state) : '—')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->extraCellAttributes(['class' => 'py-3']),
                TextColumn::make('status')
                    ->label('Estado')
                    ->icon(Heroicon::OutlinedShieldCheck)
                    ->badge()
                    ->color(fn (?string $state): string => self::statusBadgeColor($state))
                    ->formatStateUsing(fn (?string $state): string => filled($state) ? mb_strtoupper($state) : '—')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->extraCellAttributes(['class' => 'py-3']),
                TextColumn::make('email')
                    ->label('Contacto')
                    ->icon(Heroicon::OutlinedEnvelope)
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Correo copiado')
                    ->limit(28)
                    ->tooltip(fn (TelemedicineDoctor $record): ?string => strlen((string) $record->email) > 28 ? $record->email : null)
                    ->description(fn (TelemedicineDoctor $record): string => filled($record->phone) ? $record->phone : '—')
                    ->extraCellAttributes(['class' => 'py-3 min-w-[9rem] max-w-[12rem]']),
                TextColumn::make('code_cm')
                    ->label('Credenciales')
                    ->icon(Heroicon::OutlinedHashtag)
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (?string $state): string => filled($state) ? 'CM: '.$state : 'CM: —')
                    ->description(fn (TelemedicineDoctor $record): string => filled($record->code_mpps) ? 'MPPS: '.$record->code_mpps : 'MPPS: —')
                    ->searchable(query: function ($query, string $search) {
                        return $query->where(function ($q) use ($search): void {
                            $q->where('code_cm', 'like', "%{$search}%")
                                ->orWhere('code_mpps', 'like', "%{$search}%");
                        });
                    })
                    ->extraCellAttributes(['class' => 'py-3 min-w-[8rem]']),
                ImageColumn::make('signature')
                    ->label('Firma')
                    ->alignCenter()
                    ->imageHeight(48)
                    ->imageWidth(160)
                    ->extraAttributes([
                        'class' => 'max-w-[11rem] overflow-hidden',
                    ])
                    ->extraImgAttributes([
                        'class' => 'mx-auto block max-h-12 max-w-[10rem] object-contain object-center rounded',
                    ])
                    ->extraCellAttributes(['class' => 'py-3 w-[11rem] shrink-0']),
                TextColumn::make('created_at')
                    ->label('Registro')
                    ->icon(Heroicon::OutlinedCalendarDays)
                    ->dateTime('d/m/Y H:i')
                    ->description(fn (TelemedicineDoctor $record): string => $record->created_at?->diffForHumans() ?? '')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->extraCellAttributes(['class' => 'py-3']),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make()
                    ->label('Ver perfil')
                    ->icon(Heroicon::OutlinedEye)
                    ->color('primary'),
                EditAction::make()
                    ->label('Editar')
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->color('gray'),
            ])
            ->striped()
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(10);
    }
}
