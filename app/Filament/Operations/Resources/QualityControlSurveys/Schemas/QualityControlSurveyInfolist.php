<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\QualityControlSurveys\Schemas;

use App\Enums\QualityControlAffiliateType;
use App\Enums\QualityControlRating;
use App\Enums\QualityControlService;
use App\Models\QualityControlSurvey;
use App\Support\Operations\QualityControl\QualityControlAffiliateLookup;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

final class QualityControlSurveyInfolist
{
    public static function configure(Schema $schema, QualityControlAffiliateType $type): Schema
    {
        return $schema->components([
            Section::make('Paciente')
                ->icon(Heroicon::OutlinedUser)
                ->columnSpanFull()
                ->columns(['default' => 1, 'md' => 3])
                ->schema([
                    TextEntry::make('patient_name')
                        ->label('Nombre completo')
                        ->weight('semibold'),
                    TextEntry::make('patient_document')
                        ->label('Cédula')
                        ->copyable(),
                    TextEntry::make('contact_phone')
                        ->label('Número de contacto')
                        ->placeholder('Sin teléfono')
                        ->copyable(),
                    TextEntry::make('affiliate_link')
                        ->label('Afiliado en el sistema')
                        ->state(fn (QualityControlSurvey $record): string => self::affiliateLabel($record, $type))
                        ->badge()
                        ->color(fn (QualityControlSurvey $record): string => $record->affiliate_id !== null ? 'success' : 'gray'),
                    TextEntry::make('service_date')
                        ->label('Fecha de servicio')
                        ->date('d/m/Y')
                        ->placeholder('—'),
                    TextEntry::make('responded_at')
                        ->label('Marca temporal')
                        ->dateTime('d/m/Y h:i A'),
                ]),
            Section::make('Calificación')
                ->icon(Heroicon::OutlinedStar)
                ->columnSpanFull()
                ->schema([
                    Grid::make(['default' => 1, 'md' => 2, 'xl' => 4])
                        ->schema(array_map(
                            fn (string $field, string $label): TextEntry => TextEntry::make($field)
                                ->label($label)
                                ->badge()
                                ->formatStateUsing(fn (?QualityControlRating $state): string => $state?->label() ?? '—')
                                ->color(fn (?QualityControlRating $state): string => $state?->color() ?? 'gray')
                                ->placeholder('Sin respuesta'),
                            array_keys(QualityControlSurvey::RATING_FIELDS),
                            QualityControlSurvey::RATING_FIELDS,
                        )),
                ]),
            Section::make('Servicio y observaciones')
                ->icon(Heroicon::OutlinedChatBubbleLeftEllipsis)
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('services')
                        ->label('Servicio recibido')
                        ->badge()
                        ->color('info')
                        ->formatStateUsing(fn (string $state): string => QualityControlService::tryFrom($state)?->label() ?? $state)
                        ->placeholder('Sin servicio indicado'),
                    TextEntry::make('observations')
                        ->label('Sugerencia u observación')
                        ->placeholder('Sin observaciones')
                        ->prose(),
                ]),
            Section::make('Notas de seguimiento')
                ->icon(Heroicon::OutlinedChatBubbleBottomCenterText)
                ->description('Lo que se hizo con esta encuesta, de lo más reciente a lo más antiguo. Las notas no se editan ni se borran.')
                ->columnSpanFull()
                ->schema([
                    RepeatableEntry::make('notes')
                        ->hiddenLabel()
                        ->placeholder('Todavía no hay notas de seguimiento.')
                        ->schema([
                            TextEntry::make('note')
                                ->hiddenLabel()
                                ->prose()
                                ->columnSpanFull(),
                            TextEntry::make('created_by_name')
                                ->hiddenLabel()
                                ->size('xs')
                                ->color('gray')
                                ->formatStateUsing(fn (?string $state, \App\Models\QualityControlSurveyNote $record): string => ($state ?: '—').' · '.$record->created_at?->format('d/m/Y h:i A')),
                        ]),
                ]),
            Section::make('Registro')
                ->icon(Heroicon::OutlinedInformationCircle)
                ->columnSpanFull()
                ->collapsed()
                ->columns(['default' => 1, 'md' => 3])
                ->schema([
                    TextEntry::make('source')
                        ->label('Origen')
                        ->formatStateUsing(fn (?string $state): string => $state === QualityControlSurvey::SOURCE_IMPORT ? 'Importada desde Excel' : 'Registrada en el formulario'),
                    TextEntry::make('creator.name')
                        ->label('Registrada por')
                        ->placeholder('—'),
                    TextEntry::make('updated_at')
                        ->label('Última modificación')
                        ->dateTime('d/m/Y h:i A'),
                ]),
        ]);
    }

    private static function affiliateLabel(QualityControlSurvey $record, QualityControlAffiliateType $type): string
    {
        $affiliate = $type === QualityControlAffiliateType::Individual
            ? $record->individualAffiliate
            : $record->corporateAffiliate;

        if ($affiliate === null) {
            return 'No enlazado';
        }

        $status = filled($affiliate->status) ? ' · '.$affiliate->status : '';

        return 'Enlazado: '.QualityControlAffiliateLookup::nameOf($affiliate).$status;
    }
}
