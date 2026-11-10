<?php

declare(strict_types=1);

namespace App\Support\Operations;

use App\Models\OperationCoordinationService;
use App\Models\OperationMedicationInvoice;
use Closure;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;

/**
 * «¿Esta cotización genera factura?» y «Responsable de pago», compartidos por la creación
 * (Gestionar servicio, prefijo `manage_quote_`) y la edición de una cotización pendiente
 * (prefijo `edit_quote_`).
 *
 * El bloque solo aparece si la cotización es de medicamentos, laboratorios, estudios o
 * especialistas NO CUBIERTOS y el usuario tiene el permiso de facturar al paciente.
 */
final class QuoteInvoiceFormFields
{
    /**
     * @param  Closure(Get, mixed): bool  $applies  si la cotización de este formulario puede facturarse
     */
    public static function make(string $prefix, Closure $applies): Grid
    {
        $generates = $prefix.'generates_invoice';
        $payerType = $prefix.'invoice_payer_type';
        $manualAddress = $prefix.'invoice_payer_address';
        $isVisible = fn (Get $get, mixed $livewire): bool => MedicationInvoiceWizard::userCanInvoice() && $applies($get, $livewire);
        $generatesInvoice = fn (Get $get, mixed $livewire): bool => $isVisible($get, $livewire) && (bool) $get($generates);
        $selectedPayer = fn (Get $get, mixed $livewire): ?array => $generatesInvoice($get, $livewire)
            ? self::payerFor($livewire, $get($payerType))
            : null;

        return Grid::make(1)
            ->visible($isVisible)
            ->extraAttributes(['class' => 'fi-quote-invoice-block'])
            ->schema([
                ToggleButtons::make($generates)
                    ->label('¿Esta cotización genera factura?')
                    ->boolean('Sí', 'No')
                    ->colors([1 => 'success', 0 => 'gray'])
                    ->grouped()
                    ->default(false)
                    ->live()
                    ->helperText(fn (Get $get): string => (bool) $get($generates)
                        ? 'La factura se emite al aprobar la cotización, con el precio de venta de cada ítem y su cuenta por cobrar.'
                        : 'Sin factura: la cotización sigue el proceso de siempre.'),
                Radio::make($payerType)
                    ->label('Responsable de pago')
                    ->options(OperationMedicationInvoice::payerOptions())
                    ->descriptions(fn (mixed $livewire): array => self::payerDescriptions($livewire))
                    ->disableOptionWhen(fn (string $value, mixed $livewire): bool => $value === OperationMedicationInvoice::PAYER_CORPORATE
                        && self::payerFor($livewire, OperationMedicationInvoice::PAYER_CORPORATE) === null)
                    ->inline()
                    ->live()
                    ->visible($generatesInvoice)
                    ->required($generatesInvoice)
                    ->in(fn (mixed $livewire): array => self::allowedPayerTypes($livewire))
                    ->validationMessages([
                        'required' => 'Indique quién paga la factura.',
                        'in' => 'Ese responsable de pago no está disponible para este paciente.',
                    ]),
                Placeholder::make($prefix.'invoice_payer_card')
                    ->hiddenLabel()
                    ->visible(fn (Get $get, mixed $livewire): bool => $selectedPayer($get, $livewire) !== null)
                    ->content(fn (Get $get, mixed $livewire): HtmlString => self::payerCard($get($payerType), $selectedPayer($get, $livewire) ?? [])),
                TextInput::make($manualAddress)
                    ->label(fn (Get $get): string => $get($payerType) === OperationMedicationInvoice::PAYER_CORPORATE
                        ? 'Dirección fiscal del grupo corporativo'
                        : 'Dirección del paciente')
                    ->placeholder('Su ficha no tiene dirección: escríbala para la factura')
                    ->prefixIcon(Heroicon::OutlinedMapPin)
                    ->maxLength(QuoteInvoicePayer::ADDRESS_MAX)
                    ->visible(fn (Get $get, mixed $livewire): bool => ($payer = $selectedPayer($get, $livewire)) !== null && blank($payer['address']))
                    ->required(fn (Get $get, mixed $livewire): bool => ($payer = $selectedPayer($get, $livewire)) !== null && blank($payer['address']))
                    ->helperText('Queda solo en la factura; no cambia la ficha.'),
            ])
            ->columnSpanFull();
    }

    /**
     * Las claves de los ítems definen si la cotización es de un tipo facturable: todas de
     * medicamentos, laboratorios, estudios o especialistas.
     *
     * @param  array<int|string, mixed>  $lineItems
     */
    public static function lineItemsAreInvoiceable(array $lineItems): bool
    {
        $kinds = [];

        foreach ($lineItems as $line) {
            if (! is_array($line)) {
                continue;
            }

            $kinds[] = ClinicalServiceCatalog::kindFromKey((string) ($line['key'] ?? ''));
        }

        $kinds = array_values(array_unique($kinds));

        return count($kinds) === 1 && $kinds[0] !== null;
    }

    public static function record(mixed $livewire): ?OperationCoordinationService
    {
        if (! is_object($livewire) || ! method_exists($livewire, 'getRecord')) {
            return null;
        }

        $record = $livewire->getRecord();

        return $record instanceof OperationCoordinationService ? $record : null;
    }

    /**
     * @return array{name: string, identification: string|null, address: string|null}|null
     */
    private static function payerFor(mixed $livewire, mixed $type): ?array
    {
        $record = self::record($livewire);

        return $record !== null && is_string($type) ? QuoteInvoicePayer::payer($record, $type) : null;
    }

    /**
     * @return list<string>
     */
    private static function allowedPayerTypes(mixed $livewire): array
    {
        return array_values(array_filter(
            array_keys(OperationMedicationInvoice::payerOptions()),
            fn (string $type): bool => self::payerFor($livewire, $type) !== null,
        ));
    }

    /**
     * @return array<string, string>
     */
    private static function payerDescriptions(mixed $livewire): array
    {
        $record = self::record($livewire);

        if ($record === null) {
            return [];
        }

        $options = QuoteInvoicePayer::options($record);

        return [
            OperationMedicationInvoice::PAYER_PATIENT => $options['patient']['name'],
            OperationMedicationInvoice::PAYER_CORPORATE => $options['corporate']['name'] ?? (string) $options['corporate_unavailable_reason'],
        ];
    }

    /**
     * @param  array{name?: string, identification?: string|null, address?: string|null}  $payer
     */
    private static function payerCard(mixed $type, array $payer): HtmlString
    {
        $isCorporate = $type === OperationMedicationInvoice::PAYER_CORPORATE;
        $rows = [
            [$isCorporate ? 'Razón social' : 'Nombre y apellido', $payer['name'] ?? null],
            [$isCorporate ? 'RIF' : 'Cédula de identidad', $payer['identification'] ?? null],
            ['Dirección', $payer['address'] ?? null],
        ];

        $html = '<div class="rounded-2xl border border-sky-200/80 bg-sky-50/60 p-4 dark:border-sky-500/25 dark:bg-sky-950/20">'
            .'<p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-sky-800/80 dark:text-sky-200/70">Responsable de pago en la factura</p>'
            .'<dl class="mt-3 grid gap-3 sm:grid-cols-3">';

        foreach ($rows as [$label, $value]) {
            $html .= '<div class="min-w-0"><dt class="text-xs text-gray-500 dark:text-gray-400">'.e($label).'</dt>'
                .'<dd class="mt-0.5 break-words text-sm font-semibold '.(filled($value) ? 'text-gray-950 dark:text-white' : 'text-amber-700 dark:text-amber-300').'">'
                .e(filled($value) ? (string) $value : 'Sin dato en la ficha')
                .'</dd></div>';
        }

        return new HtmlString($html.'</dl></div>');
    }
}
