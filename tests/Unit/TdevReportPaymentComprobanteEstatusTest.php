<?php

declare(strict_types=1);

use App\Enums\StatusPago;
use App\Filament\Administration\Resources\TdevReports\Actions\TdevReportPaymentModalActions;

it('marca Pagado cuando el comprobante es nuevo', function (): void {
    expect(TdevReportPaymentModalActions::resolveEstatusPagoAfterComprobanteUpload(null, 'tdev/1/a.pdf', StatusPago::Pendiente->value))
        ->toBe(StatusPago::Pagado->value);
});

it('marca Pagado cuando se reemplaza el comprobante', function (): void {
    expect(TdevReportPaymentModalActions::resolveEstatusPagoAfterComprobanteUpload('old.pdf', 'new.pdf', StatusPago::Pendiente->value))
        ->toBe(StatusPago::Pagado->value);
});

it('respeta el formulario si el comprobante no cambió', function (): void {
    expect(TdevReportPaymentModalActions::resolveEstatusPagoAfterComprobanteUpload('same.pdf', 'same.pdf', StatusPago::Pendiente->value))
        ->toBe(StatusPago::Pendiente->value);
});

it('detecta comprobante nuevo o cambiado', function (): void {
    expect(TdevReportPaymentModalActions::comprobanteFueNuevoOCambiado(null, 'a.pdf'))->toBeTrue()
        ->and(TdevReportPaymentModalActions::comprobanteFueNuevoOCambiado('a.pdf', 'b.pdf'))->toBeTrue()
        ->and(TdevReportPaymentModalActions::comprobanteFueNuevoOCambiado('a.pdf', 'a.pdf'))->toBeFalse();
});
