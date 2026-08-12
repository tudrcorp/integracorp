<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\TdevAgencies\RelationManagers;

use App\Filament\Business\Resources\TdevAgencies\TdevAgencyResource;
use App\Models\City;
use App\Models\Country;
use App\Models\State;
use App\Models\TdevAgency;
use App\Support\Filament\FilamentIosButton;
use App\Support\Tdev\TdevAgencyRegistrar;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;

class ChildAgenciesRelationManager extends RelationManager
{
    protected static string $relationship = 'childAgencies';

    protected static ?string $title = 'Agencias nivel 3';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof TdevAgency && $ownerRecord->isLevelTwo();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                FileUpload::make('logo')
                    ->label('Imagen del logo')
                    ->directory('logos-agencias-tdev')
                    ->visibility('public')
                    ->image()
                    ->imageEditor()
                    ->maxSize(2048)
                    ->columnSpanFull(),
                TextInput::make('name')
                    ->label('Nombre de agencia')
                    ->required()
                    ->maxLength(255),
                TextInput::make('identification_number')
                    ->label('Número de identificación')
                    ->maxLength(50),
                TextInput::make('email')
                    ->label('Correo')
                    ->email()
                    ->maxLength(255),
                DatePicker::make('anniversary_date')
                    ->label('Fecha de aniversario de la agencia')
                    ->native(false)
                    ->displayFormat('d/m/Y'),
                TextInput::make('representative_name')
                    ->label('Nombre del representante')
                    ->maxLength(255),
                DatePicker::make('representative_birth_date')
                    ->label('Fecha de nacimiento del representante')
                    ->native(false)
                    ->displayFormat('d/m/Y'),
                TextInput::make('phone')
                    ->label('Número de teléfono')
                    ->tel()
                    ->maxLength(40),
                TextInput::make('phone_additional')
                    ->label('Número de teléfono adicional')
                    ->tel()
                    ->maxLength(40),
                TextInput::make('instagram_username')
                    ->label('Usuario Instagram')
                    ->prefix('@')
                    ->maxLength(100),
                Select::make('country_id')
                    ->label('País')
                    ->options(fn (): array => Country::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    ->preload()
                    ->live(),
                Select::make('state_id')
                    ->label('Estado')
                    ->options(function (Get $get): array {
                        $countryId = $get('country_id');

                        if (blank($countryId)) {
                            return [];
                        }

                        return State::query()
                            ->where('country_id', $countryId)
                            ->orderBy('definition')
                            ->pluck('definition', 'id')
                            ->all();
                    })
                    ->searchable()
                    ->preload()
                    ->live(),
                Select::make('city_id')
                    ->label('Ciudad')
                    ->options(function (Get $get): array {
                        $stateId = $get('state_id');

                        if (blank($stateId)) {
                            return [];
                        }

                        return City::query()
                            ->where('state_id', $stateId)
                            ->orderBy('definition')
                            ->pluck('definition', 'id')
                            ->all();
                    })
                    ->searchable()
                    ->preload(),
                Textarea::make('address')
                    ->label('Dirección')
                    ->rows(3)
                    ->columnSpanFull(),
                TextInput::make('url')
                    ->label('URL')
                    ->url()
                    ->maxLength(255),
                Hidden::make('level')
                    ->default(TdevAgency::LEVEL_THREE)
                    ->dehydrated(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(new HtmlString(
                <<<'HTML'
                <div class="flex flex-wrap items-center gap-3">
                    <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-amber-500/15 text-amber-700 dark:bg-amber-400/15 dark:text-amber-300">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-5 w-5" aria-hidden="true">
                            <path fill-rule="evenodd" d="M4.5 2.25a.75.75 0 0 0 0 1.5v16.5h-.75a.75.75 0 0 0 0 1.5H15a.75.75 0 0 0 0-1.5h-.75V3.75a.75.75 0 0 0 0-1.5h-9.75ZM9 6a.75.75 0 0 0 0 1.5h1.5a.75.75 0 0 0 0-1.5H9Zm-.75 3.75A.75.75 0 0 1 9 9h1.5a.75.75 0 0 1 0 1.5H9a.75.75 0 0 1-.75-.75ZM9 12a.75.75 0 0 0 0 1.5h1.5a.75.75 0 0 0 0-1.5H9Zm7.5-9a.75.75 0 0 1 .75.75V15a.75.75 0 0 1-1.5 0V3.75a.75.75 0 0 1 .75-.75Zm2.25.75a.75.75 0 0 0-1.5 0v11.25a.75.75 0 0 0 1.5 0V3.75Z" clip-rule="evenodd"/>
                        </svg>
                    </span>
                    <div>
                        <div class="text-base font-semibold tracking-tight text-gray-950 dark:text-white">Agencias nivel 3</div>
                        <div class="text-xs font-medium text-gray-500 dark:text-gray-400">Asociadas a esta agencia principal · gestiona agentes y enlaces</div>
                    </div>
                </div>
                HTML
            ))
            ->description('Red asociada bajo esta agencia nivel 2.')
            ->recordTitleAttribute('name')
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->emptyStateHeading('Sin agencias nivel 3')
            ->emptyStateDescription('Añade una agencia asociada o comparte el enlace público de registro.')
            ->emptyStateIcon(Heroicon::OutlinedBuildingOffice2)
            ->columns([
                ImageColumn::make('logo')
                    ->label('Logo')
                    ->disk('public')
                    ->visibility('public')
                    ->circular()
                    ->imageWidth(44)
                    ->imageHeight(44)
                    ->defaultImageUrl(fn (TdevAgency $record): string => 'https://ui-avatars.com/api/?name='.urlencode(substr($record->name, 0, 2)).'&background=F59E0B&color=fff&size=128')
                    ->extraImgAttributes([
                        'class' => 'object-cover ring-2 ring-amber-500/20 dark:ring-amber-400/25',
                    ]),
                TextColumn::make('name')
                    ->label('Agencia')
                    ->searchable()
                    ->sortable()
                    ->weight('font-semibold')
                    ->wrap()
                    ->grow()
                    ->icon(Heroicon::OutlinedBuildingOffice2)
                    ->iconColor('warning')
                    ->description(fn (TdevAgency $record): ?string => filled($record->identification_number)
                        ? 'ID: '.$record->identification_number
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
                    ->tooltip(fn (TdevAgency $record): ?string => $record->email),
                TextColumn::make('phone')
                    ->label('Teléfono')
                    ->icon(Heroicon::OutlinedPhone)
                    ->iconColor('gray')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Teléfono copiado')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('agents_count')
                    ->counts('agents')
                    ->label('Agentes')
                    ->badge()
                    ->alignCenter()
                    ->color('success')
                    ->icon(Heroicon::OutlinedUserGroup)
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Registrada')
                    ->dateTime('d/m/Y H:i')
                    ->description(fn (TdevAgency $record): string => $record->created_at?->diffForHumans() ?? '')
                    ->sortable()
                    ->toggleable(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Añadir agencia nivel 3')
                    ->icon(Heroicon::OutlinedPlusCircle)
                    ->color('warning')
                    ->extraAttributes([
                        'class' => FilamentIosButton::extraClassForFilamentColor('warning'),
                    ])
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['level'] = TdevAgency::LEVEL_THREE;
                        $data['created_by'] = Auth::user()?->name;

                        return $data;
                    }),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('viewAgency')
                        ->label('Ver ficha / agentes')
                        ->icon(Heroicon::OutlinedEye)
                        ->color('info')
                        ->url(fn (TdevAgency $record): string => TdevAgencyResource::getUrl('view', ['record' => $record])),
                    Action::make('openAgentRegistrationLink')
                        ->label('URL de agentes')
                        ->icon(Heroicon::OutlinedLink)
                        ->color('success')
                        ->url(fn (TdevAgency $record): string => TdevAgencyRegistrar::publicAgentRegistrationUrl($record))
                        ->openUrlInNewTab(),
                    EditAction::make()
                        ->label('Editar')
                        ->mutateFormDataUsing(function (array $data): array {
                            $data['updated_by'] = Auth::user()?->name;
                            $data['level'] = TdevAgency::LEVEL_THREE;

                            return $data;
                        }),
                    DeleteAction::make()
                        ->label('Eliminar'),
                ])
                    ->label('Acciones')
                    ->icon(Heroicon::OutlinedEllipsisVertical)
                    ->button()
                    ->color('gray'),
            ]);
    }
}
