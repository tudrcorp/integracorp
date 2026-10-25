<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\TelemedicinePanelEvents\Schemas;

use App\Models\TelemedicinePanelEvent as Event;
use App\Support\Telemedicine\PanelLog\TelemedicinePanelEventQueries;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Detalle de un evento de la bitácora: qué pasó, a quién, dónde, y la secuencia
 * completa de ese médico alrededor del evento.
 */
class TelemedicinePanelEventInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(fn (Event $record): string => $record->title)
                ->icon(fn (Event $record): Heroicon => $record->isProblem() ? Heroicon::OutlinedExclamationTriangle : Heroicon::OutlinedInformationCircle)
                ->iconColor(fn (Event $record): string => $record->levelEnum()?->color() ?? 'gray')
                ->columnSpanFull()
                ->schema([
                    Grid::make(4)->schema([
                        TextEntry::make('occurred_at')->label('Hora')->dateTime('d/m/Y H:i:s'),
                        TextEntry::make('level')
                            ->label('Nivel')
                            ->badge()
                            ->formatStateUsing(fn (Event $record): string => $record->levelEnum()?->label() ?? (string) $record->level)
                            ->color(fn (Event $record): string => $record->levelEnum()?->color() ?? 'gray'),
                        TextEntry::make('category')
                            ->label('Tipo')
                            ->badge()
                            ->formatStateUsing(fn (Event $record): string => $record->categoryEnum()?->label() ?? (string) $record->category)
                            ->color(fn (Event $record): string => $record->categoryEnum()?->color() ?? 'gray'),
                        TextEntry::make('event')->label('Código del evento')->copyable(),
                    ]),
                    TextEntry::make('message')
                        ->label('Mensaje / explicación')
                        ->placeholder('Sin mensaje')
                        ->columnSpanFull(),
                ]),
            Section::make('Lo que hizo el médico')
                ->description('El recorrido de ese formulario (o de ese médico en el mismo caso), en orden. El evento actual va resaltado.')
                ->icon(Heroicon::OutlinedQueueList)
                ->columnSpanFull()
                ->schema([
                    ViewEntry::make('sequence')
                        ->hiddenLabel()
                        ->view('filament.operations.telemedicine-panel-events.sequence')
                        ->state(fn (Event $record): array => [
                            'current' => $record->getKey(),
                            'events' => TelemedicinePanelEventQueries::sequenceFor($record),
                        ]),
                ]),
            Section::make('Mensajes por campo')
                ->description('Tal como los produjo el sistema. «Campos sin lugar en pantalla» son mensajes que el médico no pudo ver.')
                ->icon(Heroicon::OutlinedExclamationCircle)
                ->columnSpanFull()
                ->visible(fn (Event $record): bool => filled($record->fields))
                ->schema([
                    KeyValueEntry::make('fields')
                        ->hiddenLabel()
                        ->keyLabel('Campo')
                        ->valueLabel('Mensaje')
                        ->state(fn (Event $record): array => collect((array) $record->fields)
                            ->map(fn (mixed $messages): string => implode(' ', (array) $messages))
                            ->all()),
                ]),
            Section::make('Quién y dónde')
                ->icon(Heroicon::OutlinedUserCircle)
                ->columnSpanFull()
                ->schema([
                    Grid::make(4)->schema([
                        TextEntry::make('user_name')->label('Usuario')->placeholder('Sin sesión'),
                        TextEntry::make('telemedicine_doctor_id')->label('Id de médico')->placeholder('—'),
                        TextEntry::make('telemedicine_case_code')->label('Caso')->placeholder('—')->copyable(),
                        TextEntry::make('telemedicineServiceList.name')->label('Servicio elegido')->placeholder('—'),
                        TextEntry::make('screen')->label('Pantalla')->placeholder('—'),
                        TextEntry::make('step')->label('Paso del asistente')->placeholder('—'),
                        TextEntry::make('action')->label('Botón / acción')->placeholder('—'),
                        TextEntry::make('url')->label('Dirección')->placeholder('—')->copyable(),
                    ]),
                ]),
            Section::make('Datos técnicos')
                ->description('Para soporte: identifica la pantalla abierta y la petición exacta.')
                ->icon(Heroicon::OutlinedCommandLine)
                ->collapsed()
                ->columnSpanFull()
                ->schema([
                    Grid::make(4)->schema([
                        TextEntry::make('telemedicine_case_id')->label('Id del caso')->placeholder('—'),
                        TextEntry::make('telemedicine_patient_id')->label('Id del paciente')->placeholder('—'),
                        TextEntry::make('telemedicine_consultation_id')->label('Id de la consulta')->placeholder('—'),
                        TextEntry::make('ip')->label('IP')->placeholder('—'),
                        TextEntry::make('component')->label('Componente')->placeholder('—')->columnSpan(2),
                        TextEntry::make('livewire_id')->label('Formulario (Livewire)')->placeholder('—')->copyable(),
                        TextEntry::make('request_id')->label('Petición')->placeholder('—')->copyable(),
                        TextEntry::make('user_agent')->label('Navegador')->placeholder('—')->columnSpanFull(),
                    ]),
                    KeyValueEntry::make('context')
                        ->label('Contexto')
                        ->visible(fn (Event $record): bool => filled($record->context))
                        ->state(fn (Event $record): array => collect((array) $record->context)
                            ->map(fn (mixed $value): string => match (true) {
                                is_bool($value) => $value ? 'sí' : 'no',
                                $value === null => '—',
                                is_scalar($value) => (string) $value,
                                default => (string) json_encode($value, JSON_UNESCAPED_UNICODE),
                            })
                            ->all()),
                ]),
        ]);
    }
}
