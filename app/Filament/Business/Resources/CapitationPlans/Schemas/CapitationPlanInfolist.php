<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\CapitationPlans\Schemas;

use App\Enums\CapitationPaymentFrequency;
use App\Models\CapitationPlan;
use App\Support\Business\CapitationPlans\CapitationProviders;
use App\Support\Operations\OutsourcingMedicalDepartment;
use App\Support\Operations\ProviderDeletion;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

final class CapitationPlanInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Plan')
                ->icon(Heroicon::OutlinedClipboardDocumentList)
                ->columnSpanFull()
                ->columns(['default' => 1, 'md' => 3])
                ->schema([
                    TextEntry::make('name')->label('Nombre del plan')->weight('semibold'),
                    TextEntry::make('provider_name')
                        ->label('Proveedor')
                        ->state(fn (CapitationPlan $record): string => CapitationProviders::typeLabel($record->provider_type).' · '.ProviderDeletion::displayName($record->provider())),
                    TextEntry::make('status')
                        ->label('Estado')
                        ->badge()
                        ->formatStateUsing(fn (string $state): string => $state === CapitationPlan::STATUS_ACTIVE ? 'Activo' : 'Inactivo')
                        ->color(fn (string $state): string => $state === CapitationPlan::STATUS_ACTIVE ? 'success' : 'gray'),
                    TextEntry::make('cost_per_affiliate_usd')
                        ->label('Costo por afiliado')
                        ->money('USD')
                        ->weight('semibold'),
                    TextEntry::make('payment_frequency')
                        ->label('Frecuencia de pago')
                        ->formatStateUsing(fn (CapitationPaymentFrequency $state, CapitationPlan $record): string => $state === CapitationPaymentFrequency::Monthly
                            ? 'Mensual'
                            : $state->label().' (≈ US$ '.number_format($record->monthlyEquivalent(), 2, ',', '.').' por mes)'),
                    TextEntry::make('provider_fee')
                        ->label('Tarifa de referencia en la ficha')
                        ->state(fn (CapitationPlan $record): string => $record->provider() !== null ? OutsourcingMedicalDepartment::summary($record->provider()) : '—'),
                    TextEntry::make('valid_from')->label('Vigente desde')->date('d/m/Y')->placeholder('—'),
                    TextEntry::make('valid_until')->label('Vigente hasta')->date('d/m/Y')->placeholder('Sin fecha de fin'),
                    TextEntry::make('updated_by_name')
                        ->label('Última actualización')
                        ->state(fn (CapitationPlan $record): string => ($record->updated_by_name ?: '—').' · '.$record->updated_at?->format('d/m/Y h:i A')),
                ]),
            Section::make('Beneficios y servicios')
                ->icon(Heroicon::OutlinedSparkles)
                ->columnSpanFull()
                ->schema([
                    RepeatableEntry::make('benefits')
                        ->hiddenLabel()
                        ->columns(['default' => 1, 'md' => 3])
                        ->schema([
                            TextEntry::make('name')
                                ->hiddenLabel()
                                ->weight('medium')
                                ->columnSpan(['default' => 1, 'md' => 2]),
                            TextEntry::make('note')
                                ->hiddenLabel()
                                ->color('gray')
                                ->placeholder(fn ($record): string => $record?->benefit_id !== null ? 'Del catálogo' : 'Texto libre'),
                        ]),
                ]),
            Section::make('Negociación')
                ->icon(Heroicon::OutlinedDocumentCheck)
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('negotiation_type')->label('Tipo de negociación')->prose(),
                    TextEntry::make('documents')
                        ->label('Documentos de respaldo')
                        ->state(fn (CapitationPlan $record): HtmlString => self::documentLinks($record)),
                ]),
        ]);
    }

    /**
     * Enlaces temporales firmados (15 minutos) al disco privado: abrir y descargar.
     */
    private static function documentLinks(CapitationPlan $record): HtmlString
    {
        $documents = array_values((array) $record->documents);

        if ($documents === []) {
            return new HtmlString('<span class="text-sm text-gray-500 dark:text-gray-400">Sin documentos.</span>');
        }

        $disk = Storage::disk('local');
        $names = (array) $record->document_names;
        $items = [];

        foreach ($documents as $path) {
            $name = (string) ($names[$path] ?? basename($path));

            if (! $disk->exists($path)) {
                $items[] = '<li class="text-sm text-amber-700 dark:text-amber-300">'.e($name).' — el archivo no está disponible.</li>';

                continue;
            }

            $open = $disk->temporaryUrl($path, now()->addMinutes(15));
            $download = $disk->temporaryUrl($path, now()->addMinutes(15), ['ResponseContentDisposition' => 'attachment; filename="'.addslashes($name).'"']);

            $items[] = '<li class="flex flex-wrap items-center gap-2 text-sm">'
                .'<span class="font-medium text-gray-900 dark:text-white">'.e($name).'</span>'
                .'<a href="'.e($open).'" target="_blank" rel="noopener" class="text-primary-600 hover:underline dark:text-primary-400">Abrir</a>'
                .'<a href="'.e($download).'" download="'.e($name).'" class="text-primary-600 hover:underline dark:text-primary-400">Descargar</a>'
                .'</li>';
        }

        return new HtmlString('<ul class="space-y-1">'.implode('', $items).'</ul>');
    }
}
