<?php

declare(strict_types=1);

namespace App\Support\Companies;

use App\Models\CompanyAssociate;
use Carbon\Carbon;
use Closure;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Arr;

final class CompanyAssociateVoucherIlsUpdater
{
    /**
     * @return array<string, mixed>
     */
    public static function formDefaults(CompanyAssociate $associate): array
    {
        return [
            'vaucherIls' => $associate->vaucher_ils,
            'dateInit' => self::dateForPicker($associate->date_init),
            'dateEnd' => self::dateForPicker($associate->date_end),
            'document_ils' => filled($associate->document_ils) ? [$associate->document_ils] : [],
        ];
    }

    /**
     * @return array<int, Section>
     */
    public static function formComponents(Closure $documentRequired): array
    {
        return [
            Section::make('Datos del voucher')
                ->description('Vigencia del beneficio ILS y documento de respaldo.')
                ->icon(Heroicon::OutlinedTicket)
                ->schema([
                    Grid::make(1)->schema([
                        TextInput::make('vaucherIls')
                            ->label('Código')
                            ->required()
                            ->maxLength(255),
                    ]),
                    Grid::make(2)->schema([
                        DatePicker::make('dateInit')
                            ->label('Fecha de inicio')
                            ->format('d/m/Y')
                            ->displayFormat('d/m/Y')
                            ->required(),
                        DatePicker::make('dateEnd')
                            ->label('Fecha fin')
                            ->format('d/m/Y')
                            ->displayFormat('d/m/Y')
                            ->required()
                            ->afterOrEqual('dateInit'),
                    ]),
                    Grid::make(1)->schema([
                        FileUpload::make('document_ils')
                            ->label('Imagen del voucher')
                            ->disk('public')
                            ->directory('company-associates/voucher-ils')
                            ->image()
                            ->downloadable()
                            ->openable()
                            ->required($documentRequired),
                    ]),
                ]),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function save(CompanyAssociate $associate, array $data): void
    {
        $associate->update([
            'vaucher_ils' => $data['vaucherIls'],
            'date_init' => self::formatDateForStorage($data['dateInit']),
            'date_end' => self::formatDateForStorage($data['dateEnd']),
            'document_ils' => self::resolveDocumentPath($data['document_ils'] ?? null, $associate->document_ils),
        ]);
    }

    private static function resolveDocumentPath(mixed $uploaded, ?string $existing): ?string
    {
        if (is_array($uploaded)) {
            $uploaded = Arr::first($uploaded);
        }

        if (filled($uploaded)) {
            return (string) $uploaded;
        }

        return $existing;
    }

    private static function formatDateForStorage(mixed $value): string
    {
        if (blank($value)) {
            return '';
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('d/m/Y');
        }

        $stringValue = (string) $value;

        if (str_contains($stringValue, '/')) {
            return Carbon::createFromFormat('d/m/Y', $stringValue)->format('d/m/Y');
        }

        return Carbon::parse($stringValue)->format('d/m/Y');
    }

    private static function dateForPicker(?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        try {
            if (str_contains($value, '/')) {
                return Carbon::createFromFormat('d/m/Y', $value)->format('d/m/Y');
            }

            return Carbon::parse($value)->format('d/m/Y');
        } catch (\Throwable) {
            return null;
        }
    }
}
