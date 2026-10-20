<?php

declare(strict_types=1);

use App\Models\ObservationCase;
use App\Models\OperationAccountsPayable;
use App\Models\OperationAccountsReceivable;
use App\Models\OperationCoordinationService;
use App\Models\OperationQuoteGenerator;
use App\Models\OperationServiceFlowEvent;
use App\Models\OperationServiceOrder;
use App\Models\OperationServiceOrderItem;
use App\Models\Supplier;
use App\Models\TelemedicineCase;
use App\Models\TelemedicinePatientLab;
use App\Models\User;
use App\Support\Operations\CoordinationServiceItemEdit;
use App\Support\Operations\CoordinationServiceItemsManager;
use App\Support\Operations\CoordinationServiceQuoteManager;
use App\Support\Operations\OperationServiceOrderValidity;
use App\Support\Operations\OperationServiceOrderViewActions;
use App\Support\Operations\ServiceOrderFlowTrace;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(Tests\TestCase::class);

/**
 * Flujo cotización → orden → anulación, de punta a punta contra la base.
 * Todo corre dentro de una transacción que siempre se revierte.
 */
beforeEach(function (): void {
    DB::beginTransaction();

    $case = TelemedicineCase::query()
        ->where('status', '!=', 'ELIMINADO')
        ->whereNotNull('telemedicine_patient_id')
        ->latest('id')
        ->first();
    $suppliers = Supplier::query()->orderBy('id')->limit(2)->pluck('id');

    if ($case === null || $suppliers->count() < 2) {
        $this->markTestSkipped('La base no tiene un caso vigente y dos proveedores.');
    }

    $this->case = $case;
    [$this->supplierA, $this->supplierB] = [(int) $suppliers[0], (int) $suppliers[1]];

    $this->actingAs(User::factory()->create([
        'name' => 'QA Analista Flujo',
        'email' => 'qa.flujo.'.Str::lower(Str::random(8)).'@tudrencasa.com',
        'status' => 'ACTIVO',
        'departament' => ['SUPERADMIN', 'OPERACIONES'],
    ]));

    Filament::setCurrentPanel('operations');
});

afterEach(fn () => DB::rollBack());

/**
 * Coordinación con un laboratorio, una orden vigente que lo contiene y, si se pide,
 * la cotización aprobada que la originó.
 *
 * @return array{coordination: OperationCoordinationService, lab: TelemedicinePatientLab, order: OperationServiceOrder, quote: ?OperationQuoteGenerator}
 */
function flowScenario(bool $withLinkedQuote = true): array
{
    $test = test();
    $suffix = Str::upper(Str::random(6));

    $coordination = OperationCoordinationService::query()->forceCreate([
        'telemedicine_case_id' => $test->case->id,
        'telemedicine_patient_id' => $test->case->telemedicine_patient_id,
        'ci_patient' => 'QA',
        'relationship_patient' => 'TITULAR',
        'contractor' => 'QA',
        'symptoms_diagnosis' => 'QA flujo',
        'status' => 'EN GESTION',
        'created_by' => 'QA',
    ]);

    $lab = TelemedicinePatientLab::query()->forceCreate([
        'telemedicine_patient_id' => $test->case->telemedicine_patient_id,
        'operation_coordination_service_id' => $coordination->id,
        'laboratory' => 'HEMATOLOGIA QA '.$suffix,
        'type' => 'LABORATORIO',
        'status' => 'EN GESTION',
    ]);

    $order = OperationServiceOrder::query()->forceCreate([
        'operation_coordination_service_id' => $coordination->id,
        'order_number' => 'ORD-QA-'.$suffix,
        'service_type' => 'LABORATORIOS',
        'status' => 'EN GESTION',
        'supplier_id' => $test->supplierA,
        'service_order_pdf_path' => 'operation-service-orders/generated-pdf/qa-'.$suffix.'.pdf',
        'created_by' => 'QA',
    ]);

    OperationServiceOrderItem::query()->forceCreate([
        'operation_service_order_id' => $order->id,
        'item_name' => $lab->laboratory,
        'category' => 'LABORATORIOS',
    ]);

    $quote = $withLinkedQuote ? flowQuote($coordination, $lab, [
        'status' => OperationQuoteGenerator::STATUS_APPROVED,
        'operation_service_order_id' => $order->id,
    ]) : null;

    return ['coordination' => $coordination, 'lab' => $lab, 'order' => $order, 'quote' => $quote];
}

/**
 * @param  array<string, mixed>  $overrides
 */
function flowQuote(OperationCoordinationService $coordination, TelemedicinePatientLab $lab, array $overrides = []): OperationQuoteGenerator
{
    return OperationQuoteGenerator::query()->forceCreate(array_merge([
        'telemedicine_case_id' => $coordination->telemedicine_case_id,
        'telemedicine_patient_id' => $coordination->telemedicine_patient_id,
        'operation_coordination_service_id' => $coordination->id,
        'type_service' => 'LABORATORIOS',
        'supplier_id' => test()->supplierB,
        'status' => OperationQuoteGenerator::STATUS_PENDING,
        'items' => [[
            'key' => 'lab:'.$lab->id,
            'category' => 'Laboratorio',
            'label' => $lab->laboratory,
            'status' => 'PENDIENTE',
            'unit_price_usd' => 1,
        ]],
        'costo_dolares' => 1,
        'subtotal' => 1,
        'total' => 1,
        'created_by' => 'QA',
    ], $overrides));
}

/**
 * @return list<string>
 */
function flowEvents(OperationServiceOrder|OperationCoordinationService $subject): array
{
    $column = $subject instanceof OperationServiceOrder ? 'operation_service_order_id' : 'operation_coordination_service_id';

    return OperationServiceFlowEvent::query()
        ->where($column, $subject->getKey())
        ->orderBy('id')
        ->pluck('event')
        ->all();
}

it('no anula una orden nacida de cotización sin decidir qué hacer con la cotización', function (): void {
    ['order' => $order, 'quote' => $quote] = flowScenario();

    $voided = OperationServiceOrderViewActions::voidOrderForRegeneration($order, 'Cambio de proveedor solicitado por el paciente.');

    expect($voided)->toBeFalse()
        ->and($order->refresh()->status)->toBe('EN GESTION')
        ->and($quote->refresh()->operation_service_order_id)->toBe($order->id)
        ->and(flowEvents($order))->toContain(ServiceOrderFlowTrace::ACTION_BLOCKED);
});

it('anular liberando la cotización la deja aprobable y el ítem vuelve a pendiente', function (): void {
    ['coordination' => $coordination, 'lab' => $lab, 'order' => $order, 'quote' => $quote] = flowScenario();

    $voided = OperationServiceOrderViewActions::voidOrderForRegeneration(
        $order,
        'Cambio de proveedor solicitado por el paciente.',
        quoteDecision: OperationServiceOrderViewActions::QUOTE_RELEASE,
    );

    $order->refresh();
    $quote->refresh();

    expect($voided)->toBeTrue()
        ->and($order->status)->toBe('CANCELADA')
        ->and($order->service_order_pdf_path)->toBeNull()
        ->and($lab->refresh()->status)->toBe('PENDIENTE')
        ->and($quote->status)->toBe(OperationQuoteGenerator::STATUS_PENDING)
        ->and($quote->operation_service_order_id)->toBeNull()
        ->and(CoordinationServiceQuoteManager::approvalBlockReason($coordination, $quote))->toBeNull()
        ->and(flowEvents($order))->toContain(ServiceOrderFlowTrace::ORDER_VOIDED, ServiceOrderFlowTrace::QUOTE_RELEASED)
        ->and(ObservationCase::query()->where('telemedicine_case_id', $coordination->telemedicine_case_id)->latest('id')->value('description'))
        ->toContain('Motivo: Cambio de proveedor solicitado por el paciente.')
        ->toContain('quedó liberada');

    $voidEvent = OperationServiceFlowEvent::query()
        ->where('operation_service_order_id', $order->id)
        ->where('event', ServiceOrderFlowTrace::ORDER_VOIDED)
        ->firstOrFail();

    expect($voidEvent->reason)->toBe('Cambio de proveedor solicitado por el paciente.')
        ->and($voidEvent->actor_name)->toBe('QA Analista Flujo')
        ->and($voidEvent->changes)->toBe(['status' => ['antes' => 'EN GESTION', 'despues' => 'CANCELADA']]);
});

it('anular rechazando la cotización impide volver a usarla', function (): void {
    ['coordination' => $coordination, 'order' => $order, 'quote' => $quote] = flowScenario();

    OperationServiceOrderViewActions::voidOrderForRegeneration(
        $order,
        'El proveedor no tenía disponibilidad.',
        quoteDecision: OperationServiceOrderViewActions::QUOTE_REJECT,
    );

    $quote->refresh();

    expect($quote->status)->toBe(OperationQuoteGenerator::STATUS_REJECTED)
        ->and(CoordinationServiceQuoteManager::approvalBlockReason($coordination, $quote))->toContain('RECHAZADA');
});

it('no anula ni cancela una orden con factura registrada', function (): void {
    ['order' => $order] = flowScenario(withLinkedQuote: false);

    OperationAccountsPayable::query()->forceCreate([
        'operation_service_order_id' => $order->id,
        'invoice_date' => now()->toDateString(),
        'invoice_registration_date' => now()->toDateString(),
        'invoice_number' => 'QA-1',
        'supplier_name' => 'QA PROVEEDOR',
        'supplier_rif' => 'J-00000000-0',
        'invoice_amount' => 1,
        'created_by' => 'QA',
    ]);

    expect(OperationServiceOrderViewActions::voidOrderForRegeneration($order, 'Motivo suficientemente largo.'))->toBeFalse()
        ->and(OperationServiceOrderViewActions::cancelOrder($order, 'Motivo suficientemente largo.'))->toBeFalse()
        ->and($order->refresh()->status)->toBe('EN GESTION')
        ->and(OperationServiceOrderViewActions::closingBlockReason($order))->toContain('factura registrada');
});

it('cancelar exige motivo y lo deja en la traza', function (): void {
    ['order' => $order, 'lab' => $lab] = flowScenario(withLinkedQuote: false);

    expect(OperationServiceOrderViewActions::cancelOrder($order, 'corto'))->toBeFalse()
        ->and($order->refresh()->status)->toBe('EN GESTION');

    expect(OperationServiceOrderViewActions::cancelOrder($order, 'El paciente desistió del servicio.'))->toBeTrue()
        ->and($order->refresh()->status)->toBe('CANCELADA')
        ->and($lab->refresh()->status)->toBe('CANCELADA')
        ->and(OperationServiceFlowEvent::query()
            ->where('operation_service_order_id', $order->id)
            ->where('event', ServiceOrderFlowTrace::ORDER_CANCELLED)
            ->value('reason'))->toBe('El paciente desistió del servicio.');
});

it('no aprueba una cotización cuyos ítems siguen en una orden vigente (caso 40456-01090)', function (): void {
    ['coordination' => $coordination, 'lab' => $lab, 'order' => $order] = flowScenario(withLinkedQuote: false);
    $quote = flowQuote($coordination, $lab);
    $ordersBefore = OperationServiceOrder::query()->where('operation_coordination_service_id', $coordination->id)->count();

    expect(CoordinationServiceQuoteManager::approvalBlockReason($coordination, $quote))
        ->toContain($order->order_number)
        ->toContain('Anúlela por error');

    $result = CoordinationServiceQuoteManager::save($coordination, [
        'quote_statuses' => [[
            'quote_id' => $quote->id,
            'status' => OperationQuoteGenerator::STATUS_APPROVED,
            'is_cash' => false,
            'has_service_order' => false,
            'status_locked' => false,
        ]],
        'selected_pending_quote_ids' => [$quote->id],
        'order_number' => 'ORD-QA-NUEVA',
    ]);

    expect($result)->toBeNull()
        ->and($quote->refresh()->status)->toBe(OperationQuoteGenerator::STATUS_PENDING)
        ->and(OperationServiceOrder::query()->where('operation_coordination_service_id', $coordination->id)->count())->toBe($ordersBefore)
        ->and(flowEvents($coordination))->toContain(ServiceOrderFlowTrace::ACTION_BLOCKED);

    OperationServiceOrderViewActions::voidOrderForRegeneration($order, 'Cambio de proveedor solicitado.');

    expect(CoordinationServiceQuoteManager::approvalBlockReason($coordination, $quote->refresh()))->toBeNull();
});

it('no aprueba a la vez dos cotizaciones del mismo ítem', function (): void {
    ['coordination' => $coordination, 'lab' => $lab, 'order' => $order] = flowScenario(withLinkedQuote: false);
    OperationServiceOrderViewActions::voidOrderForRegeneration($order, 'Se generará con otro proveedor.');

    $first = flowQuote($coordination, $lab);
    $second = flowQuote($coordination, $lab);

    expect(fn () => CoordinationServiceQuoteManager::assertQuotesCanBecomeOrders($coordination, [$first->id, $second->id]))
        ->toThrow(App\Support\Operations\ServiceOrderFlowException::class, 'Apruebe solo una');
});

it('en la lista de ítems la cotización pendiente no queda tapada por la orden anulada', function (): void {
    ['coordination' => $coordination, 'lab' => $lab, 'order' => $order] = flowScenario(withLinkedQuote: false);
    OperationServiceOrderViewActions::voidOrderForRegeneration($order, 'Cambio de proveedor solicitado.');
    $quote = flowQuote($coordination, $lab);

    $html = CoordinationServiceItemsManager::renderCoordinationClinicalItemsCompactList($coordination->fresh())->toHtml();

    expect($html)->toContain(ServiceOrderFlowTrace::quoteLabel($quote))
        ->not->toContain('>'.$order->order_number.'<');
});

it('un ítem con orden anulada, cancelada o caducada puede volver a editarse', function (): void {
    $links = static fn (string $status): array => [
        'LABORATORIOS|HEMATOLOGIA COMPLETA' => ['id' => 1, 'order_number' => 'ORD-1', 'status' => $status, 'url' => '#'],
    ];

    expect(CoordinationServiceItemEdit::itemHasServiceOrder($links('EN GESTION'), 'Laboratorio', 'Hematologia completa'))->toBeTrue()
        ->and(CoordinationServiceItemEdit::itemHasServiceOrder($links('FINALIZADO'), 'Laboratorio', 'Hematologia completa'))->toBeTrue()
        ->and(CoordinationServiceItemEdit::itemHasServiceOrder($links('CANCELADA'), 'Laboratorio', 'Hematologia completa'))->toBeFalse()
        ->and(CoordinationServiceItemEdit::itemHasServiceOrder($links('CADUCADA'), 'Laboratorio', 'Hematologia completa'))->toBeFalse();
});

it('el PDF de una orden anulada lleva el sello, la fecha y el motivo', function (): void {
    ['order' => $order] = flowScenario(withLinkedQuote: false);
    OperationServiceOrderViewActions::voidOrderForRegeneration($order, 'Se cargó el proveedor equivocado.');

    $order->refresh();
    $notice = ServiceOrderFlowTrace::closingNoticeForOrder($order);

    expect($notice)->not->toBeNull()
        ->and($notice['stamp'])->toBe('ANULADA')
        ->and($notice['title'])->toContain('NO es válido')
        ->and($notice['detail'])->toContain('Motivo: Se cargó el proveedor equivocado.');

    $html = view('documents.operation-service-order-pdf', [
        'order' => $order->load(['operationCoordinationService', 'operationServiceOrderItems']),
        'logoDataUri' => '',
        'sealDataUri' => '',
        'closingNotice' => $notice,
    ])->render();

    expect($html)->toContain('void-stamp')
        ->toContain('ORDEN ANULADA: este documento NO es válido');
});

it('una orden vigente no lleva aviso de anulación', function (): void {
    ['order' => $order] = flowScenario(withLinkedQuote: false);

    expect(ServiceOrderFlowTrace::closingNoticeForOrder($order))->toBeNull();
});

it('la caducidad automática también queda en la traza', function (): void {
    ['order' => $order] = flowScenario(withLinkedQuote: false);
    $order->forceFill(['approved_at' => now()->subDays(OperationServiceOrderValidity::VALIDITY_DAYS + 2)])->saveQuietly();

    expect(OperationServiceOrderValidity::expireIfNeeded($order->refresh(), 'system'))->toBeTrue()
        ->and(flowEvents($order))->toContain(ServiceOrderFlowTrace::ORDER_EXPIRED)
        ->and(ServiceOrderFlowTrace::closingNoticeForOrder($order->refresh())['stamp'])->toBe('CADUCADA');
});

it('al anular, la cuenta por cobrar se reabre según lo decidido con la cotización', function (string $decision, string $expectedStatus, bool $keepsQuote): void {
    ['coordination' => $coordination, 'order' => $order, 'quote' => $quote] = flowScenario();

    $receivable = OperationAccountsReceivable::query()->forceCreate([
        'operation_coordination_service_id' => $coordination->id,
        'operation_service_order_id' => $order->id,
        'service_order_number' => $order->order_number,
        'operation_quote_generator_id' => $quote->id,
        'quote_number' => ServiceOrderFlowTrace::quoteLabel($quote),
        'quote_amount_usd' => 1,
        'status' => OperationAccountsReceivable::STATUS_COMPLETED,
        'created_by' => 'QA',
    ]);

    OperationServiceOrderViewActions::voidOrderForRegeneration($order, 'Se generará de nuevo la orden.', quoteDecision: $decision);

    $receivable->refresh();

    expect($receivable->operation_service_order_id)->toBeNull()
        ->and($receivable->service_order_number)->toBeNull()
        ->and($receivable->status)->toBe($expectedStatus)
        ->and($receivable->operation_quote_generator_id !== null)->toBe($keepsQuote)
        ->and(flowEvents($order))->toContain(ServiceOrderFlowTrace::RECEIVABLE_REOPENED);
})->with([
    'liberando la cotización' => [OperationServiceOrderViewActions::QUOTE_RELEASE, OperationAccountsReceivable::STATUS_QUOTE_ASSIGNED, true],
    'rechazando la cotización' => [OperationServiceOrderViewActions::QUOTE_REJECT, OperationAccountsReceivable::STATUS_PENDING_TDG, false],
]);

it('la traza de la orden incluye los eventos de su cotización y se dibuja legible', function (): void {
    ['coordination' => $coordination, 'lab' => $lab, 'order' => $order] = flowScenario(withLinkedQuote: false);
    $quote = flowQuote($coordination, $lab);
    $quote->update(['total' => 2, 'updated_by' => 'QA']);

    $events = ServiceOrderFlowTrace::eventsForCoordination((int) $coordination->id);
    $html = ServiceOrderFlowTrace::renderTimeline($events, 'vacío')->toHtml();

    expect($events->pluck('event')->all())->toContain(
        ServiceOrderFlowTrace::ORDER_CREATED,
        ServiceOrderFlowTrace::QUOTE_CREATED,
        ServiceOrderFlowTrace::QUOTE_UPDATED,
    )
        ->and($html)->toContain('Cotización registrada')
        ->toContain('Cotización editada')
        ->toContain('US$ 1,00 → US$ 2,00')
        ->toContain('QA Analista Flujo')
        ->and(ServiceOrderFlowTrace::renderTimeline(collect(), 'Sin eventos aún.')->toHtml())->toContain('Sin eventos aún.');
});

it('tras anular liberando la cotización, aprobarla crea la orden nueva vinculada y deja el camino en la traza', function (): void {
    ['coordination' => $coordination, 'lab' => $lab, 'order' => $order, 'quote' => $quote] = flowScenario();

    OperationServiceOrderViewActions::voidOrderForRegeneration(
        $order,
        'Cambio de proveedor solicitado por el paciente.',
        quoteDecision: OperationServiceOrderViewActions::QUOTE_RELEASE,
    );

    $newOrderId = CoordinationServiceQuoteManager::save($coordination->fresh(), [
        'quote_statuses' => [[
            'quote_id' => $quote->id,
            'status' => OperationQuoteGenerator::STATUS_PENDING,
            'is_cash' => false,
            'has_service_order' => false,
            'status_locked' => false,
        ]],
        'selected_pending_quote_ids' => [$quote->id],
        'order_number' => CoordinationServiceQuoteManager::nextServiceOrderNumber(),
        'telemedicine_priority_id' => $coordination->telemedicine_priority_id,
    ]);

    $quote->refresh();

    expect($newOrderId)->toBeGreaterThan(0)
        ->and($newOrderId)->not->toBe($order->id)
        ->and($quote->status)->toBe(OperationQuoteGenerator::STATUS_APPROVED)
        ->and($quote->operation_service_order_id)->toBe($newOrderId)
        ->and(OperationServiceOrder::query()->find($newOrderId)?->supplier_id)->toBe($quote->supplier_id);

    $created = OperationServiceFlowEvent::query()
        ->where('operation_service_order_id', $newOrderId)
        ->where('event', ServiceOrderFlowTrace::ORDER_CREATED)
        ->firstOrFail();

    expect($created->description)->toContain('desde la cotización '.ServiceOrderFlowTrace::quoteLabel($quote))
        ->and(flowEvents($coordination))->toContain(
            ServiceOrderFlowTrace::ORDER_VOIDED,
            ServiceOrderFlowTrace::QUOTE_RELEASED,
            ServiceOrderFlowTrace::ORDER_CREATED,
            ServiceOrderFlowTrace::QUOTE_LINKED,
        );
});

it('el modal de anular exige decidir la cotización y el motivo, y luego anula', function (): void {
    ['order' => $order, 'quote' => $quote] = flowScenario();

    $page = Livewire\Livewire::test(App\Filament\Operations\Resources\OperationServiceOrders\Pages\ViewOperationServiceOrder::class, ['record' => $order->getRouteKey()])
        ->mountAction('voidServiceOrderForRegeneration')
        ->set('mountedActions.0.data.void_reason', 'corto')
        ->callMountedAction()
        ->assertHasActionErrors(['quote_decision' => 'required', 'void_reason']);

    expect($order->refresh()->status)->toBe('EN GESTION');

    $page->set('mountedActions.0.data.quote_decision', OperationServiceOrderViewActions::QUOTE_RELEASE)
        ->set('mountedActions.0.data.void_reason', 'Cambio de proveedor solicitado por el paciente.')
        ->callMountedAction()
        ->assertHasNoActionErrors();

    expect($order->refresh()->status)->toBe('CANCELADA')
        ->and($quote->refresh()->operation_service_order_id)->toBeNull();
});

it('el modal de cancelar exige motivo', function (): void {
    ['order' => $order] = flowScenario(withLinkedQuote: false);

    Livewire\Livewire::test(App\Filament\Operations\Resources\OperationServiceOrders\Pages\ViewOperationServiceOrder::class, ['record' => $order->getRouteKey()])
        ->mountAction('cancelServiceOrder')
        ->callMountedAction()
        ->assertHasActionErrors(['cancel_reason' => 'required']);

    expect($order->refresh()->status)->toBe('EN GESTION');
});

it('al abrir la cotización desde su enlace, el formulario la carga y pide la fecha y hora de la cita', function (): void {
    ['coordination' => $coordination, 'lab' => $lab, 'order' => $order] = flowScenario(withLinkedQuote: false);
    OperationServiceOrderViewActions::voidOrderForRegeneration($order, 'Cambio de proveedor solicitado.');
    $quote = flowQuote($coordination, $lab);

    Livewire\Livewire::withQueryParams(['quote_id' => $quote->id])
        ->test(App\Filament\Operations\Resources\OperationCoordinationServices\Pages\ManageCoordinationServiceQuotes::class, ['record' => $coordination->getRouteKey()])
        ->assertSet('data.selected_pending_quote_ids', [$quote->id])
        ->assertSet('data.approved_quote_id', $quote->id)
        ->assertSee('Fecha y hora de la cita')
        ->assertDontSee('Seleccione una cotización aprobada para completar la orden de servicio.')
        ->assertDontSee('Descripción de la orden');
});

it('la orden creada desde la cotización guarda la fecha de la cita y la descripción del sistema', function (): void {
    ['coordination' => $coordination, 'lab' => $lab, 'order' => $order] = flowScenario(withLinkedQuote: false);
    OperationServiceOrderViewActions::voidOrderForRegeneration($order, 'Cambio de proveedor solicitado.');
    $quote = flowQuote($coordination, $lab);
    $appointment = now()->addDays(2)->setTime(8, 30);

    $newOrderId = CoordinationServiceQuoteManager::save($coordination->fresh(), [
        'quote_statuses' => [[
            'quote_id' => $quote->id,
            'status' => OperationQuoteGenerator::STATUS_PENDING,
            'is_cash' => false,
            'has_service_order' => false,
            'status_locked' => false,
        ]],
        'selected_pending_quote_ids' => [$quote->id],
        'order_number' => CoordinationServiceQuoteManager::nextServiceOrderNumber(),
        'telemedicine_priority_id' => $coordination->telemedicine_priority_id,
        'appointment_at' => $appointment->format('Y-m-d H:i:s'),
    ]);

    $newOrder = OperationServiceOrder::query()->findOrFail($newOrderId);

    expect($newOrder->appointment_at?->format('Y-m-d H:i'))->toBe($appointment->format('Y-m-d H:i'))
        ->and($newOrder->description)->toBe(CoordinationServiceQuoteManager::serviceOrderDescriptionForQuote($quote))
        ->and($newOrder->description)->toContain('Orden por cotización #'.$quote->id);
});
