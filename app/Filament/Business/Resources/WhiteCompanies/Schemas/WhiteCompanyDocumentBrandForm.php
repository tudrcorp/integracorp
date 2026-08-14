<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\WhiteCompanies\Schemas;

use App\Models\WhiteCompany;
use App\Models\WhiteCompanyPlanDocument;
use App\Models\WhiteCompanyPlanLabel;
use App\Support\WhiteCompanies\WhiteCompanyBrandColor;
use App\Support\WhiteCompanies\WhiteCompanyCarnetTemplateCompiler;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Support\Icons\Heroicon;

final class WhiteCompanyDocumentBrandForm
{
    private const SECTION_CARD = 'rounded-[1.25rem] border border-slate-200/90 bg-gradient-to-b from-white to-slate-50/95 shadow-[0_10px_36px_-12px_rgba(15,23,42,0.1)] dark:from-gray-900/90 dark:to-slate-950/95 dark:border-white/10 dark:shadow-[0_10px_36px_-12px_rgba(0,0,0,0.4)]';

    /**
     * @return list<Section>
     */
    public static function components(): array
    {
        return [
            Section::make('Carnet y color del certificado')
                ->description('La imagen del carnet debe usar la misma geometría que el carnet TDEC (huecos en las mismas posiciones). El color tiñe los títulos del certificado; el fondo sigue siendo el de TDEC.')
                ->icon(Heroicon::OutlinedIdentification)
                ->extraAttributes(['class' => self::SECTION_CARD])
                ->schema([
                    Grid::make(['default' => 1, 'lg' => 2])
                        ->schema([
                            FileUpload::make('carnet_template_image')
                                ->label('Imagen del carnet de afiliado')
                                ->directory('white-companies/carnets')
                                ->disk('public')
                                ->visibility('public')
                                ->image()
                                ->acceptedFileTypes(['image/png', 'image/jpeg'])
                                ->maxSize(4096)
                                ->helperText('PNG o JPG, máximo 4 MB. Misma geometría que el carnet TDEC (aprox. 1880×672 px). Sin nombre, CI ni fechas: esos datos los estampa el sistema.')
                                ->columnSpan(1),
                            ColorPicker::make('brand_primary_color')
                                ->label('Color primario del certificado')
                                ->helperText('Se aplica a los títulos del certificado. Si lo deja vacío, se usa el cian TDEC.')
                                ->hex()
                                ->default(WhiteCompanyBrandColor::DEFAULT)
                                ->columnSpan(1),
                            FileUpload::make('certificate_signature')
                                ->label('Firma del certificado')
                                ->directory('white-companies/firmas')
                                ->disk('public')
                                ->visibility('public')
                                ->image()
                                ->acceptedFileTypes(['image/png', 'image/jpeg'])
                                ->maxSize(4096)
                                ->helperText('PNG o JPG, máximo 4 MB. Se coloca al pie del certificado de la empresa aliada. Es distinta del logotipo.')
                                ->columnSpanFull(),
                        ])
                        ->columnSpanFull(),
                ])
                ->columnSpanFull(),

            Section::make('Nombres de plan en la marca')
                ->description('Opcional. El certificado y el carnet usan este nombre en lugar del plan TDEC. Si lo deja vacío, se usa Inicial, Ideal o Especial.')
                ->icon(Heroicon::OutlinedTag)
                ->extraAttributes(['class' => self::SECTION_CARD])
                ->schema([
                    Grid::make(['default' => 1, 'lg' => 3])
                        ->schema([
                            self::planLabelFields('Inicial', 'plan_label_inicial', 'plan_short_inicial'),
                            self::planLabelFields('Ideal', 'plan_label_ideal', 'plan_short_ideal'),
                            self::planLabelFields('Especial', 'plan_label_especial', 'plan_short_especial'),
                        ])
                        ->columnSpanFull(),
                ])
                ->columnSpanFull(),

            Section::make('Condicionados por plan')
                ->description('Opcional. Si no carga un PDF, al regenerar se usa el condicionado TDEC del plan.')
                ->icon(Heroicon::OutlinedDocumentText)
                ->extraAttributes(['class' => self::SECTION_CARD])
                ->schema([
                    Grid::make(['default' => 1, 'lg' => 3])
                        ->schema([
                            self::condicionadoUpload('condicionado_inicial', 'Condicionado Inicial', 1),
                            self::condicionadoUpload('condicionado_ideal', 'Condicionado Ideal', 2),
                            self::condicionadoUpload('condicionado_especial', 'Condicionado Especial', 3),
                        ])
                        ->columnSpanFull(),
                ])
                ->columnSpanFull(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function formStateFromRecord(WhiteCompany $record): array
    {
        $record->loadMissing(['planDocuments', 'planLabels']);

        $inicial = $record->planLabelForPlan(1);
        $ideal = $record->planLabelForPlan(2);
        $especial = $record->planLabelForPlan(3);

        return [
            'carnet_template_image' => $record->carnet_template_image,
            'brand_primary_color' => WhiteCompanyBrandColor::resolve($record->brand_primary_color),
            'certificate_signature' => $record->certificate_signature,
            'plan_label_inicial' => $inicial?->display_name,
            'plan_short_inicial' => $inicial?->short_label,
            'plan_label_ideal' => $ideal?->display_name,
            'plan_short_ideal' => $ideal?->short_label,
            'plan_label_especial' => $especial?->display_name,
            'plan_short_especial' => $especial?->short_label,
            'condicionado_inicial' => $record->condicionadoPathForPlan(1),
            'condicionado_ideal' => $record->condicionadoPathForPlan(2),
            'condicionado_especial' => $record->condicionadoPathForPlan(3),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function stripVirtualFields(array $data): array
    {
        unset(
            $data['condicionado_inicial'],
            $data['condicionado_ideal'],
            $data['condicionado_especial'],
            $data['plan_label_inicial'],
            $data['plan_short_inicial'],
            $data['plan_label_ideal'],
            $data['plan_short_ideal'],
            $data['plan_label_especial'],
            $data['plan_short_especial'],
        );

        if (array_key_exists('brand_primary_color', $data)) {
            $data['brand_primary_color'] = WhiteCompanyBrandColor::resolve(
                is_string($data['brand_primary_color']) ? $data['brand_primary_color'] : null
            );
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function persist(WhiteCompany $record, array $data): void
    {
        $record->fill([
            'carnet_template_image' => $data['carnet_template_image'] ?? $record->carnet_template_image,
            'brand_primary_color' => WhiteCompanyBrandColor::resolve(
                isset($data['brand_primary_color']) && is_string($data['brand_primary_color'])
                    ? $data['brand_primary_color']
                    : $record->brand_primary_color
            ),
            'certificate_signature' => $data['certificate_signature'] ?? $record->certificate_signature,
        ])->save();

        self::syncPlanDocumentsFromState($record, $data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function syncPlanDocumentsFromState(WhiteCompany $record, array $data): void
    {
        WhiteCompanyPlanDocument::syncCondicionado($record, 1, $data['condicionado_inicial'] ?? null);
        WhiteCompanyPlanDocument::syncCondicionado($record, 2, $data['condicionado_ideal'] ?? null);
        WhiteCompanyPlanDocument::syncCondicionado($record, 3, $data['condicionado_especial'] ?? null);

        WhiteCompanyPlanLabel::syncForPlan($record, 1, $data['plan_label_inicial'] ?? null, $data['plan_short_inicial'] ?? null);
        WhiteCompanyPlanLabel::syncForPlan($record, 2, $data['plan_label_ideal'] ?? null, $data['plan_short_ideal'] ?? null);
        WhiteCompanyPlanLabel::syncForPlan($record, 3, $data['plan_label_especial'] ?? null, $data['plan_short_especial'] ?? null);

        WhiteCompanyCarnetTemplateCompiler::compile($record->fresh() ?? $record);
    }

    private static function planLabelFields(string $tdecName, string $displayNameField, string $shortLabelField): Grid
    {
        return Grid::make(1)
            ->schema([
                TextInput::make($displayNameField)
                    ->label('Nombre del plan '.$tdecName)
                    ->placeholder('Ej. Plan Bienestar')
                    ->maxLength(80)
                    ->helperText('En IntegraCorp este plan es '.$tdecName.'. El certificado usará este nombre.')
                    ->columnSpanFull(),
                TextInput::make($shortLabelField)
                    ->label('Etiqueta del carnet')
                    ->placeholder('Ej. BIENESTAR')
                    ->maxLength(32)
                    ->helperText('Opcional. Si no se indica, se deriva del nombre (sin la palabra Plan).')
                    ->columnSpanFull(),
            ])
            ->columnSpan(1);
    }

    private static function condicionadoUpload(string $name, string $label, int $planId): FileUpload
    {
        return FileUpload::make($name)
            ->label($label)
            ->directory('white-companies/condicionados')
            ->disk('public')
            ->visibility('public')
            ->acceptedFileTypes(['application/pdf'])
            ->maxSize(10240)
            ->helperText('PDF del plan '.$planId.'. Si no se carga, se usa el condicionado TDEC.')
            ->columnSpan(1);
    }
}
