# Propuesta Económica con el microservicio quote-pdf

Integración del panel de **Negocios** con el servicio externo de cotización
(`quote-pdf`), que calcula la Propuesta Económica de Tu Dr En Casa y devuelve
el PDF con el formato oficial. Aplica a **cotizaciones individuales y
corporativas**; el resto de paneles no cambia.

## Variables de entorno

| Variable | Para qué | Valor local |
| --- | --- | --- |
| `TUDR_QUOTE_URL` | URL base del servicio | `https://cotizador.tudrgroup.com` |
| `TUDR_QUOTE_KEY` | Clave que viaja en `X-Api-Key`. **La valida el servicio**: la misma cadena debe estar configurada en quote-pdf | — (solo en `.env`) |
| `TUDR_QUOTE_ENABLED` | Interruptor de la integración | `false` hasta que el servicio esté publicado |
| `TUDR_QUOTE_TIMEOUT` | Segundos de espera | `10` |

Se leen en `config/services.php → tudr_quote`, donde están los valores por
defecto. **Este repositorio no versiona `.env.example`** (se eliminó en el
commit `3fb73131`), así que esta tabla es la referencia para dar de alta las
variables en cada entorno. La clave nunca se versiona ni se escribe en logs, y
el navegador jamás la recibe.

## Flujo

```
Panel de Negocios (Filament)
   └── IndividualQuotePdfGenerator / CorporateQuotePdfGenerator
        └── QuoteServiceAttempt
             └── QuoteProposalPdfService
                  ├── QuoteRenderPayload   filas por rango de edad + población
                  ├── QuoteFeeMatrix       tarifas vigentes de la tabla `fees`
                  ├── QuoteApiClient ──────► POST {url}/render   (documento completo)
                  └── QuoteDocumentAssembler (FPDI) reordena y recorta según el formato
                       └── public/storage/quotes/{code}.pdf
```

Si algo falla, `QuoteServiceAttempt` devuelve `false` y el generador DomPDF de
siempre produce el documento. **El usuario nunca se queda sin propuesta.**

### Por qué `/render` y no `/api/cotizar`

INTEGRACORP ya calcula la propuesta y la guarda por **rango de edad con
población** (`detail_individual_quotes`, `detail_corporate_quotes`): no existen
las edades persona a persona, y una cotización corporativa llega a 2.681
asegurados. Enviar el cálculo hecho mantiene al portal como única fuente de
verdad y el payload pequeño. `/api/cotizar` se usa solo en el endpoint de vista
previa, donde las edades sí llegan una a una.

## Composición del documento

El microservicio entrega el **documento oficial completo** —portada, «acerca de
nosotros», una página de cálculos por plan, patologías del Especial y
contraportada— dibujado sobre los mismos artes que usa el portal. Por eso
INTEGRACORP no añade páginas: sería duplicar la portada.

Lo que sí hace es aplicar el formato que el SUPERADMIN configure en **Negocios →
CONFIGURACIÓN → Formato de la cotización**: `QuoteDocumentAssembler` mueve el
bloque de páginas de cálculo a la hoja indicada y recorta al número de hojas
pedido, con FPDI. Reglas:

- Con los valores por defecto (**7 hojas, cálculos en la 3**) el documento sale
  exactamente como lo dibuja el servicio y el PDF ni siquiera se reescribe.
- Al recortar se descartan páginas institucionales del final, **nunca las
  tarifas**.
- Pedir los cálculos en una hoja posterior al total acota la posición al total.

## Endpoints

| Ruta | Qué hace |
| --- | --- |
| `POST /api/propuestas/cotizar` | Vista previa: calcula y devuelve `{control, planes, resumen, pdf_url}`. Requiere sesión, token CSRF y admite 30 llamadas por minuto. Máximo 20 afiliados. |
| `GET /propuestas/{control}/pdf` | Sirve el PDF `inline`, solo a quien lo generó o puede abrir esa cotización. |

## Diagnóstico

Cada vez que el portal descarta el microservicio deja el motivo en el log
(`quote-pdf: …`): integración desactivada, plan no dibujable, detalle
incompleto, servicio no disponible o error de escritura. Si no aparece nada y
las propuestas salen por el camino viejo, revisa antes que nada que PHP-FPM se
haya recargado tras el último `config:cache` (ver más abajo).

El tamaño del PDF es el diagnóstico más rápido: **600 KB–1,4 MB** lo dibujó el
microservicio; **2,4–3 MB** lo hizo DomPDF.

## Activar y desactivar

`TUDR_QUOTE_ENABLED=false` deja el sistema **exactamente como estaba**: el
endpoint responde `503`, no se hace ninguna llamada saliente y las propuestas
se generan con DomPDF.

Tras cambiar el `.env` en producción:

```bash
php artisan config:clear && php artisan config:cache
systemctl reload php8.3-fpm     # imprescindible
```

Sin recargar PHP-FPM, el panel sigue usando la configuración anterior aunque
`php artisan tinker` informe del valor nuevo: OPcache sirve el
`bootstrap/cache/config.php` viejo. Es el fallo que costó cuatro rondas de
diagnóstico el 20/09/2026.

## Ejecutar el microservicio

El código vive fuera de este repositorio, en `~/whatsapp-sales-agent/deploy/quote-pdf`
(Python 3.12 · Flask · ReportLab). Escucha en el puerto **8080** y lee la clave
de la variable `QUOTE_API_KEY`.

Con Docker:

```bash
cd ~/whatsapp-sales-agent/deploy/quote-pdf
docker build -t quote-pdf .
docker run -d --name quote-pdf -p 8080:8080 -e QUOTE_API_KEY="$TUDR_QUOTE_KEY" quote-pdf
```

Sin Docker (entorno virtual):

```bash
cd ~/whatsapp-sales-agent/deploy/quote-pdf
python3 -m venv .venv && .venv/bin/pip install "flask==3.*" "reportlab==4.*" pillow
QUOTE_API_KEY="$TUDR_QUOTE_KEY" .venv/bin/python app.py
```

Para que INTEGRACORP lo use en local, en `.env`:
`TUDR_QUOTE_URL=http://127.0.0.1:8080`, `TUDR_QUOTE_ENABLED=true`, y después
`php artisan config:clear`.

## Probar contra el servicio

```bash
curl -X POST "$TUDR_QUOTE_URL/api/cotizar" \
  -H "X-Api-Key: $TUDR_QUOTE_KEY" \
  -H 'Content-Type: application/json' \
  -d '{"titular":"Prueba","planes":"todos","edades":[25,50,70],"formato":"pdf"}' \
  -o Propuesta.pdf
```

Salud del servicio (no lleva clave): `curl $TUDR_QUOTE_URL/health` → `{"ok":true}`.

## Errores esperables

| Situación | Respuesta del servicio | Qué hace el portal |
| --- | --- | --- |
| Falta tarifa para una edad o plan | `422` con `faltantes[].motivo` | Notificación de aviso con el motivo; el flujo manual sigue |
| Clave ausente o inválida | `401` | Se registra y cae al generador local |
| Servicio caído o lento | `5xx` / timeout | Dos intentos (200 ms), luego generador local |
| Respuesta que no es un PDF | `200` con otro contenido | Se descarta y cae al generador local |

## Límites conocidos

- Solo se envían al servicio los planes **Inicial, Ideal y Especial** (ids 1, 2
  y 3), tanto en cotizaciones de un plan como **multiplan** (`CM`, una página de
  cálculos por plan en el mismo documento). Si alguno de los planes de la
  cotización no es de esos tres, el documento **entero** lo arma el generador
  local: nunca se entrega una propuesta a la que le falte un plan.
- Si a un rango de edad le faltan coberturas —cotizaciones antiguas cuyo plan
  ganó coberturas después, ~5 % del histórico— también se usa el generador
  local, para no dibujar «0 US$» en una casilla de precio.
- `cotizador.tudrgroup.com` aún no resuelve (NXDOMAIN): hasta publicarlo, la
  integración se prueba contra el servicio en local.
- El servicio trae sus propias tarifas en `tariffs.json`, que **no** cubren todos
  los rangos del Ideal. INTEGRACORP siempre envía las suyas (`fuente_tarifas:
  payload`), así que el Ideal se cotiza correctamente.

## Tests

```bash
php artisan test tests/Unit/TuDrQuoteServiceTest.php \
  tests/Unit/TuDrQuoteProposalFlowTest.php \
  tests/Unit/TuDrQuoteEndpointTest.php \
  tests/Unit/QuoteDocumentLayoutPageTest.php
```
