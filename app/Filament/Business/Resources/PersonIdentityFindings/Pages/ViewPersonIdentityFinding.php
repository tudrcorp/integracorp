<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\PersonIdentityFindings\Pages;

use App\Enums\PersonIdentityFindingKind;
use App\Enums\PersonIdentitySource;
use App\Filament\Business\Resources\PersonIdentityFindings\PersonIdentityFindingResource;
use App\Models\AffiliateCorporate;
use App\Models\PersonIdentityFinding as Finding;
use App\Models\PersonIdentityFindingEvent;
use App\Models\User;
use App\Support\Affiliations\PersonIdentity\PersonIdentityNormalizer;
use App\Support\Affiliations\PersonIdentity\PersonIdentityRecord;
use App\Support\Affiliations\PersonIdentity\PersonIdentityResolver;
use App\Support\Affiliations\PersonIdentity\PersonIdentityScanner;
use App\Support\Filament\RecordPageHeader;
use App\Support\Operations\AffiliatePersonalDataUpdater;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Grid;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class ViewPersonIdentityFinding extends ViewRecord
{
    protected static string $resource = PersonIdentityFindingResource::class;

    /**
     * Registros de cada lado ya leídos en este request (las acciones los consultan varias veces).
     *
     * @var array<string, ?Model>
     */
    private array $loadedModels = [];

    public function getTitle(): string|Htmlable
    {
        /** @var Finding $record */
        $record = $this->getRecord();

        return ($record->kindEnum()?->label() ?? 'Caso').' · '.$record->person_name;
    }

    /**
     * Encabezado de ficha como el de las afiliaciones ({@see RecordPageHeader}): qué tipo de caso es,
     * la persona, el estado y dónde está registrada cada vez.
     */
    public function getHeading(): string|Htmlable
    {
        /** @var Finding $record */
        $record = $this->getRecord();
        $kind = $record->kindEnum();
        $a = $record->side('a');
        $b = $record->side('b');
        $sources = array_values(array_unique(array_filter([
            $record->sourceA()?->shortLabel(),
            $record->sourceB()?->shortLabel(),
        ])));
        $documents = array_values(array_unique(array_filter([
            trim((string) ($a['document'] ?? '')),
            trim((string) ($b['document'] ?? '')),
        ])));
        $where = array_values(array_filter([
            (string) ($a['parent_code'] ?? ''),
            (string) ($b['parent_code'] ?? ''),
        ]));

        return RecordPageHeader::render(
            eyebrow: 'Personas duplicadas · '.($kind?->label() ?? 'Caso'),
            title: (string) ($record->person_name ?: 'Sin nombre'),
            status: RecordPageHeader::tag(
                $record->statusLabel(),
                match ($record->status) {
                    Finding::STATUS_OPEN => RecordPageHeader::TONE_DANGER,
                    Finding::STATUS_CORRECTED => RecordPageHeader::TONE_SUCCESS,
                    Finding::STATUS_DISMISSED => RecordPageHeader::TONE_NEUTRAL,
                    default => RecordPageHeader::TONE_INFO,
                },
            ),
            chips: [
                RecordPageHeader::tag(
                    'Gravedad '.mb_strtolower((string) $record->severity),
                    $record->severity === PersonIdentityFindingKind::SEVERITY_HIGH ? RecordPageHeader::TONE_DANGER : RecordPageHeader::TONE_WARNING,
                ),
                $sources !== [] ? RecordPageHeader::tag(implode(' + ', $sources), RecordPageHeader::TONE_VIOLET) : null,
            ],
            facts: [
                ($kind?->isDuplicate() ?? true) ? 'Registrada en' : 'Registro' => implode(' y ', $where),
                count($documents) > 1 ? 'Cédulas' : 'Cédula' => $documents !== [] ? implode(' / ', $documents) : 'Sin cédula',
                'Nacimiento' => (string) ($a['birth_date'] ?? ''),
                'Detectado' => $record->first_detected_at?->format('d/m/Y'),
            ],
        );
    }

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                $this->correctAction('a'),
                $this->correctAction('b'),
            ])->label('Corregir datos')->icon(Heroicon::OutlinedPencilSquare)->button()->color('warning'),
            ActionGroup::make([
                $this->deactivateAction('a'),
                $this->deactivateAction('b'),
            ])->label('Dar de baja')->icon(Heroicon::OutlinedUserMinus)->button()->color('danger'),
            $this->dismissAction(),
            $this->revertAction(),
            $this->reopenAction(),
            ActionGroup::make([
                Action::make('rescan')
                    ->label('Volver a revisar este caso')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->action(function (Finding $record): void {
                        PersonIdentityScanner::rescanFinding($record, $this->actor());
                        $this->refreshRecord();

                        Notification::make()->title('Caso revisado')->body('Estado: '.$this->record->statusLabel().'.')->success()->send();
                    }),
            ])->label('Más')->icon(Heroicon::OutlinedEllipsisVertical)->button()->color('gray'),
            Action::make('back')
                ->label('Volver')
                ->icon(Heroicon::OutlinedArrowLeft)
                ->color('gray')
                ->url(PersonIdentityFindingResource::getUrl()),
        ];
    }

    private function correctAction(string $side): Action
    {
        return Action::make('correct_'.$side)
            ->label(fn (Finding $record): string => 'Registro '.mb_strtoupper($side).': '.$this->sideLabel($record, $side))
            ->icon(Heroicon::OutlinedPencilSquare)
            ->modalHeading(fn (Finding $record): string => 'Corregir datos del registro '.mb_strtoupper($side))
            ->modalDescription(fn (Finding $record): string => $this->sideLabel($record, $side).'. Solo se cambia lo que usted modifique. Si el registro es un afiliado, su paciente de telemedicina se actualiza igual y Afiliaciones recibe el aviso. Se puede revertir después.')
            ->modalWidth(Width::ThreeExtraLarge)
            ->modalSubmitActionLabel('Guardar corrección')
            ->visible(fn (Finding $record): bool => $this->allowed() && ($side === 'a' || $record->record_b_id !== null) && $this->model($record, $side) !== null)
            ->authorize(fn (): bool => $this->allowed())
            ->fillForm(fn (Finding $record): array => $this->currentValues($record, $side))
            ->schema(fn (Finding $record): array => $this->correctionFields($record, $side))
            ->action(function (Finding $record, array $data) use ($side): void {
                $note = (string) ($data['note'] ?? '');
                unset($data['note']);

                $this->run(function () use ($record, $side, $data, $note): string {
                    $changes = PersonIdentityResolver::correct($record, $side, $data, $note, $this->actor());

                    return count($changes).' dato(s) corregido(s).';
                }, 'Corrección guardada');
            });
    }

    private function deactivateAction(string $side): Action
    {
        return Action::make('deactivate_'.$side)
            ->label(fn (Finding $record): string => 'Registro '.mb_strtoupper($side).': '.$this->sideLabel($record, $side))
            ->icon(Heroicon::OutlinedUserMinus)
            ->color('danger')
            ->modalHeading(fn (): string => 'Dar de baja el registro '.mb_strtoupper($side))
            ->modalIcon(Heroicon::OutlinedExclamationTriangle)
            ->modalIconColor('danger')
            ->modalWidth(Width::TwoExtraLarge)
            ->modalSubmitActionLabel('Dar de baja')
            ->visible(fn (Finding $record): bool => $this->allowed()
                && ($record->kindEnum()?->isDuplicate() ?? false)
                && $record->status === Finding::STATUS_OPEN
                && ($side === 'a' || $record->record_b_id !== null)
                && ($this->record($record, $side)?->isActive() ?? false))
            ->authorize(fn (): bool => $this->allowed())
            ->schema([
                Placeholder::make('consequence')
                    ->hiddenLabel()
                    ->content(fn (Finding $record): HtmlString => new HtmlString(
                        '<div class="space-y-2 text-sm"><p>Se da de baja: <strong>'.e($this->sideLabel($record, $side)).'</strong>.</p>'
                        .'<ul class="list-disc pl-5">'
                        .'<li>Afiliado individual: queda INACTIVO y se recalculan los totales de su afiliación.</li>'
                        .'<li>Afiliado corporativo: queda INACTIVO y se descuentan su tarifa, la población del plan y los totales de la afiliación.</li>'
                        .'<li>Nuevos Negocios: el asociado queda ANULADO y se le devuelve el día al responsable.</li>'
                        .'</ul><p class="text-red-700 dark:text-red-300">No se revierte desde aquí. Si es el titular o el único miembro de una afiliación individual, revise la afiliación después.</p></div>'
                    ))
                    ->columnSpanFull(),
                Textarea::make('note')
                    ->label('Nota: por qué se da de baja este registro y no el otro')
                    ->required()
                    ->minLength(PersonIdentityResolver::NOTE_MIN_LENGTH)
                    ->maxLength(2000)
                    ->rows(3)
                    ->placeholder('Ejemplo: el registro bueno es el de TDEC-COR-00060 (activo y con plan escolar); este se cargó dos veces en el padrón de enero.'),
                Checkbox::make('confirmed')
                    ->label('Entiendo que este registro sale de su población y que no se revierte desde aquí.')
                    ->accepted()
                    ->required(),
            ])
            ->action(function (Finding $record, array $data) use ($side): void {
                $this->run(
                    fn (): string => PersonIdentityResolver::deactivate($record, $side, (string) ($data['note'] ?? ''), $this->actor()),
                    'Registro dado de baja',
                );
            });
    }

    private function dismissAction(): Action
    {
        return Action::make('dismiss')
            ->label(fn (Finding $record): string => ($record->kindEnum()?->isDuplicate() ?? true) ? 'No es la misma persona' : 'El nombre está bien')
            ->icon(Heroicon::OutlinedHandThumbUp)
            ->color('gray')
            ->modalHeading(fn (Finding $record): string => ($record->kindEnum()?->isDuplicate() ?? true) ? 'Marcar como «no es la misma persona»' : 'Marcar el nombre como correcto')
            ->modalDescription('Sale de la lista de abiertos mientras los datos de los registros no cambien. Si cambian, el sistema lo vuelve a abrir. Mientras siga marcado, guardar estos registros no se bloquea entre sí.')
            ->modalSubmitActionLabel('Marcar')
            ->visible(fn (Finding $record): bool => $this->allowed() && $record->status === Finding::STATUS_OPEN)
            ->authorize(fn (): bool => $this->allowed())
            ->schema([
                Textarea::make('note')
                    ->label('Nota explicativa')
                    ->required()
                    ->minLength(PersonIdentityResolver::NOTE_MIN_LENGTH)
                    ->maxLength(2000)
                    ->rows(4)
                    ->placeholder('Ejemplo: son gemelos con el mismo nombre de pila registrados con la cédula de la madre; se confirmó con la empresa.'),
            ])
            ->action(function (Finding $record, array $data): void {
                $this->run(function () use ($record, $data): string {
                    PersonIdentityResolver::dismiss($record, (string) ($data['note'] ?? ''), $this->actor());

                    return 'Ya no aparece en la lista de abiertos.';
                }, 'Caso marcado');
            });
    }

    private function revertAction(): Action
    {
        return Action::make('revertCorrection')
            ->label('Revertir corrección')
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('danger')
            ->modalHeading('Revertir la última corrección de datos')
            ->modalWidth(Width::TwoExtraLarge)
            ->modalSubmitActionLabel('Revertir ahora')
            ->visible(fn (Finding $record): bool => $this->allowed() && PersonIdentityResolver::revertibleCorrection($record) instanceof PersonIdentityFindingEvent)
            ->authorize(fn (): bool => $this->allowed())
            ->schema([
                Placeholder::make('rollback')
                    ->hiddenLabel()
                    ->content(fn (Finding $record): HtmlString => self::revertHtml($record))
                    ->columnSpanFull(),
                Textarea::make('note')
                    ->label('Motivo de la reversión')
                    ->required()
                    ->minLength(PersonIdentityResolver::NOTE_MIN_LENGTH)
                    ->maxLength(2000)
                    ->rows(3),
            ])
            ->action(function (Finding $record, array $data): void {
                $this->run(function () use ($record, $data): string {
                    PersonIdentityResolver::revertCorrection($record, (string) ($data['note'] ?? ''), $this->actor());

                    return 'Los datos volvieron a como estaban antes de la corrección.';
                }, 'Corrección revertida');
            });
    }

    private function reopenAction(): Action
    {
        return Action::make('reopen')
            ->label('Reabrir')
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('warning')
            ->modalHeading('Reabrir caso')
            ->modalDescription('Vuelve a la lista de abiertos y vuelve a bloquear el guardado entre estos registros.')
            ->modalSubmitActionLabel('Reabrir')
            ->visible(fn (Finding $record): bool => $this->allowed() && $record->status === Finding::STATUS_DISMISSED)
            ->authorize(fn (): bool => $this->allowed())
            ->schema([
                Textarea::make('note')
                    ->label('Motivo')
                    ->required()
                    ->minLength(PersonIdentityResolver::NOTE_MIN_LENGTH)
                    ->maxLength(2000)
                    ->rows(3),
            ])
            ->action(function (Finding $record, array $data): void {
                $this->run(function () use ($record, $data): string {
                    PersonIdentityResolver::reopen($record, (string) ($data['note'] ?? ''), $this->actor());

                    return 'Volvió a la lista de abiertos.';
                }, 'Caso reabierto');
            });
    }

    /**
     * @return list<\Filament\Schemas\Components\Component|\Filament\Forms\Components\Field>
     */
    private function correctionFields(Finding $record, string $side): array
    {
        $source = $side === 'b' ? $record->sourceB() : $record->sourceA();

        $nameFields = $source === PersonIdentitySource::Corporate
            ? [
                TextInput::make('first_name')->label('Nombres')->required()->maxLength(100)
                    ->helperText('Solo los nombres, una vez. Ejemplo: ESTHEFANO JOSUE'),
                TextInput::make('last_name')->label('Apellidos')->required()->maxLength(100)
                    ->helperText('Solo los apellidos, una vez. Ejemplo: ROBLES OTERO'),
            ]
            : [
                TextInput::make('full_name')->label('Nombre completo')->required()->maxLength(255)->columnSpanFull()
                    ->helperText('Nombres y apellidos, una sola vez.'),
            ];

        $documentField = $source === PersonIdentitySource::Associate
            ? TextInput::make('identity_card')->label('Cédula o pasaporte')->required()->maxLength(30)
            : TextInput::make('nro_identificacion')->label('Cédula')->required()->maxLength(20)
                ->helperText('Se guarda solo con números, como en el resto de Negocios. Un menor con la cédula del representante: cédula y número de hijo (191311542).')
                ->rule(fn (): \Closure => function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! AffiliatePersonalDataUpdater::documentIsValid($value)) {
                        $fail('La cédula debe tener entre 5 y 12 números (puede llevar V, E, puntos o guiones).');
                    }
                });

        return [
            Grid::make(2)->schema([
                ...$nameFields,
                $documentField,
                DatePicker::make('birth_date')
                    ->label('Fecha de nacimiento')
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->format('d/m/Y')
                    ->maxDate(now())
                    ->closeOnDateSelection(),
            ])->columnSpanFull(),
            Textarea::make('note')
                ->label('Nota: qué corrige y de dónde salió el dato correcto')
                ->required()
                ->minLength(PersonIdentityResolver::NOTE_MIN_LENGTH)
                ->maxLength(2000)
                ->rows(3)
                ->placeholder('Ejemplo: el padrón de enero cargó nombres y apellidos repetidos; se corrige según la cédula enviada por la empresa.')
                ->columnSpanFull(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function currentValues(Finding $record, string $side): array
    {
        $model = $this->model($record, $side);

        if ($model === null) {
            return [];
        }

        $source = PersonIdentitySource::fromModel($model);
        $values = [];

        foreach (array_keys(PersonIdentityResolver::correctableFields($source)) as $field) {
            $value = $model->getAttribute($field);
            $values[$field] = $field === 'birth_date'
                ? PersonIdentityNormalizer::birthDate($value)?->format('d/m/Y')
                : $value;
        }

        if ($model instanceof AffiliateCorporate && PersonIdentityNormalizer::isRepeatedName((string) $model->first_name, (string) $model->last_name)) {
            $values['last_name'] = '';
        }

        return [...$values, 'note' => ''];
    }

    private static function revertHtml(Finding $record): HtmlString
    {
        $correction = PersonIdentityResolver::revertibleCorrection($record);

        if ($correction === null) {
            return new HtmlString('<p class="text-sm">No hay una corrección para revertir.</p>');
        }

        $html = '<div class="space-y-2 text-sm"><p>Corrección del '.e($correction->created_at?->format('d/m/Y H:i') ?? '').' por '.e($correction->user_name ?? 'SISTEMA').'. Vuelve a:</p><ul class="list-disc pl-5">';

        foreach ((array) ($correction->payload['changes'] ?? []) as $change) {
            $html .= '<li>'.e((string) ($change['label'] ?? '')).': «'.e(trim((string) ($change['before'] ?? ''))).'»</li>';
        }

        $html .= '</ul>';
        $blockers = PersonIdentityResolver::revertBlockers($correction);

        if ($blockers !== []) {
            $html .= '<div class="text-red-700 dark:text-red-300"><p class="font-semibold">No se puede revertir:</p><ul class="list-disc pl-5">';

            foreach ($blockers as $blocker) {
                $html .= '<li>'.e($blocker).'</li>';
            }

            $html .= '</ul></div>';
        }

        return new HtmlString($html.'</div>');
    }

    private function sideLabel(Finding $record, string $side): string
    {
        return (string) ($this->record($record, $side)?->whereDescription() ?? ($record->side($side)['where'] ?? 'registro'));
    }

    private function model(Finding $record, string $side): ?Model
    {
        $key = $record->getKey().':'.$record->updated_at?->getTimestamp().':'.$side;

        if (! array_key_exists($key, $this->loadedModels)) {
            $this->loadedModels[$key] = PersonIdentityResolver::modelOf($record, $side);
        }

        return $this->loadedModels[$key];
    }

    private function record(Finding $record, string $side): ?PersonIdentityRecord
    {
        $model = $this->model($record, $side);

        return $model !== null ? PersonIdentityRecord::fromModel($model) : null;
    }

    /**
     * @param  callable(): string  $operation
     */
    private function run(callable $operation, string $successTitle): void
    {
        try {
            $body = $operation();
        } catch (ValidationException $exception) {
            $this->failed('No se pudo completar', collect($exception->errors())->flatten()->implode(' '));

            return;
        } catch (RuntimeException $exception) {
            $this->failed('No se pudo completar', $exception->getMessage());

            return;
        } catch (Throwable $exception) {
            report($exception);
            $this->failed('No se pudo completar', 'Ocurrió un error inesperado y se deshizo todo.');

            return;
        }

        $this->refreshRecord();

        Notification::make()
            ->title($successTitle)
            ->body($body.' Estado del caso: '.$this->record->statusLabel().'.')
            ->success()
            ->send();
    }

    private function failed(string $title, string $body): void
    {
        Notification::make()->title($title)->body($body.' No se cambió ningún dato.')->danger()->persistent()->send();
    }

    private function refreshRecord(): void
    {
        $this->record->refresh();
        $this->record->unsetRelation('events');
    }

    private function allowed(): bool
    {
        return PersonIdentityResolver::userCan(Auth::user() instanceof User ? Auth::user() : null);
    }

    private function actor(): User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : throw new RuntimeException('Sesión no válida.');
    }
}
