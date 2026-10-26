<?php

declare(strict_types=1);

namespace App\Filament\Shared\Affiliations;

use App\Models\Affiliation;
use App\Models\AffiliationCorporate;
use App\Models\AffiliationDeletion;
use App\Models\User;
use App\Support\Affiliations\Deletion\AffiliationDeletionInspector;
use App\Support\Affiliations\Deletion\AffiliationDeletionService;
use App\Support\Affiliations\Deletion\AffiliationDeletionSubject;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Support\Enums\Width;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * «Eliminar afiliación» y «Restaurar afiliación» para las tablas de afiliaciones individuales y
 * corporativas de Negocios y Administración, más el interruptor «Ver solo afiliaciones eliminadas».
 *
 * La eliminación es un asistente de tres pasos que acompaña al analista: primero la revisión de
 * seguridad (qué impide eliminar y cómo resolverlo), después exactamente lo que va a pasar, y al
 * final el motivo y la confirmación con el código. La lógica vive en
 * {@see AffiliationDeletionService}; aquí solo hay pantalla y mensajes.
 */
final class AffiliationDeletionActions
{
    /** Clave del interruptor «Ver solo afiliaciones eliminadas». */
    public const DELETED_FILTER = 'eliminadas';

    /** Máximo de afiliaciones por eliminación masiva: cada una corre su propia revisión. */
    public const MAX_BULK = 50;

    /**
     * Acciones de fila: eliminar (en la vista normal) y restaurar (en la vista de eliminadas).
     *
     * @return list<Action>
     */
    public static function recordActions(): array
    {
        return [self::deleteAction(), self::restoreAction()];
    }

    public static function userCan(): bool
    {
        $user = Auth::user();

        return AffiliationDeletionService::userCan($user instanceof User ? $user : null);
    }

    public static function viewingDeleted(mixed $livewire): bool
    {
        return data_get($livewire, 'tableFilters.'.self::DELETED_FILTER.'.isActive') === true && self::userCan();
    }

    public static function deletedFilter(): Filter
    {
        return Filter::make(self::DELETED_FILTER)
            ->label('Ver solo afiliaciones eliminadas')
            ->toggle()
            ->columnSpanFull()
            ->visible(fn (): bool => self::userCan())
            ->baseQuery(fn (Builder $query, array $data): Builder => ($data['isActive'] ?? false) === true
                ? $query->onlyTrashed()
                : $query);
    }

    public static function deleteAction(): Action
    {
        return Action::make('deleteAffiliation')
            ->label('Eliminar afiliación')
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->modalHeading(fn (Model $record): string => 'Eliminar '.AffiliationDeletionSubject::for($record)->label())
            ->modalDescription('El sistema lo acompaña en tres pasos: revisa que sea seguro, le muestra exactamente qué va a pasar y le pide el motivo. Nada se borra de la base de datos y todo se puede revertir.')
            ->modalIcon(Heroicon::OutlinedTrash)
            ->modalIconColor('danger')
            ->modalWidth(Width::FourExtraLarge)
            ->modalSubmitActionLabel('Eliminar afiliación')
            ->closeModalByClickingAway(false)
            ->visible(fn (mixed $livewire, mixed $record): bool => self::userCan()
                && ! self::viewingDeleted($livewire)
                && ($record instanceof Affiliation || $record instanceof AffiliationCorporate)
                && ! $record->trashed())
            ->authorize(fn (): bool => self::userCan())
            ->fillForm(fn (): array => ['inspection' => null, 'reason' => '', 'code' => '', 'confirmed' => false])
            ->steps([
                Step::make('Revisión de seguridad')
                    ->description('¿Se puede eliminar?')
                    ->icon(Heroicon::OutlinedShieldCheck)
                    ->schema([
                        Hidden::make('inspection'),
                        Placeholder::make('checks')
                            ->hiddenLabel()
                            ->content(fn (Model $record): HtmlString => self::checksHtml(self::inspection($record)))
                            ->columnSpanFull(),
                    ])
                    ->afterValidation(function (Model $record, Set $set): void {
                        $inspection = AffiliationDeletionInspector::inspect(AffiliationDeletionSubject::for($record));

                        if (! $inspection['ok']) {
                            Notification::make()
                                ->title('Todavía no se puede eliminar')
                                ->body('Resuelva primero lo marcado en rojo. Cada punto dice qué lo bloquea y dónde se resuelve.')
                                ->warning()
                                ->send();

                            throw new Halt;
                        }

                        $set('inspection', $inspection);
                    }),
                Step::make('Qué va a pasar')
                    ->description('Revise el resultado')
                    ->icon(Heroicon::OutlinedListBullet)
                    ->schema([
                        Placeholder::make('effects')
                            ->hiddenLabel()
                            ->content(fn (Model $record, Get $get): HtmlString => self::effectsHtml(AffiliationDeletionSubject::for($record), (array) ($get('inspection') ?? self::inspection($record))))
                            ->columnSpanFull(),
                    ]),
                Step::make('Motivo y confirmación')
                    ->description('Deje la traza')
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->schema([
                        Textarea::make('reason')
                            ->label('Nota obligatoria: ¿por qué se elimina esta afiliación?')
                            ->helperText('Queda en las observaciones de la afiliación, en la traza de la eliminación y en la auditoría. Mínimo '.AffiliationDeletionService::REASON_MIN_LENGTH.' caracteres.')
                            ->placeholder('Ejemplo: afiliación cargada por error; el grupo ya estaba registrado en TDEC-COR-00060.')
                            ->required()
                            ->minLength(AffiliationDeletionService::REASON_MIN_LENGTH)
                            ->maxLength(2000)
                            ->rows(4)
                            ->columnSpanFull()
                            ->validationMessages([
                                'required' => 'Escriba el motivo: es lo que verá quien revise o restaure esta eliminación.',
                                'min' => 'Explique un poco más el motivo (mínimo '.AffiliationDeletionService::REASON_MIN_LENGTH.' caracteres).',
                            ]),
                        TextInput::make('code')
                            ->label(fn (Model $record): string => 'Para confirmar, escriba el código: '.$record->code)
                            ->required()
                            ->rule(fn (Model $record): \Closure => function (string $attribute, mixed $value, \Closure $fail) use ($record): void {
                                if (mb_strtoupper(trim((string) $value)) !== mb_strtoupper(trim((string) $record->code))) {
                                    $fail('El código no coincide. Escríbalo tal como aparece: '.$record->code.'.');
                                }
                            })
                            ->columnSpanFull(),
                        Checkbox::make('confirmed')
                            ->label('Revisé lo que va a pasar y confirmo la eliminación. Sé que puedo revertirla desde «Ver solo afiliaciones eliminadas».')
                            ->accepted()
                            ->required()
                            ->validationMessages(['accepted' => 'Marque la confirmación para continuar.'])
                            ->columnSpanFull(),
                    ]),
            ])
            ->action(function (Model $record, array $data): void {
                try {
                    $result = AffiliationDeletionService::delete($record, (string) ($data['reason'] ?? ''), self::actor());
                } catch (RuntimeException $exception) {
                    self::failed('No se eliminó la afiliación', $exception->getMessage());

                    return;
                } catch (Throwable $exception) {
                    report($exception);
                    self::failed('No se eliminó la afiliación', 'Ocurrió un error inesperado y el sistema deshizo todo. Intente de nuevo; si se repite, avise a Sistemas.');

                    return;
                }

                Notification::make()
                    ->title('Afiliación '.$record->code.' eliminada')
                    ->body(implode(' ', $result['lines']).' Si fue un error, actívela de nuevo en el filtro «Ver solo afiliaciones eliminadas» → «Restaurar».')
                    ->success()
                    ->persistent()
                    ->send();
            });
    }

    /**
     * Versión masiva y segura de la eliminación: reemplaza el viejo «Eliminar registro(s)», que
     * borraba sin revisar nada. Cada afiliación pasa por la misma revisión de seguridad y la misma
     * nota; las que no se pueden eliminar se quedan como están y el analista ve por qué.
     */
    public static function deleteBulkAction(): BulkAction
    {
        return BulkAction::make('deleteAffiliations')
            ->label('Eliminar afiliaciones')
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->modalHeading('Eliminar las afiliaciones seleccionadas')
            ->modalDescription(fn (EloquentCollection $records): HtmlString => self::bulkPreviewHtml($records))
            ->modalIcon(Heroicon::OutlinedTrash)
            ->modalIconColor('danger')
            ->modalWidth(Width::ThreeExtraLarge)
            ->modalSubmitActionLabel('Eliminar las que se pueden')
            ->closeModalByClickingAway(false)
            ->visible(fn (mixed $livewire): bool => self::userCan() && ! self::viewingDeleted($livewire))
            ->authorize(fn (): bool => self::userCan())
            ->deselectRecordsAfterCompletion()
            ->schema([
                Textarea::make('reason')
                    ->label('Nota obligatoria: ¿por qué se eliminan estas afiliaciones?')
                    ->helperText('La misma nota queda en cada afiliación eliminada, en su traza y en la auditoría. Mínimo '.AffiliationDeletionService::REASON_MIN_LENGTH.' caracteres.')
                    ->placeholder('Ejemplo: afiliaciones de prueba cargadas por error durante la capacitación del 20/10.')
                    ->required()
                    ->minLength(AffiliationDeletionService::REASON_MIN_LENGTH)
                    ->maxLength(2000)
                    ->rows(3)
                    ->validationMessages([
                        'required' => 'Escriba el motivo: es lo que verá quien revise o restaure estas eliminaciones.',
                        'min' => 'Explique un poco más el motivo (mínimo '.AffiliationDeletionService::REASON_MIN_LENGTH.' caracteres).',
                    ]),
                Checkbox::make('confirmed')
                    ->label('Revisé la lista. Entiendo que solo se eliminan las marcadas con ✓ y que puedo revertirlas desde «Ver solo afiliaciones eliminadas».')
                    ->accepted()
                    ->required()
                    ->validationMessages(['accepted' => 'Marque la confirmación para continuar.']),
            ])
            ->action(function (EloquentCollection $records, array $data): void {
                if ($records->count() > self::MAX_BULK) {
                    self::failed('No se eliminó ninguna', 'Seleccionó '.$records->count().' afiliaciones; el máximo por vez es '.self::MAX_BULK.'.');

                    return;
                }

                $deleted = [];
                $skipped = [];

                foreach ($records as $record) {
                    if (! $record instanceof Affiliation && ! $record instanceof AffiliationCorporate) {
                        continue;
                    }

                    try {
                        AffiliationDeletionService::delete($record, (string) ($data['reason'] ?? ''), self::actor());
                        $deleted[] = (string) $record->code;
                    } catch (RuntimeException $exception) {
                        $skipped[] = $record->code.': '.$exception->getMessage();
                    } catch (Throwable $exception) {
                        report($exception);
                        $skipped[] = $record->code.': error inesperado; no se cambió nada en ella.';
                    }
                }

                $body = ($deleted === [] ? 'No se eliminó ninguna.' : count($deleted).' eliminada(s): '.implode(', ', $deleted).'.')
                    .($skipped === [] ? '' : ' No se eliminaron '.count($skipped).': '.implode(' · ', $skipped))
                    .($deleted === [] ? '' : ' Se pueden restaurar desde «Ver solo afiliaciones eliminadas».');

                Notification::make()
                    ->title($deleted === [] ? 'No se eliminó ninguna afiliación' : 'Eliminación completada')
                    ->body($body)
                    ->status($skipped === [] ? 'success' : ($deleted === [] ? 'danger' : 'warning'))
                    ->persistent()
                    ->send();
            });
    }

    public static function restoreAction(): Action
    {
        return Action::make('restoreAffiliation')
            ->label('Restaurar afiliación')
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('success')
            ->modalHeading(fn (Model $record): string => 'Restaurar '.AffiliationDeletionSubject::for($record)->label())
            ->modalDescription('Antes de devolver nada, el sistema comprueba que no se mezcle con lo que pasó después. Es todo o nada.')
            ->modalIcon(Heroicon::OutlinedArrowUturnLeft)
            ->modalIconColor('success')
            ->modalWidth(Width::ThreeExtraLarge)
            ->modalSubmitActionLabel('Restaurar afiliación')
            ->closeModalByClickingAway(false)
            ->visible(fn (mixed $record): bool => self::userCan()
                && ($record instanceof Affiliation || $record instanceof AffiliationCorporate)
                && $record->trashed())
            ->authorize(fn (): bool => self::userCan())
            ->schema([
                Placeholder::make('restore')
                    ->hiddenLabel()
                    ->content(fn (Model $record): HtmlString => self::restoreHtml(AffiliationDeletionService::activeDeletionFor($record)))
                    ->columnSpanFull(),
                Textarea::make('reason')
                    ->label('Nota obligatoria: ¿por qué se restaura esta afiliación?')
                    ->placeholder('Ejemplo: se eliminó por error; la empresa confirma que la población es correcta.')
                    ->required()
                    ->minLength(AffiliationDeletionService::REASON_MIN_LENGTH)
                    ->maxLength(2000)
                    ->rows(3)
                    ->columnSpanFull()
                    ->validationMessages([
                        'required' => 'Escriba el motivo de la restauración.',
                        'min' => 'Explique un poco más el motivo (mínimo '.AffiliationDeletionService::REASON_MIN_LENGTH.' caracteres).',
                    ]),
            ])
            ->action(function (Model $record, array $data): void {
                $deletion = AffiliationDeletionService::activeDeletionFor($record);

                if ($deletion === null) {
                    self::failed('No se restauró la afiliación', 'No se encontró la traza de su eliminación. Avise a Sistemas: no se restaura sin el punto de restauración.');

                    return;
                }

                try {
                    $result = AffiliationDeletionService::restore($deletion, (string) ($data['reason'] ?? ''), self::actor());
                } catch (RuntimeException $exception) {
                    self::failed('No se restauró la afiliación', $exception->getMessage());

                    return;
                } catch (Throwable $exception) {
                    report($exception);
                    self::failed('No se restauró la afiliación', 'Ocurrió un error inesperado y el sistema deshizo todo. Intente de nuevo; si se repite, avise a Sistemas.');

                    return;
                }

                Notification::make()
                    ->title('Afiliación '.$record->code.' restaurada')
                    ->body(implode(' ', $result['lines']).($result['warnings'] !== [] ? ' Tenga en cuenta: '.implode(' ', $result['warnings']) : '').' Desactive «Ver solo afiliaciones eliminadas» para verla en la lista.')
                    ->status($result['warnings'] === [] ? 'success' : 'warning')
                    ->persistent()
                    ->send();
            });
    }

    /**
     * @return array<string, mixed>
     */
    private static function inspection(Model $record): array
    {
        static $cache = [];
        $key = $record::class.':'.$record->getKey();

        return $cache[$key] ??= AffiliationDeletionInspector::inspect(AffiliationDeletionSubject::for($record));
    }

    private static function bulkPreviewHtml(EloquentCollection $records): HtmlString
    {
        if ($records->count() > self::MAX_BULK) {
            return new HtmlString(self::banner('#dc2626', 'Demasiadas afiliaciones seleccionadas.', 'El máximo por vez es '.self::MAX_BULK.'. Seleccione menos y vuelva a intentarlo.'));
        }

        $rows = '';
        $allowed = 0;

        foreach ($records as $record) {
            if (! $record instanceof Affiliation && ! $record instanceof AffiliationCorporate) {
                continue;
            }

            $inspection = self::inspection($record);
            $label = '<strong>'.e((string) $record->code).'</strong> · '.e(AffiliationDeletionSubject::for($record)->name());

            if ($inspection['ok']) {
                $allowed++;
                $rows .= '<li><span style="color:#16a34a;font-weight:700;">✓</span> '.$label.' <span class="text-gray-500 dark:text-gray-400">— se eliminará con sus '.count($inspection['affiliates']).' afiliado(s)</span></li>';

                continue;
            }

            $first = collect($inspection['checks'])->first(fn (array $check): bool => ! $check['passed']);
            $rows .= '<li><span style="color:#dc2626;font-weight:700;">✕</span> '.$label.' <span class="text-gray-600 dark:text-gray-300">— no se eliminará: '.e(Str::lower((string) ($first['label'] ?? 'revisión'))).'. '.e((string) ($first['detail'] ?? '')).'</span></li>';
        }

        $total = $records->count();
        $banner = $allowed === 0
            ? self::banner('#dc2626', 'Ninguna de las '.$total.' se puede eliminar.', 'Cada una dice por qué. Las afiliaciones con historia real se dan de baja, no se eliminan.')
            : self::banner('#2563eb', $allowed.' de '.$total.' se pueden eliminar.', 'Las marcadas con ✕ se quedan como están. Nada se borra de la base de datos y todo se puede revertir. Para ver el detalle de una sola, use «Eliminar afiliación» en su fila.');

        return new HtmlString('<div class="space-y-3 text-sm">'.$banner.'<ul style="display:flex;flex-direction:column;gap:6px;">'.$rows.'</ul></div>');
    }

    /**
     * @param  array<string, mixed>  $inspection
     */
    private static function checksHtml(array $inspection): HtmlString
    {
        $items = '';

        foreach ((array) $inspection['checks'] as $check) {
            $color = $check['passed'] ? '#16a34a' : '#dc2626';
            $icon = $check['passed'] ? '✓' : '✕';
            $lines = '';

            foreach (array_slice((array) $check['lines'], 0, AffiliationDeletionInspector::SHOWN_ITEMS) as $line) {
                $lines .= '<li>'.e($line).'</li>';
            }

            $more = count((array) $check['lines']) - AffiliationDeletionInspector::SHOWN_ITEMS;

            if ($more > 0) {
                $lines .= '<li>y '.$more.' más.</li>';
            }

            $items .= '<li style="display:flex;gap:10px;align-items:flex-start;">'
                .'<span style="flex:none;display:inline-flex;align-items:center;justify-content:center;width:22px;height:22px;border-radius:9999px;background:'.$color.';color:#fff;font-weight:700;font-size:.8rem;">'.$icon.'</span>'
                .'<div><p class="font-semibold text-gray-900 dark:text-white">'.e($check['label']).'</p>'
                .'<p class="text-gray-600 dark:text-gray-300">'.e($check['detail']).'</p>'
                .($lines !== '' ? '<ul class="list-disc ps-5 text-gray-600 dark:text-gray-300">'.$lines.'</ul>' : '')
                .'</div></li>';
        }

        $banner = $inspection['ok']
            ? self::banner('#16a34a', 'Es seguro eliminarla.', 'Pase al siguiente paso para ver exactamente lo que va a pasar. Todavía no se ha cambiado nada.')
            : self::banner('#dc2626', 'Todavía no se puede eliminar.', 'Resuelva los puntos marcados en rojo y vuelva a intentarlo. Si la afiliación tiene historia real, use «Dar de baja» en lugar de eliminar.');

        return new HtmlString('<div class="space-y-4 text-sm">'.$banner.'<ul style="display:flex;flex-direction:column;gap:12px;">'.$items.'</ul></div>');
    }

    /**
     * @param  array<string, mixed>  $inspection
     */
    private static function effectsHtml(AffiliationDeletionSubject $subject, array $inspection): HtmlString
    {
        $affiliates = (array) ($inspection['affiliates'] ?? []);
        $collections = (array) ($inspection['collections'] ?? []);
        $patients = (array) ($inspection['patients'] ?? []);
        $sections = [];

        $names = array_map(static fn (array $affiliate): string => e($affiliate['name']).($affiliate['document'] !== '' ? ' <span class="text-gray-500">('.e($affiliate['document']).')</span>' : ''), array_slice($affiliates, 0, AffiliationDeletionInspector::SHOWN_ITEMS));
        $sections[] = self::section(
            'La afiliación y sus '.count($affiliates).' afiliado(s) dejan de verse',
            'Desaparecen de las listas, búsquedas, reportes, carnets y del detector de personas duplicadas. Nada se borra de la base de datos.',
            $names,
            count($affiliates) - AffiliationDeletionInspector::SHOWN_ITEMS,
        );

        if ($collections !== []) {
            $sections[] = self::section(
                count($collections).' aviso(s) de cobro por pagar se anulan',
                'Así no quedan cuotas cobrándose de una afiliación que no existe. Si restaura, vuelven a «por pagar».',
                array_map(static fn (array $collection): string => e($collection['invoice']).' · vence '.e($collection['due'] ?: '—').' · $'.number_format((float) $collection['amount'], 2), array_slice($collections, 0, AffiliationDeletionInspector::SHOWN_ITEMS)),
                count($collections) - AffiliationDeletionInspector::SHOWN_ITEMS,
            );
        }

        foreach ($patients as $patient) {
            $lines = [];

            foreach ((array) $patient['hide_cases'] as $case) {
                $lines[] = 'Se oculta el caso cerrado '.e($case['code']).' ('.e(mb_strtolower($case['status'])).', '.e($case['created']).'). Sus cupos se devuelven.';
            }

            foreach ((array) $patient['kept_cases'] as $case) {
                $lines[] = 'Se conserva el caso '.e($case['code']).': '.e($case['why']).'.';
            }

            $sections[] = self::section(
                $patient['action'] === 'reubicar'
                    ? 'El paciente '.$patient['name'].' pasa a '.($patient['other']['parent_code'] ?? 'su afiliación vigente')
                    : 'El paciente '.$patient['name'].' se desvincula de la afiliación',
                $patient['action'] === 'reubicar'
                    ? 'La misma persona sigue vigente como '.mb_strtolower((string) ($patient['other']['where'] ?? '')).'. Telemedicina la seguirá atendiendo con ese plan.'
                    : 'Su ficha e historia clínica se conservan en Telemedicina, pero queda sin plan: no podrá recibir servicios cubiertos hasta que se le asocie otra afiliación.',
                $lines,
                0,
            );
        }

        if ($patients === []) {
            $sections[] = self::section('Telemedicina no cambia', 'Ningún afiliado tiene paciente de telemedicina asociado a esta afiliación.', [], 0);
        }

        $banner = self::banner('#2563eb', 'Todo esto se puede revertir.', 'Al eliminar, el sistema guarda una foto exacta de lo que cambia. Desde «Ver solo afiliaciones eliminadas» → «Restaurar afiliación» vuelve todo a como estaba.');

        return new HtmlString('<div class="space-y-4 text-sm">'.implode('', $sections).$banner.'</div>');
    }

    private static function restoreHtml(?AffiliationDeletion $deletion): HtmlString
    {
        if ($deletion === null) {
            return new HtmlString(self::banner('#dc2626', 'No se encontró la traza de esta eliminación.', 'Sin el punto de restauración no se puede restaurar con seguridad. Avise a Sistemas.'));
        }

        $inspection = AffiliationDeletionService::inspectRestore($deletion);
        $who = '<p class="text-gray-600 dark:text-gray-300">Eliminada el <strong>'.e($deletion->deleted_at_moment?->format('d/m/Y H:i') ?? '—').'</strong> por <strong>'.e($deletion->deleted_by_name ?? '—').'</strong>. Motivo: «'.e($deletion->reason).'»</p>';

        $banner = $inspection['ok']
            ? self::banner('#16a34a', 'Se puede restaurar.', 'Esto es lo que vuelve:')
            : self::banner('#dc2626', 'Todavía no se puede restaurar.', 'Resuelva lo siguiente y vuelva a intentarlo:');

        $items = $inspection['ok'] ? $inspection['lines'] : $inspection['blockers'];
        $list = '<ul class="list-disc ps-5 text-gray-700 dark:text-gray-200">'.implode('', array_map(static fn (string $line): string => '<li>'.e($line).'</li>', $items)).'</ul>';
        $warnings = $inspection['warnings'] === []
            ? ''
            : self::banner('#d97706', 'Tenga en cuenta', implode(' ', $inspection['warnings']));

        return new HtmlString('<div class="space-y-3 text-sm">'.$who.$banner.$list.$warnings.'</div>');
    }

    /**
     * @param  list<string>  $htmlLines  ya escapadas
     */
    private static function section(string $title, string $text, array $htmlLines, int $more): string
    {
        $list = $htmlLines === [] ? '' : '<ul class="list-disc ps-5 text-gray-600 dark:text-gray-300">'.implode('', array_map(static fn (string $line): string => '<li>'.$line.'</li>', $htmlLines)).($more > 0 ? '<li>y '.$more.' más.</li>' : '').'</ul>';

        return '<div style="border:1px solid rgba(127,127,127,.25);border-radius:12px;padding:12px 14px;">'
            .'<p class="font-semibold text-gray-900 dark:text-white">'.e($title).'</p>'
            .'<p class="text-gray-600 dark:text-gray-300">'.e($text).'</p>'
            .$list
            .'</div>';
    }

    private static function banner(string $color, string $title, string $text): string
    {
        return '<div style="border-left:4px solid '.$color.';background:'.$color.'1a;border-radius:10px;padding:10px 14px;">'
            .'<p style="color:'.$color.';font-weight:700;">'.e($title).'</p>'
            .'<p class="text-gray-700 dark:text-gray-200">'.e($text).'</p>'
            .'</div>';
    }

    private static function failed(string $title, string $body): void
    {
        Notification::make()->title($title)->body($body.' No se cambió ningún dato.')->danger()->persistent()->send();
    }

    private static function actor(): User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : throw new RuntimeException('Su sesión expiró. Vuelva a iniciar sesión.');
    }
}
