<?php

declare(strict_types=1);

namespace App\Support\Operations;

use App\Models\CorporateAlly;
use App\Models\CorporateAllyObservacion;
use App\Models\DoctorNurse;
use App\Models\DoctorNurseObservacion;
use App\Models\Supplier;
use App\Models\SupplierObservacion;
use App\Support\Filament\BusinessFilamentActionAccess;
use App\Support\Filament\BusinessFilamentActionPermissionRegistry;
use App\Support\Filament\FilamentIosButton;
use App\Support\SecurityAudit;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HtmlString;
use InvalidArgumentException;
use Throwable;

/**
 * Eliminación reversible de proveedores jurídicos, naturales y aliados
 * corporativos (Operaciones).
 *
 * Nunca se borra el registro: `deleted_at` lo oculta en todo el sistema y se
 * puede restaurar. Solo se elimina un proveedor que no tenga **ningún** registro
 * en Operaciones ni en Telemedicina (histórico incluido) ni usuarios del portal:
 * así ninguna orden, cuenta o caso queda apuntando a un proveedor oculto. Exige
 * el permiso {@see BusinessFilamentActionPermissionRegistry::DELETE_PROVIDERS}.
 */
final class ProviderDeletion
{
    public const TYPE_SUPPLIER = 'supplier';

    public const TYPE_DOCTOR_NURSE = 'doctor_nurse';

    public const TYPE_CORPORATE_ALLY = 'corporate_ally';

    /** Clave del interruptor «Ver solo proveedores eliminados». */
    public const DELETED_FILTER = 'eliminados';

    public const MINIMUM_REASON_LENGTH = 10;

    /**
     * @var array<string, array{model: class-string<Model>, name: string, singular: string, plural: string, observation: class-string<Model>, observation_key: string}>
     */
    private const TYPES = [
        self::TYPE_SUPPLIER => [
            'model' => Supplier::class,
            'name' => 'name',
            'singular' => 'proveedor jurídico',
            'plural' => 'proveedores jurídicos',
            'observation' => SupplierObservacion::class,
            'observation_key' => 'supplier_id',
        ],
        self::TYPE_DOCTOR_NURSE => [
            'model' => DoctorNurse::class,
            'name' => 'name',
            'singular' => 'proveedor natural',
            'plural' => 'proveedores naturales',
            'observation' => DoctorNurseObservacion::class,
            'observation_key' => 'doctor_nurse_id',
        ],
        self::TYPE_CORPORATE_ALLY => [
            'model' => CorporateAlly::class,
            'name' => 'company_name',
            'singular' => 'aliado corporativo',
            'plural' => 'aliados corporativos',
            'observation' => CorporateAllyObservacion::class,
            'observation_key' => 'corporate_ally_id',
        ],
    ];

    /**
     * Dónde puede estar usado cada tipo de proveedor. Varias columnas pueden
     * compartir etiqueta: se suman. Sus propios datos (contactos, observaciones,
     * red, zonas de cobertura) no cuentan como servicio.
     *
     * @var array<string, list<array{0: string, 1: string, 2: string}>> tipo => [tabla, columna, etiqueta]
     */
    private const REFERENCES = [
        self::TYPE_SUPPLIER => [
            ['operation_service_orders', 'supplier_id', 'órdenes de servicio'],
            ['operation_service_orders', 'telemedicine_supplier_id', 'órdenes de servicio'],
            ['operation_service_order_quotes', 'supplier_id', 'cotizaciones de órdenes'],
            ['operation_coordination_services', 'supplier_id', 'servicios médicos'],
            ['operation_coordination_services', 'assigned_supplier_id', 'servicios médicos'],
            ['operation_accounts_payables', 'supplier_id', 'cuentas por pagar'],
            ['operation_accounts_receivables', 'reassignment_supplier_id', 'cuentas por cobrar'],
            ['operation_medical_appointments', 'supplier_id', 'citas médicas'],
            ['operation_quote_generators', 'supplier_id', 'cotizaciones del generador'],
            ['telemedicine_cases', 'supplier_id', 'casos de telemedicina'],
            ['telemedicine_cases', 'medical_team_supplier_id', 'casos de telemedicina'],
            ['telemedicine_patients', 'supplier_id', 'pacientes de telemedicina'],
            ['telemedicine_history_patients', 'supplier_id', 'historias clínicas'],
            ['telemedicine_amd_informs', 'supplier_id', 'informes AMD'],
            ['telemedicine_doctors', 'supplier_id', 'médicos de telemedicina'],
            ['users', 'supplier_id', 'usuarios del portal'],
        ],
        self::TYPE_DOCTOR_NURSE => [
            ['operation_service_orders', 'doctor_nurse_id', 'órdenes de servicio'],
            ['operation_coordination_services', 'assigned_doctor_nurse_id', 'servicios médicos'],
        ],
        self::TYPE_CORPORATE_ALLY => [
            ['operation_coordination_services', 'assigned_corporate_ally_id', 'servicios médicos'],
        ],
    ];

    public static function userCan(): bool
    {
        return BusinessFilamentActionAccess::userCan(BusinessFilamentActionPermissionRegistry::DELETE_PROVIDERS);
    }

    /**
     * @return list<string>
     */
    public static function types(): array
    {
        return array_keys(self::TYPES);
    }

    /**
     * @return list<array{0: string, 1: string, 2: string}>
     */
    public static function references(string $type): array
    {
        return self::REFERENCES[self::assertType($type)];
    }

    /**
     * Uso de cada proveedor, en una consulta agrupada por referencia (sin N+1).
     *
     * Va con el constructor de consultas a propósito: cuenta también filas que
     * los scopes globales ocultan (por ejemplo, trazas de casos eliminados).
     * «Cualquier servicio, histórico incluido» bloquea la eliminación.
     *
     * @param  list<int>  $ids
     * @return array<int, array<string, int>> id => [etiqueta => cantidad]
     */
    public static function usageFor(string $type, array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0)));
        $usage = [];

        if ($ids === []) {
            return $usage;
        }

        foreach (self::references($type) as [$table, $column, $label]) {
            $rows = DB::table($table)
                ->whereIn($column, $ids)
                ->selectRaw($column.' as provider_id, COUNT(*) as total')
                ->groupBy($column)
                ->get();

            foreach ($rows as $row) {
                $providerId = (int) $row->provider_id;
                $usage[$providerId][$label] = ($usage[$providerId][$label] ?? 0) + (int) $row->total;
            }
        }

        return $usage;
    }

    /**
     * @param  array<string, int>  $usage
     */
    public static function usageSummary(array $usage): string
    {
        return implode(' · ', array_map(
            static fn (string $label, int $count): string => $count.' '.$label,
            array_keys($usage),
            array_values($usage),
        ));
    }

    /**
     * @throws InvalidArgumentException si tiene servicios, ya está eliminado o el motivo es corto
     * @throws Throwable
     */
    public static function delete(Model $provider, string $reason): void
    {
        $type = self::typeOf($provider);
        $reason = self::validatedReason($reason);
        $user = Auth::user();

        DB::transaction(function () use ($provider, $type, $reason, $user): void {
            /** Se relee bloqueado: entre abrir la ventana y confirmar pudo recibir un servicio. */
            $fresh = $provider::query()->withTrashed()->lockForUpdate()->find($provider->getKey());

            if ($fresh === null || $fresh->trashed()) {
                throw new InvalidArgumentException(self::label($provider).' ya está eliminado.');
            }

            $usage = self::usageFor($type, [(int) $fresh->getKey()])[(int) $fresh->getKey()] ?? [];

            if ($usage !== []) {
                throw new InvalidArgumentException(self::label($fresh).' tiene '.self::usageSummary($usage).': no se puede eliminar.');
            }

            $fresh->forceFill([
                'deleted_by_user_id' => $user?->getAuthIdentifier(),
                'deleted_by_name' => (string) ($user->name ?? 'SISTEMA'),
                'deletion_reason' => $reason,
            ])->saveQuietly();

            $fresh->delete();

            self::writeObservation($type, $fresh, 'PROVEEDOR ELIMINADO · Motivo: '.$reason);

            SecurityAudit::log('AUDIT_PROVIDER_DELETED', 'operations.providers.delete', [
                'type' => $type,
                'provider_id' => (int) $fresh->getKey(),
                'provider_name' => self::name($fresh),
                'reason' => $reason,
            ]);
        });
    }

    /**
     * @throws InvalidArgumentException si no está eliminado o el motivo es corto
     * @throws Throwable
     */
    public static function restore(Model $provider, string $reason): void
    {
        $type = self::typeOf($provider);
        $reason = self::validatedReason($reason);

        DB::transaction(function () use ($provider, $type, $reason): void {
            $fresh = $provider::query()->withTrashed()->lockForUpdate()->find($provider->getKey());

            if ($fresh === null || ! $fresh->trashed()) {
                throw new InvalidArgumentException(self::label($provider).' no está eliminado.');
            }

            $deletedBy = (string) ($fresh->deleted_by_name ?? '—');

            $fresh->restore();
            $fresh->forceFill([
                'deleted_by_user_id' => null,
                'deleted_by_name' => null,
                'deletion_reason' => null,
            ])->saveQuietly();

            self::writeObservation($type, $fresh, 'PROVEEDOR RESTAURADO · Motivo: '.$reason);

            SecurityAudit::log('AUDIT_PROVIDER_RESTORED', 'operations.providers.restore', [
                'type' => $type,
                'provider_id' => (int) $fresh->getKey(),
                'provider_name' => self::name($fresh),
                'deleted_by' => $deletedBy,
                'reason' => $reason,
            ]);
        });
    }

    public static function makeDeleteAction(string $type): Action
    {
        return self::configureDeletion(Action::make('delete_provider'), $type, bulk: false)
            ->visible(fn (mixed $livewire, mixed $record): bool => self::userCan()
                && ! self::viewingDeleted($livewire)
                && $record instanceof Model
                && ! $record->trashed())
            ->action(function (Model $record, array $data) use ($type): void {
                self::runOver($type, Collection::make([$record]), (string) ($data['deletion_reason'] ?? ''), deleting: true);
            });
    }

    public static function makeDeleteBulkAction(string $type): BulkAction
    {
        return self::configureDeletion(BulkAction::make('delete_providers'), $type, bulk: true)
            ->visible(fn (mixed $livewire): bool => self::userCan() && ! self::viewingDeleted($livewire))
            ->deselectRecordsAfterCompletion()
            ->action(function (EloquentCollection|Collection $records, array $data) use ($type): void {
                self::runOver($type, Collection::make($records), (string) ($data['deletion_reason'] ?? ''), deleting: true);
            });
    }

    public static function makeRestoreAction(string $type): Action
    {
        return self::configureRestoration(Action::make('restore_provider'), $type, bulk: false)
            ->visible(fn (mixed $record): bool => self::userCan() && $record instanceof Model && $record->trashed())
            ->action(function (Model $record, array $data) use ($type): void {
                self::runOver($type, Collection::make([$record]), (string) ($data['restoration_reason'] ?? ''), deleting: false);
            });
    }

    public static function makeRestoreBulkAction(string $type): BulkAction
    {
        return self::configureRestoration(BulkAction::make('restore_providers'), $type, bulk: true)
            ->visible(fn (mixed $livewire): bool => self::userCan() && self::viewingDeleted($livewire))
            ->deselectRecordsAfterCompletion()
            ->action(function (EloquentCollection|Collection $records, array $data) use ($type): void {
                self::runOver($type, Collection::make($records), (string) ($data['restoration_reason'] ?? ''), deleting: false);
            });
    }

    /**
     * «Ver solo proveedores eliminados». Fuera de esa vista el borrado suave los oculta.
     */
    public static function makeDeletedFilter(): Filter
    {
        return Filter::make(self::DELETED_FILTER)
            ->label('Ver solo proveedores eliminados')
            ->toggle()
            ->columnSpanFull()
            ->visible(fn (): bool => self::userCan())
            ->baseQuery(fn (Builder $query, array $data): Builder => ($data['isActive'] ?? false) === true
                ? $query->onlyTrashed()
                : $query);
    }

    public static function viewingDeleted(mixed $livewire): bool
    {
        return data_get($livewire, 'tableFilters.'.self::DELETED_FILTER.'.isActive') === true && self::userCan();
    }

    /**
     * @template TAction of Action
     *
     * @param  TAction  $action
     * @return TAction
     */
    private static function configureDeletion(Action $action, string $type, bool $bulk): Action
    {
        $definition = self::TYPES[self::assertType($type)];

        return $action
            ->label('Eliminar')
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->modalHeading($bulk ? 'Eliminar '.$definition['plural'] : 'Eliminar '.$definition['singular'])
            ->modalDescription($bulk
                ? fn (EloquentCollection|Collection $records): HtmlString => self::deletionDescription($type, Collection::make($records))
                : fn (Model $record): HtmlString => self::deletionDescription($type, Collection::make([$record])))
            ->modalIcon(Heroicon::OutlinedTrash)
            ->modalIconColor('danger')
            ->modalWidth(Width::Large)
            ->modalSubmitActionLabel('Sí, eliminar')
            ->modalCancelActionLabel('Cancelar')
            ->closeModalByClickingAway(false)
            ->modalSubmitAction(fn (Action $action): Action => self::styleButton($action, 'danger'))
            ->modalCancelAction(fn (Action $action): Action => self::styleButton($action, 'gray'))
            ->schema([self::reasonField('deletion_reason', deleting: true)]);
    }

    /**
     * @template TAction of Action
     *
     * @param  TAction  $action
     * @return TAction
     */
    private static function configureRestoration(Action $action, string $type, bool $bulk): Action
    {
        $definition = self::TYPES[self::assertType($type)];

        return $action
            ->label('Restaurar')
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('success')
            ->modalHeading('Restaurar '.$definition['singular'])
            ->modalDescription($bulk
                ? fn (EloquentCollection|Collection $records): HtmlString => self::restorationDescription(Collection::make($records))
                : fn (Model $record): HtmlString => self::restorationDescription(Collection::make([$record])))
            ->modalIcon(Heroicon::OutlinedArrowUturnLeft)
            ->modalIconColor('success')
            ->modalWidth(Width::Large)
            ->modalSubmitActionLabel('Sí, restaurar')
            ->modalCancelActionLabel('Cancelar')
            ->closeModalByClickingAway(false)
            ->modalSubmitAction(fn (Action $action): Action => self::styleButton($action, 'success'))
            ->modalCancelAction(fn (Action $action): Action => self::styleButton($action, 'gray'))
            ->schema([self::reasonField('restoration_reason', deleting: false)]);
    }

    /**
     * @param  Collection<int, mixed>  $records
     */
    private static function runOver(string $type, Collection $records, string $reason, bool $deleting): void
    {
        if (! self::userCan()) {
            Notification::make()
                ->title('Acción no autorizada')
                ->body('No tiene permiso para eliminar o restaurar proveedores.')
                ->danger()
                ->send();

            return;
        }

        $processed = 0;
        /** @var list<string> $skipped */
        $skipped = [];

        foreach ($records->filter(fn (mixed $record): bool => $record instanceof Model) as $provider) {
            try {
                $deleting ? self::delete($provider, $reason) : self::restore($provider, $reason);
                $processed++;
            } catch (InvalidArgumentException $exception) {
                $skipped[] = $exception->getMessage();
            } catch (Throwable $exception) {
                Log::error('ProviderDeletion: error', [
                    'type' => $type,
                    'provider_id' => $provider->getKey(),
                    'deleting' => $deleting,
                    'message' => $exception->getMessage(),
                ]);

                $skipped[] = self::label($provider).' no pudo procesarse por un error inesperado.';
            }
        }

        $definition = self::TYPES[$type];
        $noun = $processed === 1 ? $definition['singular'] : $definition['plural'];

        if ($processed === 0) {
            Notification::make()
                ->title($deleting ? 'No se eliminó ningún '.$definition['singular'] : 'No se restauró ningún '.$definition['singular'])
                ->body($skipped === [] ? 'No hubo registros válidos para procesar.' : implode(' ', $skipped))
                ->warning()
                ->persistent()
                ->send();

            return;
        }

        $body = $deleting
            ? "{$processed} {$noun} eliminado(s). Ya no aparecen en el sistema; se pueden restaurar con «Ver solo proveedores eliminados»."
            : "{$processed} {$noun} restaurado(s). Vuelven a estar disponibles en todo el sistema.";

        if ($skipped !== []) {
            $body .= ' No se procesaron: '.implode(' ', $skipped);
        }

        $notification = Notification::make()
            ->title($deleting ? 'Eliminación completada' : 'Restauración completada')
            ->body($body)
            ->status($skipped === [] ? 'success' : 'warning');

        if ($skipped !== []) {
            $notification->persistent();
        }

        $notification->send();
    }

    /**
     * @param  Collection<int, mixed>  $records
     */
    private static function deletionDescription(string $type, Collection $records): HtmlString
    {
        $providers = $records->filter(fn (mixed $record): bool => $record instanceof Model)->values();

        if ($providers->isEmpty()) {
            return new HtmlString('<p class="text-sm text-gray-600 dark:text-gray-300">Seleccione al menos un registro.</p>');
        }

        $usage = self::usageFor($type, $providers->map(fn (Model $provider): int => (int) $provider->getKey())->all());
        $rows = [];
        $blocked = 0;

        foreach ($providers as $provider) {
            $providerUsage = $usage[(int) $provider->getKey()] ?? [];

            if ($providerUsage === []) {
                $rows[] = '<li><span class="font-semibold text-gray-900 dark:text-white">'.e(self::name($provider)).'</span>'
                    .' <span class="text-xs text-gray-500 dark:text-gray-400">· sin servicios: se eliminará</span></li>';

                continue;
            }

            $blocked++;
            $rows[] = '<li class="text-amber-700 dark:text-amber-300"><span class="font-semibold">'.e(self::name($provider)).'</span>'
                .' — no se eliminará: tiene '.e(self::usageSummary($providerUsage)).'.</li>';
        }

        $note = $blocked === 0
            ? ''
            : '<p class="font-semibold text-amber-700 dark:text-amber-300">'.$blocked.' de '.$providers->count().' tiene(n) servicios o usuarios del portal y se omitirá(n). Para sacarlo(s) de circulación sin eliminarlo(s), cambie su estatus de sistema.</p>';

        return new HtmlString(
            '<div class="space-y-3 text-sm text-gray-600 dark:text-gray-300">'
            .'<p>El registro dejará de aparecer en tablas, buscadores y selectores de todo el sistema. '
            .'<span class="font-semibold text-gray-900 dark:text-white">Nada se borra de la base de datos</span>: se puede restaurar activando «Ver solo proveedores eliminados».</p>'
            .'<p>Solo se elimina un proveedor sin ningún servicio (también históricos) ni usuarios del portal.</p>'
            .$note
            .'<ul class="list-disc space-y-1 ps-5">'.implode('', $rows).'</ul>'
            .'</div>'
        );
    }

    /**
     * @param  Collection<int, mixed>  $records
     */
    private static function restorationDescription(Collection $records): HtmlString
    {
        $rows = $records
            ->filter(fn (mixed $record): bool => $record instanceof Model)
            ->map(fn (Model $provider): string => '<li><span class="font-semibold text-gray-900 dark:text-white">'.e(self::name($provider)).'</span>'
                .'<br><span class="text-xs text-gray-500 dark:text-gray-400">Eliminado por '.e((string) ($provider->deleted_by_name ?? '—'))
                .($provider->deleted_at !== null ? ' el '.e($provider->deleted_at->format('d/m/Y H:i')) : '')
                .(filled($provider->deletion_reason) ? ' · Motivo: '.e((string) $provider->deletion_reason) : '')
                .'</span></li>')
            ->implode('');

        return new HtmlString(
            '<div class="space-y-3 text-sm text-gray-600 dark:text-gray-300">'
            .'<p>Volverá a aparecer en todo el sistema con los mismos datos que tenía.</p>'
            .'<ul class="list-disc space-y-2 ps-5">'.$rows.'</ul>'
            .'</div>'
        );
    }

    private static function reasonField(string $name, bool $deleting): Textarea
    {
        return Textarea::make($name)
            ->label($deleting ? 'Motivo de la eliminación' : 'Motivo de la restauración')
            ->placeholder($deleting
                ? 'Ej.: registro duplicado, cargado por error, el proveedor ya no existe…'
                : 'Ej.: se eliminó por error, el proveedor vuelve a trabajar con nosotros…')
            ->helperText('Obligatorio, mínimo '.self::MINIMUM_REASON_LENGTH.' caracteres. Queda en las observaciones del proveedor y en la auditoría.')
            ->required()
            ->minLength(self::MINIMUM_REASON_LENGTH)
            ->maxLength(2000)
            ->rows(4)
            ->columnSpanFull()
            ->validationMessages([
                'required' => 'Debe indicar el motivo.',
                'min' => 'El motivo debe tener al menos '.self::MINIMUM_REASON_LENGTH.' caracteres.',
                'minLength' => 'El motivo debe tener al menos '.self::MINIMUM_REASON_LENGTH.' caracteres.',
            ]);
    }

    private static function writeObservation(string $type, Model $provider, string $text): void
    {
        $definition = self::TYPES[$type];
        $user = Auth::user();

        $definition['observation']::query()->create(array_filter([
            $definition['observation_key'] => $provider->getKey(),
            'observation' => $text,
            'created_by' => (string) ($user->name ?? 'SISTEMA'),
        ], static fn (mixed $value): bool => $value !== null));
    }

    private static function typeOf(Model $provider): string
    {
        foreach (self::TYPES as $type => $definition) {
            if ($provider instanceof $definition['model']) {
                return $type;
            }
        }

        throw new InvalidArgumentException('El registro no es un proveedor.');
    }

    private static function assertType(string $type): string
    {
        if (! isset(self::TYPES[$type])) {
            throw new InvalidArgumentException('Tipo de proveedor desconocido: '.$type);
        }

        return $type;
    }

    private static function name(Model $provider): string
    {
        $type = self::typeOf($provider);
        $name = trim((string) $provider->getAttribute(self::TYPES[$type]['name']));

        return $name !== '' ? $name : 'Registro #'.$provider->getKey();
    }

    private static function label(Model $provider): string
    {
        return 'El '.self::TYPES[self::typeOf($provider)]['singular'].' '.self::name($provider);
    }

    private static function validatedReason(string $reason): string
    {
        $reason = trim($reason);

        if (mb_strlen($reason) < self::MINIMUM_REASON_LENGTH) {
            throw new InvalidArgumentException('Debe indicar el motivo con al menos '.self::MINIMUM_REASON_LENGTH.' caracteres.');
        }

        return $reason;
    }

    private static function styleButton(Action $action, string $color): Action
    {
        return $action
            ->color($color)
            ->extraAttributes(['class' => FilamentIosButton::extraClassForFilamentColor($color)]);
    }
}
