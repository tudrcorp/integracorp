<?php

namespace App\Filament\Business\Resources\DressTylorQuotes\Tables;

use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;

class DressTylorQuotesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('full_name')
                    ->searchable(),
                TextColumn::make('rif_ci')
                    ->searchable(),
                TextColumn::make('email')
                    ->searchable(),
                TextColumn::make('plan_name')
                    ->searchable(),
                TextColumn::make('created_by')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),
                TextColumn::make('quote_structure')
                    ->label('Estructura de la cotización')
                    ->formatStateUsing(function ($state, $record = null) {
                        $data = $record?->quote_structure ?? $state;

                        return self::formatQuoteStructureOnce($record, $data);
                    })
                    ->html()
                    ->hidden(fn () => Auth::user()->email !== 'gcamacho@tudrencasa.com'),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                Action::make('download_pdf')
                    ->label('PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->url(fn ($record) => route('business.dress-tylor-quotes.pdf', ['record' => $record->getKey()]))
                    ->openUrlInNewTab()
                    ->hidden(fn ($record) => empty($record->quote_structure))
                    ->color('gray'),
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * Devuelve el resumen solo en la primera llamada por registro para evitar que se repita N veces en la celda.
     *
     * @param  array<string, mixed>|mixed  $data
     */
    protected static function formatQuoteStructureOnce(mixed $record, mixed $data): HtmlString
    {
        static $renderedKeys = [];

        $key = $record?->getKey();
        if ($key !== null) {
            if (isset($renderedKeys[$key])) {
                return new HtmlString("\u{200B}");
            }
            $renderedKeys[$key] = true;
        }

        return self::formatQuoteStructure($data);
    }

    /**
     * Formatea quote_structure para mostrar un resumen legible de cómo se construyó la cotización.
     *
     * @param  array<string, mixed>|mixed  $state
     */
    protected static function formatQuoteStructure(mixed $state): HtmlString
    {

        $planNameEsc = $state['plan_name'] ?? '';
        $benefitsCount = count($state['benefits_processed'] ?? []);
        $coverageLabel = count($state['all_coverages'] ?? []);
        $ageLabel = count($state['age_analysis'] ?? []);
        $upgradeLabel = count($state['upgrade_benefits'] ?? []);
        $totalLabel = $state['grand_total'] ?? 0;
        $dateLabel = $state['date'] ?? '';

        $html = '<div class="dress-tylor-quote-summary fi-ta-summary text-sm text-gray-700 space-y-1">'
            .'<div><strong>Plan:</strong> '.$planNameEsc.'</div>'
            .'<div><strong>Beneficios:</strong> '.$benefitsCount.'</div>'
            .'<div><strong>Coberturas:</strong> '.e($coverageLabel).'</div>'
            .'<div><strong>Rangos de edad:</strong> '.e($ageLabel).'</div>'
            .'<div><strong>Upgrade:</strong> '.$upgradeLabel.'</div>'
            .'<div><strong>Total:</strong> US$ '.$totalLabel.'</div>'
            .'<div class="text-gray-500 text-xs">'.$dateLabel.'</div>'
            .'</div>';

        return new HtmlString($html);
    }
}
