# Propuesta Económica con el microservicio quote-pdf

Integración del panel de **Negocios** con el servicio externo de cotización
(`quote-pdf`), que calcula la Propuesta Económica de Tu Dr En Casa y devuelve
el PDF con el formato oficial. Aplica a **cotizaciones individuales y
corporativas**; el resto de paneles no cambia.

## Variables de entorno

| Variable | Para qué | Valor |
| --- | --- | --- |
| `TUDR_QUOTE_URL` | URL base del servicio | `https://cotizador.tudrgroup.com` (en local, `http://127.0.0.1:8080`) |
| `TUDR_QUOTE_KEY` | Clave que viaja en `X-Api-Key`. **La valida el servicio**: la misma cadena debe estar en la variable `QUOTE_API_KEY` del contenedor | — (solo en `.env`) |
| `TUDR_QUOTE_ENABLED` | Interruptor de la integración | `true` en producción desde el 20/09/2026 |
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

## Dónde corre el servicio

El microservicio (Python 3.12 · Flask · ReportLab) **no vive en este
repositorio**. Su código fuente está en `~/whatsapp-sales-agent/deploy/quote-pdf`
y en producción corre así:

| | |
| --- | --- |
| Servidor | `srvapi` — 74.91.115.211 |
| Ruta del código | `/opt/quote-pdf` |
| Contenedor / imagen | `tudr-quote` |
| Puerto | `127.0.0.1:8090` → `8080` del contenedor (no se expone a internet) |
| Proxy | nginx, vhost `/etc/nginx/sites-available/cotizador`, TLS por certbot |
| Acceso | `https://cotizador.tudrgroup.com`, restringido por IP a INTEGRACORP (74.91.112.83); `/health` abierto |
| Variables | `QUOTE_API_KEY`, `AGENTE_DEFAULT`, `TZ_NAME` |

Es **stateless** y no necesita base de datos: INTEGRACORP le envía sus tarifas
en cada llamada.

## Operación

### Comprobar que Docker está sano

```bash
systemctl is-active docker                       # active
docker info | grep -E "Server Version|Running"
docker system df                                 # espacio de imágenes y contenedores
df -h /var/lib/docker
```

### Comprobar el contenedor

```bash
docker ps --filter name=tudr-quote --format 'table {{.Names}}\t{{.Status}}\t{{.Ports}}'
docker inspect -f '{{.State.Status}} | reinicios: {{.RestartCount}} | salida: {{.State.ExitCode}}' tudr-quote
docker stats tudr-quote --no-stream
docker logs --tail 50 tudr-quote
docker logs -f tudr-quote            # en vivo
```

Esperado en `docker ps`: `Up X` y `127.0.0.1:8090->8080/tcp`.

### Comprobar que el servicio responde

```bash
# 1. Directo al contenedor (descarta nginx y TLS)
curl -s http://127.0.0.1:8090/health; echo

# 2. A través de nginx con TLS
curl -s https://cotizador.tudrgroup.com/health; echo

# 3. El cierre por IP funciona: desde fuera debe dar 403
curl -s -o /dev/null -w "%{http_code}\n" -X POST https://cotizador.tudrgroup.com/api/cotizar -d '{}'
```

`/health` debe responder `{"auth":true,"ok":true,"service":"quote-pdf"}`. **Si
`auth` sale `false`, el contenedor arrancó sin clave y el servicio está
abierto**: hay que recrearlo con `QUOTE_API_KEY`.

Prueba funcional —valida también que las plantillas de `assets/` están sanas—:

```bash
curl -s -X POST http://127.0.0.1:8090/api/cotizar \
  -H "X-Api-Key: $QUOTE_API_KEY" \
  -H 'Content-Type: application/json' \
  -d '{"titular":"Chequeo","planes":"todos","edades":[25,50,70],"formato":"pdf"}' \
  -o /tmp/chequeo.pdf

ls -la /tmp/chequeo.pdf && head -c 4 /tmp/chequeo.pdf; echo    # cientos de KB y "%PDF"
```

Y desde INTEGRACORP, que es lo que de verdad importa:

```bash
php artisan tinker --execute="dd(app(App\Services\TuDr\QuoteApiClient::class)->health());"   # true
```

### Bajar, subir y reiniciar

```bash
docker stop tudr-quote       # bajar (el contenedor se conserva)
docker start tudr-quote      # subir
docker restart tudr-quote    # reiniciar
```

Tarda ~1 segundo en estar listo. **Mientras está abajo, INTEGRACORP no se
rompe**: detecta que no responde y genera las propuestas con DomPDF. Para una
parada planificada conviene apagar antes el flag en INTEGRACORP, para que ni
siquiera intente la llamada (ver «Activar y desactivar»).

### Recrear o actualizar el servicio

Tras cambiar el código o los artes de `assets/`:

```bash
cd /opt/quote-pdf
docker build -t tudr-quote .
docker rm -f tudr-quote

docker run -d --name tudr-quote \
  --restart unless-stopped \
  -p 127.0.0.1:8090:8080 \
  -e QUOTE_API_KEY='<clave>' \
  -e AGENTE_DEFAULT='Asesor Digital TuDr' \
  -e TZ_NAME='America/Caracas' \
  --memory=512m --cpus=1 \
  tudr-quote

curl -s http://127.0.0.1:8090/health; echo
docker image prune -f        # limpiar imágenes viejas
```

Ver con qué variables quedó arrancado (sin mostrar la clave):

```bash
docker inspect -f '{{range .Config.Env}}{{println .}}{{end}}' tudr-quote | grep -v QUOTE_API_KEY
```

Opcional, para que Docker vigile la salud y `docker ps` muestre `(healthy)`,
añade al `docker run`:

```bash
  --health-cmd='python -c "import urllib.request;urllib.request.urlopen(\"http://127.0.0.1:8080/health\").read()"' \
  --health-interval=30s --health-retries=3 \
```

### Si algo falla, en este orden

```bash
systemctl is-active docker                        # 1. el daemon
docker ps --filter name=tudr-quote                # 2. el contenedor
curl -s http://127.0.0.1:8090/health              # 3. la app
nginx -t && systemctl is-active nginx             # 4. el proxy
curl -s https://cotizador.tudrgroup.com/health    # 5. TLS y DNS
tail -20 /var/log/nginx/cotizador.error.log       # 6. errores del proxy
docker logs --tail 50 tudr-quote                  # 7. errores de la app
```

El primero que falle señala dónde está el problema. Si todo lo anterior está
bien y aun así las propuestas salen por el camino viejo, el sospechoso es
PHP-FPM sin recargar (ver «Activar y desactivar»).

### Ejecutarlo en local, para desarrollo

```bash
cd ~/whatsapp-sales-agent/deploy/quote-pdf
python3 -m venv .venv && .venv/bin/pip install "flask==3.*" "reportlab==4.*" pillow
QUOTE_API_KEY='<clave>' .venv/bin/python app.py        # escucha en 8080
```

En el `.env` de INTEGRACORP: `TUDR_QUOTE_URL=http://127.0.0.1:8080`,
`TUDR_QUOTE_ENABLED=true` y `php artisan config:clear`.

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
