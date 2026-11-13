<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\FiscalBooks\Schemas;

use App\Enums\FiscalBook;
use App\Models\FiscalBookEntry;
use App\Support\Administration\FiscalBooks\FiscalAmount;
use App\Support\Administration\FiscalBooks\FiscalBookLedger;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;
use InvalidArgumentException;

/**
 * Formulario de una línea del libro (crear y editar, en modal).
 *
 * Los montos se escriben en positivo; el sistema resta las notas de crédito.
 * El IVA se propone solo a partir de la base, y el total se muestra en vivo con
 * la misma aritmética exacta con la que se guarda.
 */
final class FiscalBookEntryForm
{
    /**
     * @return array<int, \Filament\Schemas\Components\Component|\Filament\Forms\Components\Field>
     */
    public static function components(FiscalBook $book): array
    {
        return [
            Section::make('Documento')
                ->icon(Heroicon::OutlinedDocumentText)
                ->columns(4)
                ->columnSpanFull()
                ->schema([
                    ToggleButtons::make('document_type')
                        ->label('Tipo')
                        ->options(FiscalBookEntry::documentTypeOptions())
                        ->default(FiscalBookEntry::TYPE_INVOICE)
                        ->inline()
                        ->required()
                        ->live()
                        ->columnSpanFull(),
                    DatePicker::make('document_date')
                        ->label('Fecha del documento')
                        ->displayFormat('d/m/Y')
                        ->native(false)
                        ->maxDate(now()->endOfMonth())
                        ->required(),
                    TextInput::make('document_number')
                        ->label('N° de factura o documento')
                        ->required()
                        ->maxLength(40),
                    TextInput::make('control_number')
                        ->label('N° de control')
                        ->maxLength(40),
                    TextInput::make('series')
                        ->label('Serie')
                        ->placeholder('Sin serie')
                        ->maxLength(20),
                    TextInput::make('affected_document_number')
                        ->label('Factura que afecta la nota')
                        ->helperText('Número de la factura original que corrige esta nota.')
                        ->maxLength(40)
                        ->required(fn (Get $get): bool => $get('document_type') !== FiscalBookEntry::TYPE_INVOICE)
                        ->visible(fn (Get $get): bool => $get('document_type') !== FiscalBookEntry::TYPE_INVOICE)
                        ->columnSpan(2),
                ]),
            Section::make($book->counterpartyLabel())
                ->icon($book === FiscalBook::Sales ? Heroicon::OutlinedUser : Heroicon::OutlinedBuildingOffice2)
                ->columns(4)
                ->columnSpanFull()
                ->schema([
                    TextInput::make('counterparty_name')
                        ->label('Nombre o razón social')
                        ->required()
                        ->maxLength(255)
                        ->columnSpan(2),
                    TextInput::make('counterparty_rif')
                        ->label('RIF o cédula')
                        ->placeholder('J-503583681 o V-12345678')
                        ->maxLength(30),
                    Select::make('taxpayer_type')
                        ->label('Tipo de contribuyente')
                        ->options([
                            FiscalBookEntry::TAXPAYER => 'Contribuyente (C)',
                            FiscalBookEntry::NON_TAXPAYER => 'No contribuyente (NC)',
                        ])
                        ->default(FiscalBookEntry::TAXPAYER)
                        ->selectablePlaceholder(false)
                        ->required(),
                ]),
            Section::make('Montos en bolívares')
                ->description('Escriba los montos en positivo: las notas de crédito se restan solas. Si la factura trae el monto gravado con el IVA incluido, use «Separar IVA».')
                ->icon(Heroicon::OutlinedCalculator)
                ->columns(4)
                ->columnSpanFull()
                ->schema([
                    self::money('exempt_amount', 'Exento, exonerado o no sujeto'),
                    self::money('export_amount', $book === FiscalBook::Sales ? 'Monto de exportación' : 'Monto de importación'),
                    self::money('taxable_base', 'Base imponible')
                        ->suffixAction(
                            Action::make('splitTaxIncluded')
                                ->icon(Heroicon::OutlinedScissors)
                                ->tooltip('Separar IVA de un monto gravado con IVA incluido')
                                ->modalHeading('Separar el IVA')
                                ->modalDescription('Escriba el monto gravado tal como aparece en la factura, con el IVA incluido. El sistema calcula la base y el IVA al céntimo.')
                                ->modalSubmitActionLabel('Separar')
                                ->modalWidth('md')
                                ->schema([
                                    TextInput::make('amount_with_tax')
                                        ->label('Monto gravado con IVA incluido')
                                        ->prefix('Bs.')
                                        ->numeric()
                                        ->minValue(0)
                                        ->required(),
                                ])
                                ->action(function (array $data, Get $get, Set $set): void {
                                    $split = FiscalAmount::splitTaxIncluded($data['amount_with_tax'], $get('tax_rate') ?: FiscalBookLedger::RATE_GENERAL);
                                    $set('taxable_base', $split['base']);
                                    $set('tax_amount', $split['tax']);
                                }),
                        )
                        ->afterStateUpdated(fn (Get $get, Set $set) => self::proposeTax($get, $set)),
                    Select::make('tax_rate')
                        ->label('Alícuota')
                        ->options(FiscalBookLedger::rateOptions())
                        ->default(FiscalBookLedger::RATE_GENERAL)
                        ->selectablePlaceholder(false)
                        ->live()
                        ->afterStateUpdated(fn (Get $get, Set $set) => self::proposeTax($get, $set))
                        ->required(),
                    self::money('tax_amount', 'IVA')
                        ->helperText(fn (Get $get): string => 'Se calcula de la base. Corríjalo solo si la factura trae otro redondeo (máximo '.FiscalAmount::format('0.05').').'),
                    Placeholder::make('total_preview')
                        ->label('Total del documento')
                        ->content(fn (Get $get): HtmlString => self::totalPreview($get))
                        ->columnSpan(3),
                ]),
            Section::make('Retención de IVA')
                ->description('Solo si hubo comprobante de retención.')
                ->icon(Heroicon::OutlinedReceiptPercent)
                ->columns(3)
                ->columnSpanFull()
                ->collapsed(fn (Get $get): bool => blank($get('withholding_number')))
                ->schema([
                    DatePicker::make('withholding_date')
                        ->label('Fecha del comprobante')
                        ->displayFormat('d/m/Y')
                        ->native(false),
                    TextInput::make('withholding_number')
                        ->label('N° de comprobante')
                        ->maxLength(40),
                    self::money('withholding_amount', 'Monto retenido'),
                ]),
            Grid::make(1)
                ->columnSpanFull()
                ->schema([
                    Textarea::make('notes')
                        ->label('Nota interna')
                        ->rows(2)
                        ->maxLength(500),
                ]),
        ];
    }

    private static function money(string $name, string $label): TextInput
    {
        return TextInput::make($name)
            ->label($label)
            ->prefix('Bs.')
            ->numeric()
            ->minValue(0)
            ->step('0.01')
            ->placeholder('0,00')
            ->live(onBlur: true);
    }

    private static function proposeTax(Get $get, Set $set): void
    {
        try {
            $set('tax_amount', FiscalAmount::taxFromBase($get('taxable_base'), $get('tax_rate') ?: FiscalBookLedger::RATE_GENERAL));
        } catch (InvalidArgumentException) {
            $set('tax_amount', null);
        }
    }

    private static function totalPreview(Get $get): HtmlString
    {
        try {
            $total = FiscalAmount::add($get('exempt_amount'), $get('export_amount'), $get('taxable_base'), $get('tax_amount'));
        } catch (InvalidArgumentException) {
            return new HtmlString('<span style="color:#b91c1c;">Revise los montos: alguno no es un número válido.</span>');
        }

        $credit = $get('document_type') === FiscalBookEntry::TYPE_CREDIT_NOTE;

        return new HtmlString('<span style="font-size:1.15rem;font-weight:700;">Bs. '.e(FiscalAmount::format($credit ? FiscalAmount::negate($total) : $total)).'</span>'
            .($credit ? ' <span style="color:#64748b;">(nota de crédito: resta)</span>' : ''));
    }
}
