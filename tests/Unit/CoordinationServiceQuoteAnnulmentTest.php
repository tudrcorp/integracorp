<?php

declare(strict_types=1);

use App\Models\OperationQuoteGenerator;
use App\Support\Operations\CoordinationServiceQuoteAnnulment;
use App\Support\Operations\CoordinationServiceQuoteManager;

function quoteAnnulmentSource(string $relativePath): string
{
    return (string) file_get_contents(dirname(__DIR__, 2).'/'.$relativePath);
}

it('ofrece el estatus Anulada como terminal y bloquea su edición', function (): void {
    expect(OperationQuoteGenerator::statusOptions())
        ->toHaveKey(OperationQuoteGenerator::STATUS_ANNULLED, 'Anulada')
        ->and(OperationQuoteGenerator::STATUS_ANNULLED)->toBe('ANULADA')
        ->and(OperationQuoteGenerator::isTerminalStatus(OperationQuoteGenerator::STATUS_ANNULLED))->toBeTrue()
        ->and(OperationQuoteGenerator::isTerminalStatus('  anulada '))->toBeTrue()
        ->and(OperationQuoteGenerator::isTerminalStatus(OperationQuoteGenerator::STATUS_PENDING))->toBeFalse();

    $annulled = new OperationQuoteGenerator(['status' => OperationQuoteGenerator::STATUS_ANNULLED]);

    expect(CoordinationServiceQuoteManager::quoteStatusUpdateIsLocked($annulled))->toBeTrue()
        ->and(CoordinationServiceQuoteManager::isQuotePendingForApproval($annulled))->toBeFalse();
});

it('exige un motivo de anulación entre 10 y 1000 caracteres', function (?string $reason, ?string $expected): void {
    expect(CoordinationServiceQuoteAnnulment::reasonValidationMessage($reason))->toBe($expected);
})->with([
    'nulo' => [null, 'Indique el motivo de la anulación.'],
    'vacío' => ['', 'Indique el motivo de la anulación.'],
    'solo espacios' => ['          ', 'Indique el motivo de la anulación.'],
    'muy corto' => ['corto', 'El motivo de la anulación debe tener al menos 10 caracteres.'],
    'nueve con espacios alrededor' => ['  123456789  ', 'El motivo de la anulación debe tener al menos 10 caracteres.'],
    'mínimo exacto' => ['1234567890', null],
    'con acentos' => ['Precio erróneo del proveedor', null],
    'máximo exacto' => [str_repeat('a', 1000), null],
    'demasiado largo' => [str_repeat('a', 1001), 'El motivo de la anulación no puede superar los 1000 caracteres.'],
]);

it('agrega columnas de anulación con una migración aditiva, idempotente y reversible', function (): void {
    $migration = quoteAnnulmentSource('database/migrations/2026_11_20_120000_add_annulment_columns_to_operation_quote_generators_table.php');

    expect($migration)
        ->toContain("'operation_quote_generators'")
        ->toContain("Schema::hasColumn(self::TABLE, 'annulled_at')")
        ->toContain("->timestamp('annulled_at')->nullable()")
        ->toContain("->unsignedBigInteger('annulled_by_user_id')->nullable()")
        ->toContain("->string('annulled_by_name', 255)->nullable()")
        ->toContain("->text('annulment_reason')->nullable()")
        ->toContain('public function down(): void')
        ->not->toContain('dropIfExists')
        ->not->toContain('->change()');
});

it('pide el motivo de forma obligatoria en el formulario y solo ofrece Anulada con la migración aplicada', function (): void {
    $form = quoteAnnulmentSource('app/Filament/Operations/Resources/OperationCoordinationServices/Schemas/ManageCoordinationServiceQuotesForm.php');
    $manager = quoteAnnulmentSource('app/Support/Operations/CoordinationServiceQuoteManager.php');

    expect($form)
        ->toContain("Textarea::make('annulment_reason')")
        ->toContain("->label('Motivo de la anulación')")
        ->toContain("->required(fn (Get \$get): bool => (string) \$get('status') === OperationQuoteGenerator::STATUS_ANNULLED)")
        ->toContain('->minLength(CoordinationServiceQuoteAnnulment::MINIMUM_REASON_LENGTH)')
        ->toContain('->maxLength(CoordinationServiceQuoteAnnulment::MAXIMUM_REASON_LENGTH)')
        ->toContain('CoordinationServiceQuoteManager::quoteStatusOptions()')
        ->not->toContain('->options(OperationQuoteGenerator::statusOptions())');

    expect($manager)
        ->toContain('function quoteStatusOptions(): array')
        ->toContain('if (! CoordinationServiceQuoteAnnulment::isAvailable()) {')
        ->toContain('unset($options[OperationQuoteGenerator::STATUS_ANNULLED]);')
        ->toContain("'annulment_reason' => null,");
});

it('anula dentro de la transacción del guardado y nunca convierte una anulada en orden', function (): void {
    $manager = quoteAnnulmentSource('app/Support/Operations/CoordinationServiceQuoteManager.php');

    expect($manager)
        ->toContain('if ($status === OperationQuoteGenerator::STATUS_ANNULLED) {')
        ->toContain('CoordinationServiceQuoteAnnulment::annul(')
        ->toContain('catch (QuoteAnnulmentException $exception)')
        ->toContain("->title('No se anuló la cotización')")
        ->toContain('OperationQuoteGenerator::STATUS_REJECTED, OperationQuoteGenerator::STATUS_PRIVATE_CARE, OperationQuoteGenerator::STATUS_ANNULLED')
        ->toContain('CourtesyFinanceNotifier::dispatchForAnnulledQuote(')
        ->toContain('El servicio volvió a PENDIENTE para gestionarse de nuevo.');

    $persistStart = strpos($manager, 'private static function persistQuoteStatusesAndOrders(');
    $annulBranch = strpos($manager, 'CoordinationServiceQuoteAnnulment::annul(', (int) $persistStart);
    $genericSave = strpos($manager, '$quote->status = $status;', (int) $persistStart);

    expect($persistStart)->not->toBeFalse()
        ->and($annulBranch)->toBeGreaterThan($persistStart)
        ->and($annulBranch)->toBeLessThan($genericSave);
});

it('devuelve ítems y servicio a PENDIENTE sin liberar lo que sigue en curso', function (): void {
    $annulment = quoteAnnulmentSource('app/Support/Operations/CoordinationServiceQuoteAnnulment.php');

    expect($annulment)
        ->toContain('->lockForUpdate()')
        ->toContain('CoordinationServiceQuoteManager::isQuotePendingForApproval($lockedQuote)')
        ->toContain("\$item->update(['status' => 'PENDIENTE']);")
        ->toContain("!== 'EN GESTION'")
        ->toContain('CoordinationServiceItemFinalizationReversal::orderContainingItem($coordination, $type, $item, closedOnly: false)')
        ->toContain('in_array($key, $keysInLiveQuotes, true)')
        ->toContain("\$coordination->status = 'PENDIENTE';")
        ->toContain('OperationServiceOrderValidity::closedStatuses()')
        ->toContain('OperationAccountsReceivable::STATUS_PENDING_TDG')
        ->toContain('ServiceOrderFlowTrace::withContext(')
        ->toContain('ObservationCase::query()->create(')
        ->not->toContain('->delete()');
});

it('avisa a administración cuando se anula una cotización por cortesía', function (): void {
    $notifier = quoteAnnulmentSource('app/Support/Operations/CourtesyFinanceNotifier.php');
    $job = quoteAnnulmentSource('app/Jobs/SendCourtesyFinanceEmail.php');

    expect($notifier)
        ->toContain('public static function dispatchForAnnulledQuote(int $quoteId): void')
        ->toContain("'type' => 'quote_annulled'")
        ->toContain('COTIZACIÓN POR CORTESÍA ANULADA');

    expect($job)
        ->toContain("if (\$this->type === 'quote_annulled') {")
        ->toContain('private function sendForAnnulledQuote(): void')
        ->toContain('$quote->status !== OperationQuoteGenerator::STATUS_ANNULLED');
});

it('no usa cotizaciones anuladas como proveedor de la orden ni como costo de medicamentos', function (): void {
    expect(quoteAnnulmentSource('app/Support/Operations/OperationServiceOrderProviderSummary.php'))
        ->toContain('OperationQuoteGenerator::STATUS_ANNULLED');

    expect(quoteAnnulmentSource('app/Support/Operations/Reports/CaseMedicationReportBuilder.php'))
        ->toContain("->where('status', '!=', OperationQuoteGenerator::STATUS_ANNULLED)");
});
