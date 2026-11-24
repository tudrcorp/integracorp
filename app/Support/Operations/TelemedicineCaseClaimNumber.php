<?php

declare(strict_types=1);

namespace App\Support\Operations;

use App\Models\ObservationCase;
use App\Models\OperationMedicationInvoice;
use App\Models\OperationMedicationInvoiceEvent;
use App\Models\TelemedicineCase;
use App\Models\User;
use App\Support\Filament\BusinessFilamentActionAccess;
use App\Support\Filament\BusinessFilamentActionPermissionRegistry;
use App\Support\SecurityAudit;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * N.º de siniestro del caso de telemedicina. Lo registra el analista desde la ficha del caso
 * (Operaciones → Casos → «Registrar N.º de siniestro») y sin él no se emite ninguna factura a
 * pacientes del caso: la factura lo imprime en «NRO. DE SINIESTRO» y lo copia al emitirse, así
 * que corregirlo después no cambia las facturas que ya lo tienen. Las que se emitieron sin
 * siniestro (antes de existir la regla) lo reciben al registrarlo.
 */
final class TelemedicineCaseClaimNumber
{
    public const MAX_LENGTH = 60;

    public const AUDIT = 'AUDIT_TELEMEDICINE_CASE_CLAIM_NUMBER_SET';

    public const AUDIT_ROUTE = 'operations.telemedicine-cases.claim-number';

    public static function canManage(): bool
    {
        return BusinessFilamentActionAccess::userCan(BusinessFilamentActionPermissionRegistry::SET_TELEMEDICINE_CASE_CLAIM_NUMBER);
    }

    public static function normalize(mixed $number): string
    {
        return mb_strtoupper(trim((string) preg_replace('/\s+/u', ' ', is_scalar($number) ? (string) $number : '')));
    }

    public static function problem(string $number): ?string
    {
        if ($number === '') {
            return 'Escriba el número de siniestro.';
        }

        if (mb_strlen($number) > self::MAX_LENGTH) {
            return 'El número no puede pasar de '.self::MAX_LENGTH.' caracteres.';
        }

        if (preg_match('/^[A-Z0-9][A-Z0-9 .\/-]*$/u', $number) !== 1) {
            return 'Use solo letras, números, guion, barra, punto o espacio, empezando por una letra o un número.';
        }

        return null;
    }

    /**
     * El N.º de siniestro del caso, obligatorio para facturar.
     *
     * @throws MedicationInvoiceException
     */
    public static function requireForCase(mixed $telemedicineCaseId): string
    {
        $case = filled($telemedicineCaseId)
            ? TelemedicineCase::query()->whereKey((int) $telemedicineCaseId)->first(['id', 'code', 'claim_number'])
            : null;

        if (! $case instanceof TelemedicineCase) {
            throw new MedicationInvoiceException('El servicio no tiene un caso de telemedicina asociado: sin caso no hay N.º de siniestro y no se puede facturar.');
        }

        if (blank($case->claim_number)) {
            throw new MedicationInvoiceException('Registre el N.º de siniestro del caso '.($case->code ?: '#'.$case->getKey()).' antes de facturar (ficha del caso → «Registrar N.º de siniestro»).');
        }

        return (string) $case->claim_number;
    }

    /**
     * Registra o corrige el número. Corregirlo exige motivo. Queda en la bitácora de
     * observaciones del caso y en la auditoría.
     *
     * @throws MedicationInvoiceException
     */
    public static function set(TelemedicineCase $case, mixed $number, ?string $reason = null, ?User $user = null): TelemedicineCase
    {
        $user ??= Auth::user();
        $userName = filled($user?->name) ? (string) $user->name : 'SISTEMA';
        $number = self::normalize($number);
        $reason = trim((string) $reason);

        if (($problem = self::problem($number)) !== null) {
            throw new MedicationInvoiceException($problem);
        }

        $previous = DB::transaction(function () use ($case, $number, $reason, $userName, $user): ?string {
            $locked = TelemedicineCase::query()->whereKey($case->getKey())->lockForUpdate()->first(['id', 'code', 'claim_number']);

            if (! $locked instanceof TelemedicineCase) {
                throw new MedicationInvoiceException('El caso ya no existe.');
            }

            $previous = filled($locked->claim_number) ? (string) $locked->claim_number : null;

            if ($previous === $number) {
                throw new MedicationInvoiceException('El caso ya tiene el N.º de siniestro '.$number.'.');
            }

            if ($previous !== null && mb_strlen($reason) < 5) {
                throw new MedicationInvoiceException('Indique el motivo de la corrección.');
            }

            /** Sin pasar por los observadores del caso: solo cambia el siniestro. */
            TelemedicineCase::query()->whereKey($locked->getKey())->update([
                'claim_number' => $number,
                'claim_number_set_at' => now(),
                'claim_number_set_by_name' => $userName,
            ]);

            $completed = self::completePendingInvoices((int) $locked->getKey(), $number, $userName, $user);

            ObservationCase::query()->create([
                'telemedicine_case_id' => $locked->getKey(),
                'description' => $previous === null
                    ? 'N.º de siniestro '.$number.' registrado por '.$userName.'.'
                    : 'N.º de siniestro corregido por '.$userName.': '.$previous.' → '.$number.'. Motivo: '.$reason.'. Las facturas que ya tenían siniestro conservan el número anterior.',
                'created_by' => $user?->getKey() !== null ? (string) $user->getKey() : $userName,
            ]);

            if ($completed !== []) {
                ObservationCase::query()->create([
                    'telemedicine_case_id' => $locked->getKey(),
                    'description' => 'N.º de siniestro '.$number.' completado en '.(count($completed) === 1 ? 'la factura ' : 'las facturas ').implode(', ', $completed).', que no lo tenían.',
                    'created_by' => $user?->getKey() !== null ? (string) $user->getKey() : $userName,
                ]);
            }

            return $previous;
        });

        SecurityAudit::log(self::AUDIT, self::AUDIT_ROUTE, [
            'telemedicine_case_id' => $case->getKey(),
            'previous' => $previous,
            'number' => $number,
        ], $user);

        return $case->refresh();
    }

    /**
     * Completa el siniestro en las facturas del caso que no lo tienen (emitidas antes de que
     * existiera) y no están anuladas. Nunca cambia el de una factura que ya lo tiene. Debe
     * llamarse dentro de una transacción.
     *
     * @return list<string> números de las facturas completadas
     */
    public static function completePendingInvoices(int $telemedicineCaseId, string $number, string $userName, ?User $user = null): array
    {
        $invoices = OperationMedicationInvoice::query()
            ->where('telemedicine_case_id', $telemedicineCaseId)
            ->whereNull('claim_number')
            ->where('status', '!=', OperationMedicationInvoice::STATUS_ANNULLED)
            ->lockForUpdate()
            ->get(['id', 'invoice_number', 'manual_invoice_number']);

        foreach ($invoices as $invoice) {
            OperationMedicationInvoice::query()->whereKey($invoice->getKey())->whereNull('claim_number')->update(['claim_number' => $number]);

            OperationMedicationInvoiceEvent::query()->create([
                'operation_medication_invoice_id' => $invoice->getKey(),
                'event' => OperationMedicationInvoiceEvent::EVENT_CLAIM_SET,
                'description' => 'N.º de siniestro '.$number.' completado desde el caso por '.$userName.' (la factura se emitió sin siniestro).',
                'payload' => ['claim_number' => $number],
                'user_id' => $user?->getKey(),
                'user_name' => $userName,
            ]);
        }

        return $invoices->map(fn (OperationMedicationInvoice $invoice): string => $invoice->displayNumber())->values()->all();
    }

    public static function action(): Action
    {
        return Action::make('set_claim_number')
            ->label(fn (TelemedicineCase $record): string => filled($record->claim_number) ? 'Corregir N.º de siniestro' : 'Registrar N.º de siniestro')
            ->icon(Heroicon::OutlinedShieldCheck)
            ->color(fn (TelemedicineCase $record): string => filled($record->claim_number) ? 'gray' : 'warning')
            ->visible(fn (): bool => self::canManage())
            ->modalHeading(fn (TelemedicineCase $record): string => (filled($record->claim_number) ? 'Corregir' : 'Registrar').' el N.º de siniestro del caso '.$record->code)
            ->modalDescription('Es obligatorio para emitir las facturas del caso y se imprime en ellas como «NRO. DE SINIESTRO». Las facturas del caso emitidas sin siniestro lo reciben ahora; si lo corrige, las que ya lo tenían conservan el número anterior.')
            ->modalIcon(Heroicon::OutlinedShieldCheck)
            ->modalWidth(Width::Large)
            ->modalSubmitActionLabel(fn (TelemedicineCase $record): string => filled($record->claim_number) ? 'Corregir' : 'Registrar')
            ->fillForm(fn (TelemedicineCase $record): array => ['number' => $record->claim_number])
            ->schema([
                TextInput::make('number')
                    ->label('N.º de siniestro')
                    ->placeholder('Ej.: 2026-00123456')
                    ->helperText('Tal como lo indica la aseguradora o el cliente.')
                    ->required()
                    ->maxLength(self::MAX_LENGTH)
                    ->autofocus()
                    ->rule(fn () => function (string $attribute, mixed $value, \Closure $fail): void {
                        if (($problem = self::problem(self::normalize($value))) !== null) {
                            $fail($problem);
                        }
                    }),
                Textarea::make('reason')
                    ->label('Motivo de la corrección')
                    ->placeholder('Ej.: la aseguradora asignó otro número; se transcribió mal…')
                    ->rows(2)
                    ->minLength(5)
                    ->maxLength(500)
                    ->required()
                    ->visible(fn (TelemedicineCase $record): bool => filled($record->claim_number)),
            ])
            ->action(function (TelemedicineCase $record, array $data, Action $action): void {
                if (! self::canManage()) {
                    Notification::make()->title('Sin permiso')->body('No tiene permiso para registrar el N.º de siniestro.')->danger()->send();
                    $action->halt();
                }

                $hadNumber = filled($record->claim_number);

                try {
                    $updated = self::set($record, $data['number'] ?? '', $data['reason'] ?? null);
                } catch (MedicationInvoiceException $exception) {
                    Notification::make()->title('No se guardó el N.º de siniestro')->body($exception->getMessage())->danger()->send();
                    $action->halt();

                    return;
                }

                Notification::make()
                    ->title($hadNumber ? 'N.º de siniestro corregido' : 'N.º de siniestro registrado')
                    ->body('Caso '.$updated->code.': siniestro '.$updated->claim_number.'. Ya puede emitir sus facturas.')
                    ->success()
                    ->send();
            });
    }
}
