<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\QualityControlSurveys\Schemas;

use App\Enums\QualityControlAffiliateType;
use App\Enums\QualityControlRating;
use App\Enums\QualityControlService;
use App\Models\QualityControlSurvey;
use App\Support\Operations\QualityControl\QualityControlAffiliateLookup;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;

final class QualityControlSurveyForm
{
    public static function configure(Schema $schema, QualityControlAffiliateType $type): Schema
    {
        return $schema->components([
            Section::make('Paciente')
                ->description('Escriba la cédula: si es un '.$type->affiliateLabel().' del sistema, el nombre y el teléfono se completan solos y la encuesta queda enlazada a su ficha.')
                ->icon(Heroicon::OutlinedUser)
                ->columnSpanFull()
                ->columns(2)
                ->schema([
                    TextInput::make('patient_document')
                        ->label('Cédula de identidad del paciente')
                        ->placeholder('Ej.: 14582677')
                        ->required()
                        ->maxLength(60)
                        ->autofocus()
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (?string $state, Set $set, Get $get) use ($type): void {
                            self::applyLookup($type, $state, $set, $get);
                        }),
                    DatePicker::make('service_date')
                        ->label('Fecha de servicio')
                        ->required()
                        ->native(false)
                        ->displayFormat('d/m/Y')
                        ->maxDate(now())
                        ->closeOnDateSelection(),
                    Placeholder::make('lookup_result')
                        ->hiddenLabel()
                        ->columnSpanFull()
                        ->visible(fn (Get $get, ?QualityControlSurvey $record): bool => filled($get('lookup_status')) || $record?->affiliate_id !== null)
                        ->content(fn (Get $get, ?QualityControlSurvey $record): HtmlString => self::lookupMessage($type, $get('lookup_status'), $record)),
                    Hidden::make('lookup_status')
                        ->dehydrated(false),
                    TextInput::make('patient_name')
                        ->label('Nombre completo del paciente')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('contact_phone')
                        ->label('Número de contacto')
                        ->tel()
                        ->placeholder('Ej.: 0414-6924655')
                        ->maxLength(60),
                ]),
            Section::make('Calificación')
                ->description('Cómo calificó el paciente cada aspecto del servicio.')
                ->icon(Heroicon::OutlinedStar)
                ->columnSpanFull()
                ->schema([
                    Grid::make(['default' => 1, 'lg' => 2])
                        ->schema(array_map(
                            fn (string $field, string $label): ToggleButtons => ToggleButtons::make($field)
                                ->label($label)
                                ->options(QualityControlRating::options())
                                ->colors(collect(QualityControlRating::cases())->mapWithKeys(fn (QualityControlRating $rating): array => [$rating->value => $rating->color()])->all())
                                ->inline()
                                ->required()
                                ->validationMessages(['required' => 'Elija una calificación.']),
                            array_keys(QualityControlSurvey::RATING_FIELDS),
                            self::ratingQuestions(),
                        )),
                ]),
            Section::make('Servicio y observaciones')
                ->icon(Heroicon::OutlinedChatBubbleLeftEllipsis)
                ->columnSpanFull()
                ->schema([
                    CheckboxList::make('services')
                        ->label('Servicio recibido')
                        ->helperText('Marque todos los servicios que recibió el paciente.')
                        ->options(QualityControlService::options())
                        ->columns(['default' => 1, 'sm' => 2, 'lg' => 3])
                        ->required()
                        ->validationMessages(['required' => 'Marque al menos un servicio recibido.']),
                    Textarea::make('observations')
                        ->label('¿Tiene alguna sugerencia u observación?')
                        ->placeholder('Lo que comentó el paciente, con sus palabras.')
                        ->rows(3)
                        ->maxLength(5000),
                ]),
        ]);
    }

    /**
     * @return list<string>
     */
    private static function ratingQuestions(): array
    {
        return [
            '¿Cómo calificaría los canales de atención?',
            '¿Cómo calificaría el tiempo de respuesta?',
            '¿Cómo calificaría el servicio médico?',
            '¿Quedó satisfecho con el servicio?',
        ];
    }

    private static function applyLookup(QualityControlAffiliateType $type, ?string $document, Set $set, Get $get): void
    {
        if (blank($document)) {
            $set('lookup_status', null);

            return;
        }

        $match = QualityControlAffiliateLookup::find($type, $document);

        if ($match === null) {
            $set('lookup_status', json_encode(['found' => false]));

            return;
        }

        $set('lookup_status', json_encode(['found' => true, ...$match], JSON_UNESCAPED_UNICODE));
        $set('patient_name', $match['name']);

        if (blank($get('contact_phone')) && filled($match['phone'])) {
            $set('contact_phone', $match['phone']);
        }
    }

    private static function lookupMessage(QualityControlAffiliateType $type, mixed $status, ?QualityControlSurvey $record): HtmlString
    {
        $decoded = is_string($status) ? json_decode($status, true) : null;

        if (! is_array($decoded) && $record?->affiliate_id !== null) {
            return self::callout('success', 'Encuesta enlazada a un '.$type->affiliateLabel().' del sistema.');
        }

        if (! is_array($decoded) || ! ($decoded['found'] ?? false)) {
            return self::callout('warning', 'No hay ningún '.$type->affiliateLabel().' con esa cédula. Puede guardar igual con los datos que escriba.');
        }

        $details = array_filter([
            e((string) $decoded['name']),
            filled($decoded['affiliation_code'] ?? null) ? 'Afiliación '.e((string) $decoded['affiliation_code']) : null,
            filled($decoded['status'] ?? null) ? 'Estatus '.e((string) $decoded['status']) : null,
        ]);

        return self::callout('success', 'Afiliado encontrado: '.implode(' · ', $details).'.', escape: false);
    }

    private static function callout(string $tone, string $message, bool $escape = true): HtmlString
    {
        $classes = $tone === 'success'
            ? 'bg-emerald-50 text-emerald-800 ring-emerald-600/20 dark:bg-emerald-500/10 dark:text-emerald-300 dark:ring-emerald-400/30'
            : 'bg-amber-50 text-amber-800 ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-400/30';

        return new HtmlString('<p class="rounded-lg px-3 py-2 text-sm font-medium ring-1 '.$classes.'">'.($escape ? e($message) : $message).'</p>');
    }
}
