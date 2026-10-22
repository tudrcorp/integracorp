<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\AffiliationDateInconsistencies\Schemas;

use App\Enums\AffiliationDateInconsistencyKind as Kind;
use App\Models\AffiliationDateInconsistency as Inconsistency;
use App\Models\AffiliationDateInconsistencyEvent as Event;
use App\Support\Affiliations\DateCorrection\DateCorrectionSubject;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;

/**
 * Detalle completo de una inconsistencia: qué está mal, los datos, por qué importa, cómo se
 * corrige y todo lo que se hizo con ella.
 */
class AffiliationDateInconsistencyInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(fn (Inconsistency $record): string => $record->kindEnum()?->label() ?? 'Inconsistencia')
                ->icon(Heroicon::OutlinedExclamationTriangle)
                ->columnSpanFull()
                ->schema([
                    Grid::make(4)->schema([
                        TextEntry::make('affiliation_code')->label('Afiliación')->weight('bold')->copyable(),
                        TextEntry::make('affiliation_type')
                            ->label('Tipo')
                            ->formatStateUsing(fn (?string $state): string => $state === DateCorrectionSubject::TYPE_CORPORATE ? 'Corporativa' : 'Individual'),
                        TextEntry::make('severity')
                            ->label('Gravedad')
                            ->badge()
                            ->color(fn (?string $state): string => Kind::severityColor($state)),
                        TextEntry::make('status')
                            ->label('Estado')
                            ->badge()
                            ->formatStateUsing(fn (Inconsistency $record): string => $record->statusLabel())
                            ->color(fn (?string $state): string => match ($state) {
                                Inconsistency::STATUS_OPEN => 'danger',
                                Inconsistency::STATUS_REPAIRED => 'success',
                                Inconsistency::STATUS_DISMISSED => 'gray',
                                default => 'info',
                            }),
                    ]),
                    TextEntry::make('details.explanation')->label('Qué está mal')->columnSpanFull(),
                    TextEntry::make('details.consequence')->label('Por qué importa')->columnSpanFull(),
                    TextEntry::make('details.repair')->label('Cómo se corrige')->columnSpanFull(),
                ]),
            Section::make('Datos de la afiliación')
                ->description('Los valores tal como están hoy en el sistema.')
                ->icon(Heroicon::OutlinedTableCells)
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('facts')
                        ->hiddenLabel()
                        ->state(fn (Inconsistency $record): HtmlString => self::factsHtml((array) ($record->details['facts'] ?? [])))
                        ->columnSpanFull(),
                ]),
            Section::make('Resolución')
                ->icon(Heroicon::OutlinedCheckBadge)
                ->columnSpanFull()
                ->visible(fn (Inconsistency $record): bool => $record->status !== Inconsistency::STATUS_OPEN)
                ->schema([
                    Grid::make(3)->schema([
                        TextEntry::make('resolved_by_name')->label('Por')->placeholder('Sistema'),
                        TextEntry::make('resolved_at')->label('Fecha')->dateTime('d/m/Y H:i'),
                        TextEntry::make('correction_id')->label('Corrección aplicada')->placeholder('—')->prefix('#'),
                    ]),
                    TextEntry::make('resolution_note')->label('Nota')->placeholder('—')->columnSpanFull(),
                ]),
            Section::make('Historial')
                ->description('Cada paso, con su nota y quién lo hizo.')
                ->icon(Heroicon::OutlinedClock)
                ->columnSpanFull()
                ->collapsible()
                ->schema([
                    TextEntry::make('history')
                        ->hiddenLabel()
                        ->state(fn (Inconsistency $record): HtmlString => self::historyHtml($record))
                        ->columnSpanFull(),
                ]),
        ]);
    }

    /**
     * @param  array<string, string>  $facts
     */
    private static function factsHtml(array $facts): HtmlString
    {
        $rows = '';

        foreach ($facts as $label => $value) {
            $rows .= '<tr class="border-b border-gray-100 dark:border-white/5"><td class="px-2 py-1.5 font-medium text-gray-600 dark:text-gray-400" style="width:34%">'.e($label).'</td>'
                .'<td class="px-2 py-1.5">'.e($value).'</td></tr>';
        }

        return new HtmlString('<table class="w-full text-left text-sm">'.$rows.'</table>');
    }

    private static function historyHtml(Inconsistency $record): HtmlString
    {
        $labels = Event::actionLabels();
        $html = '<ol class="space-y-3 text-sm">';

        foreach ($record->events()->limit(50)->get() as $event) {
            $html .= '<li class="border-l-2 border-gray-200 pl-3 dark:border-white/10"><p><strong>'.e($labels[$event->action] ?? $event->action).'</strong> · '
                .e($event->created_at?->format('d/m/Y H:i') ?? '').' · '.e($event->user_name ?? 'SISTEMA').'</p>';

            if (filled($event->note)) {
                $html .= '<p class="text-gray-600 dark:text-gray-400">'.e($event->note).'</p>';
            }

            foreach ((array) ($event->payload['lines'] ?? []) as $line) {
                $html .= '<p class="text-gray-600 dark:text-gray-400">· '.e($line).'</p>';
            }

            $html .= '</li>';
        }

        return new HtmlString($html.'</ol>');
    }
}
