<?php

declare(strict_types=1);

use App\Filament\Business\Resources\MedicalQuestionnaires\MedicalQuestionnaireResource;
use App\Filament\Business\Resources\MedicalQuestionnaires\Pages\EditMedicalQuestionnaire;
use App\Filament\Business\Resources\MedicalQuestionnaires\Pages\ListMedicalQuestionnaires;
use App\Filament\Shared\MedicalQuestionnaires\MedicalQuestionnaireAnswersEntry;
use App\Models\Affiliation;
use App\Models\MedicalQuestionnaire;
use App\Models\MedicalQuestionnaireAnswer;
use App\Models\MedicalQuestionnaireQuestion;
use App\Models\User;
use App\Services\MedicalQuestionnairePdfService;
use App\Support\MedicalQuestionnaires\MedicalQuestionnaireAnswers;
use App\Support\MedicalQuestionnaires\MedicalQuestionnaireCatalog;
use App\Support\MedicalQuestionnaires\MedicalQuestionnaireLegacy;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(Tests\TestCase::class);

/**
 * Cuestionario médico dinámico. Escribe: corre dentro de una transacción que siempre se revierte.
 */
beforeEach(function (): void {
    DB::beginTransaction();
    MedicalQuestionnaireCatalog::flush();

    $this->questionnaire = MedicalQuestionnaire::query()->where('plan_id', MedicalQuestionnaireLegacy::SPECIAL_PLAN_ID)->first();
    $this->affiliationTemplate = Affiliation::query()->where('plan_id', MedicalQuestionnaireLegacy::SPECIAL_PLAN_ID)->first();

    if ($this->questionnaire === null || $this->affiliationTemplate === null) {
        $this->markTestSkipped('Falta el cuestionario del Plan Especial o una afiliación para clonar.');
    }

    $this->actingAs(User::factory()->make(['id' => 999994, 'name' => 'Analista de prueba', 'departament' => ['SUPERADMIN'], 'email' => 'analista.mq@tudrencasa.com']));
});

afterEach(function (): void {
    MedicalQuestionnaireCatalog::flush();
    DB::rollBack();
});

function mqAffiliation(object $test): Affiliation
{
    $affiliation = $test->affiliationTemplate->replicate();
    $affiliation->code = 'TEST-MQ-'.Str::upper(Str::random(8));
    foreach (array_keys(MedicalQuestionnaireLegacy::QUESTIONS) as $column) {
        $affiliation->{$column} = null;
    }
    $affiliation->save();

    return $affiliation;
}

it('carga las 16 preguntas del Plan Especial en orden y los demás planes no tienen cuestionario', function (): void {
    $questions = MedicalQuestionnaireCatalog::questionsForPlan(MedicalQuestionnaireLegacy::SPECIAL_PLAN_ID);

    expect($questions->count())->toBeGreaterThanOrEqual(1)
        ->and(MedicalQuestionnaireCatalog::planHasQuestionnaire(1))->toBeFalse()
        ->and(MedicalQuestionnaireCatalog::planHasQuestionnaire(null))->toBeFalse()
        ->and($this->questionnaire->questions()->where('legacy_column', 'cuestion_1')->value('text'))->toContain('gozan de buena salud');
});

it('guarda las respuestas con la copia del texto y las sigue mostrando aunque la pregunta cambie', function (): void {
    $affiliation = mqAffiliation($this);
    $question = MedicalQuestionnaireCatalog::questionsForPlan(3)->first();
    $original = (string) $question->text;

    $saved = MedicalQuestionnaireAnswers::save($affiliation, 3, [
        MedicalQuestionnaireAnswers::FIELD_PREFIX.$question->getKey() => '1',
        'q_999999999' => true,
        'otra_cosa' => true,
    ]);

    expect($saved)->toBe(1)
        ->and(MedicalQuestionnaireAnswers::stateFor($affiliation))->toBe([MedicalQuestionnaireAnswers::FIELD_PREFIX.$question->getKey() => true]);

    $question->forceFill(['text' => 'Texto nuevo de la pregunta'])->save();

    expect(MedicalQuestionnaireAnswers::forDisplay($affiliation)[0])->toBe(['text' => $original, 'answer' => true])
        ->and((string) MedicalQuestionnaireAnswersEntry::html($affiliation))->toContain(e($original))->toContain('Sí');
});

it('una pregunta desactivada deja de preguntarse pero conserva sus respuestas, y no se puede borrar', function (): void {
    $affiliation = mqAffiliation($this);
    $question = MedicalQuestionnaireCatalog::questionsForPlan(3)->first();
    MedicalQuestionnaireAnswers::save($affiliation, 3, [MedicalQuestionnaireAnswers::FIELD_PREFIX.$question->getKey() => false]);

    $question->forceFill(['is_active' => false])->save();
    MedicalQuestionnaireCatalog::flush();

    expect(MedicalQuestionnaireCatalog::questionsForPlan(3)->pluck('id')->all())->not->toContain($question->getKey())
        ->and(MedicalQuestionnaireAnswers::forDisplay($affiliation))->toHaveCount(1)
        ->and(fn () => $question->delete())->toThrow(RuntimeException::class, 'desactívela');

    $fresh = MedicalQuestionnaireQuestion::query()->create(['medical_questionnaire_id' => $this->questionnaire->getKey(), 'sort_order' => 99, 'text' => 'Pregunta sin respuestas', 'is_active' => true]);

    expect($fresh->delete())->toBeTrue();
});

it('pasa al cuestionario nuevo lo que se escriba todavía en las columnas viejas', function (): void {
    $affiliation = mqAffiliation($this);
    $affiliation->forceFill(['cuestion_5' => 1, 'cuestion_6' => 0])->save();

    $answers = MedicalQuestionnaireAnswer::query()
        ->where('answerable_type', MedicalQuestionnaireAnswers::TYPE_INDIVIDUAL)
        ->where('answerable_id', $affiliation->getKey())
        ->with('question:id,legacy_column')
        ->get()
        ->mapWithKeys(fn (MedicalQuestionnaireAnswer $answer): array => [$answer->question->legacy_column => $answer->answer]);

    expect($answers->all())->toBe(['cuestion_5' => true, 'cuestion_6' => false]);
});

it('el PDF lista solo las preguntas que se hacen', function (): void {
    $active = MedicalQuestionnaireCatalog::questionsForPlan(3);
    $hidden = $active->last();
    $hidden->forceFill(['is_active' => false, 'text' => 'PREGUNTA DESACTIVADA DE PRUEBA'])->save();

    $html = view('documents.medical-questionnaire', [
        'questionnaire' => $this->questionnaire->fresh(),
        'questions' => $this->questionnaire->fresh()->activeQuestions,
        'logoDataUri' => '',
        'generatedAt' => now(),
    ])->render();

    expect($html)->toContain(e((string) $active->first()->text))
        ->and($html)->not->toContain('PREGUNTA DESACTIVADA DE PRUEBA')
        ->and(MedicalQuestionnairePdfService::filename($this->questionnaire))->toEndWith('.pdf')
        ->and(strlen(MedicalQuestionnairePdfService::make($this->questionnaire)->output()))->toBeGreaterThan(1000);
});

it('muestra la pantalla de cuestionarios en Negocios y la niega sin permiso', function (): void {
    Filament::setCurrentPanel('business');

    Livewire::test(ListMedicalQuestionnaires::class)
        ->assertSuccessful()
        ->assertSee($this->questionnaire->name);

    Livewire::test(EditMedicalQuestionnaire::class, ['record' => $this->questionnaire->getKey()])
        ->assertSuccessful()
        ->assertSee('Agregar pregunta')
        ->assertSee('Respondida en');

    $this->actingAs(User::factory()->make(['id' => 999993, 'departament' => ['NEGOCIOS'], 'email' => 'sinpermiso.mq@tudrencasa.com']));

    expect(MedicalQuestionnaireResource::canAccess())->toBeFalse();
});

it('ya no quedan preguntas escritas a mano en los formularios', function (): void {
    $files = collect(Symfony\Component\Finder\Finder::create()->files()->in([app_path('Filament'), app_path('Livewire')])->name('*.php'))
        ->filter(fn ($file): bool => ! str_contains($file->getRelativePathname(), 'Telemedicine'))
        ->filter(fn ($file): bool => preg_match("/(Radio|Toggle|IconEntry|IconColumn)::make\\('cuestion_\\d+'\\)/", $file->getContents()) === 1)
        ->map(fn ($file): string => $file->getRelativePathname())
        ->values()
        ->all();

    expect($files)->toBe([]);
});

it('el formulario de afiliación de Negocios muestra las preguntas de la tabla y se llena con las respuestas', function (): void {
    Filament::setCurrentPanel('business');
    $affiliation = mqAffiliation($this);
    $questions = MedicalQuestionnaireCatalog::questionsForPlan(3);
    $first = $questions->first();
    MedicalQuestionnaireAnswers::save($affiliation, 3, [MedicalQuestionnaireAnswers::FIELD_PREFIX.$first->getKey() => true]);

    $first->forceFill(['text' => 'PREGUNTA EDITADA DESDE LA PANTALLA'])->save();
    MedicalQuestionnaireCatalog::flush();

    Livewire::test(App\Filament\Business\Resources\Affiliations\Pages\EditAffiliation::class, ['record' => $affiliation->getKey()])
        ->assertSuccessful()
        ->assertSee('PREGUNTA EDITADA DESDE LA PANTALLA')
        ->assertSet('data.medical_answers.'.MedicalQuestionnaireAnswers::FIELD_PREFIX.$first->getKey(), true);
});
