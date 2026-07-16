<?php

declare(strict_types=1);

namespace App\Support\Operations;

use App\Models\Supplier;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Alta rápida de proveedor jurídico desde el select de Parámetros de cotización.
 */
final class ManageQuoteSupplierCreator
{
    private const STATUS_CONVENIO = 'NO CONVENIDO';

    private const STATUS_SISTEMA = 'PENDIENTE';

    /**
     * @return array<int, TextInput>
     */
    public static function createOptionForm(): array
    {
        return [
            TextInput::make('name')
                ->label('Nombre / Razón social')
                ->required()
                ->maxLength(255)
                ->placeholder('Nombre comercial o razón social')
                ->prefixIcon(Heroicon::OutlinedBuildingOffice2)
                ->afterStateUpdatedJs(<<<'JS'
                    $set('name', $state.toUpperCase());
                JS)
                ->columnSpanFull(),
            TextInput::make('rif')
                ->label('RIF')
                ->required()
                ->maxLength(30)
                ->placeholder('J-123456789')
                ->prefixIcon(Heroicon::OutlinedIdentification)
                ->columnSpanFull(),
            TextInput::make('personal_phone')
                ->label('Teléfono')
                ->tel()
                ->maxLength(30)
                ->placeholder('04141234567')
                ->prefixIcon(Heroicon::OutlinedPhone)
                ->columnSpanFull(),
            TextInput::make('correo_principal')
                ->label('Correo electrónico')
                ->email()
                ->maxLength(255)
                ->placeholder('correo@dominio.com')
                ->prefixIcon(Heroicon::OutlinedEnvelope)
                ->columnSpanFull(),
            TextInput::make('ubicacion_principal')
                ->label('Dirección')
                ->maxLength(255)
                ->placeholder('Dirección física del proveedor')
                ->prefixIcon(Heroicon::OutlinedMapPin)
                ->afterStateUpdatedJs(<<<'JS'
                    $set('ubicacion_principal', $state.toUpperCase());
                JS)
                ->columnSpanFull(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function create(array $data): int
    {
        $userName = Auth::user()?->name ?? 'Sistema';
        $name = Str::upper(trim((string) ($data['name'] ?? '')));
        $rif = Str::upper(trim((string) ($data['rif'] ?? '')));

        $supplier = Supplier::query()->create([
            'name' => $name,
            'rif' => $rif,
            'razon_social' => $name,
            'personal_phone' => self::nullableString($data['personal_phone'] ?? null),
            'correo_principal' => self::nullableString($data['correo_principal'] ?? null),
            'ubicacion_principal' => self::nullableString(
                filled($data['ubicacion_principal'] ?? null)
                    ? Str::upper(trim((string) $data['ubicacion_principal']))
                    : null
            ),
            'status_convenio' => self::STATUS_CONVENIO,
            'status_sistema' => self::STATUS_SISTEMA,
            'observaciones' => 'Registro rápido desde parámetros de cotización.',
            'created_by' => $userName,
            'updated_by' => $userName,
        ]);

        return (int) $supplier->getKey();
    }

    public static function configureSelect(Select $select): Select
    {
        return $select
            ->createOptionForm(self::createOptionForm())
            ->createOptionUsing(fn (array $data): int => self::create($data))
            ->createOptionModalHeading('Registrar nuevo proveedor')
            ->createOptionAction(fn (Action $action): Action => $action
                ->label('Crear proveedor')
                ->modalHeading('Registrar nuevo proveedor')
                ->modalDescription('Complete los datos del proveedor. Quedará disponible en la lista y seleccionado automáticamente.')
                ->modalSubmitActionLabel('Crear y seleccionar')
                ->icon(Heroicon::OutlinedPlusCircle));
    }

    private static function nullableString(mixed $value): ?string
    {
        $string = trim((string) ($value ?? ''));

        return $string !== '' ? $string : null;
    }
}
