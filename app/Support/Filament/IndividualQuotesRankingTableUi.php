<?php

namespace App\Support\Filament;

use App\Models\Agent;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

class IndividualQuotesRankingTableUi
{
    /**
     * @param  class-string<Model>  $modelClass
     */
    public static function apply(
        Table $table,
        string $variant,
        Builder $query,
        string $modelClass,
        string $nameAttribute,
        string $nameLabel,
        string $typeRelation,
        string $searchPlaceholder,
        string $emptyHeading,
        string $emptyDescription,
        string|callable|null $heading = null,
    ): Table {
        $tableClass = self::tableClass($variant);

        $nameColumn = self::nameColumn($modelClass, $nameAttribute, $nameLabel, $variant);

        $table = $table
            ->heading($heading ?? self::heading($variant))
            ->defaultSort('total_quotes', 'desc')
            ->searchPlaceholder($searchPlaceholder)
            ->striped()
            ->emptyStateHeading($emptyHeading)
            ->emptyStateDescription($emptyDescription)
            ->emptyStateIcon(self::emptyStateIcon($variant))
            ->query(fn (): Builder => $query)
            ->extraAttributes([
                'class' => $tableClass,
                'data-iq-ranking-variant' => $variant,
            ])
            ->columns([
                self::rankColumn(),
                $nameColumn,
                self::typeColumn($typeRelation, $variant),
                self::totalColumn($variant),
            ])
            ->paginated([8, 16, 25, 50])
            ->defaultPaginationPageOption(8)
            ->extremePaginationLinks();

        return $table;
    }

    public static function widgetClass(string $variant): string
    {
        return 'fi-iq-ranking-table-widget fi-iq-ranking-table-widget--'.$variant;
    }

    public static function tableClass(string $variant): string
    {
        return 'individual-quotes-ranking-table-ios individual-quotes-ranking-table-ios--'.$variant;
    }

    public static function heading(string $variant): string
    {
        return $variant === 'agency'
            ? 'Cotizaciones por agencia'
            : 'Cotizaciones por agente';
    }

    public static function emptyStateIcon(string $variant): Heroicon
    {
        return $variant === 'agency'
            ? Heroicon::OutlinedBuildingOffice2
            : Heroicon::OutlinedUserGroup;
    }

    protected static function rankColumn(): TextColumn
    {
        return TextColumn::make('rank')
            ->label('#')
            ->rowIndex()
            ->alignCenter()
            ->size(TextSize::Small)
            ->weight(FontWeight::Bold)
            ->formatStateUsing(fn (string $state): string => $state)
            ->badge()
            ->extraAttributes(fn (string $state): array => [
                'class' => match ((int) $state) {
                    1 => 'iq-ranking-rank-badge iq-ranking-rank-badge--gold',
                    2 => 'iq-ranking-rank-badge iq-ranking-rank-badge--silver',
                    3 => 'iq-ranking-rank-badge iq-ranking-rank-badge--bronze',
                    default => 'iq-ranking-rank-badge iq-ranking-rank-badge--default',
                },
            ]);
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    protected static function nameColumn(
        string $modelClass,
        string $nameAttribute,
        string $nameLabel,
        string $variant,
    ): TextColumn {
        return TextColumn::make($nameAttribute)
            ->label($nameLabel)
            ->searchable()
            ->sortable()
            ->weight(FontWeight::SemiBold)
            ->icon($variant === 'agency' ? Heroicon::BuildingOffice2 : Heroicon::UserCircle)
            ->iconColor($variant === 'agency' ? 'info' : 'primary')
            ->formatStateUsing(function (?string $state, Model $record) use ($variant): HtmlString|string {
                $name = e(mb_strtoupper($state ?? '', 'UTF-8'));

                if ($variant === 'agent' && $record instanceof Agent && filled($record->code_agent)) {
                    return new HtmlString($name.' <span class="iq-ranking-inline-code">· '.e($record->code_agent).'</span>');
                }

                return $name;
            })
            ->html(fn (): bool => $variant === 'agent')
            ->wrap(false)
            ->lineClamp(1)
            ->tooltip(fn (?string $state): ?string => filled($state) ? $state : null)
            ->extraCellAttributes(['class' => 'iq-ranking-name-cell']);
    }

    protected static function typeColumn(string $typeRelation, string $variant): TextColumn
    {
        return TextColumn::make($typeRelation.'.definition')
            ->label('Tipo')
            ->badge()
            ->sortable()
            ->placeholder('—')
            ->color(fn (?string $state): string => self::typeBadgeColor($state, $variant))
            ->extraAttributes(['class' => 'iq-ranking-type-badge']);
    }

    protected static function totalColumn(string $variant): TextColumn
    {
        return TextColumn::make('total_quotes')
            ->label('Total')
            ->numeric()
            ->sortable()
            ->alignEnd()
            ->weight(FontWeight::Bold)
            ->size(TextSize::Small)
            ->color($variant === 'agency' ? 'info' : 'primary')
            ->extraCellAttributes(['class' => 'iq-ranking-total-cell']);
    }

    public static function typeBadgeColor(?string $definition, string $variant): string
    {
        $normalized = mb_strtoupper(trim((string) $definition), 'UTF-8');

        if ($variant === 'agency') {
            return match (true) {
                str_contains($normalized, 'MASTER') => 'warning',
                str_contains($normalized, 'GENERAL') => 'info',
                default => 'gray',
            };
        }

        return match (true) {
            str_contains($normalized, 'SUB') => 'gray',
            str_contains($normalized, 'AGENT') => 'primary',
            default => 'success',
        };
    }
}
