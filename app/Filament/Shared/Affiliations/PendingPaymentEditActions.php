<?php

declare(strict_types=1);

namespace App\Filament\Shared\Affiliations;

use App\Models\PaidMembership;
use App\Models\PaidMembershipCorporate;
use App\Models\PaymentEditLog;
use App\Models\User;
use App\Support\Affiliations\PaymentVoucherFile;
use App\Support\Affiliations\PendingPaymentDeletion;
use App\Support\Affiliations\PendingPaymentEdition;
use App\Support\Filament\BusinessFilamentActionAccess;
use App\Support\Filament\BusinessFilamentActionPermissionRegistry;
use App\Support\PaidMembershipDocumentUrl;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use InvalidArgumentException;
use Throwable;

/**
 * «Editar pago» y «Ver ediciones» de la tabla «Pagos registrados» de
 * Administración (individual y corporativa). Las reglas viven en
 * {@see PendingPaymentEdition}.
 */
final class PendingPaymentEditActions
{
    private const VOUCHER_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'application/pdf'];

    private const VOUCHER_MAX_KB = 10240;

    private static ?bool $traceTableExists = null;

    public static function edit(): Action
    {
        return Action::make('editPendingPayment')
            ->label('Editar')
            ->icon(Heroicon::OutlinedPencilSquare)
            ->color('warning')
            ->visible(fn (Model $record): bool => self::isPending($record)
                && BusinessFilamentActionAccess::userCan(BusinessFilamentActionPermissionRegistry::EDIT_PENDING_PAYMENT))
            ->modalIcon(Heroicon::OutlinedPencilSquare)
            ->modalHeading('Editar pago pendiente')
            ->modalDescription('Corrija los datos del pago antes de aprobarlo. El total a pagar no se modifica. Cada cambio queda en la traza con su motivo.')
            ->modalWidth(Width::FourExtraLarge)
            ->modalSubmitActionLabel('Guardar cambios')
            ->modalSubmitAction(fn (Action $action, Model $record): Action|false => self::blockers($record) === []
                ? $action->color('warning')
                : false)
            ->modalCancelActionLabel('Cancelar')
            ->fillForm(fn (Model $record): array => $record instanceof PaidMembership || $record instanceof PaidMembershipCorporate
                ? PendingPaymentEdition::formState($record)
                : [])
            ->schema(fn (Model $record): array => self::blockers($record) !== []
                ? [self::blockedNotice($record)]
                : self::formComponents($record))
            ->action(function (Model $record, array $data, Action $action): void {
                $user = Auth::user();
                abort_unless(
                    $user instanceof User
                        && ($record instanceof PaidMembership || $record instanceof PaidMembershipCorporate)
                        && BusinessFilamentActionAccess::userCan(BusinessFilamentActionPermissionRegistry::EDIT_PENDING_PAYMENT),
                    403,
                );

                $uploads = self::newUploads($record, $data);

                try {
                    $result = PendingPaymentEdition::update(
                        $record,
                        $data,
                        (string) ($data['note'] ?? ''),
                        $user,
                        Filament::getCurrentPanel()?->getId(),
                        is_string($data['fingerprint'] ?? null) ? $data['fingerprint'] : null,
                    );
                } catch (InvalidArgumentException $exception) {
                    self::discardUploads($uploads);

                    Notification::make()
                        ->title('No se guardaron los cambios')
                        ->body($exception->getMessage())
                        ->danger()
                        ->persistent()
                        ->send();

                    $action->halt();

                    return;
                } catch (Throwable $exception) {
                    self::discardUploads($uploads);
                    report($exception);

                    Notification::make()
                        ->title('No se guardaron los cambios')
                        ->body('El pago quedó como estaba. Intente de nuevo; si se repite, avise a soporte con la hora del intento.')
                        ->danger()
                        ->send();

                    $action->halt();

                    return;
                }

                $fields = array_map(
                    static fn (string $field): string => PendingPaymentEdition::FIELD_LABELS[$field] ?? $field,
                    $result['changed'],
                );

                Notification::make()
                    ->title('Pago actualizado')
                    ->body('Se cambió: '.implode(', ', $fields).'. Quedó registrado en la traza (#'.$result['trace_id'].').')
                    ->success()
                    ->send();
            });
    }

    public static function history(): Action
    {
        return Action::make('pendingPaymentEditHistory')
            ->label('Ediciones')
            ->icon(Heroicon::OutlinedClock)
            ->color('gray')
            ->visible(fn (Model $record): bool => (int) ($record->getAttribute('edit_logs_count') ?? 0) > 0)
            ->modalHeading(fn (Model $record): string => 'Ediciones del pago #'.$record->getKey())
            ->modalDescription('Cambios hechos mientras el pago estaba pendiente, del más reciente al más antiguo.')
            ->modalWidth(Width::ThreeExtraLarge)
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Cerrar')
            ->modalContent(fn (Model $record): View => view('filament.shared.affiliations.payment-edit-history', [
                'logs' => PaymentEditLog::query()
                    ->where('payment_type', $record instanceof PaidMembershipCorporate ? PendingPaymentDeletion::TYPE_CORPORATE : PendingPaymentDeletion::TYPE_INDIVIDUAL)
                    ->where('payment_id', $record->getKey())
                    ->latest('id')
                    ->limit(50)
                    ->get(),
            ]));
    }

    /**
     * Agrega el conteo de ediciones a la consulta de la tabla (sin N+1). Si la
     * migración aún no se aplicó, la tabla sigue funcionando sin el conteo.
     */
    public static function withEditCount(Builder $query): Builder
    {
        return self::traceTableExists() ? $query->withCount('editLogs') : $query;
    }

    public static function editedDescription(Model $record): ?string
    {
        $count = (int) ($record->getAttribute('edit_logs_count') ?? 0);

        return match (true) {
            $count === 1 => 'Editado 1 vez',
            $count > 1 => 'Editado '.$count.' veces',
            default => null,
        };
    }

    private static function traceTableExists(): bool
    {
        return self::$traceTableExists ??= Schema::hasTable('payment_edit_logs');
    }

    private static function isPending(Model $record): bool
    {
        return strtoupper(trim((string) $record->getAttribute('status'))) === PendingPaymentDeletion::PENDING_STATUS;
    }

    /**
     * @return list<string>
     */
    private static function blockers(Model $record): array
    {
        if (! $record instanceof PaidMembership && ! $record instanceof PaidMembershipCorporate) {
            return ['Este registro no es un pago de afiliación.'];
        }

        return PendingPaymentEdition::blockers($record);
    }

    private static function blockedNotice(Model $record): Placeholder
    {
        $items = implode('', array_map(
            static fn (string $blocker): string => '<li>'.e($blocker).'</li>',
            self::blockers($record),
        ));

        return Placeholder::make('blocked')
            ->hiddenLabel()
            ->content(new HtmlString(
                '<div class="rounded-lg bg-rose-50 px-3 py-3 text-sm text-rose-900 ring-1 ring-inset ring-rose-600/20 dark:bg-rose-500/10 dark:text-rose-200 dark:ring-rose-400/30">'
                .'<p class="font-semibold">Este pago no se puede editar</p>'
                .'<ul class="mt-1.5 list-disc space-y-1 ps-5">'.$items.'</ul></div>'
            ));
    }

    /**
     * @return array<int, \Filament\Schemas\Components\Component>
     */
    private static function formComponents(Model $record): array
    {
        $total = round((float) $record->getAttribute('total_amount'), 2);
        $isUsd = static fn (Get $get): bool => in_array($get('payment_method'), PendingPaymentEdition::USD_METHODS, true);
        $isVes = static fn (Get $get): bool => in_array($get('payment_method'), PendingPaymentEdition::VES_METHODS, true);
        $isMultiple = static fn (Get $get): bool => $get('payment_method') === PendingPaymentEdition::MULTIPLE;
        $usdMethod = static fn (Get $get): ?string => $get('payment_method') === PendingPaymentEdition::MULTIPLE
            ? $get('payment_method_usd')
            : $get('payment_method');

        return [
            Hidden::make('fingerprint'),

            Placeholder::make('summary')
                ->hiddenLabel()
                ->content(new HtmlString(
                    '<div class="grid gap-2 sm:grid-cols-3">'
                    .self::fact('Total a pagar (no editable)', number_format($total, 2, ',', '.').' US$')
                    .self::fact('Cargado', ($record->getAttribute('created_at')?->format('d/m/Y H:i') ?? '—').' · '.e((string) ($record->getAttribute('created_by') ?: '—')))
                    .self::fact('Estatus', 'PENDIENTE')
                    .'</div>'
                )),

            Grid::make(['default' => 1, 'md' => 2])
                ->schema([
                    Select::make('payment_method')
                        ->label('Método de pago')
                        ->options(PendingPaymentEdition::METHOD_OPTIONS)
                        ->native(false)
                        ->required()
                        ->live(),
                    DatePicker::make('date_payment_voucher')
                        ->label('Fecha del comprobante')
                        ->native(false)
                        ->format('d/m/Y')
                        ->displayFormat('d/m/Y')
                        ->maxDate(today())
                        ->required(),
                ])
                ->columnSpanFull(),

            Section::make('Pago en dólares (US$)')
                ->icon(Heroicon::OutlinedCurrencyDollar)
                ->compact()
                ->visible(fn (Get $get): bool => $isUsd($get) || $isMultiple($get))
                ->schema([
                    Grid::make(['default' => 1, 'md' => 2])->schema([
                        Select::make('payment_method_usd')
                            ->label('Método en dólares')
                            ->options(PendingPaymentEdition::MULTIPLE_USD_METHOD_OPTIONS)
                            ->native(false)
                            ->required()
                            ->live()
                            ->visible($isMultiple),
                        TextInput::make('pay_amount_usd')
                            ->label('Monto en US$')
                            ->helperText('Debe ser menor que el total; el resto se paga en bolívares.')
                            ->prefix('US$')
                            ->numeric()
                            ->minValue(0.01)
                            ->maxValue(max(0.01, $total - 0.01))
                            ->required()
                            ->live(onBlur: true)
                            ->visible($isMultiple),
                        TextInput::make('name_ti_usd')
                            ->label('Titular de la cuenta')
                            ->helperText('Nombre y apellido.')
                            ->maxLength(255)
                            ->required()
                            ->visible(fn (Get $get): bool => in_array($usdMethod($get), ['ZELLE', 'TRANSFERENCIA US$', 'LINK DE PAGO'], true)),
                        Select::make('bank_usd')
                            ->label('Banco (US$)')
                            ->options(fn (Get $get): array => self::withCurrent(
                                $get('payment_method') === 'EFECTIVO US$' ? PendingPaymentEdition::BANK_USD_CASH_OPTIONS : PendingPaymentEdition::BANK_USD_OPTIONS,
                                $record->getAttribute('bank_usd'),
                            ))
                            ->native(false)
                            ->searchable()
                            ->required(fn (Get $get): bool => in_array($get('payment_method'), ['TRANSFERENCIA US$', 'EFECTIVO US$'], true))
                            ->visible(fn (Get $get): bool => $isMultiple($get) || in_array($get('payment_method'), ['TRANSFERENCIA US$', 'EFECTIVO US$'], true)),
                        TextInput::make('reference_payment_usd')
                            ->label('Referencia (US$)')
                            ->helperText('Completa. Solo letras, números y guion (-).')
                            ->prefix('#')
                            ->maxLength(100)
                            ->regex('/^[A-Za-z0-9\-]+$/')
                            ->validationMessages(['regex' => 'Solo se permiten letras, números y el guion (-).'])
                            ->required()
                            ->visible(fn (Get $get): bool => in_array($usdMethod($get), ['ZELLE', 'LINK DE PAGO'], true)),
                    ]),
                    self::currentVoucher($record, 'document_usd'),
                    self::voucherUpload('document_usd', 'Reemplazar comprobante (US$)')
                        ->required(fn (Get $get): bool => ! $isMultiple($get) && PaymentVoucherFile::path($record, PaymentVoucherFile::CURRENCY_USD) === null),
                ])
                ->columnSpanFull(),

            Section::make('Pago en bolívares (VES)')
                ->icon(Heroicon::OutlinedBanknotes)
                ->compact()
                ->visible(fn (Get $get): bool => $isVes($get) || $isMultiple($get))
                ->schema([
                    Grid::make(['default' => 1, 'md' => 2])->schema([
                        Select::make('payment_method_ves')
                            ->label('Método en bolívares')
                            ->options(PendingPaymentEdition::MULTIPLE_VES_METHOD_OPTIONS)
                            ->native(false)
                            ->required()
                            ->visible($isMultiple),
                        TextInput::make('pay_amount_ves')
                            ->label('Monto en VES')
                            ->prefix('VES')
                            ->numeric()
                            ->minValue(0.01)
                            ->maxValue(PendingPaymentEdition::MAX_AMOUNT)
                            ->required()
                            ->live(onBlur: true),
                        Placeholder::make('tasa_bcv_preview')
                            ->label('Tasa BCV (se calcula sola)')
                            ->content(function (Get $get) use ($total): string {
                                $rate = PendingPaymentEdition::previewRate($get('payment_method'), $get('pay_amount_ves'), $get('pay_amount_usd'), $total);

                                return $rate === null
                                    ? 'Indique los montos para calcularla.'
                                    : number_format((float) $rate, 2, ',', '.').' Bs por US$';
                            })
                            ->helperText(fn (Get $get): string => $isMultiple($get)
                                ? 'Monto en VES ÷ (total − monto en US$).'
                                : 'Monto en VES ÷ total a pagar.'),
                        Select::make('bank_ves')
                            ->label('Banco (VES)')
                            ->options(fn (Get $get): array => self::withCurrent(
                                $isMultiple($get) ? PendingPaymentEdition::BANK_VES_MULTIPLE_OPTIONS : PendingPaymentEdition::BANK_VES_OPTIONS,
                                $record->getAttribute('bank_ves'),
                            ))
                            ->native(false)
                            ->searchable()
                            ->required($isMultiple),
                        TextInput::make('reference_payment_ves')
                            ->label('Referencia (VES)')
                            ->helperText('Últimos 6 dígitos del comprobante.')
                            ->prefix('Ref:')
                            ->inputMode('numeric')
                            ->maxLength(6)
                            ->regex('/^\d{1,6}$/')
                            ->validationMessages(['regex' => 'Solo números, máximo 6 dígitos.'])
                            ->required($isMultiple),
                    ]),
                    self::currentVoucher($record, 'document_ves'),
                    self::voucherUpload('document_ves', 'Reemplazar comprobante (VES)')
                        ->required(fn (): bool => PaymentVoucherFile::path($record, PaymentVoucherFile::CURRENCY_VES) === null),
                ])
                ->columnSpanFull(),

            Textarea::make('observations_payment')
                ->label('Observaciones del pago')
                ->rows(2)
                ->maxLength(PendingPaymentEdition::OBSERVATIONS_MAX_LENGTH)
                ->columnSpanFull(),

            Textarea::make('note')
                ->label('Motivo de la edición')
                ->placeholder('Ej.: La referencia cargada no coincide con el comprobante; la correcta es 211556.')
                ->helperText('Obligatorio, mínimo '.PendingPaymentEdition::NOTE_MIN_LENGTH.' caracteres. Queda en la traza con su nombre y la fecha.')
                ->rows(3)
                ->required()
                ->minLength(PendingPaymentEdition::NOTE_MIN_LENGTH)
                ->maxLength(PendingPaymentEdition::NOTE_MAX_LENGTH)
                ->validationAttribute('motivo')
                ->dehydrated()
                ->columnSpanFull(),
        ];
    }

    private static function voucherUpload(string $name, string $label): FileUpload
    {
        return FileUpload::make($name)
            ->label($label)
            ->helperText('Opcional si el comprobante actual es correcto. Imagen o PDF, máximo 10 MB. El anterior no se borra: queda en la traza.')
            ->disk(PaymentVoucherFile::DISK)
            ->visibility('public')
            ->acceptedFileTypes(self::VOUCHER_TYPES)
            ->maxSize(self::VOUCHER_MAX_KB)
            ->uploadingMessage('Cargando...')
            ->validationMessages(['required' => 'Este pago no tiene comprobante en esta moneda: adjúntelo.']);
    }

    private static function currentVoucher(Model $record, string $column): Placeholder
    {
        $raw = trim((string) $record->getAttribute($column));
        $hasFile = $raw !== '' && strtoupper($raw) !== 'N/A';

        return Placeholder::make($column.'_current')
            ->label('Comprobante actual')
            ->content(new HtmlString($hasFile
                ? '<a href="'.e(PaidMembershipDocumentUrl::from($raw)).'" target="_blank" rel="noopener" class="font-medium text-primary-600 underline hover:text-primary-500 dark:text-primary-400">Ver '.e(basename($raw)).'</a>'
                : '<span class="text-gray-500 dark:text-gray-400">Sin comprobante cargado</span>'));
    }

    private static function fact(string $label, string $value): string
    {
        return '<div class="rounded-lg bg-gray-50 px-3 py-2 ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">'
            .'<p class="text-[0.65rem] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">'.e($label).'</p>'
            .'<p class="mt-0.5 text-sm font-semibold text-gray-950 dark:text-white">'.$value.'</p></div>';
    }

    /**
     * Opciones del catálogo más el valor que ya tiene el pago, para no forzar a
     * cambiar un banco histórico que no está en la lista actual.
     *
     * @param  array<string, string>  $options
     * @return array<string, string>
     */
    private static function withCurrent(array $options, mixed $current): array
    {
        $value = trim((string) $current);

        if ($value !== '' && strtoupper($value) !== 'N/A' && ! array_key_exists($value, $options)) {
            $options[$value] = $value;
        }

        return $options;
    }

    /**
     * Archivos recién subidos en este intento (no los que ya tenía el pago).
     *
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    private static function newUploads(Model $record, array $data): array
    {
        $paths = [];

        foreach (['document_usd', 'document_ves'] as $column) {
            $value = $data[$column] ?? null;
            $value = is_array($value) ? (array_values(array_filter($value))[0] ?? null) : $value;

            if (is_string($value) && trim($value) !== '' && $value !== $record->getAttribute($column)) {
                $paths[] = trim($value);
            }
        }

        return $paths;
    }

    /**
     * Si no se guardó, los archivos subidos en el intento quedan huérfanos: se
     * quitan, salvo que algún pago ya los use.
     *
     * @param  list<string>  $paths
     */
    private static function discardUploads(array $paths): void
    {
        foreach ($paths as $path) {
            if (str_contains($path, '..')) {
                continue;
            }

            $inUse = PaidMembership::query()->where('document_usd', $path)->orWhere('document_ves', $path)->exists()
                || PaidMembershipCorporate::query()->where('document_usd', $path)->orWhere('document_ves', $path)->exists();

            if (! $inUse) {
                try {
                    Storage::disk(PaymentVoucherFile::DISK)->delete($path);
                } catch (Throwable) {
                    // El archivo huérfano no afecta al pago.
                }
            }
        }
    }
}
