<?php

declare(strict_types=1);

namespace App\Support\Affiliations;

use App\Models\ServiceProvider;
use App\Models\Supplier;
use App\Support\SecurityAudit;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Campo «Proveedor(es) de Servicios» de las afiliaciones individuales y corporativas.
 *
 * Se guarda como lista de textos (p. ej. ["ATENMEDI","ILS"]): el gráfico de proveedores,
 * el generador de reportes, las exportaciones y las fichas PDF leen ese texto.
 */
final class AffiliationServiceProvidersField
{
    /** @var list<string> */
    public const DEFAULT_PROVIDERS = ['ILS', 'TDEC'];

    public const NAME_MAX_LENGTH = 60;

    public static function make(): Select
    {
        return Select::make('service_providers')
            ->label('Provvedor(es) de Servicios')
            ->multiple()
            ->options(fn (Select $component): array => self::options($component->getState()))
            ->searchable()
            ->preload()
            ->prefixIcon('fontisto-person')
            ->helperText('Incluye ILS, TDEC y los alias de los proveedores con gestión activa en Integracorp. Si el proveedor no aparece, créelo con el botón «+».')
            ->afterStateUpdated(function (Select $component, mixed $state): void {
                $normalized = self::normalizeList($state);

                if ($normalized !== $state) {
                    $component->state($normalized);
                }
            })
            ->dehydrateStateUsing(fn (mixed $state): array => self::normalizeList($state))
            ->createOptionModalHeading('Nuevo proveedor de servicio')
            ->createOptionForm([
                TextInput::make('name')
                    ->label('Nombre del proveedor de servicio')
                    ->placeholder('Ej.: ASISTENCIA MÉDICA DEL CENTRO')
                    ->helperText('Se guardará en MAYÚSCULAS y quedará disponible para las próximas afiliaciones.')
                    ->required()
                    ->maxLength(self::NAME_MAX_LENGTH)
                    ->rule(fn (): \Closure => function (string $attribute, mixed $value, \Closure $fail): void {
                        if (self::normalizeName($value) === null) {
                            $fail('Ingrese el nombre del proveedor de servicio.');
                        }
                    })
                    ->extraInputAttributes([
                        'class' => 'uppercase',
                        'autocomplete' => 'off',
                    ]),
            ])
            ->createOptionUsing(fn (array $data): string => self::createCatalogProvider($data['name'] ?? null));
    }

    /**
     * ILS y TDEC, el catálogo `service_providers`, los alias de proveedores con gestión activa
     * y los valores ya guardados en la afiliación (para no perderlos al editar).
     *
     * @return array<string, string>
     */
    public static function options(mixed $currentState = []): array
    {
        $names = [
            ...self::DEFAULT_PROVIDERS,
            ...ServiceProvider::query()->pluck('name')->all(),
            ...Supplier::query()
                ->where('gestion_integracorp', true)
                ->whereNotNull('integracorp_alias')
                ->pluck('integracorp_alias')
                ->all(),
            ...self::normalizeList($currentState),
        ];

        $options = self::normalizeList($names);
        sort($options, SORT_NATURAL | SORT_FLAG_CASE);

        return array_combine($options, $options);
    }

    /**
     * Registra el proveedor en el catálogo (o reutiliza el existente) y devuelve su nombre normalizado.
     */
    public static function createCatalogProvider(mixed $name): string
    {
        $normalized = self::normalizeName($name);

        if ($normalized === null) {
            throw new \InvalidArgumentException('El nombre del proveedor de servicio es obligatorio.');
        }

        $provider = ServiceProvider::query()->createOrFirst(['name' => $normalized]);

        if ($provider->wasRecentlyCreated) {
            SecurityAudit::log('AUDIT_BUSINESS_SERVICE_PROVIDER_CREATED', 'business.affiliations.service-providers.create', [
                'service_provider_id' => $provider->getKey(),
                'name' => $normalized,
                'created_by' => Auth::user()?->name,
            ]);
        }

        return $normalized;
    }

    public static function normalizeName(mixed $name): ?string
    {
        if (! is_string($name) && ! is_numeric($name)) {
            return null;
        }

        $normalized = Str::upper(Str::squish((string) $name));

        return $normalized === '' ? null : $normalized;
    }

    /**
     * @return list<string>
     */
    public static function normalizeList(mixed $names): array
    {
        if (is_string($names)) {
            $decoded = json_decode($names, true);
            $names = is_array($decoded) ? $decoded : [$names];
        }

        if (! is_array($names)) {
            return [];
        }

        return collect($names)
            ->flatten()
            ->map(fn (mixed $name): ?string => self::normalizeName($name))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
