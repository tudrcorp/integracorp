<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\TdevAgencies\RelationManagers;

use App\Models\TdevAgent;
use App\Support\Filament\FilamentIosButton;
use App\Support\Tdev\TdevAgencyRegistrar;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;

class AgentsRelationManager extends RelationManager
{
    protected static string $relationship = 'agents';

    protected static ?string $title = 'Agentes TDEV';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('full_name')
                    ->label('Nombre y apellido')
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdatedJs(<<<'JS'
                        $set('full_name', $state.toUpperCase());
                    JS),
                TextInput::make('position')
                    ->label('Cargo')
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdatedJs(<<<'JS'
                        $set('position', $state.toUpperCase());
                    JS),
                TextInput::make('email')
                    ->label('Correo')
                    ->email()
                    ->maxLength(255),
                TextInput::make('phone')
                    ->label('Teléfono')
                    ->tel()
                    ->maxLength(40),
                DatePicker::make('birth_date')
                    ->label('Fecha de nacimiento')
                    ->native(false)
                    ->displayFormat('d/m/Y'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(new HtmlString(
                <<<'HTML'
                <div class="flex flex-wrap items-center gap-3">
                    <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-500/15 text-emerald-700 dark:bg-emerald-400/15 dark:text-emerald-300">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-5 w-5" aria-hidden="true">
                            <path fill-rule="evenodd" d="M8.25 6.75a3.75 3.75 0 1 1 7.5 0 3.75 3.75 0 0 1-7.5 0ZM15.75 9.75a3 3 0 1 1 6 0 3 3 0 0 1-6 0ZM2.25 9.75a3 3 0 1 1 6 0 3 3 0 0 1-6 0ZM6.31 15.117A6.745 6.745 0 0 1 12 12a6.745 6.745 0 0 1 6.709 7.498.75.75 0 0 1-.372.568A12.696 12.696 0 0 1 12 21.75a12.696 12.696 0 0 1-6.337-1.684.75.75 0 0 1-.372-.568 6.787 6.787 0 0 1 1.019-4.38Z" clip-rule="evenodd"/>
                            <path d="M5.082 14.254a8.287 8.287 0 0 0-1.308 5.135 9.687 9.687 0 0 1-1.764-.44l-.115-.04a.563.563 0 0 1-.373-.487l-.01-.121a3.75 3.75 0 0 1 3.57-4.047ZM20.226 19.389a8.287 8.287 0 0 0-1.308-5.135 3.75 3.75 0 0 1 3.57 4.047l-.01.121a.563.563 0 0 1-.373.487l-.115.04c-.576.19-1.17.328-1.764.44Z"/>
                        </svg>
                    </span>
                    <div>
                        <div class="text-base font-semibold tracking-tight text-gray-950 dark:text-white">Agentes TDEV</div>
                        <div class="text-xs font-medium text-gray-500 dark:text-gray-400">Registrados por panel o formulario público de esta agencia</div>
                    </div>
                </div>
                HTML
            ))
            ->description('Equipo comercial vinculado a esta agencia.')
            ->recordTitleAttribute('full_name')
            ->defaultSort('registered_at', 'desc')
            ->striped()
            ->emptyStateHeading('Sin agentes registrados')
            ->emptyStateDescription('Añade un agente desde el panel o comparte la URL pública de registro.')
            ->emptyStateIcon(Heroicon::OutlinedUserGroup)
            ->columns([
                TextColumn::make('full_name')
                    ->label('Agente')
                    ->searchable()
                    ->sortable()
                    ->weight('font-semibold')
                    ->wrap()
                    ->grow()
                    ->icon(Heroicon::OutlinedUser)
                    ->iconColor('success')
                    ->description(fn (TdevAgent $record): ?string => filled($record->position)
                        ? (string) $record->position
                        : null),
                TextColumn::make('email')
                    ->label('Correo')
                    ->icon(Heroicon::OutlinedEnvelope)
                    ->iconColor('gray')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Correo copiado')
                    ->placeholder('—')
                    ->limit(28)
                    ->tooltip(fn (TdevAgent $record): ?string => $record->email),
                TextColumn::make('phone')
                    ->label('Teléfono')
                    ->icon(Heroicon::OutlinedPhone)
                    ->iconColor('gray')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Teléfono copiado')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('birth_date')
                    ->label('Nacimiento')
                    ->date('d/m/Y')
                    ->icon(Heroicon::OutlinedCake)
                    ->iconColor('gray')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('registration_source')
                    ->label('Origen')
                    ->badge()
                    ->alignCenter()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        TdevAgencyRegistrar::REGISTRATION_SOURCE_PUBLIC => 'Público',
                        TdevAgencyRegistrar::REGISTRATION_SOURCE_PANEL => 'Panel',
                        default => $state ?: '—',
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        TdevAgencyRegistrar::REGISTRATION_SOURCE_PUBLIC => 'success',
                        TdevAgencyRegistrar::REGISTRATION_SOURCE_PANEL => 'info',
                        default => 'gray',
                    })
                    ->icon(fn (?string $state): Heroicon => match ($state) {
                        TdevAgencyRegistrar::REGISTRATION_SOURCE_PUBLIC => Heroicon::OutlinedGlobeAlt,
                        TdevAgencyRegistrar::REGISTRATION_SOURCE_PANEL => Heroicon::OutlinedComputerDesktop,
                        default => Heroicon::OutlinedQuestionMarkCircle,
                    }),
                TextColumn::make('registered_at')
                    ->label('Registrado')
                    ->dateTime('d/m/Y H:i')
                    ->description(fn (TdevAgent $record): string => $record->registered_at?->diffForHumans() ?? '')
                    ->sortable()
                    ->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('registration_source')
                    ->label('Origen')
                    ->options([
                        TdevAgencyRegistrar::REGISTRATION_SOURCE_PUBLIC => 'Público',
                        TdevAgencyRegistrar::REGISTRATION_SOURCE_PANEL => 'Panel',
                    ]),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Añadir agente')
                    ->icon(Heroicon::OutlinedPlusCircle)
                    ->color('success')
                    ->extraAttributes([
                        'class' => FilamentIosButton::extraClassForFilamentColor('success'),
                    ])
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['registration_source'] = TdevAgencyRegistrar::REGISTRATION_SOURCE_PANEL;
                        $data['registered_at'] = now();
                        $data['created_by'] = Auth::user()?->name;

                        return $data;
                    }),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make()
                        ->label('Editar')
                        ->mutateFormDataUsing(function (array $data): array {
                            $data['updated_by'] = Auth::user()?->name;

                            return $data;
                        }),
                    DeleteAction::make()
                        ->label('Eliminar'),
                ])
                    ->label('Acciones')
                    ->icon(Heroicon::OutlinedEllipsisVertical)
                    ->button()
                    ->color('gray'),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
            ]);
    }
}
