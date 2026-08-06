<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\Agencies\Actions;

use App\Models\Agency;
use App\Services\AgencySalesReportService;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

final class DownloadAgencySalesReportAction
{
    public static function make(): Action
    {
        return self::baseAction('download_sales_report')
            ->label('Descargar')
            ->action(function (Agency $record, array $data): mixed {
                return self::redirectToDownload((int) $record->getKey(), $data);
            });
    }

    /**
     * Header action (p. ej. tabla de Ventas) con selección de agencia.
     */
    public static function makeHeader(): Action
    {
        return self::baseAction('download_agency_sales_report_header')
            ->label('Reporte ventas por agencia')
            ->form(self::formSchema(withAgencySelect: true))
            ->action(function (array $data): mixed {
                return self::redirectToDownload((int) ($data['agency_id'] ?? 0), $data);
            });
    }

    private static function baseAction(string $name): Action
    {
        return Action::make($name)
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->color('info')
            ->modalHeading('Descargar reporte de ventas')
            ->modalDescription('Incluye ventas individuales y corporativas desde la tabla de ventas (total_amount US$). El PDF agrega un gráfico de línea del año en curso: individual vs corporativo.')
            ->modalSubmitActionLabel('Descargar')
            ->fillForm([
                'period' => 'current_year',
                'format' => 'pdf',
            ])
            ->form(self::formSchema(withAgencySelect: false));
    }

    /**
     * @return array<int, mixed>
     */
    private static function formSchema(bool $withAgencySelect): array
    {
        $fields = [];

        if ($withAgencySelect) {
            $fields[] = Select::make('agency_id')
                ->label('Agencia')
                ->placeholder('Seleccione la agencia')
                ->options(fn (): array => self::agencyOptions())
                ->searchable()
                ->preload()
                ->required()
                ->columnSpanFull();
        }

        $fields[] = Section::make('Parámetros del reporte')
            ->schema([
                Radio::make('period')
                    ->label('Periodo')
                    ->options([
                        'current_year' => 'Año en curso',
                        'range' => 'Rango de fechas',
                    ])
                    ->descriptions([
                        'current_year' => 'Desde el 1 de enero hasta hoy.',
                        'range' => 'Define fecha desde y hasta.',
                    ])
                    ->default('current_year')
                    ->live()
                    ->required()
                    ->inline()
                    ->columnSpanFull(),
                DatePicker::make('date_from')
                    ->label('Desde')
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->visible(fn (Get $get): bool => $get('period') === 'range')
                    ->required(fn (Get $get): bool => $get('period') === 'range'),
                DatePicker::make('date_to')
                    ->label('Hasta')
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->visible(fn (Get $get): bool => $get('period') === 'range')
                    ->required(fn (Get $get): bool => $get('period') === 'range')
                    ->afterOrEqual('date_from'),
                Radio::make('format')
                    ->label('Formato')
                    ->options([
                        'pdf' => 'PDF (con gráfico del año en curso)',
                        'csv' => 'CSV',
                    ])
                    ->default('pdf')
                    ->required()
                    ->inline()
                    ->columnSpanFull(),
            ])
            ->columns(2);

        return $fields;
    }

    /**
     * @param  array{period?: string, date_from?: mixed, date_to?: mixed, format?: string}  $data
     */
    private static function redirectToDownload(int $agencyId, array $data): mixed
    {
        if ($agencyId <= 0) {
            return null;
        }

        $token = AgencySalesReportService::storeParamsAndGetToken([
            'agency_id' => $agencyId,
            'period' => $data['period'] ?? 'current_year',
            'date_from' => $data['date_from'] ?? null,
            'date_to' => $data['date_to'] ?? null,
            'format' => $data['format'] ?? 'pdf',
        ]);

        return redirect()->route('administration.agencies.sales-report.download', [
            'token' => $token,
        ]);
    }

    /**
     * @return array<int|string, string>
     */
    private static function agencyOptions(): array
    {
        return self::scopedAgencyQuery()
            ->orderBy('name_corporative')
            ->get(['id', 'code', 'name_corporative'])
            ->mapWithKeys(static function (Agency $agency): array {
                $code = trim((string) ($agency->code ?? ''));
                $name = trim((string) ($agency->name_corporative ?? ''));
                $label = $code !== '' && $name !== ''
                    ? $code.' — '.$name
                    : ($name !== '' ? $name : ($code !== '' ? $code : 'Agencia #'.$agency->getKey()));

                return [(int) $agency->getKey() => $label];
            })
            ->all();
    }

    private static function scopedAgencyQuery(): Builder
    {
        $query = Agency::query();
        $user = Auth::user();

        if ($user !== null && ! empty($user->is_accountManagers)) {
            $query->where($query->qualifyColumn('ownerAccountManagers'), $user->id);
        }

        return $query;
    }
}
