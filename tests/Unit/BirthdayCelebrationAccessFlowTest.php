<?php

declare(strict_types=1);

use App\Enums\BirthdayCouponRedemptionStatus;
use App\Enums\BirthdayCouponType;
use App\Models\BirthdayNotification;
use App\Models\BirthdayNotificationDelivery;
use App\Models\User;
use App\Support\Birthday\BirthdayCelebrationAccessIssuer;
use App\Support\Birthday\BirthdayCouponRedemptionService;
use App\Support\Birthday\BirthdayCouponWhatsAppMessages;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class);

beforeEach(function (): void {
    config([
        'database.default' => 'sqlite',
        'database.connections.sqlite.database' => ':memory:',
        'session.driver' => 'array',
    ]);

    DB::purge('sqlite');
    DB::reconnect('sqlite');

    ensureBirthdayCelebrationFlowSchema();

    Carbon::setTestNow(Carbon::parse('2026-07-17 10:00:00', 'America/Caracas'));
});

afterEach(function (): void {
    Carbon::setTestNow();
});

function ensureBirthdayCelebrationFlowSchema(): void
{
    if (! Schema::hasTable('users')) {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->string('email')->unique();
            $table->string('password')->nullable();
            $table->string('status')->nullable();
            $table->json('departament')->nullable();
            $table->boolean('is_admin')->default(false);
            $table->timestamps();
        });
    }

    if (! Schema::hasTable('birthday_notifications')) {
        Schema::create('birthday_notifications', function (Blueprint $table): void {
            $table->id();
            $table->string('title')->nullable();
            $table->text('content')->nullable();
            $table->string('file')->nullable();
            $table->string('status')->nullable();
            $table->json('channels')->nullable();
            $table->string('data_type')->nullable();
            $table->string('type')->nullable();
            $table->timestamps();
        });
    }

    if (! Schema::hasTable('birthday_notification_deliveries')) {
        Schema::create('birthday_notification_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('birthday_notification_id');
            $table->string('full_name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('document_number', 32)->nullable();
            $table->date('birth_date')->nullable();
            $table->date('delivery_date');
            $table->string('email_status')->nullable();
            $table->string('whatsapp_status')->nullable();
            $table->timestamps();
        });
    }

    if (! Schema::hasTable('birthday_celebration_accesses')) {
        Schema::create('birthday_celebration_accesses', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('birthday_notification_delivery_id')->nullable();
            $table->string('public_token', 64)->unique();
            $table->string('full_name');
            $table->string('document_number', 32)->nullable();
            $table->string('phone', 32)->nullable();
            $table->date('birth_date')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();
        });
    }

    if (! Schema::hasTable('birthday_coupon_tokens')) {
        Schema::create('birthday_coupon_tokens', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('birthday_celebration_access_id');
            $table->string('coupon_type', 32);
            $table->string('public_token', 64)->unique();
            $table->string('status', 32)->default('pending');
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->unsignedBigInteger('used_by_user_id')->nullable();
            $table->string('used_from_ip', 45)->nullable();
            $table->timestamps();
        });
    }
}

it('exige login para ver la ficha del cupón', function (): void {
    $access = BirthdayCelebrationAccessIssuer::ensureForDelivery(
        1,
        'Ana Pérez',
        '04141234567',
        'V12345678',
        Carbon::parse('1990-04-27'),
    );

    $couponToken = $access->couponTokens->first();

    $this->get(route('birthday.coupon.show', ['token' => $couponToken->public_token]))
        ->assertRedirect(route('home'));
});

it('devuelve a la modal del cupón tras login en el hub', function (): void {
    $access = BirthdayCelebrationAccessIssuer::ensureForDelivery(
        1,
        'Ana Hub',
        '04141234567',
    );

    $couponToken = $access->couponTokens->first();
    $showUrl = route('birthday.coupon.show', ['token' => $couponToken->public_token]);

    $user = User::query()->create([
        'name' => 'Staff Hub',
        'email' => 'hub-birthday-test@tudrencasa.com',
        'password' => bcrypt('secret'),
        'status' => 'ACTIVO',
        'departament' => ['MARKETING'],
        'is_admin' => true,
    ]);

    $this->get($showUrl)->assertRedirect(route('home'));

    $this->actingAs($user)
        ->get($showUrl)
        ->assertSuccessful()
        ->assertSee('Ana Hub', false);
});

it('permite marcar cupón como usado con usuario autenticado', function (): void {
    $notification = BirthdayNotification::query()->create([
        'title' => 'Tarjeta',
        'content' => 'Feliz cumpleaños',
        'status' => 'APROBADA',
        'channels' => ['whatsapp'],
        'data_type' => 'agents',
        'type' => 'image',
    ]);

    $delivery = BirthdayNotificationDelivery::query()->create([
        'birthday_notification_id' => $notification->id,
        'full_name' => 'Ana Pérez',
        'phone' => '04141234567',
        'delivery_date' => '2026-07-17',
    ]);

    $access = BirthdayCelebrationAccessIssuer::ensureForDelivery(
        $delivery->id,
        'Ana Pérez',
        '04141234567',
        'V12345678',
        Carbon::parse('1990-04-27'),
    );

    $coupon = $access->couponTokens
        ->first(fn ($row) => $row->coupon_type === BirthdayCouponType::Viajes);

    $user = User::query()->create([
        'name' => 'Staff Test',
        'email' => 'staff-birthday-test@tudrencasa.com',
        'password' => bcrypt('secret'),
        'status' => 'ACTIVO',
        'departament' => ['MARKETING'],
        'is_admin' => true,
    ]);

    $showUrl = route('birthday.coupon.show', ['token' => $coupon->public_token]);

    $this->actingAs($user)
        ->get($showUrl)
        ->assertSuccessful()
        ->assertSee('Ana Pérez', false);

    $this->actingAs($user)
        ->from($showUrl)
        ->post(route('birthday.coupon.redeem', ['token' => $coupon->public_token]), [
            '_token' => csrf_token(),
        ])
        ->assertRedirect($showUrl);

    expect($coupon->refresh()->status)->toBe(BirthdayCouponRedemptionStatus::Used)
        ->and($coupon->used_by_user_id)->toBe($user->id);
});

it('emite acceso con vigencia de 24 horas desde la emisión', function (): void {
    $access = BirthdayCelebrationAccessIssuer::ensureForDelivery(
        99,
        'Vigencia Test',
        '04142222222',
    );

    expect($access->expires_at)->not->toBeNull()
        ->and($access->expires_at->equalTo(Carbon::parse('2026-07-18 10:00:00')))->toBeTrue();

    $access->couponTokens->each(function ($token): void {
        expect($token->expires_at?->equalTo(Carbon::parse('2026-07-18 10:00:00')))->toBeTrue();
    });
});

it('rechaza animación y cupón cuando el enlace expiró', function (): void {
    $access = BirthdayCelebrationAccessIssuer::ensureForDelivery(
        98,
        'Expirado Test',
        '04143333333',
    );

    $expiredAt = Carbon::parse('2026-07-17 09:00:00');
    $access->forceFill(['expires_at' => $expiredAt])->save();
    $access->couponTokens()->update(['expires_at' => $expiredAt]);

    $couponToken = $access->couponTokens->first();

    $this->get(route('birthday.animation.access', ['token' => $access->public_token]))
        ->assertNotFound();

    $user = User::query()->create([
        'name' => 'Staff Expirado',
        'email' => 'expirado-birthday@tudrencasa.com',
        'password' => bcrypt('secret'),
        'status' => 'ACTIVO',
        'departament' => ['MARKETING'],
        'is_admin' => true,
    ]);

    $this->actingAs($user)
        ->get(route('birthday.coupon.show', ['token' => $couponToken->public_token]))
        ->assertNotFound();
});

it('sirve la animación solo con token válido y expone payload de cupones', function (): void {
    $access = BirthdayCelebrationAccessIssuer::ensureForDelivery(
        1,
        'María',
        '04141234567',
        null,
        Carbon::parse('1985-01-15'),
    );

    $this->get('/feliz-cumpleanos')
        ->assertNotFound();

    $response = $this->get(route('birthday.animation.access', ['token' => $access->public_token]));

    $response->assertSuccessful()
        ->assertSee('BIRTHDAY_COUPON_PAYLOAD', false)
        ->assertSee('cupon-viajes.jpg', false)
        ->assertSee('María', false);
});

it('expone estatus y token en el payload de cupones para la animación', function (): void {
    $access = BirthdayCelebrationAccessIssuer::ensureForDelivery(
        1,
        'Pedro',
        '04141111111',
    );

    $payload = BirthdayCouponWhatsAppMessages::payloadForAnimation($access->couponTokens);

    expect($payload)->toHaveCount(4)
        ->and($payload[0])->toHaveKeys(['publicToken', 'status', 'verifyUrl'])
        ->and($payload[0]['status'])->toBe('pending');
});

it('rechaza marcar cupón ya usado', function (): void {
    $access = BirthdayCelebrationAccessIssuer::ensureForDelivery(
        1,
        'Luis',
        '04140000000',
    );

    $coupon = $access->couponTokens->first();
    $user = User::query()->create([
        'name' => 'Operador',
        'email' => 'operador-birthday@tudrencasa.com',
        'password' => bcrypt('secret'),
        'status' => 'ACTIVO',
        'departament' => ['ADMINISTRACION'],
        'is_admin' => true,
    ]);

    $service = app(BirthdayCouponRedemptionService::class);
    $service->redeem($coupon, $user);

    expect(fn () => $service->redeem($coupon->refresh(), $user))
        ->toThrow(Illuminate\Validation\ValidationException::class);
});
