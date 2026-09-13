@php
    $invoice = $slide['data']['invoice_modal'] ?? [];
    $receipt = $slide['data']['receipt_modal'] ?? [];
@endphp

<div class="os-modals">
    <figure class="os-modal-frame">
        <div class="os-modal os-modal--invoice" aria-hidden="true">
            <header class="os-modal__head">
                <span class="os-modal__icon os-modal__icon--invoice" aria-hidden="true">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M7 3.5h7.2L19 8.2V20a1.5 1.5 0 0 1-1.5 1.5h-10A1.5 1.5 0 0 1 6 20V5a1.5 1.5 0 0 1 1.5-1.5z"/><path d="M14 3.5V8h4.6"/><path d="M9 12.2h6.5M9 15.4h6.5M9 18.5h4"/></svg>
                </span>
                <div class="min-w-0">
                    <div class="os-modal__title">{{ $invoice['heading'] ?? 'Cargar factura del proveedor' }}</div>
                    <div class="os-modal__desc">{{ $invoice['description'] ?? '' }}</div>
                </div>
            </header>

            <div class="os-modal__chip">
                <strong>{{ $invoice['order'] ?? 'OS' }}</strong>
                <span>{{ $invoice['supplier'] ?? '' }}</span>
                <span>Cotizado {{ $invoice['quoted'] ?? '' }}</span>
            </div>

            <div class="os-modal__grid">
                <label class="os-field">
                    <span>N° de factura</span>
                    <em>{{ $invoice['invoice_number'] ?? '' }}</em>
                </label>
                <label class="os-field">
                    <span>N° de control</span>
                    <em>{{ $invoice['control_number'] ?? '' }}</em>
                </label>
                <label class="os-field">
                    <span>Emisión</span>
                    <em>{{ $invoice['issued_at'] ?? '' }}</em>
                </label>
                <label class="os-field">
                    <span>Registro</span>
                    <em>{{ $invoice['registered_at'] ?? '' }}</em>
                </label>
                <label class="os-field os-field--full">
                    <span>Monto facturado en US$</span>
                    <em>US$ {{ $invoice['amount_usd'] ?? '' }}</em>
                </label>
            </div>

            <div class="os-dropzone">
                <span class="os-dropzone__file">PDF</span>
                <div>
                    <strong>{{ $invoice['file'] ?? 'factura.pdf' }}</strong>
                    <small>Documento de la factura · PDF, JPG, PNG · máx. 4 MB</small>
                </div>
            </div>

            <div class="os-modal__section">Datos para cuentas por pagar</div>
            <div class="os-modal__grid">
                <label class="os-field">
                    <span>Proveedor</span>
                    <em>{{ $invoice['supplier'] ?? '' }}</em>
                </label>
                <label class="os-field">
                    <span>RIF</span>
                    <em>{{ $invoice['rif'] ?? '' }}</em>
                </label>
            </div>

            <footer class="os-modal__foot">
                <span class="os-btn os-btn--ghost">Cancelar</span>
                <span class="os-btn os-btn--invoice">Guardar factura</span>
            </footer>
        </div>
        <figcaption class="pwa-device__caption">{{ $slide['data']['invoice_caption'] ?? 'Operaciones · Cargar factura' }}</figcaption>
    </figure>

    <div class="os-bridge" aria-hidden="true">
        <span class="os-bridge__arrow">→</span>
        <span class="os-bridge__label">1 OS = 1 CxP</span>
        <span class="os-bridge__sub">Pendiente por pagar</span>
    </div>

    <figure class="os-modal-frame">
        <div class="os-modal os-modal--receipt" aria-hidden="true">
            <header class="os-modal__head">
                <span class="os-modal__icon os-modal__icon--receipt" aria-hidden="true">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3.5" y="6" width="17" height="12" rx="2"/><path d="M7 10h4M7 13h2.5"/><circle cx="16" cy="12" r="2.2"/></svg>
                </span>
                <div class="min-w-0">
                    <div class="os-modal__title">{{ $receipt['heading'] ?? 'Cargar comprobante de pago' }}</div>
                    <div class="os-modal__desc">{{ $receipt['description'] ?? '' }}</div>
                </div>
            </header>

            <div class="os-modal__note">
                Al guardar, la factura queda en estatus <strong>Pagada</strong>. Referencia, fecha, banco y monto viajan con el archivo.
            </div>

            <div class="os-dropzone os-dropzone--receipt">
                <span class="os-dropzone__file os-dropzone__file--receipt">PDF</span>
                <div>
                    <strong>{{ $receipt['file'] ?? 'comprobante.pdf' }}</strong>
                    <small>Archivo del comprobante · PDF, JPG, PNG · máx. 5 MB</small>
                </div>
            </div>

            <div class="os-modal__section">Datos del pago</div>
            <div class="os-modal__grid">
                <label class="os-field">
                    <span>Estatus de pago</span>
                    <em class="os-status">{{ $receipt['status'] ?? 'Pagada' }}</em>
                </label>
                <label class="os-field">
                    <span>Referencia</span>
                    <em>{{ $receipt['reference'] ?? '' }}</em>
                </label>
                <label class="os-field">
                    <span>Fecha del pago</span>
                    <em>{{ $receipt['paid_at'] ?? '' }}</em>
                </label>
                <label class="os-field">
                    <span>Banco nacional</span>
                    <em>{{ $receipt['bank'] ?? '' }}</em>
                </label>
                <label class="os-field os-field--full">
                    <span>Monto del pago en US$</span>
                    <em>US$ {{ $receipt['amount_usd'] ?? '' }}</em>
                </label>
            </div>

            <footer class="os-modal__foot">
                <span class="os-btn os-btn--ghost">Cancelar</span>
                <span class="os-btn os-btn--receipt">Guardar comprobante</span>
            </footer>
        </div>
        <figcaption class="pwa-device__caption">{{ $slide['data']['receipt_caption'] ?? 'Cuentas por pagar · Cargar comprobante' }}</figcaption>
    </figure>
</div>
