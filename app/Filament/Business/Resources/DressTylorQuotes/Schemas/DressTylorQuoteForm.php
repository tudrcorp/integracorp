<?php

namespace App\Filament\Business\Resources\DressTylorQuotes\Schemas;

use App\Models\AgeRange;
use App\Models\Benefit;
use App\Models\BenefitCoverage;
use App\Models\BenefitPlan;
use App\Models\Coverage;
use App\Models\Fee;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Illuminate\Support\HtmlString;

class DressTylorQuoteForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([

                // VERSION 2.0
                // ESTA VERSION ES ESTABLE

                // Control de ajuste porcentual
                Hidden::make('annual_adjustment_factor')
                    ->default(1.0)
                    ->live(),
                Hidden::make('created_by')
                    ->default(Auth::user()->name),

                // 1. PORTADA
                Section::make('Portada de la Cotización')
                    ->description('Datos Principales de la Cotización. Todos los campos son requeridos')
                    ->icon('heroicon-o-document-text')
                    ->collapsed()
                    ->headerActions([
                        Action::make('clear_form')
                            ->label('Limpiar Formulario')
                            ->icon('heroicon-o-clipboard')
                            ->color('danger')
                            ->requiresConfirmation()
                            ->action(function (Set $set) {
                                $set('full_name', null);
                                $set('rif_ci', null);
                                $set('email', null);
                                $set('plan_id', null);
                                $set('benefits_repeater', []);
                                $set('manual_adjustment_percent', 0);
                            }),
                    ])
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                TextInput::make('full_name')
                                    ->label('Nombre Completo o Razón Social del Solicitante')
                                    ->placeholder('Ej: Juan Perez, Addidas C.A.')
                                    ->required(),
                                TextInput::make('rif_ci')
                                    ->label('RIF / Cédula')
                                    ->placeholder('Ej: J-123456789, 16887656')
                                    ->required(),
                                TextInput::make('email')
                                    ->label('Correo Electrónico')
                                    ->placeholder('Ej: test@gmail.com')
                                    ->required(),
                                TextInput::make('plan_name')
                                    ->label('Nombre del Plan')
                                    ->placeholder('Opcional'),
                            ]),
                    ])->compact()->columnSpanFull(),

                // 2. PLANES Y BENEFICIOS DINÁMICOS
                Section::make('Sección de Planes y Beneficios')
                    ->description('Gestione beneficios y distribución de población por cobertura')
                    ->icon('heroicon-m-sparkles')
                    ->collapsed()
                    ->schema([
                        ToggleButtons::make('plan_id')
                            ->label('Tipo de Plan a Cotizar')
                            ->inline()
                            ->live()
                            ->options([
                                '1' => 'PLAN INICIAL',
                                '2' => 'PLAN IDEAL',
                                '3' => 'PLAN ESPECIAL',
                                '4' => 'PERSONALIZAR',
                            ])
                            ->colors(['1' => 'planIncial', '2' => 'planIdeal', '3' => 'planEspecial', '4' => 'warning'])
                            ->afterStateUpdated(function (Set $set, ?string $state) {
                                if ($state === '4' || ! $state) {
                                    $set('benefits_repeater', []);

                                    return;
                                }
                                $benefitIds = BenefitPlan::where('plan_id', $state)->pluck('benefit_id');
                                $benefitsData = Benefit::whereIn('id', $benefitIds)->get();
                                $repeaterItems = $benefitsData->map(fn ($b) => [
                                    'benefit_id' => $b->id,
                                    'pvp' => (float) $b->pvp,
                                    'limit' => $b->limit->cuota,
                                    'net_amount' => (float) $b->pvp * $b->limit->cuota,
                                    'distribution' => [],
                                ])->toArray();
                                $set('benefits_repeater', $repeaterItems);
                            }),

                        Repeater::make('benefits_repeater')
                            ->label('Detalle de Beneficios')
                            ->schema([
                                Grid::make(3)
                                    ->schema([
                                        Select::make('benefit_id')
                                            ->label('Beneficio')
                                            ->options(Benefit::all()->pluck('description', 'id'))
                                            ->searchable()
                                            ->required()
                                            ->live()
                                            ->columnSpan(3)
                                            ->afterStateUpdated(function (Set $set, Get $get, $state) {
                                                $benefit = Benefit::find($state);
                                                $pvp = (float) ($benefit?->pvp ?? 0);
                                                $set('pvp', $pvp);
                                                $set('net_amount', $pvp * (float) ($get('limit') ?? 1));
                                                $set('distribution', []);
                                            }),
                                        TextInput::make('pvp')->label('PVP ($)')->prefix('$')->readOnly(),
                                        TextInput::make('limit')->label('Límite')
                                            ->numeric()
                                            ->live(),
                                        TextInput::make('net_amount')->label('Neto ($)')->prefix('$')->readOnly(),
                                    ]),
                                Repeater::make('distribution')
                                    ->label('Gestión de Coberturas, Rango de Edad y Cantidad de Personas')
                                    ->schema([
                                        Grid::make(3)->schema([
                                            Select::make('coverage_ids')
                                                ->label('Coberturas')
                                                ->multiple()
                                                ->options(fn (Get $get) => Coverage::whereHas('benefits', fn ($q) => $q->where('benefits.id', $get('../../benefit_id')))->pluck('price', 'id'))
                                                ->placeholder('Sin cobertura específica')
                                                ->live(),

                                            Select::make('age_range_ids')
                                                ->label('Rangos de Edad')
                                                ->multiple()
                                                ->options(function (Get $get) {
                                                    $selectedCoverages = $get('coverage_ids');

                                                    // Si no hay coberturas, permitimos elegir cualquier rango de edad disponible
                                                    if (empty($selectedCoverages)) {
                                                        return AgeRange::all()->pluck('range', 'id');
                                                    }

                                                    return AgeRange::whereIn('id', function ($query) use ($selectedCoverages) {
                                                        $query->select('age_range_id')
                                                            ->from('fees')
                                                            ->whereIn('coverage_id', $selectedCoverages);
                                                    })->pluck('range', 'id');
                                                })
                                                ->required()
                                                ->live(),

                                            TextInput::make('population')->label('Población')->numeric()->default(1)->required()->live(),
                                        ]),
                                    ])
                                    ->addActionLabel('Agregar Coberturas')
                                    ->columnSpanFull()->compact(),
                            ])
                            ->columnSpanFull()->addActionLabel('Agregar Beneficio')->live(),
                    ])->columnSpanFull(),

                // 3. AJUSTE GLOBAL
                Section::make('Ajuste de Cotización')
                    ->description('Ajuste global de la cotización en porcentaje(%)')
                    ->collapsed()
                    ->icon('heroicon-o-chart-pie')
                    ->schema([
                        TextInput::make('manual_adjustment_percent')
                            ->label('Porcentaje de Ajuste Global (%)')
                            ->helperText('Para aumentar el porcentaje debe colocar un numero positivo y para disminuir el porcentaje debe agregar un numero negativo, Ejemplo: Aumentar(45, 67, 250), Disminuir(-20, -60, -80)')
                            ->numeric()->default(0)->suffix('%')->live(),
                    ])->columnSpanFull()->columns(3),

                // 4. VISTA PREVIA PREMIUM (PDF)
                Section::make('Vista Previa de la Cotización (PDF)')
                    ->description('Vista previa de la cotización, todos los ajuste se realizan en tiempo real.')
                    ->icon('heroicon-o-presentation-chart-bar')
                    ->collapsed()
                    ->columnSpanFull()
                    ->headerActions([
                        // NUEVA ACCIÓN USANDO DOMPDF
                        Action::make('print_quote_final')
                            ->label('Descargar PDF (DomPDF)')
                            ->icon('heroicon-o-arrow-down-tray')
                            ->color('success')
                            ->action(fn (Get $get) => self::generatePdf($get)),
                    ])
                    ->schema([
                        Placeholder::make('pdf_preview')
                            ->hiddenLabel()
                            ->live()
                            ->content(function (Get $get) {
                                $data = self::getQuotationData($get);
                                if ($data['is_empty']) {
                                    return new HtmlString('<div class="p-10 text-center text-gray-400 border-2 border-dashed rounded-xl">Agregue beneficios y poblaciones para generar la vista previa...</div>');
                                }

                                $style = "
                                    <style>
                                        .preview-container { background: white; color: #1a1a1a; padding: 40px; border-radius: 8px; font-family: 'Inter', sans-serif; position: relative; border: 1px solid #eee; }
                                        .preview-logo { position: absolute; top: 40px; right: 40px; width: 140px; text-align: right; }
                                        .preview-logo img { width: 100%; height: auto; max-width: 140px; margin-bottom: 5px; }
                                        .preview-header { border-bottom: 3px solid #3b82f6; padding-bottom: 15px; margin-bottom: 25px; margin-right: 160px; }
                                        .preview-title { font-size: 24px; font-weight: 800; color: #1e3a8a; margin: 0; text-transform: uppercase; }
                                        .preview-subtitle { font-size: 16px; color: #64748b; margin: 5px 0 0; }
                                        .preview-meta { margin-top: 10px; font-size: 11px; color: #94a3b8; display: flex; gap: 20px; }
                                        .preview-table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 12px; table-layout: fixed; }
                                        .preview-table th, .preview-table td { border: 1px solid #e2e8f0; text-align: center; }
                                        .preview-table th { background: #f1f5f9; padding: 12px 8px; color: #475569; text-transform: uppercase; font-size: 10px; }
                                        .preview-table td { padding: 10px 8px; }

                                        /* Alineación de columnas para simetría */
                                        .col-label { width: 35%; text-align: left !important; font-weight: 500; }
                                        .col-data { width: auto; }

                                        .footer-row { background: #f8fafc; font-weight: 800; color: #1e3a8a; }
                                        .check-cell { color: #10b981; font-weight: bold; font-size: 14px; }
                                        .price-cell { font-weight: 700; color: #0f172a; }
                                        .total-summary-row { background: #1e3a8a !important; color: white !important; font-weight: 800; }
                                        .total-summary-row td { border-color: #1e3a8a; color: white !important; }
                                        .subtotal-row { background: #f1f5f9; font-weight: 700; color: #334155; }
                                        .calc-detail { display: block; font-size: 9px; color: #94a3b8; font-weight: 400; margin-top: 4px; border-top: 1px solid #f1f5f9; padding-top: 2px; }
                                        .section-label { background: #eff6ff; color: #1d4ed8; padding: 4px 12px; border-radius: 99px; font-size: 10px; font-weight: 800; display: inline-block; margin-top: 25px; margin-bottom: 10px; }
                                    </style>
                                ";

                                $logoImg = '<div class="preview-logo"><img src="'.asset('image/logoNewPdf.png').'" alt="Logo"><div style="font-size: 8px; color: #94a3b8;">Fecha: '.$data['date'].'</div></div>';
                                $headerHtml = "<div class='preview-header'><h1 class='preview-title'>{$data['title']}</h1><p class='preview-subtitle'>{$data['subtitle']}</p><div class='preview-meta'><span>ESTADO: <strong>BORRADOR DE PROPUESTA</strong></span></div></div>";

                                // 1. Tabla de Beneficios
                                $benefitsTable = "<div class='section-label'>TABLA DE BENEFICIOS Y COBERTURAS</div><table class='preview-table'><thead><tr><th class='col-label'>DESCRIPCIÓN DEL BENEFICIO</th>";
                                foreach ($data['all_coverages'] as $cov) {
                                    $benefitsTable .= "<th class='col-data'>{$cov->name}</th>";
                                }
                                if ($data['all_coverages']->isEmpty()) {
                                    $benefitsTable .= "<th class='col-data'>COBERTURA GLOBAL</th>";
                                }
                                $benefitsTable .= '</tr></thead><tbody>';

                                foreach ($data['benefits_processed'] as $b) {
                                    $benefitsTable .= "<tr><td class='col-label'>{$b['name']}</td>";
                                    if ($data['all_coverages']->isEmpty()) {
                                        $benefitsTable .= "<td class='check-cell'>✔</td>";
                                    } else {
                                        foreach ($data['all_coverages'] as $cov) {
                                            $hasRel = BenefitCoverage::where('benefit_id', $b['id'])->where('coverage_id', $cov->id)->exists();
                                            $benefitsTable .= $hasRel ? "<td class='check-cell'>US$ ".number_format($cov->price, 2).'</td>' : "<td class='check-cell'>✔</td>";
                                        }
                                    }
                                    $benefitsTable .= '</tr>';
                                }
                                $benefitsTable .= '</tbody></table>';

                                // 2. Tabla Análisis de Costos
                                $costsTable = "<div class='section-label'>ANÁLISIS DE COSTOS POR EDAD Y POBLACIÓN</div>";
                                $costsTable .= "<div style='font-size: 10px; color: #64748b; margin-bottom: 5px;'>Suma total de beneficios aplicados por persona: <strong>US$ ".number_format($data['total_benefits_per_person'], 2).'</strong></div>';

                                $costsTable .= "<table class='preview-table'><thead><tr><th class='col-label'>RANGO DE EDAD / DESCRIPCIÓN</th>";
                                foreach ($data['all_coverages'] as $cov) {
                                    $costsTable .= "<th class='col-data'>{$cov->name}</th>";
                                }
                                if ($data['all_coverages']->isEmpty()) {
                                    $costsTable .= "<th class='col-data'>TARIFA BASE + BENEFICIOS</th>";
                                }
                                $costsTable .= '</tr></thead><tbody>';

                                foreach ($data['age_analysis'] as $row) {
                                    $costsTable .= "<tr><td class='col-label'>{$row['age_range']}</td>";
                                    if ($data['all_coverages']->isEmpty()) {
                                        $cell = $row['costs_by_coverage']['base'] ?? null;
                                        $costsTable .= $cell ? "<td class='price-cell'>$".number_format($cell['total'], 2)."<span class='calc-detail'>($".number_format($cell['fee_only'], 2).' + $'.number_format($data['total_benefits_per_person'], 2).") x {$cell['pop']} Pax</span></td>" : '<td>-</td>';
                                    } else {
                                        foreach ($data['all_coverages'] as $cov) {
                                            $cell = $row['costs_by_coverage'][$cov->id] ?? null;
                                            $costsTable .= $cell ? "<td class='price-cell'>$".number_format($cell['total'], 2)."<span class='calc-detail'>($".number_format($cell['fee_only'], 2).' + $'.number_format($data['total_benefits_per_person'], 2).") x {$cell['pop']} Pax</span></td>" : '<td>-</td>';
                                        }
                                    }
                                    $costsTable .= '</tr>';
                                }
                                $costsTable .= '</tbody></table>';

                                // 3. Tabla Resumen
                                $summaryTable = "<div class='section-label'>RESUMEN DE TOTALES Y FORMAS DE PAGO</div><table class='preview-table'><tbody>";
                                $summaryTable .= "<tr class='total-summary-row'><td class='col-label'>TOTAL ANUALIZADO (100%)</td>";
                                foreach ($data['summary_columns'] as $val) {
                                    $summaryTable .= "<td class='col-data'>$".number_format($val, 2).'</td>';
                                }
                                $summaryTable .= "</tr><tr class='subtotal-row'><td class='col-label'>VALOR SEMESTRAL (50%)</td>";
                                foreach ($data['summary_columns'] as $val) {
                                    $summaryTable .= "<td class='col-data'>$".number_format($val / 2, 2).'</td>';
                                }
                                $summaryTable .= "</tr><tr class='subtotal-row'><td class='col-label'>VALOR TRIMESTRAL (25%)</td>";
                                foreach ($data['summary_columns'] as $val) {
                                    $summaryTable .= "<td class='col-data'>$".number_format($val / 4, 2).'</td>';
                                }
                                $summaryTable .= '</tr></tbody></table>';

                                return new HtmlString("<div id='quotation-print-wrapper'><div class='preview-container'>$style $logoImg $headerHtml $benefitsTable $costsTable $summaryTable</div></div>");
                            }),
                    ]),

            ]);
    }

    public static function getQuotationData(Get $get): array
    {
        $benefitsRaw = $get('benefits_repeater') ?? [];
        $adjPercent = (float) ($get('manual_adjustment_percent') ?? 0);
        $adjFactor = 1 + ($adjPercent / 100);

        $totalBenefitsPerPerson = collect($benefitsRaw)->sum(fn ($b) => (float) ($b['net_amount'] ?? 0));

        $coverageIds = collect($benefitsRaw)
            ->flatMap(fn ($b) => collect($b['distribution'] ?? [])
                ->flatMap(fn ($d) => $d['coverage_ids'] ?? []))
            ->unique()
            ->filter()
            ->values();

        $allCoverages = Coverage::whereIn('id', $coverageIds)->get()->sortBy('price')->values();

        $ageAnalysis = [];
        $totalsByCoverage = [];
        $benefitsProcessed = [];

        foreach ($benefitsRaw as $item) {
            $bModel = Benefit::find($item['benefit_id']);
            if (! $bModel) {
                continue;
            }

            $benefitsProcessed[] = ['id' => $bModel->id, 'name' => $bModel->description];

            foreach ($item['distribution'] ?? [] as $dist) {
                $pop = (int) ($dist['population'] ?? 1);
                $distCovIds = $dist['coverage_ids'] ?? [];
                $distAgeIds = $dist['age_range_ids'] ?? [];

                foreach ($distAgeIds as $aid) {
                    $ageModel = AgeRange::find($aid);
                    if (! $ageModel) {
                        continue;
                    }

                    if (! isset($ageAnalysis[$aid])) {
                        $ageAnalysis[$aid] = ['age_range' => $ageModel->range, 'costs_by_coverage' => []];
                    }

                    // Caso A: El usuario seleccionó coberturas específicas
                    if (! empty($distCovIds)) {
                        foreach ($distCovIds as $cid) {
                            $fee = Fee::where('coverage_id', $cid)->where('age_range_id', $aid)->first();
                            $baseFee = (float) ($fee?->price ?? 0);
                            $unitPrice = ($baseFee + $totalBenefitsPerPerson) * $adjFactor;
                            $totalRow = $unitPrice * $pop;

                            $ageAnalysis[$aid]['costs_by_coverage'][$cid] = [
                                'unit' => $unitPrice,
                                'fee_only' => $baseFee,
                                'pop' => ($ageAnalysis[$aid]['costs_by_coverage'][$cid]['pop'] ?? 0) + $pop,
                                'total' => ($ageAnalysis[$aid]['costs_by_coverage'][$cid]['total'] ?? 0) + $totalRow,
                            ];
                            $totalsByCoverage[$cid] = ($totalsByCoverage[$cid] ?? 0) + $totalRow;
                        }
                    }
                    // Caso B: El usuario NO seleccionó coberturas (Beneficios globales por rango de edad)
                    else {
                        // Buscamos una tarifa base para el rango de edad (si existe en Fees sin cobertura o similar)
                        // O simplemente tomamos los beneficios si no hay tarifa de cobertura asociada
                        $fee = Fee::where('age_range_id', $aid)->first(); // Buscamos cualquier tarifa referencial
                        $baseFee = (float) ($fee?->price ?? 0);

                        $unitPrice = ($baseFee + $totalBenefitsPerPerson) * $adjFactor;
                        $totalRow = $unitPrice * $pop;

                        $ageAnalysis[$aid]['costs_by_coverage']['base'] = [
                            'unit' => $unitPrice,
                            'fee_only' => $baseFee,
                            'pop' => ($ageAnalysis[$aid]['costs_by_coverage']['base']['pop'] ?? 0) + $pop,
                            'total' => ($ageAnalysis[$aid]['costs_by_coverage']['base']['total'] ?? 0) + $totalRow,
                        ];
                        $totalsByCoverage['base'] = ($totalsByCoverage['base'] ?? 0) + $totalRow;
                    }
                }
            }
        }

        $summaryColumns = $allCoverages->isEmpty()
            ? [$totalsByCoverage['base'] ?? 0]
            : $allCoverages->map(fn ($c) => $totalsByCoverage[$c->id] ?? 0)->toArray();

        return [
            'is_empty' => empty($benefitsRaw),
            'plan_name' => $get('plan_name') ?? 'N/A',
            'full_name' => $get('full_name') ?? 'N/A',
            'rif_ci' => $get('rif_ci') ?? 'N/A',
            'email' => $get('email') ?? 'N/A',
            'title' => $get('title') ?? 'COTIZACIÓN',
            'subtitle' => $get('subtitle') ?? 'PLAN MAESTRO DE BENEFICIOS Y COBERTURAS',
            'date' => now()->format('d/m/Y'),
            'all_coverages' => $allCoverages,
            'benefits_processed' => $benefitsProcessed,
            'total_benefits_per_person' => $totalBenefitsPerPerson,
            'age_analysis' => collect($ageAnalysis)->values()->toArray(),
            'summary_columns' => $summaryColumns,
            'grand_total' => array_sum($summaryColumns),
            'user_name' => Auth::user()->name,
        ];
    }

    /**
     * Genera el PDF enviando la data a una vista de Blade
     */
    public static function generatePdf(Get $get)
    {

        $data = self::getQuotationData($get);
        // dd($data);

        // Obtenemos el HTML renderizado primero para debug o procesamiento
        $html = View::make('documents.dress-tylor', [
            'data' => $data,
            'isPreview' => false,
        ])->render();

        $pdf = Pdf::loadHTML($html)
            ->setPaper('a4', 'portrait')
            ->setWarnings(false)
            ->setOptions([
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => true,
                'defaultFont' => 'sans-serif',
            ]);

        // Retornamos el stream download para que Filament no rompa la codificación UTF-8
        return response()->streamDownload(
            fn () => print ($pdf->output()),
            'Cotizacion-'.date('d-m-Y').'.pdf'
        );
    }

    /**
     * Lógica unificada de renderizado para Preview y PDF
     */
    private static function renderHtmlContent(array $data, bool $isForPdf = false): string
    {
        $style = "
            <style>
                body { font-family: 'Helvetica', sans-serif; }
                .preview-container { background: white; padding: 20px; color: #1a1a1a; }
                .preview-header { border-bottom: 3px solid #3b82f6; padding-bottom: 10px; margin-bottom: 20px; }
                .preview-title { font-size: 20px; font-weight: bold; color: #1e3a8a; margin: 0; }
                .preview-table { width: 100%; border-collapse: collapse; margin-top: 15px; font-size: 11px; table-layout: fixed; }
                .preview-table th, .preview-table td { border: 1px solid #e2e8f0; padding: 8px; text-align: center; }
                .preview-table th { background: #f1f5f9; color: #475569; font-size: 9px; }
                .col-label { width: 35% !important; text-align: left !important; font-weight: bold; }
                .col-data { width: auto !important; }
                .section-label { background: #eff6ff; color: #1d4ed8; padding: 5px 10px; border-radius: 15px; font-size: 10px; font-weight: bold; margin-top: 20px; display: inline-block; }
                .total-row { background: #1e3a8a; color: white; font-weight: bold; }
            </style>
        ";

        $html = "<div class='preview-container'>{$style}";
        $html .= "<div class='preview-header'><h1 class='preview-title'>{$data['title']}</h1><p>{$data['subtitle']}</p></div>";

        // Tabla 1: Beneficios
        $html .= "<div class='section-label'>1. TABLA DE BENEFICIOS</div>";
        $html .= "<table class='preview-table'><thead><tr><th class='col-label'>DESCRIPCIÓN</th>";
        foreach ($data['all_coverages'] as $cov) {
            $html .= "<th>{$cov->name}</th>";
        }
        $html .= '</tr></thead><tbody>';
        foreach ($data['benefits_processed'] as $b) {
            $html .= "<tr><td class='col-label'>{$b['name']}</td>";
            foreach ($data['all_coverages'] as $cov) {
                $hasRel = BenefitCoverage::where('benefit_id', $b['id'])->where('coverage_id', $cov->id)->exists();
                $html .= $hasRel ? '<td>US$ '.number_format($cov->price, 2).'</td>' : '<td>✔</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';

        // Tabla 2: Análisis de Costos (Alineada con la 3)
        $html .= "<div class='section-label'>2. ANÁLISIS DE COSTOS POR POBLACIÓN</div>";
        $html .= "<table class='preview-table'><thead><tr><th class='col-label'>RANGO DE EDAD / DESCRIPCIÓN</th>";
        foreach ($data['all_coverages'] as $cov) {
            $html .= "<th>{$cov->name}</th>";
        }
        $html .= '</tr></thead><tbody>';
        foreach ($data['age_analysis'] as $row) {
            $html .= "<tr><td class='col-label'>{$row['age_range']}</td>";
            foreach ($data['all_coverages'] as $cov) {
                $cell = $row['costs_by_coverage'][$cov->id] ?? null;
                $html .= $cell ? '<td>$'.number_format($cell['total'], 2).'</td>' : '<td>-</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';

        // Tabla 3: Resumen (Mismos anchos que la Tabla 2)
        $html .= "<div class='section-label'>3. TABLA RESUMEN DE PAGOS</div>";
        $html .= "<table class='preview-table'><tbody>";
        $html .= "<tr class='total-row'><td class='col-label'>TOTAL ANUALIZADO (100%)</td>";
        foreach ($data['summary_columns'] as $val) {
            $html .= '<td>$'.number_format($val, 2).'</td>';
        }
        $html .= "</tr><tr><td class='col-label'>VALOR SEMESTRAL (50%)</td>";
        foreach ($data['summary_columns'] as $val) {
            $html .= '<td>$'.number_format($val / 2, 2).'</td>';
        }
        $html .= "</tr><tr><td class='col-label'>VALOR TRIMESTRAL (25%)</td>";
        foreach ($data['summary_columns'] as $val) {
            $html .= '<td>$'.number_format($val / 4, 2).'</td>';
        }
        $html .= '</tr></tbody></table></div>';

        return $html;
    }

    // public static function getQuotationDataPdf(Get $get): array
    // {
    //     $benefitsRaw = $get('benefits_repeater') ?? [];
    //     $adjFactor = 1 + ((float)($get('manual_adjustment_percent') ?? 0) / 100);
    //     $totalBenefitsPerPerson = collect($benefitsRaw)->sum(fn($b) => (float)($b['net_amount'] ?? 0));

    //     $coverageIds = collect($benefitsRaw)
    //         ->flatMap(fn($b) => collect($b['distribution'] ?? [])
    //             ->flatMap(fn($d) => $d['coverage_ids'] ?? []))
    //         ->unique()->filter()->values();

    //     $allCoverages = Coverage::whereIn('id', $coverageIds)->get()->sortBy('price')->values();

    //     $ageAnalysis = [];
    //     $totalsByCoverage = [];
    //     $benefitsProcessed = [];

    //     foreach ($benefitsRaw as $item) {
    //         $bModel = Benefit::find($item['benefit_id']);
    //         if (!$bModel) continue;

    //         // Pre-procesar relaciones de cobertura
    //         $coverageRelations = [];
    //         foreach ($allCoverages as $cov) {
    //             $coverageRelations[$cov->id] = BenefitCoverage::where('benefit_id', $bModel->id)
    //                 ->where('coverage_id', $cov->id)
    //                 ->exists();
    //         }

    //         $benefitsProcessed[] = [
    //             'id' => $bModel->id,
    //             'name' => $bModel->description,
    //             'coverages' => $coverageRelations
    //         ];

    //         foreach ($item['distribution'] ?? [] as $dist) {
    //             // Captura del valor de población definido por el usuario (default 1)
    //             $popValue = (int)($dist['population'] ?? 1);

    //             foreach ($dist['age_range_ids'] ?? [] as $aid) {
    //                 $ageModel = AgeRange::find($aid);
    //                 if (!$ageModel) continue;

    //                 // Inicializamos el nodo del rango si no existe
    //                 if (!isset($ageAnalysis[$aid])) {
    //                     $ageAnalysis[$aid] = [
    //                         'age_range' => $ageModel->range,
    //                         'population' => 0,
    //                         'costs_by_coverage' => []
    //                     ];
    //                 }

    //                 // Sumamos la población al total acumulado de este rango de edad
    //                 $ageAnalysis[$aid]['population'] += $popValue;

    //                 foreach ($dist['coverage_ids'] ?? [] as $cid) {
    //                     $fee = Fee::where('coverage_id', $cid)->where('age_range_id', $aid)->first();
    //                     $basePrice = (float)($fee?->price ?? 0);

    //                     // Cálculo del costo total considerando población
    //                     $totalRow = (($basePrice + $totalBenefitsPerPerson) * $adjFactor) * $popValue;

    //                     $ageAnalysis[$aid]['costs_by_coverage'][$cid]['total'] = ($ageAnalysis[$aid]['costs_by_coverage'][$cid]['total'] ?? 0) + $totalRow;
    //                     $totalsByCoverage[$cid] = ($totalsByCoverage[$cid] ?? 0) + $totalRow;
    //                 }
    //             }
    //         }
    //     }

    //     $summaryColumns = $allCoverages->map(fn($c) => $totalsByCoverage[$c->id] ?? 0)->toArray();

    //     return [
    //         'is_empty'                  => empty($benefitsRaw),
    //         'title'                     => $get('title') ?? 'COTIZACIÓN',
    //         'subtitle'                  => $get('subtitle') ?? 'DETALLE TÉCNICO',
    //         'date'                      => now()->format('d/m/Y'),
    //         'all_coverages'             => $allCoverages,
    //         'benefits_processed'        => $benefitsProcessed,
    //         'total_benefits_per_person' => $totalBenefitsPerPerson,
    //         'age_analysis'              => collect($ageAnalysis)->values()->toArray(),
    //         'summary_columns'           => $summaryColumns,
    //         'user_name'                 => Auth::user()->name
    //     ];
    // }
}
