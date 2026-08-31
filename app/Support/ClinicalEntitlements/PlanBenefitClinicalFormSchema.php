<?php

declare(strict_types=1);

namespace App\Support\ClinicalEntitlements;

use App\Enums\ClinicalQuotaScope;
use App\Enums\ClinicalServiceChannel;
use App\Models\BenefitClinicalSetting;
use App\Models\TelemedicineServiceList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

/**
 * Campos de uso clínico reutilizados en el asistente de planes, el catálogo
 * de beneficios y la página «Uso clínico» de planes históricos.
 */
final class PlanBenefitClinicalFormSchema
{
    /**
     * @return array<int, mixed>
     */
    public static function fields(string $statePathPrefix = ''): array
    {
        $getPath = static fn (string $name): string => $statePathPrefix.$name;

        return [
            Section::make('Uso clínico bloqueado')
                ->description('Ambiente restrictivo de IntegraCorp. Solicite la clave OTP en el botón de la cabecera. La clave llega solo a SUPERADMIN y vale únicamente para esta visita. Un cambio mal ejecutado acarrea consecuencias sobre telemedicina y operaciones.')
                ->icon('heroicon-o-shield-exclamation')
                ->visible(fn (): bool => ! ClinicalUsageAccessOtp::allowsEditingOnCurrentPage())
                ->columnSpanFull(),
            Section::make('Uso clínico (telemedicina y operaciones)')
                ->description('Esto no cambia tarifas ni cotizaciones. Define qué servicio puede usar el médico y con qué cupo.')
                ->visible(fn (): bool => ClinicalUsageAccessOtp::allowsEditingOnCurrentPage())
                ->dehydrated(fn (): bool => ClinicalUsageAccessOtp::allowsEditingOnCurrentPage())
                ->schema([
                    Grid::make(2)->schema([
                        Toggle::make('applies_clinically')
                            ->label('Aplica en consulta / operaciones')
                            ->helperText('Apágalo para beneficios que no se asignan en telemedicina (funerarios, muerte accidental, etc.).')
                            ->default(true)
                            ->live()
                            ->inline(false),
                        Select::make('channel')
                            ->label('Servicio clínico')
                            ->helperText('Un beneficio, un servicio. Servicios Macro es el select de la consulta; el resto son las tildes.')
                            ->options(ClinicalServiceChannel::options())
                            ->required(fn (Get $get): bool => (bool) $get($getPath('applies_clinically')))
                            ->visible(fn (Get $get): bool => (bool) $get($getPath('applies_clinically')))
                            ->live()
                            ->native(false)
                            ->searchable(),
                    ]),
                    Grid::make(3)->schema([
                        Select::make('telemedicine_service_list_id')
                            ->label('Servicio macro')
                            ->helperText('El nombre que verá el médico en el select de la consulta.')
                            ->options(fn (): array => TelemedicineServiceList::query()
                                ->where('level', 1)
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all())
                            ->searchable()
                            ->preload()
                            ->required(fn (Get $get): bool => (bool) $get($getPath('applies_clinically'))
                                && ClinicalServiceChannel::fromStored($get($getPath('channel')))?->usesTelemedicineServiceList())
                            ->visible(fn (Get $get): bool => (bool) $get($getPath('applies_clinically'))
                                && ClinicalServiceChannel::fromStored($get($getPath('channel')))?->usesTelemedicineServiceList()),
                        Select::make('quota_scope')
                            ->label('Cómo se cuenta el cupo')
                            ->options(ClinicalQuotaScope::options())
                            ->required(fn (Get $get): bool => (bool) $get($getPath('applies_clinically')))
                            ->visible(fn (Get $get): bool => (bool) $get($getPath('applies_clinically')))
                            ->live()
                            ->native(false)
                            ->helperText(fn (Get $get): ?string => ClinicalQuotaScope::fromStored($get($getPath('quota_scope')))?->helperForAnalyst()),
                        TextInput::make('quota')
                            ->label('Cantidad')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(999)
                            ->required(fn (Get $get): bool => (bool) $get($getPath('applies_clinically'))
                                && (ClinicalQuotaScope::fromStored($get($getPath('quota_scope')))?->requiresQuota() ?? false))
                            ->visible(fn (Get $get): bool => (bool) $get($getPath('applies_clinically'))
                                && (ClinicalQuotaScope::fromStored($get($getPath('quota_scope')))?->requiresQuota() ?? true))
                            ->helperText('Año de vigencia, contrato o casos distintos, según lo elegido a la izquierda.'),
                    ]),
                ])
                ->columnSpanFull(),
        ];
    }

    public static function applyCatalogDefaults(Set $set, mixed $benefitId): void
    {
        $id = (int) $benefitId;
        if ($id < 1) {
            return;
        }

        $row = BenefitClinicalSetting::query()->where('benefit_id', $id)->first();
        if ($row === null) {
            return;
        }

        $set('applies_clinically', (bool) $row->applies_clinically);
        $set('channel', $row->channel instanceof ClinicalServiceChannel ? $row->channel->value : $row->channel);
        $set('telemedicine_service_list_id', $row->telemedicine_service_list_id);
        $set('quota_scope', $row->quota_scope instanceof ClinicalQuotaScope ? $row->quota_scope->value : $row->quota_scope);
        $set('quota', $row->quota);
    }
}
