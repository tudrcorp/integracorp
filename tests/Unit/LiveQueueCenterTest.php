<?php

declare(strict_types=1);

use App\Enums\SystemNotificationKey;
use App\Filament\Business\Pages\LiveQueueCenter;
use App\Jobs\NotifyLiveSecurityAlertJob;
use App\Models\FailedJob;
use App\Models\User;
use App\Support\LivePresence\ErrorTracker;
use App\Support\LivePresence\ExceptionFingerprint;
use App\Support\LivePresence\FailedJobActions;
use App\Support\LivePresence\FailedJobCatalog;
use App\Support\LivePresence\FailureDiagnosis;
use App\Support\LivePresence\LiveActivitySnapshot;
use App\Support\LivePresence\LivePresenceStore;
use App\Support\LivePresence\OperationsAdvisor;
use App\Support\LivePresence\QueueActivityRecorder;
use App\Support\LivePresence\QueueHealth;
use App\Support\LivePresence\QueueJobActions;
use App\Support\LivePresence\RedisQueueJobStore;
use App\Support\LivePresence\SystemHealthWatcher;
use Filament\Facades\Filament;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Queue\Events\Looping;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(Tests\TestCase::class);

/**
 * Todo en memoria: caché `array` y una sqlite `:memory:` propia con `jobs` y
 * `failed_jobs`. Ninguna prueba toca la base de desarrollo ni las colas reales.
 */
beforeEach(function (): void {
    config()->set('database.connections.lqc_testing', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => false]);
    $this->previousConnection = config('database.default');
    config()->set('database.default', 'lqc_testing');
    DB::purge('lqc_testing');
    DB::setDefaultConnection('lqc_testing');

    expect(DB::connection()->getDriverName())->toBe('sqlite');

    Schema::create('failed_jobs', function (Blueprint $table): void {
        $table->id();
        $table->string('uuid')->unique();
        $table->text('connection');
        $table->text('queue');
        $table->longText('payload');
        $table->longText('exception');
        $table->timestamp('failed_at')->useCurrent();
    });
    Schema::create('jobs', function (Blueprint $table): void {
        $table->id();
        $table->string('queue')->index();
        $table->longText('payload');
        $table->unsignedTinyInteger('attempts');
        $table->unsignedInteger('reserved_at')->nullable();
        $table->unsignedInteger('available_at');
        $table->unsignedInteger('created_at');
    });
    Schema::create('logs', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('user_id')->nullable();
        $table->string('action')->nullable();
        $table->string('route')->nullable();
        $table->text('response')->nullable();
        $table->string('method')->nullable();
        $table->string('ip')->nullable();
        $table->text('user_agent')->nullable();
        $table->timestamps();
    });

    config([
        'live-presence.enabled' => true,
        'live-presence.store' => 'cache',
        'live-presence.cache_store' => 'array',
        'live-presence.allowed_emails' => ['gcamacho@tudrencasa.com'],
        'live-presence.queues.names' => ['lqc-renovations', 'lqc-system'],
        'live-presence.queues.stuck_after_minutes' => 30,
        'queue.default' => 'database',
        'queue.connections.database.connection' => 'lqc_testing',
        'queue.connections.database.retry_after' => 180,
        'queue.failed.driver' => 'database-uuids',
        'queue.failed.database' => 'lqc_testing',
        'queue.failed.table' => 'failed_jobs',
        'session.driver' => 'array',
        'cache.default' => 'array',
    ]);
    Cache::store('array')->flush();
    LivePresenceStore::swap(null);
    QueueActivityRecorder::reset();
    QueueJobActions::swapStore(null, false);
});

afterEach(function (): void {
    LivePresenceStore::swap(null);
    QueueActivityRecorder::reset();
    QueueJobActions::swapStore(null, false);
    DB::purge('lqc_testing');
    config()->set('database.default', $this->previousConnection);
    DB::setDefaultConnection($this->previousConnection);
});

/**
 * Texto de excepción igual al que Laravel guarda en failed_jobs en producción.
 */
function lqcException(string $head, string $appFile = 'app/Jobs/SendNotificacionWhatsApp.php', int $appLine = 98): string
{
    $base = '/var/www/integracorp.tudrgroup.com/integracorp/';

    return $head."\nStack trace:\n"
        .'#0 '.$base.'vendor/laravel/framework/src/Illuminate/Http/Client/PendingRequest.php(910): Illuminate\Http\Client\Response->throw()'."\n"
        .'#1 '.$base.$appFile.'('.$appLine.'): Illuminate\Http\Client\PendingRequest->post()'."\n"
        .'#2 [internal function]: App\Jobs\SendNotificacionWhatsApp->handle()'."\n"
        .'#3 {main}';
}

function lqcFailed(string $job, string $exception, ?string $failedAt = null, string $queue = 'default', ?string $uuid = null, string $command = 'O:8:"stdClass":0:{}'): string
{
    $uuid ??= (string) Str::uuid();

    DB::table('failed_jobs')->insert([
        'uuid' => $uuid,
        'connection' => 'database',
        'queue' => $queue,
        'payload' => json_encode(['uuid' => $uuid, 'displayName' => $job, 'job' => 'Illuminate\\Queue\\CallQueuedHandler@call', 'maxTries' => 3, 'timeout' => 120, 'data' => ['commandName' => $job, 'command' => $command]]),
        'exception' => $exception,
        'failed_at' => $failedAt ?? now()->toDateTimeString(),
    ]);

    return $uuid;
}

function lqcAdmin(): User
{
    $user = User::factory()->make(['id' => 2, 'name' => 'Gustavo', 'email' => 'gcamacho@tudrencasa.com', 'status' => 'ACTIVO']);
    $user->setRawAttributes([...$user->getAttributes(), 'departament' => json_encode(['SUPERADMIN'])], true);

    return $user;
}

describe('lectura de excepciones', function (): void {
    it('encuentra la línea de nuestro código aunque el error se lance en vendor', function (): void {
        $parsed = ExceptionFingerprint::fromReport(lqcException('Illuminate\Http\Client\RequestException: HTTP request returned status code 500 in /var/www/integracorp.tudrgroup.com/integracorp/vendor/laravel/framework/src/Illuminate/Http/Client/Response.php:337'));

        expect($parsed['short_class'])->toBe('RequestException')
            ->and($parsed['message'])->toBe('HTTP request returned status code 500')
            ->and($parsed['location'])->toBe('vendor/laravel/framework/src/Illuminate/Http/Client/Response.php:337')
            ->and($parsed['origin'])->toBe('app/Jobs/SendNotificacionWhatsApp.php:98')
            ->and($parsed['app_frames'])->toHaveCount(1)
            ->and($parsed['frames'])->toHaveCount(3);
    });

    it('lee mensajes de varias líneas, como el rechazo de Gmail', function (): void {
        $parsed = ExceptionFingerprint::fromReport("Symfony\\Component\\Mailer\\Exception\\UnexpectedResponseException: Expected response code \"354\" but got code \"550\", with message \"550-5.4.5 Daily user sending limit exceeded.\r\n550 5.4.5 More info\". in /srv/app/vendor/symfony/mailer/Transport/Smtp/SmtpTransport.php:342\nStack trace:\n#0 {main}");

        expect($parsed['short_class'])->toBe('UnexpectedResponseException')
            ->and($parsed['message'])->toContain('Daily user sending limit exceeded')
            ->and($parsed['location'])->toBe('vendor/symfony/mailer/Transport/Smtp/SmtpTransport.php:342');
    });

    it('agrupa el mismo error con IDs distintos, pero no mezcla códigos HTTP', function (): void {
        $a = ExceptionFingerprint::fingerprint('Job', 'Exception', 'No se encontró la afiliación 123456 para ana@correo.com', 'app/X.php:1');
        $b = ExceptionFingerprint::fingerprint('Job', 'Exception', 'No se encontró la afiliación 987654 para luis@correo.com', 'app/X.php:1');
        $http429 = ExceptionFingerprint::fingerprint('Job', 'Exception', 'ViVEplus respondió HTTP 429', 'app/X.php:1');
        $http500 = ExceptionFingerprint::fingerprint('Job', 'Exception', 'ViVEplus respondió HTTP 500', 'app/X.php:1');

        expect($a)->toBe($b)
            ->and($http429)->not->toBe($http500);
    });

    it('agrupa los rechazos de Gmail y los adjuntos faltantes aunque cada uno traiga su propio identificador', function (): void {
        $gmail = static fn (string $token): string => ExceptionFingerprint::normalizeMessage('Expected response code "354" but got code "550", with message "550-5.4.5 Daily user sending limit exceeded. 550 5.4.5  https://support.google.com/a/answer/166852 '.$token.' - gsmtp".');
        $attachment = static fn (string $file): string => ExceptionFingerprint::normalizeMessage('Unable to open path "/var/www/integracorp.tudrgroup.com/integracorp/public/storage/'.$file.'".');

        expect($gmail('d9443c01a7336-2db14ae8af3sm61042635ad.80'))->toBe($gmail('d9443c01a7336-2db14ae8af3sm60993585ad.80'))
            ->and($gmail('x'))->toContain('Daily user sending limit exceeded')
            ->and($attachment('CER-TDEC-IND-000244.pdf'))->toBe($attachment('CER-TDEC-IND-000396.pdf'))
            ->and($attachment('tarjeta-afiliacion/TAR-TDEC-IND-000405.pdf'))->not->toBe($attachment('CER-TDEC-IND-000396.pdf'))
            ->and(ExceptionFingerprint::normalizeMessage('Class "App\\Jobs\\SendNotificacionWhatsApp" not found'))->toContain('SendNotificacionWhatsApp');
    });

    it('una vista Blade compilada cuenta como nuestro código', function (): void {
        $parsed = ExceptionFingerprint::fromReport('ErrorException: Attempt to read property "price" on null in /var/www/integracorp.tudrgroup.com/integracorp/storage/framework/views/c133b4eccfd21c017cf9eead61c45fc9.php:332'."\nStack trace:\n#0 {main}");

        expect($parsed['origin'])->toBe('storage/framework/views/c133b4eccfd21c017cf9eead61c45fc9.php:332');
    });
});

describe('diagnóstico', function (): void {
    it('traduce los errores reales a causa y acción', function (string $class, string $message, string $category, string $action): void {
        $diagnosis = FailureDiagnosis::diagnose($class, $message);

        expect($diagnosis['category'])->toBe($category)
            ->and($diagnosis['action'])->toBe($action)
            ->and($diagnosis['title'])->not->toBe('');
    })->with([
        'UltraMsg sin pago' => ['Exception', 'CURL Error (404): {"error":"Your instance has been Stopped due to non-payment. you can activate this instance by extending your subscription."}', FailureDiagnosis::CATEGORY_PROVIDER, FailureDiagnosis::ACTION_RETRY],
        'leyenda larga' => ['Exception', 'UltraMsg image send failed: [{"caption":"max length limit exceeded of 1024"}]', FailureDiagnosis::CATEGORY_CODE, FailureDiagnosis::ACTION_FIX],
        'ViVEplus 429' => ['App\Exceptions\ViveplusDocumentWebhookPermanentException', 'ViVEplus respondió HTTP 429 al entregar el documento.', FailureDiagnosis::CATEGORY_PROVIDER, FailureDiagnosis::ACTION_RETRY],
        'ViVEplus 500' => ['App\Exceptions\ViveplusDocumentWebhookTransientException', 'ViVEplus respondió HTTP 500 al entregar el documento.', FailureDiagnosis::CATEGORY_PROVIDER, FailureDiagnosis::ACTION_RETRY],
        'Pusher' => ['Error', 'Class "Pusher\Pusher" not found', FailureDiagnosis::CATEGORY_CONFIG, FailureDiagnosis::ACTION_CONFIG],
        'Gmail sin cupo' => ['Symfony\Component\Mailer\Exception\UnexpectedResponseException', 'Expected response code "354" but got code "550", with message "550-5.4.5 Daily user sending limit exceeded.', FailureDiagnosis::CATEGORY_PROVIDER, FailureDiagnosis::ACTION_RETRY],
        'registro borrado' => ['Illuminate\Database\Eloquent\ModelNotFoundException', 'No query results for model [App\Models\Affiliation].', FailureDiagnosis::CATEGORY_DATA, FailureDiagnosis::ACTION_DELETE],
        'bug' => ['TypeError', 'App\Support\X::y(): Argument #1 ($a) must be of type string, null given', FailureDiagnosis::CATEGORY_CODE, FailureDiagnosis::ACTION_FIX],
        'adjunto que no existe' => ['Symfony\Component\Mime\Exception\InvalidArgumentException', 'Unable to open path "/var/www/x/public/storage/CER-TDEC-IND-000244.pdf".', FailureDiagnosis::CATEGORY_DATA, FailureDiagnosis::ACTION_FIX],
        'trabajo de otra aplicación' => ['Error', 'The script tried to call a method on an incomplete object. Please ensure that the class LiveQueueCenterTest "App\Mail\SendMailKitBienvenida" of the object you are trying to operate on was loaded _before_ unserialize() gets called', FailureDiagnosis::CATEGORY_CONFIG, FailureDiagnosis::ACTION_DELETE],
        'desconocido' => ['RuntimeException', 'Algo raro', FailureDiagnosis::CATEGORY_UNKNOWN, FailureDiagnosis::ACTION_REVIEW],
    ]);

    it('recomienda eliminar un error de código que no se repite hace días, pero no uno del proveedor', function (): void {
        $bug = FailureDiagnosis::diagnose('TypeError', 'must be of type string, null given');
        $provider = FailureDiagnosis::diagnose('Exception', 'HTTP 429');

        expect(FailureDiagnosis::forStaleGroup($bug, 10, 7)['action'])->toBe(FailureDiagnosis::ACTION_DELETE)
            ->and(FailureDiagnosis::forStaleGroup($bug, 10, 7)['advice'])->toContain('10 días')
            ->and(FailureDiagnosis::forStaleGroup($bug, 2, 7)['action'])->toBe(FailureDiagnosis::ACTION_FIX)
            ->and(FailureDiagnosis::forStaleGroup($provider, 30, 7)['action'])->toBe(FailureDiagnosis::ACTION_RETRY)
            ->and(FailureDiagnosis::forStaleGroup($provider, 30, 7, sendsMessages: true)['action'])->toBe(FailureDiagnosis::ACTION_DELETE)
            ->and(FailureDiagnosis::forStaleGroup($provider, 30, 7, sendsMessages: true)['advice'])->toContain('mensajes viejos')
            ->and(FailureDiagnosis::forStaleGroup($provider, 2, 7, sendsMessages: true)['action'])->toBe(FailureDiagnosis::ACTION_RETRY);
    });
});

describe('trabajos fallidos', function (): void {
    it('agrupa por causa, ordena por cantidad y abre el detalle sin deserializar', function (): void {
        $command = 'O:32:"App\Jobs\SendNotificacionWhatsApp":1:{s:11:"affiliation";O:45:"Illuminate\Contracts\Database\ModelIdentifier":5:{s:5:"class";s:22:"App\Models\Affiliation";s:2:"id";i:4521;s:9:"relations";a:0:{}s:10:"connection";s:5:"mysql";s:15:"collectionClass";N;}}';

        foreach (range(1, 3) as $i) {
            lqcFailed('App\Jobs\SendNotificacionWhatsApp', lqcException('Exception: CURL Error (404): {"error":"Your instance has been Stopped due to non-payment."} in /var/www/integracorp.tudrgroup.com/integracorp/app/Jobs/SendNotificacionWhatsApp.php:88'), now()->subMinutes($i)->toDateTimeString(), command: $command);
        }

        $viveplus = lqcFailed('App\Jobs\PushAffiliationDocumentToViveplusJob', lqcException('App\Exceptions\ViveplusDocumentWebhookTransientException: ViVEplus respondió HTTP 500 al entregar el documento. in /var/www/x/integracorp/app/Support/Viveplus/ViveplusDocumentWebhookClient.php:87'), queue: 'documents');

        $groups = FailedJobCatalog::groups();

        expect($groups)->toHaveCount(2)
            ->and($groups[0])->toMatchArray(['job' => 'SendNotificacionWhatsApp', 'count' => 3, 'sends_messages' => true])
            ->and($groups[0]['diagnosis']['title'])->toContain('falta de pago')
            ->and($groups[1]['queues'])->toBe(['documents'])
            ->and(FailedJobCatalog::uuidsForGroup($groups[0]['fingerprint']))->toHaveCount(3);

        $detail = FailedJobCatalog::detail($groups[0]['last_uuid']);

        expect($detail['models'])->toBe([['class' => 'App\Models\Affiliation', 'id' => '4521', 'label' => 'Affiliation #4521']])
            ->and($detail['exception']['origin'])->toBe('app/Jobs/SendNotificacionWhatsApp.php:88')
            ->and($detail['fingerprint'])->toBe($groups[0]['fingerprint'])
            ->and($detail['copy_text'])->toContain('Origen en nuestro código: app/Jobs/SendNotificacionWhatsApp.php:88')
            ->and($detail['copy_text'])->toContain('Affiliation #4521')
            ->and(FailedJobCatalog::detail($viveplus)['diagnosis']['action'])->toBe(FailureDiagnosis::ACTION_RETRY)
            ->and(FailedJobCatalog::detail('no-existe'))->toBeNull();
    });

    it('elimina por grupo, por antigüedad, por selección y todo, y lo audita', function (): void {
        foreach (range(1, 3) as $i) {
            lqcFailed('App\Jobs\Obsoleto', lqcException('Exception: Leyenda de más de 1024 in /srv/app/Jobs/Obsoleto.php:10'));
        }

        $old = lqcFailed('App\Jobs\Viejo', lqcException('Exception: Algo viejo in /srv/app/Jobs/Viejo.php:5'), now()->subDays(40)->toDateTimeString());
        $keep = lqcFailed('App\Jobs\Nuevo', lqcException('Exception: Algo nuevo in /srv/app/Jobs/Nuevo.php:5'));
        $other = lqcFailed('App\Jobs\Nuevo', lqcException('Exception: Otro in /srv/app/Jobs/Nuevo.php:9'));

        $group = collect(FailedJobCatalog::groups())->firstWhere('job', 'Obsoleto');

        expect(FailedJobActions::deleteGroup($group['fingerprint']))->toBe(3)
            ->and(FailedJobActions::deleteOlderThan(30))->toBe(1)
            ->and(FailedJob::query()->where('uuid', $old)->exists())->toBeFalse()
            ->and(FailedJobActions::delete([$keep, 'uuid-invalido-!!', $keep]))->toBe(1)
            ->and(FailedJob::query()->pluck('uuid')->all())->toBe([$other])
            ->and(FailedJobActions::deleteAll())->toBe(1)
            ->and(FailedJob::query()->count())->toBe(0)
            ->and(DB::table('logs')->where('action', 'AUDIT_LIVE_QUEUE_FAILED_DELETED')->count())->toBe(4);

        expect(fn () => FailedJobActions::deleteOlderThan(0))->toThrow(InvalidArgumentException::class);
    });

    it('reintenta devolviendo el trabajo a su cola original', function (): void {
        $uuid = lqcFailed('App\Jobs\PushAffiliationDocumentToViveplusJob', lqcException('Exception: ViVEplus respondió HTTP 429 in /srv/app/Jobs/X.php:1'), queue: 'documents');

        $result = FailedJobActions::retry([$uuid]);

        expect($result)->toBe(['requested' => 1, 'retried' => 1, 'pending' => 0])
            ->and(FailedJob::query()->count())->toBe(0)
            ->and(DB::table('jobs')->where('queue', 'documents')->count())->toBe(1)
            ->and(DB::table('logs')->where('action', 'AUDIT_LIVE_QUEUE_FAILED_RETRIED')->count())->toBe(1);
    });
});

describe('colas y workers', function (): void {
    it('detecta al instante una cola con trabajo que ningún worker escucha', function (): void {
        $now = now()->getTimestamp();
        DB::table('jobs')->insert([
            ['queue' => 'lqc-renovations', 'payload' => '{"uuid":"a","displayName":"App\\\\Jobs\\\\PrepareAffiliationRenovations"}', 'attempts' => 0, 'reserved_at' => null, 'available_at' => $now - 120, 'created_at' => $now - 120],
            ['queue' => 'lqc-renovations', 'payload' => '{"uuid":"b","displayName":"App\\\\Jobs\\\\PrepareAffiliationRenovations"}', 'attempts' => 0, 'reserved_at' => null, 'available_at' => $now - 90, 'created_at' => $now - 90],
            ['queue' => 'lqc-system', 'payload' => '{"uuid":"c","displayName":"App\\\\Jobs\\\\BackupDatabase"}', 'attempts' => 1, 'reserved_at' => $now - 600, 'available_at' => $now - 700, 'created_at' => $now - 700],
        ]);

        QueueActivityRecorder::onLooping(new Looping('database', 'lqc-system,default'));

        $report = QueueHealth::measure();
        $byName = collect($report['queues'])->keyBy('name');

        expect($report['workers']['known'])->toBeTrue()
            ->and($report['workers']['alive'])->toHaveCount(1)
            ->and($report['workers']['alive'][0]['queues'])->toBe(['lqc-system', 'default'])
            ->and($report['unattended'])->toBe(['lqc-renovations'])
            ->and($report['queues'][0]['name'])->toBe('lqc-renovations')
            ->and($byName['lqc-renovations']['status'])->toBe('unattended')
            ->and($byName['lqc-renovations']['advice'])->toContain('ningún worker escucha')
            ->and($byName['lqc-renovations']['pending_by_class'])->toBe(['PrepareAffiliationRenovations' => 2])
            ->and($byName['lqc-system']['status'])->toBe('zombie')
            ->and($byName['lqc-system']['zombies'])->toBe(1)
            ->and($byName['lqc-system']['listeners'])->toBe(1)
            ->and($report['zombies'])->toBe(1);
    });

    it('sin latido previo no afirma que falten workers', function (): void {
        DB::table('jobs')->insert(['queue' => 'lqc-renovations', 'payload' => '{}', 'attempts' => 0, 'reserved_at' => null, 'available_at' => now()->getTimestamp() - 300, 'created_at' => now()->getTimestamp() - 300]);

        $report = QueueHealth::measure();

        expect($report['workers']['known'])->toBeFalse()
            ->and($report['unattended'])->toBe([])
            ->and(collect($report['queues'])->firstWhere('name', 'lqc-renovations')['status'])->toBe('busy');
    });
});

describe('errores del sistema', function (): void {
    it('agrupa el mismo error, cuenta repeticiones y deja un aviso solo la primera vez', function (): void {
        $throw = static function (int $id): void {
            try {
                throw new RuntimeException('No se encontró la cotización '.$id);
            } catch (RuntimeException $exception) {
                ErrorTracker::capture($exception);
            }
        };

        $throw(100001);
        $throw(100002);

        $groups = ErrorTracker::groups();

        expect($groups)->toHaveCount(1)
            ->and($groups[0]['count'])->toBe(2)
            ->and($groups[0]['status'])->toBe('new')
            ->and($groups[0]['origin'])->toStartWith('tests/Unit/LiveQueueCenterTest.php:')
            ->and($groups[0]['contexts'][0])->toStartWith('Consola')
            ->and(ErrorTracker::pullPendingAlerts())->toHaveCount(1)
            ->and(ErrorTracker::pullPendingAlerts())->toBe([]);

        ErrorTracker::resolve($groups[0]['fingerprint'], 'Gustavo');

        expect(ErrorTracker::groups()[0]['status'])->toBe('resolved');

        $throw(100003);

        $alerts = ErrorTracker::pullPendingAlerts();

        expect(ErrorTracker::groups()[0]['status'])->toBe('regression')
            ->and($alerts)->toHaveCount(1)
            ->and($alerts[0]['type'])->toBe('error_regression');

        ErrorTracker::forget($groups[0]['fingerprint']);

        expect(ErrorTracker::groups())->toBe([]);
    });

    it('no guarda secretos ni valores de SQL', function (): void {
        expect(ErrorTracker::sanitizeMessage('Fallo con password=Sup3rClave y token: abc123'))
            ->toBe('Fallo con password=*** y token: ***')
            ->and(ErrorTracker::sanitizeMessage("SQLSTATE[23000]: Integrity (Connection: mysql, SQL: insert into users (email, name) values ('ana@correo.com', 'Ana'))"))
            ->toBe('SQLSTATE[23000]: Integrity (Connection: mysql, SQL: insert into users (email, name) values (?, ?))');

        $request = Illuminate\Http\Request::create('/business/afiliaciones?token=secreto&page=2');

        expect(ErrorTracker::sanitizeUrl($request))->toBe('/business/afiliaciones?token=…&page=…');
    });
});

describe('vigilante y avisos', function (): void {
    it('avisa sin pasar por la cola cuando nadie atiende una cola, y no repite el aviso', function (): void {
        Bus::fake([NotifyLiveSecurityAlertJob::class]);
        DB::table('jobs')->insert(['queue' => 'lqc-renovations', 'payload' => '{}', 'attempts' => 0, 'reserved_at' => null, 'available_at' => now()->getTimestamp() - 300, 'created_at' => now()->getTimestamp() - 300]);
        QueueActivityRecorder::onLooping(new Looping('database', 'lqc-system'));

        expect(SystemHealthWatcher::run())->toBe(['detected' => 1, 'sent' => 1, 'silenced' => 0]);

        Bus::assertDispatchedSync(NotifyLiveSecurityAlertJob::class, fn (NotifyLiveSecurityAlertJob $job): bool => $job->notificationKey === SystemNotificationKey::LiveSystemAlert->value
            && $job->sendWhatsAppNow
            && $job->event['type'] === 'queue_unattended'
            && str_contains($job->event['action'], 'php artisan queue:work'));

        expect(SystemHealthWatcher::run())->toBe(['detected' => 1, 'sent' => 0, 'silenced' => 1]);
    });

    it('avisa de una ráfaga de fallidos con su causa principal', function (): void {
        Bus::fake([NotifyLiveSecurityAlertJob::class]);

        foreach (range(1, 10) as $i) {
            lqcFailed('App\Jobs\SendTelemedicineConsultationDocuments', lqcException('Symfony\Component\Mailer\Exception\UnexpectedResponseException: 550-5.4.5 Daily user sending limit exceeded. in /srv/vendor/x.php:1'));
        }

        $events = SystemHealthWatcher::failedSpike();

        expect($events)->toHaveCount(1)
            ->and($events[0]['title'])->toBe('10 trabajos fallaron en 10 minutos')
            ->and($events[0]['detail'])->toContain('límite diario');
    });

    it('el WhatsApp de colas dice qué hacer y dónde mirar', function (): void {
        $body = NotifyLiveSecurityAlertJob::whatsappBody(['channel' => 'system', 'title' => 'Nadie atiende la cola «documents»', 'detail' => '12 trabajos esperan.', 'action' => 'Reinicie el worker.', 'at' => time()]);

        expect($body)->toStartWith('⚠️ *Nadie atiende la cola «documents»*')
            ->toContain('Qué hacer: Reinicie el worker.')
            ->toContain('Colas y errores');
    });
});

it('el asesor pone lo urgente primero con un botón para resolverlo', function (): void {
    DB::table('jobs')->insert(['queue' => 'lqc-renovations', 'payload' => '{}', 'attempts' => 0, 'reserved_at' => null, 'available_at' => now()->getTimestamp() - 300, 'created_at' => now()->getTimestamp() - 300]);
    QueueActivityRecorder::onLooping(new Looping('database', 'lqc-system'));

    $advice = OperationsAdvisor::advise(['level' => 'green', 'level_label' => 'Normal', 'reasons' => [], 'offenders' => []], QueueHealth::measure(), []);

    expect($advice['lights']['queues']['level'])->toBe('red')
        ->and($advice['lights']['errors']['level'])->toBe('green')
        ->and($advice['actions'][0]['severity'])->toBe('critical')
        ->and($advice['actions'][0]['title'])->toBe('Nadie atiende la cola «lqc-renovations»')
        ->and($advice['actions'][0]['cta']['type'])->toBe('copy');
});

describe('pantalla Colas y errores', function (): void {
    beforeEach(function (): void {
        Filament::setCurrentPanel('business');
        LiveActivitySnapshot::forgetSystemHealth();
    });

    it('solo la ven los usuarios del monitor', function (): void {
        $this->actingAs(User::factory()->make(['id' => 9, 'email' => 'otro@tudrencasa.com', 'status' => 'ACTIVO']));

        expect(LiveQueueCenter::canAccess())->toBeFalse();
        Livewire::test(LiveQueueCenter::class)->assertForbidden();
    });

    it('muestra los fallidos por causa y reintenta o elimina un grupo', function (): void {
        $this->actingAs(lqcAdmin());

        foreach (range(1, 2) as $i) {
            lqcFailed('App\Jobs\SendNotificacionWhatsApp', lqcException('Exception: CURL Error (404): Stopped due to non-payment in /srv/app/Jobs/SendNotificacionWhatsApp.php:88'));
        }

        lqcFailed('App\Jobs\PushAffiliationDocumentToViveplusJob', lqcException('Exception: ViVEplus respondió HTTP 429 in /srv/app/Jobs/X.php:1'), queue: 'documents');

        $groups = FailedJobCatalog::groups();
        $whatsApp = collect($groups)->firstWhere('job', 'SendNotificacionWhatsApp');
        $viveplus = collect($groups)->firstWhere('job', 'PushAffiliationDocumentToViveplusJob');

        Livewire::test(LiveQueueCenter::class)
            ->assertOk()
            ->assertSee('Fallidos por causa')
            ->assertSee('WhatsApp (UltraMsg) suspendido por falta de pago')
            ->assertSee('Recomendado: Reintentar')
            ->mountAction('deleteGroup', ['fingerprint' => $whatsApp['fingerprint']])
            ->assertMountedActionModalSee('Eliminar 2 fallidos de SendNotificacionWhatsApp')
            ->callMountedAction()
            ->assertNotified('2 fallidos eliminados')
            ->callAction('retryGroup', arguments: ['fingerprint' => $viveplus['fingerprint']])
            ->assertNotified('1 trabajo devuelto a la cola');

        expect(FailedJob::query()->count())->toBe(0)
            ->and(DB::table('jobs')->where('queue', 'documents')->count())->toBe(1);
    });

    it('la tabla lista, filtra y elimina en bloque; vaciar todo exige escribir ELIMINAR', function (): void {
        $this->actingAs(lqcAdmin());

        $recent = lqcFailed('App\Jobs\Uno', lqcException('Exception: Uno in /srv/app/Jobs/Uno.php:1'), queue: 'system');
        $old = lqcFailed('App\Jobs\Dos', lqcException('Exception: Dos in /srv/app/Jobs/Dos.php:1'), now()->subDays(45)->toDateTimeString());
        lqcFailed('App\Jobs\Tres', lqcException('Exception: Tres in /srv/app/Jobs/Tres.php:1'));

        $records = FailedJob::query()->get();

        $component = Livewire::test(LiveQueueCenter::class)
            ->call('selectTab', 'fallidos')
            ->assertCanSeeTableRecords($records)
            ->filterTable('period', 'older')
            ->assertCanSeeTableRecords($records->where('uuid', $old))
            ->assertCanNotSeeTableRecords($records->where('uuid', $recent))
            ->resetTableFilters()
            ->callTableBulkAction('deleteSelected', $records->whereIn('uuid', [$recent, $old]))
            ->assertNotified('2 fallidos eliminados');

        expect(FailedJob::query()->count())->toBe(1);

        $component->mountAction('deleteAll')
            ->set('mountedActions.0.data.confirmation', 'eliminar')
            ->callMountedAction()
            ->assertHasActionErrors(['confirmation' => 'in']);

        expect(FailedJob::query()->count())->toBe(1);

        $component->mountAction('deleteAll')
            ->set('mountedActions.0.data.confirmation', 'ELIMINAR')
            ->callMountedAction()
            ->assertHasNoActionErrors();

        expect(FailedJob::query()->count())->toBe(0);
    });

    it('muestra los errores con su línea y permite marcarlos resueltos', function (): void {
        $this->actingAs(lqcAdmin());

        try {
            throw new LogicException('Plan sin tarifas para el rango 65+');
        } catch (LogicException $exception) {
            ErrorTracker::capture($exception);
        }

        $error = ErrorTracker::groups()[0];

        Livewire::test(LiveQueueCenter::class)
            ->call('selectTab', 'errores')
            ->assertSee('Plan sin tarifas para el rango 65+')
            ->assertSee('Nuevo')
            ->assertSee($error['origin'])
            ->mountAction('viewError', ['fingerprint' => $error['fingerprint']])
            ->assertMountedActionModalSee(['Copiar diagnóstico', 'Dónde corregir (nuestro código)', $error['origin']])
            ->callMountedAction()
            ->callAction('resolveError', arguments: ['fingerprint' => $error['fingerprint']])
            ->assertNotified('Error marcado como resuelto');

        expect(ErrorTracker::groups()[0]['status'])->toBe('resolved');
    });

    it('en Colas y workers se ve quién escucha cada cola y el comando para arrancarlo', function (): void {
        $this->actingAs(lqcAdmin());
        QueueActivityRecorder::onLooping(new Looping('database', 'lqc-renovations,lqc-system'));

        Livewire::test(LiveQueueCenter::class)
            ->call('selectTab', 'colas')
            ->assertSee('Workers vivos')
            ->assertSee('lqc-renovations')
            ->assertSee('php artisan queue:work --queue=lqc-renovations,lqc-system');
    });
});

describe('liberar colas atascadas', function (): void {
    beforeEach(function (): void {
        $now = now()->getTimestamp();
        $job = static fn (string $class, string $uuid): string => json_encode(['uuid' => $uuid, 'displayName' => $class, 'data' => ['command' => 'O:8:"stdClass":0:{}']]);

        DB::table('jobs')->insert([
            ['queue' => 'lqc-renovations', 'payload' => $job('App\Jobs\SendNotificacionWhatsApp', 'u-atascado-1'), 'attempts' => 0, 'reserved_at' => null, 'available_at' => $now - 7200, 'created_at' => $now - 7200],
            ['queue' => 'lqc-renovations', 'payload' => $job('App\Jobs\SendNotificacionWhatsApp', 'u-atascado-2'), 'attempts' => 0, 'reserved_at' => null, 'available_at' => $now - 3600, 'created_at' => $now - 3600],
            ['queue' => 'lqc-renovations', 'payload' => $job('App\Jobs\PrepareAffiliationRenovations', 'u-reciente'), 'attempts' => 0, 'reserved_at' => null, 'available_at' => $now - 60, 'created_at' => $now - 60],
            ['queue' => 'lqc-renovations', 'payload' => $job('App\Jobs\PrepareAffiliationRenovations', 'u-colgado'), 'attempts' => 1, 'reserved_at' => $now - 900, 'available_at' => $now - 1000, 'created_at' => $now - 1000],
            ['queue' => 'lqc-renovations', 'payload' => $job('App\Jobs\PrepareAffiliationRenovations', 'u-programado'), 'attempts' => 0, 'reserved_at' => null, 'available_at' => $now + 3600, 'created_at' => $now],
            ['queue' => 'lqc-system', 'payload' => $job('App\Jobs\BackupDatabase', 'u-otra-cola'), 'attempts' => 0, 'reserved_at' => null, 'available_at' => $now - 7200, 'created_at' => $now - 7200],
        ]);
    });

    it('cuenta qué tocaría cada opción y qué tipos de trabajo hay', function (): void {
        expect(QueueJobActions::counts('lqc-renovations'))->toBe(['stuck' => 2, 'duplicates' => 1, 'pending' => 3, 'zombies' => 1, 'all' => 5])
            ->and(QueueJobActions::counts('lqc-renovations', 'App\Jobs\PrepareAffiliationRenovations'))->toBe(['stuck' => 0, 'duplicates' => 0, 'pending' => 1, 'zombies' => 1, 'all' => 3])
            ->and(QueueJobActions::jobClasses('lqc-renovations'))->toBe([
                'App\Jobs\PrepareAffiliationRenovations' => '3 × PrepareAffiliationRenovations',
                'App\Jobs\SendNotificacionWhatsApp' => '2 × SendNotificacionWhatsApp',
            ]);
    });

    it('mueve los atascados a fallidos sin tocar los recientes ni otras colas, y se pueden reintentar', function (): void {
        $result = QueueJobActions::release('lqc-renovations', QueueJobActions::SCOPE_STUCK, QueueJobActions::MODE_MOVE, reason: 'UltraMsg caído', actor: 'Gustavo');

        expect($result)->toBe(['affected' => 2, 'moved' => 2, 'deleted' => 0])
            ->and(DB::table('jobs')->where('queue', 'lqc-renovations')->count())->toBe(3)
            ->and(DB::table('jobs')->where('queue', 'lqc-system')->count())->toBe(1)
            ->and(FailedJob::query()->pluck('uuid')->sort()->values()->all())->toBe(['u-atascado-1', 'u-atascado-2'])
            ->and(DB::table('logs')->where('action', 'AUDIT_LIVE_QUEUE_RELEASED')->count())->toBe(1);

        $group = FailedJobCatalog::groups()[0];

        expect($group['diagnosis']['category'])->toBe(FailureDiagnosis::CATEGORY_MANUAL)
            ->and($group['count'])->toBe(2);

        expect(FailedJobActions::retryGroup($group['fingerprint'])['retried'])->toBe(2)
            ->and(DB::table('jobs')->where('queue', 'lqc-renovations')->count())->toBe(5);
    });

    it('elimina los colgados o solo un tipo de trabajo', function (): void {
        expect(QueueJobActions::release('lqc-renovations', QueueJobActions::SCOPE_ZOMBIES, QueueJobActions::MODE_DELETE, reason: 'Worker murió a mitad')['deleted'])->toBe(1)
            ->and(QueueJobActions::release('lqc-renovations', QueueJobActions::SCOPE_ALL, QueueJobActions::MODE_DELETE, 'App\Jobs\SendNotificacionWhatsApp', 'Mensajes viejos')['deleted'])->toBe(2)
            ->and(DB::table('jobs')->where('queue', 'lqc-renovations')->pluck('payload')->map(fn ($payload) => json_decode($payload, true)['uuid'])->sort()->values()->all())->toBe(['u-programado', 'u-reciente'])
            ->and(FailedJob::query()->count())->toBe(0);
    });

    it('no acepta operaciones inválidas', function (): void {
        expect(fn () => QueueJobActions::release('lqc-renovations', 'todo-mal', QueueJobActions::MODE_MOVE))->toThrow(InvalidArgumentException::class)
            ->and(fn () => QueueJobActions::release('', QueueJobActions::SCOPE_STUCK, QueueJobActions::MODE_MOVE))->toThrow(InvalidArgumentException::class, 'Indique la cola');

        config(['queue.default' => 'sync']);

        expect(fn () => QueueJobActions::release('lqc-renovations', QueueJobActions::SCOPE_STUCK, QueueJobActions::MODE_MOVE))->toThrow(InvalidArgumentException::class, '«database» o «redis»');
    });

    it('desde la pantalla se libera una cola; eliminar exige escribir su nombre', function (): void {
        Filament::setCurrentPanel('business');
        $this->actingAs(lqcAdmin());

        $component = Livewire::test(LiveQueueCenter::class)
            ->call('selectTab', 'colas')
            ->assertSee('Liberar')
            ->mountAction('releaseQueue', ['queue' => 'lqc-renovations'])
            ->assertMountedActionModalSee(['Liberar la cola «lqc-renovations»', 'Solo los atascados'])
            ->set('mountedActions.0.data.mode', QueueJobActions::MODE_DELETE)
            ->set('mountedActions.0.data.scope', QueueJobActions::SCOPE_STUCK)
            ->set('mountedActions.0.data.reason', 'Envíos viejos que traban la cola.')
            ->set('mountedActions.0.data.confirmation', 'otra-cola')
            ->callMountedAction()
            ->assertHasActionErrors(['confirmation' => 'in']);

        expect(DB::table('jobs')->count())->toBe(6);

        $component->set('mountedActions.0.data.confirmation', 'lqc-renovations')
            ->callMountedAction()
            ->assertHasNoActionErrors()
            ->assertNotified('Cola «lqc-renovations» liberada');

        expect(DB::table('jobs')->where('queue', 'lqc-renovations')->count())->toBe(3);

        $component->mountAction('releaseQueue', ['queue' => 'lqc-renovations'])
            ->set('mountedActions.0.data.scope', QueueJobActions::SCOPE_ZOMBIES)
            ->callMountedAction()
            ->assertHasNoActionErrors()
            ->assertNotified('Cola «lqc-renovations» liberada');

        expect(FailedJob::query()->pluck('uuid')->all())->toBe(['u-colgado']);
    });
});

describe('usabilidad de la pantalla', function (): void {
    beforeEach(function (): void {
        Filament::setCurrentPanel('business');
        $this->actingAs(lqcAdmin());
    });

    it('pinta las ventanas de las acciones en todas las pestañas, no solo en la de la tabla', function (string $tab): void {
        lqcFailed('App\Jobs\Uno', lqcException('Exception: Uno in /srv/app/Jobs/Uno.php:1'));

        Livewire::test(LiveQueueCenter::class)
            ->call('selectTab', $tab)
            ->assertSeeHtml('filamentActionModals');
    })->with(['causas', 'fallidos', 'errores', 'colas']);

    it('pagina de 10 en 10 la tabla y las causas', function (): void {
        foreach (range(1, 12) as $i) {
            lqcFailed('App\Jobs\Trabajo'.$i, lqcException('Exception: Falla '.$i.' in /srv/app/Jobs/T.php:'.$i), now()->subMinutes($i)->toDateTimeString());
        }

        $component = Livewire::test(LiveQueueCenter::class)
            ->assertSee('Mostrando 1–10 de 12')
            ->assertSee('Trabajo10')
            ->assertDontSee('Trabajo11')
            ->call('goToPage', 'groups', 2)
            ->assertSee('Mostrando 11–12 de 12')
            ->assertSee('Trabajo11')
            ->call('goToPage', 'groups', 99)
            ->assertSee('Mostrando 11–12 de 12');

        $component->call('selectTab', 'fallidos');

        expect($component->instance()->getTableRecordsPerPage())->toBe(10)
            ->and($component->instance()->getTableRecords()->count())->toBe(10);
    });

    it('dice la antigüedad en días cuando pasan de 48 horas', function (): void {
        expect(LiveActivitySnapshot::ago(116 * 3600))->toBe('hace 4 días')
            ->and(LiveActivitySnapshot::ago(30 * 3600))->toBe('hace 30 h');
    });

    it('las notificaciones internas del panel no se marcan como envíos externos', function (): void {
        expect(FailedJobCatalog::sendsMessages('Filament\Notifications\Events\DatabaseNotificationsSent'))->toBeFalse()
            ->and(FailedJobCatalog::sendsMessages('App\Jobs\SendNotificacionWhatsApp'))->toBeTrue();
    });
});

it('el monitor se dibuja entero con una cola sin atender y ofrece liberarla', function (): void {
    config(['app.debug' => true]);
    Filament::setCurrentPanel('business');
    LiveActivitySnapshot::forgetSystemHealth();
    $this->actingAs(lqcAdmin());
    DB::table('jobs')->insert(['queue' => 'lqc-renovations', 'payload' => '{}', 'attempts' => 0, 'reserved_at' => null, 'available_at' => now()->getTimestamp() - 3600, 'created_at' => now()->getTimestamp() - 3600]);
    QueueActivityRecorder::onLooping(new Looping('database', 'lqc-system'));

    /** Con app.debug, Livewire rechaza el HTML si una etiqueta mal cerrada deja dos raíces. */
    Livewire::test(App\Filament\Business\Pages\LiveActivityMonitor::class)
        ->assertOk()
        ->assertSee('Cola sin atender')
        ->assertSee('libere la cola');
});

/**
 * Redis en memoria con la misma estructura que usa Laravel: lista de
 * pendientes y conjuntos ordenados de programados y reservados.
 */
final class LqcFakeRedis
{
    /** @var array<string, list<string>> */
    public array $lists = [];

    /** @var array<string, array<string, float>> */
    public array $sets = [];

    /** Simula un worker que toma el trabajo justo antes de que se quite. */
    public ?string $stolenOnRemove = null;

    public function lrange(string $key, int $start, int $stop): array
    {
        return array_slice($this->lists[$key] ?? [], $start, $stop < 0 ? null : $stop - $start + 1);
    }

    public function lrem(string $key, int $count, string $value): int
    {
        if ($this->stolenOnRemove === $value) {
            $this->lists[$key] = array_values(array_filter($this->lists[$key] ?? [], fn (string $item): bool => $item !== $value));
        }

        $index = array_search($value, $this->lists[$key] ?? [], true);

        if ($index === false) {
            return 0;
        }

        array_splice($this->lists[$key], $index, 1);

        return 1;
    }

    public function zrangebyscore(string $key, string $min, string $max, array $options = []): array
    {
        $members = $this->sets[$key] ?? [];
        asort($members);

        return $members;
    }

    public function zrem(string $key, string $member): int
    {
        if (! isset($this->sets[$key][$member])) {
            return 0;
        }

        unset($this->sets[$key][$member]);

        return 1;
    }
}

function lqcRedisPayload(string $class, string $uuid, int $createdAt, string $command = 'O:8:"stdClass":0:{}'): string
{
    return json_encode(['uuid' => $uuid, 'displayName' => $class, 'createdAt' => $createdAt, 'data' => ['commandName' => $class, 'command' => $command]]);
}

describe('liberar colas en Redis (producción)', function (): void {
    beforeEach(function (): void {
        $now = now()->getTimestamp();
        $this->redis = new LqcFakeRedis;
        $renovation = static fn (string $uuid, int $days): string => lqcRedisPayload('App\Jobs\PrepareAffiliationRenovations', $uuid, $now - $days * 86400);

        /** El caso real: 55 renovaciones diarias acumuladas porque ningún worker escucha la cola. */
        $this->redis->lists['queues:renovations'] = [
            $renovation('dia-27', 27),
            $renovation('dia-26', 26),
            $renovation('dia-1', 1),
            lqcRedisPayload('App\Jobs\SendNotificacionWhatsApp', 'wa-ana', $now - 3600, 'O:8:"stdClass":1:{s:5:"phone";s:4:"ana1";}'),
            lqcRedisPayload('App\Jobs\SendNotificacionWhatsApp', 'wa-luis', $now - 3600, 'O:8:"stdClass":1:{s:5:"phone";s:4:"luis";}'),
            lqcRedisPayload('App\Jobs\PrepareAffiliationCorporateRenovations', 'reciente', $now - 60),
        ];
        $this->redis->sets['queues:renovations:delayed'] = [lqcRedisPayload('App\Jobs\X', 'programado', $now) => $now + 3600];
        $this->redis->sets['queues:renovations:reserved'] = [
            lqcRedisPayload('App\Jobs\X', 'colgado', $now - 5000) => $now - 600,
            lqcRedisPayload('App\Jobs\X', 'trabajando', $now - 30) => $now + 800,
        ];

        QueueJobActions::swapStore(new RedisQueueJobStore($this->redis, static fn (string $queue): string => 'queues:'.$queue));
    });

    it('lee pendientes, programados y reservados, y cuenta cada opción', function (): void {
        expect(QueueJobActions::isSupported())->toBeTrue()
            ->and(QueueJobActions::counts('renovations'))->toBe(['stuck' => 5, 'duplicates' => 2, 'pending' => 6, 'zombies' => 1, 'all' => 9])
            ->and(QueueJobActions::summary('renovations'))->toBe([
                'pending_by_class' => ['PrepareAffiliationRenovations' => 3, 'SendNotificacionWhatsApp' => 2, 'PrepareAffiliationCorporateRenovations' => 1],
                'zombies' => 1,
            ]);
    });

    it('saca los repetidos idénticos dejando el más reciente, sin tocar mensajes distintos', function (): void {
        $result = QueueJobActions::release('renovations', QueueJobActions::SCOPE_DUPLICATES, QueueJobActions::MODE_MOVE, reason: 'Renovaciones acumuladas', actor: 'Gustavo');

        $remaining = array_map(fn (string $payload): string => json_decode($payload, true)['uuid'], $this->redis->lists['queues:renovations']);

        expect($result)->toBe(['affected' => 2, 'moved' => 2, 'deleted' => 0])
            ->and($remaining)->toBe(['dia-1', 'wa-ana', 'wa-luis', 'reciente'])
            ->and(FailedJob::query()->pluck('uuid')->sort()->values()->all())->toBe(['dia-26', 'dia-27'])
            ->and(FailedJob::query()->value('queue'))->toBe('renovations');
    });

    it('elimina los colgados sin tocar el que un worker está procesando', function (): void {
        expect(QueueJobActions::release('renovations', QueueJobActions::SCOPE_ZOMBIES, QueueJobActions::MODE_DELETE, reason: 'Worker murió')['deleted'])->toBe(1)
            ->and(array_map(fn (string $payload): string => json_decode($payload, true)['uuid'], array_keys($this->redis->sets['queues:renovations:reserved'])))->toBe(['trabajando'])
            ->and(FailedJob::query()->count())->toBe(0);
    });

    it('si un worker toma el trabajo mientras tanto, no lo duplica en fallidos', function (): void {
        $this->redis->stolenOnRemove = $this->redis->lists['queues:renovations'][0];

        $result = QueueJobActions::release('renovations', QueueJobActions::SCOPE_STUCK, QueueJobActions::MODE_MOVE);

        expect($result['moved'])->toBe(4)
            ->and(FailedJob::query()->where('uuid', 'dia-27')->exists())->toBeFalse()
            ->and(FailedJob::query()->count())->toBe(4);
    });

    it('en la pantalla aparece el botón Liberar y la cola se libera', function (): void {
        Filament::setCurrentPanel('business');
        $this->actingAs(lqcAdmin());

        Livewire::test(LiveQueueCenter::class)
            ->mountAction('releaseQueue', ['queue' => 'renovations'])
            ->assertMountedActionModalSee(['Repetidos idénticos', 'Solo los atascados'])
            ->set('mountedActions.0.data.scope', QueueJobActions::SCOPE_DUPLICATES)
            ->callMountedAction()
            ->assertHasNoActionErrors()
            ->assertNotified('Cola «renovations» liberada');

        expect(count($this->redis->lists['queues:renovations']))->toBe(4);
    });
});

it('muestra el nombre de la cola en una sola línea', function (): void {
    $center = file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/business/pages/live-queue-center.blade.php');
    $panel = file_get_contents(dirname(__DIR__, 2).'/resources/views/live-presence/partials/security-panel.blade.php');

    expect($center)->toContain('.lqc-queue-name { font-weight: 700; white-space: nowrap; word-break: normal; }')
        ->and($center)->toContain('<td class="lqc-mono lqc-queue-name">{{ $queue[\'name\'] }}</td>')
        ->and($panel)->toContain('<td class="lsec-mono" style="font-weight: 700; white-space: nowrap;">{{ $row[\'name\'] }}</td>');
});
