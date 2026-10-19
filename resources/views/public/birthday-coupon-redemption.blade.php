<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Validación cupón cumpleaños · Tu Doctor Group</title>
    <link rel="icon" href="/image/ico_Android_IOS.png" type="image/png">
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: Helvetica, 'Helvetica Neue', Arial, sans-serif;
            background: #17335E;
            color: #fff;
        }
        .wrap {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 16px;
        }
        .card {
            width: 100%;
            max-width: 420px;
            border-radius: 16px;
            border: 1px solid rgba(255, 255, 255, 0.12);
            background: rgba(255, 255, 255, 0.06);
            padding: 24px;
            box-shadow: 0 24px 48px rgba(0, 0, 0, 0.25);
        }
        .eyebrow {
            margin: 0;
            font-size: 11px;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #D7DBDE;
            font-weight: 700;
        }
        h1 {
            margin: 4px 0 0;
            font-size: 24px;
            line-height: 1.2;
        }
        .notice {
            margin-top: 16px;
            padding: 12px 14px;
            border-radius: 12px;
            font-size: 14px;
        }
        .notice--ok {
            border: 1px solid rgba(52, 211, 153, 0.45);
            background: rgba(16, 185, 129, 0.12);
            color: #d1fae5;
        }
        .notice--error {
            border: 1px solid rgba(248, 113, 113, 0.45);
            background: rgba(239, 68, 68, 0.12);
            color: #fee2e2;
        }
        dl { margin: 24px 0 0; }
        .row { margin-bottom: 12px; }
        dt {
            margin: 0;
            font-size: 13px;
            color: #D7DBDE;
        }
        dd {
            margin: 2px 0 0;
            font-size: 16px;
            font-weight: 600;
        }
        .btn {
            display: block;
            width: 100%;
            margin-top: 28px;
            border: 0;
            border-radius: 12px;
            padding: 14px 16px;
            font-size: 14px;
            font-weight: 700;
            background: #fff;
            color: #17335E;
            cursor: pointer;
        }
        .btn:hover { background: #D7DBDE; }
        .muted {
            margin-top: 28px;
            padding: 12px 14px;
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            background: rgba(0, 0, 0, 0.15);
            text-align: center;
            font-size: 14px;
            color: #D7DBDE;
        }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="card">
            <p class="eyebrow">Cupón de cumpleaños</p>
            <h1>{{ $coupon->coupon_type->label() }}</h1>

            @if (session('birthday_coupon_notice'))
                <div class="notice notice--ok">{{ session('birthday_coupon_notice') }}</div>
            @endif

            @if ($errors->any())
                <div class="notice notice--error">{{ $errors->first() }}</div>
            @endif

            <dl>
                <div class="row">
                    <dt>Nombre</dt>
                    <dd>{{ $access->full_name }}</dd>
                </div>
                <div class="row">
                    <dt>Cédula</dt>
                    <dd>{{ $access->document_number ?: '—' }}</dd>
                </div>
                <div class="row">
                    <dt>Teléfono</dt>
                    <dd>{{ $access->phone ?: '—' }}</dd>
                </div>
                <div class="row">
                    <dt>Fecha de nacimiento</dt>
                    <dd>{{ $access->birth_date?->format('d/m/Y') ?: '—' }}</dd>
                </div>
                <div class="row">
                    <dt>Estatus del cupón</dt>
                    <dd>{{ $coupon->status->label() }}</dd>
                </div>
                <div class="row">
                    <dt>Vigencia</dt>
                    <dd>Hasta {{ $coupon->expires_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</dd>
                </div>
                @if ($coupon->used_at)
                    <div class="row">
                        <dt>Usado el</dt>
                        <dd>{{ $coupon->used_at->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</dd>
                    </div>
                @endif
            </dl>

            @if ($coupon->isRedeemable())
                <form method="post" action="{{ route('birthday.coupon.redeem', ['token' => $coupon->public_token]) }}"
                    onsubmit="return confirm('¿Confirmas marcar este cupón como usado? Esta acción no se puede deshacer.');">
                    @csrf
                    <button type="submit" class="btn">Marcar como usado</button>
                </form>
            @else
                <p class="muted">Este cupón no está disponible para canje.</p>
            @endif
        </div>
    </div>
    <script>
        (function () {
            var token = @json($coupon->public_token);
            var status = @json($coupon->status->value);
            if (!token || (status !== 'used' && status !== 'expired')) {
                return;
            }
            try {
                localStorage.setItem('birthday_coupon_status_' + token, status);
            } catch (e) {}
        })();
    </script>
</body>
</html>
