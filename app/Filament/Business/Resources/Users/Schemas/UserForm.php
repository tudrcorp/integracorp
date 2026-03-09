<?php

namespace App\Filament\Business\Resources\Users\Schemas;

use App\Models\Permission;
use App\Models\Rol;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class UserForm
{
    /**
     * Módulos/departamentos desde la tabla rols (campo name).
     *
     * @return array<int, string>
     */
    public static function getDepartamentModules(): array
    {
        return Rol::query()->orderBy('name')->pluck('name')->values()->all();
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informacion del Usuario')
                    ->description('Informacion principal del usuario INTEGRACORP.')
                    ->aside()
                    ->icon('heroicon-s-user')
                    ->schema([
                        Fieldset::make('Informacion del Usuario')
                            ->schema([
                                TextInput::make('name')
                                    ->label('Nombre y Apellido del usuario')
                                    ->required(),
                                TextInput::make('phone')
                                    ->label('Telefono')
                                    ->tel(),
                                DatePicker::make('birth_date')
                                    ->label('Fecha de Nacimiento')
                                    ->format('d/m/Y')
                                    ->native(false)
                                    ->displayFormat('d/m/Y'),
                                TextInput::make('email')
                                    ->label('Correo Electrónico')
                                    ->required()
                                    ->email()
                                    ->hiddenOn('edit'),
                                Select::make('departament')
                                    ->label('Módulo(s) al que pertenece el usuario')
                                    ->required()
                                    ->live()
                                    ->helperText('El usuario puede tener acceso a los módulos seleccionados.')
                                    ->options(Rol::all()->pluck('name', 'name'))
                                    ->multiple(),
                                Select::make('status')
                                    ->label('Estado')
                                    ->required()
                                    ->options([
                                        'ACTIVO' => 'ACTIVO',
                                        'INACTIVO' => 'INACTIVO',
                                    ]),

                            ])->columnSpanFull()->columns(3),

                        Fieldset::make('Contraseño del Usuario')
                            ->schema([
                                TextInput::make('password')
                                    ->label('Contraseño')
                                    ->required()
                                    ->password()
                                    ->revealable(),
                                TextInput::make('password_confirmation')
                                    ->label('Confirmar Contraseño')
                                    ->password()
                                    ->required()
                                    ->revealable(),
                            ])->columnSpanFull()->columns(2)->hiddenOn('edit'),
                    ])->columnSpanFull()->columns(3),

                Section::make('Roles del Usuario')
                    ->description('Roles asociados al usuario.')
                    ->aside()
                    ->icon('heroicon-s-user')
                    ->schema([
                        Fieldset::make('Roles')
                            ->schema([
                                Toggle::make('is_agent')
                                    ->label('Agente'),
                                Toggle::make('is_subagent')
                                    ->label('Subagente'),
                                Toggle::make('is_agency')
                                    ->label('Agencia'),
                                Toggle::make('is_accountManagers')
                                    ->label('Administrador de Cuentas'),
                                Toggle::make('is_superAdmin')
                                    ->label('Super Administrador'),
                                Toggle::make('is_business_admin')
                                    ->label('Administrador de Negocios'),
                            ])->columnSpanFull(),
                    ])->columnSpanFull(),

                Section::make('Permisos por departamento')
                    ->description('Permisos agrupados por departamento. Selecciona los permisos que deseas otorgar en cada módulo.')
                    ->aside()
                    ->icon('heroicon-s-key')
                    ->visible(fn (Get $get): bool => ! empty($get('departament')))
                    ->schema(
                        collect(self::getDepartamentModules())->map(function (string $module): Fieldset {
                            return Fieldset::make("PERMISOS MODULO: {$module}")
                                ->visible(fn (Get $get): bool => in_array($module, $get('departament') ?? [], true))
                                ->schema([
                                    CheckboxList::make("permissions_{$module}")
                                        ->label('Lista de permisos disponibles')
                                        ->options(
                                            fn () => Permission::query()
                                                ->where('module', $module)
                                                ->orderBy('name')
                                                ->pluck('name', 'id')
                                                ->toArray()
                                        )
                                        ->bulkToggleable()
                                        ->live()
                                        ->columns(4)
                                        ->gridDirection('row'),
                                ])->columnSpanFull()->columns(1);
                        })->all()
                    )->columnSpanFull(),

                Hidden::make('created_by')->default(fn () => Auth::user()->name),
                Hidden::make('updated_by'),
            ]);
    }
}
