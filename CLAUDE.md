# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

# IntegraCorp — Instrucciones permanentes para agentes (Claude / Cursor)

Este archivo es la **fuente de verdad** del proyecto para cualquier agente de IA. Si hay conflicto entre Laravel Boost, Filament, Pest u otra guía genérica y lo que aquí se indica, **prevalecen las reglas de IntegraCorp**.

---

## 0. Mandatos irrenunciables

### 0.1 Idioma de las respuestas (obligatorio)

- **Todas las respuestas al usuario deben estar en español.** Sin excepciones.
- Código, identificadores, nombres de clases, rutas, claves de config y comentarios técnicos en el código siguen las convenciones ya existentes del repositorio (inglés o español según el archivo hermano).
- Explicaciones, resúmenes, planes, preguntas, mensajes de commit sugeridos y reportes de tests: **siempre en español**.
- La UI que se construya para el usuario final debe estar en español, coherente con labels, notificaciones y textos actuales del sistema.

### 0.2 Protección absoluta de la base de datos de desarrollo

Está **prohibido** ejecutar, sugerir o encadenar cualquier comando, script, trait o SQL que elimine, vacíe, trunque o recree la base de datos de desarrollo. Los datos locales y de staging son irreemplazables.

**Nunca ejecutar (salvo petición explícita, consciente y por escrito del usuario):**

- `php artisan migrate:fresh`
- `php artisan migrate:refresh`
- `php artisan migrate:reset`
- `php artisan db:wipe`
- `DROP DATABASE`, `DROP SCHEMA`, `DROP TABLE`
- `TRUNCATE TABLE` / `TRUNCATE`
- `DELETE FROM` masivos o seeds que vacíen tablas reales
- Traits `RefreshDatabase`, `DatabaseMigrations`, `LazilyRefreshDatabase` contra MySQL/MariaDB de desarrollo
- Recrear el esquema de Herd, importar dumps destructivos o “resetear para que pasen los tests”
- Invocar la ruta de debug `GET /truncate` (borra usuarios con `id > 2` y reinicia el autoincremento). Tampoco `/pp`, `/notify`, `/d` ni `/r4/*` de prueba.

**Sí se permite:**

- `php artisan migrate --path=...` sobre un archivo concreto (nunca fresh/refresh/wipe)
- Tests filtrados: `php artisan test tests/Unit/Archivo.php` o `--filter=nombre`
- Tests que **solo leen**, o que escriben dentro de una transacción revertida (ver abajo: `phpunit.xml` **no** basta para aislarlos).
- Lecturas (`SELECT`) y cambios puntuales de datos que el usuario pida de forma explícita

Si un test “necesita” recrear la base real, **no se recrea**. Se adapta el test (SQLite en memoria, fakes, factories aisladas) o se informa al usuario.

**Red de seguridad ya implementada (no desactivarla ni “simplificarla”):**

- `tests/TestCase.php` intercepta `refreshApplication()`: si la clase de test usa `RefreshDatabase`, `LazilyRefreshDatabase`, `DatabaseMigrations` o `DatabaseTruncation`, fuerza sqlite `:memory:` (más session/cache array y queue sync) **antes** de que el trait corra `migrate:fresh`, y lanza `RuntimeException` si aun así la conexión no es sqlite en memoria.
- `phpunit.xml` declara `DB_CONNECTION`, `DB_DATABASE`, `CACHE_STORE`, `MAIL_MAILER`, `QUEUE_CONNECTION` y `SESSION_DRIVER` con `force="true"`.
- `ensureSqliteInMemoryDatabaseOrSkip()` (`tests/Pest.php`) salta el test si la conexión no es sqlite en memoria: usarlo en cualquier test unitario que **escriba** en DB sin traits destructivos.

**Pero esa red solo cubre los traits destructivos. Hoy los tests de `tests/Unit` que arrancan la aplicación escriben en la base MySQL de desarrollo real.**

`bootstrap/cache/config.php` existe y está cacheado con `database.default = mysql` → `operaciones`. Una config cacheada ignora los `env()`, así que los `force="true"` de `phpunit.xml` **no se aplican**. Como `TestCase` solo redirige a sqlite cuando detecta un trait destructivo, un test unitario que haga `uses(Tests\TestCase::class)` y escriba deja basura en la base del usuario. Ya pasó (`PlanFormSchemaTest`, `PlanAndQuoteSupportTest`).

**El interruptor que desarma toda esta trampa es `php artisan config:clear`.** Sin config cacheada, los `force="true"` de `phpunit.xml` sí se aplican y *toda* la suite corre contra sqlite `:memory:`, no contra `operaciones`. Por eso `composer run test` (que hace `config:clear` y luego `php artisan test`) es la forma segura de correr tests, y `php artisan test` a secas no lo es. El costo es que después la config queda sin cachear — aceptable en local, y en todo caso preferible a escribir en la base real.

Aun así, la regla de la transacción revertida sigue vigente: es la red que protege al agente que corre `php artisan test --filter=...` directamente, que es lo habitual.

Todo test unitario que **escriba** debe envolverse en una transacción que siempre se revierte — patrón ya establecido en `tests/Unit/PlanStructurePersistenceTest.php`:

```php
uses(Tests\TestCase::class);

beforeEach(fn () => DB::beginTransaction());
afterEach(fn () => DB::rollBack());
```

Si solo hace falta un usuario autenticado, `User::factory()->make()`, nunca `create()`. Después de correr tests, verificar conteos en la base.

### 0.3 Robustez 200 %, integridad y rendimiento

Toda mejora, corrección, actualización o desarrollo nuevo debe ser **extremadamente robusto**. No se aceptan parches frágiles, happy-paths a medias ni “luego lo endurecemos”.

**Integridad del sistema**

- Transacciones de base de datos en operaciones que toquen más de un modelo o archivos + DB (`databaseTransactions()` ya está activo en paneles Filament; replicar la misma disciplina en jobs y servicios).
- Validación estricta de entrada (Form Requests, reglas Filament, enums). Nunca confiar en el cliente.
- Autorización real: paneles, `User::canAccessPanel()`, `UserNavigationAccess`, permisos granulares (`permissions` / slugs) y visibilidad de acciones. No filtrar “solo en el menú”.
- No romper flujos existentes (cotización → afiliación → cobranza → renovación → operaciones → telemedicina).
- Migraciones aditivas y reversibles. Al alterar una columna, **repetir todos los atributos previos** (Laravel 12).
- Jobs idempotentes cuando puedan reintentarse (WhatsApp, PDFs, webhooks Viveplus, exportaciones).
- No introducir N+1. Eager load. Índices cuando se consulten columnas nuevas en tablas grandes.
- Auditoría: respetar `created_by` / `updated_by`, `SecurityAudit`, observers y bitácoras ya existentes.
- Fallos visibles y recuperables: notificaciones Filament en español, logs, sin datos a medias.

**Rendimiento de cara al usuario final**

- Consultas acotadas, paginación, debounce en búsquedas globales (los paneles internos ya usan 300–350 ms).
- Tablas Filament: columnas justas, `deferFilters` coherente con Filament 4, evitar `get()` masivos en widgets.
- Trabajo pesado (PDF, Excel, WhatsApp, CSV, certificados, tarjetas) **siempre en cola** (`ShouldQueue`), nunca bloqueando el request HTTP.
- Caches y locks donde ya existen (notificaciones masivas WhatsApp, rate BCV, etc.): no saltárselos.
- Assets: Vite; no inflar JS/CSS. Temas Filament ya viven en `resources/css/filament/`.
- Evitar `wire:model.live` en campos pesados; usar debounce. SPA de paneles ya está activa: no forzar recargas completas innecesarias.
- Redis/Predis está disponible: usarlo para locks, cache y colas cuando el patrón del módulo lo haga, sin inventar infra nueva.

### 0.4 Experiencia de usuario 100 % optimizada

La UX no es un extra: es un requisito de cada cambio.

- Textos en español, claros, sin jerga de implementación. Labels, helpers, placeholders, toasts y estados vacíos deben enseñar qué hacer.
- Acciones destructivas: confirmación, color `danger`, consecuencias explícitas.
- Loading states (`wire:loading`), disabled mientras se guarda, feedback de éxito/error inmediato.
- Tablas usables en desktop: columnas prioritarias, búsqueda, filtros con sentido de negocio, acciones agrupadas como en recursos hermanos.
- Formularios Filament v4: `Schemas` (`Grid`, `Section`, `Fieldset`, `Tabs`, `Wizard`). No mezclar APIs de v3.
- Accesibilidad y dark mode cuando el panel/página ya lo soporta.
- Flujos públicos (pre-afiliación, registro de agente/agencia, TDEV, chat, asociados de empresa): móviles, rápidos, sin pasos muertos.
- No romper atajos existentes (`Ctrl/Cmd+K` en business/operations), tickers de helpdesk, ni navegación rápida entre paneles.
- Si un cambio empeora clics, tiempo de espera o claridad, no se mergea: se rediseña.

### 0.5 Calidad de entrega

- Seguir convenciones del archivo hermano (estructura Filament: `Schemas/`, `Tables/`, `Pages/`, `RelationManagers/`).
- No crear carpetas raíz nuevas ni añadir dependencias Composer/NPM sin aprobación.
- Probar de forma programática (Pest) el happy path, el fallo y el caso raro. Correr **solo** los tests afectados.
- Antes de cerrar: `vendor/bin/pint --dirty`.
- No inventar documentación Markdown extra salvo que el usuario la pida.
- No commitear ni pushear a menos que el usuario lo pida.

---

## 1. Qué es este proyecto

**IntegraCorp** es el sistema operativo interno y comercial de **Tu Dr. Group (TDG) / Tu Dr En Casa** (correo interno `@tudrencasa.com`). No es un CRUD genérico: es la plataforma que sostiene el ciclo de vida de productos de **asistencia médica (TDEC)** y **asistencia de viajes (TDEV)** vendidos a través de una red de agencias, agentes y subagentes, más la operación clínica, telemedicina, administración, RRHH, marketing y proyectos.

- Producción: `https://integracorp.tudrgroup.com` (`config/parameters.php` → `INTEGRACORP_URL`)
- Local (Laravel Herd): `https://www.integracorp.test` (valor real de `APP_URL`). No hay que levantar un servidor HTTP: Herd ya lo hace.
- Logout de red comercial: `https://tudrencasa.com` (`REDIRECT_LOGOUT_EXTERNAL_URL`)
- Casa matriz comercial: código de agencia **`TDG-100`**

Marca visual: logos `public/image/logoNewTDG.png` (claro) y `logoTDG.png` (oscuro), favicon `ico_Android_IOS.png`. Color institucional frecuente: `#052F60` / variantes por panel.

### 1.1 Líneas de producto

| Código | Significado en el negocio | Dónde aparece |
| --- | --- | --- |
| **TDEC** | Producto de salud / “Tu Dr En Casa”. Planes, afiliaciones individuales y corporativas, telemedicina, coordinaciones médicas, inventario Diagnomóvil. | Comisiones `tdec` / `commission_tdec`, grupos de navegación, PDFs, cobranza |
| **TDEV** | Producto de viajes. Agencias y agentes TDEV, reportes de compensación, landings y registros públicos. | Campos `tdev` en agencias/agentes, `TdevAgency`, `TdevAgent`, panel Administración → COMPENSACION TDEV |
| **White Company** | Compañía blanca: catálogo, tarifas negociadas, planes asignados, documentos de marca y liquidaciones. | `WhiteCompany`, `WhiteCompanyFee`, `WhiteCompanyPlan`, panel Business |
| **Dress Tylor** | Cotizaciones / coberturas especiales de ese convenio. | `DressTylorQuote`, enums `DressTaylorCompany` |

### 1.2 Actores del negocio

1. **Titular / afiliado / grupo familiar** — personas cubiertas por un plan (individual o corporativo).
2. **Pagador** — quien paga la afiliación (puede no ser el titular).
3. **Agente / subagente** — fuerza de ventas; tiene código, jerarquía, comisiones TDEC/TDEV.
4. **Agencia MASTER o GENERAL** — dueña de una red; entra a paneles `master` o `general`.
5. **Account manager** — administrador de cuentas comerciales.
6. **Analista interno** (Negocios, Operaciones, Administración, Marketing, Proyectos, Métricas) — email `@tudrencasa.com` + departamento en `users.departament` (array).
7. **Médico / enfermero / proveedor AMD** — operación y telemedicina.
8. **Proveedor jurídico (`Supplier`)** — clínicas y red; puede tener usuario con `supplier_id`.
9. **Colaborador RRHH** — nómina, préstamos, vacaciones, aniversarios.
10. **Prospecto** — pipeline comercial antes de ser agente.
11. **Asociado de empresa (`CompanyAssociate`)** — altas públicas de población corporativa / nuevos negocios.

---

## 2. Stack técnico

Versiones objetivo (no “subir” sin aprobación):

| Pieza | Versión |
| --- | --- |
| PHP | 8.3.25 (`composer.json` pide ^8.2) |
| Laravel | 12 |
| Filament | 4 |
| Livewire | 3 |
| Livewire Volt | 1 |
| Flux UI Free + Pro | 2 |
| Pest | 3 |
| Laravel Pint | 1 |
| Tailwind CSS | 4 (Vite 6) |
| Predis | 3 (Redis) |
| DomPDF / FPDF+FPDI | PDFs y plantillas |
| Simple QrCode | tarjetas y documentos |
| Flowframe Laravel Trend | series temporales en widgets |
| Swis Filament Backgrounds | fondos de login por panel |
| Laravel Boost | MCP de documentación y herramientas del ecosistema |

Otras librerías relevantes: iconos Blade (FluentUI, Fontisto, Bootstrap, Entypo, FontAwesome, Health Icons), `solution-forest/filament-header-select`.

**No hay Inertia.** El frontend de producto es Filament + Livewire + Blade + Flux + Alpine (incluido en Livewire).

Cola por defecto: `database` (`config/queue.php`), con `retry_after` alto por jobs de WhatsApp e imports CSV. Redis se usa cuando el `.env` lo indica (imports largos, locks).

### 2.1 Comandos del día a día

El sitio **siempre** está servido por Herd en `https://www.integracorp.test` (`APP_URL` del `.env`). Nunca levantar `php artisan serve` ni abrir puertos; para compartir una URL con el usuario usar el tool `get-absolute-url` de Boost.

| Necesidad | Comando |
| --- | --- |
| Compilar assets una vez | `npm run build` |
| Vite en watch (cambios de CSS/JS/tema) | `npm run dev` |
| Todo junto: queue + vite | `php artisan queue:listen --tries=1` y `npm run dev` en dos terminales. **Ojo:** `composer run dev` también arranca `php artisan serve`, que con Herd sobra (levanta un segundo servidor en `:8000`) |
| Worker de colas | `php artisan queue:listen --tries=1` (jobs en cola `database`, más `system` y `renovations`) |
| Formateo obligatorio antes de cerrar | `vendor/bin/pint --dirty` |
| Un archivo de test | `php artisan test tests/Unit/NombreTest.php` |
| Un test concreto | `php artisan test --filter=nombreDelTest` |
| Suite completa (**solo si el usuario lo pide**) | `php artisan test` / `./vendor/bin/pest` |
| Suite con la DB **realmente aislada** | `composer run test` (hace `config:clear` primero; ver §0.2) |
| Migrar | **`php artisan migrate --path=database/migrations/<archivo>.php`** (una por una; `migrate` a secas siempre falla, ver abajo) |
| Sincronizar permisos de menú Filament | `php artisan permissions:sync-navigation --panel=business` |
| Crear archivos Filament | `php artisan make:filament-resource ... --no-interaction` (ver `list-artisan-commands` de Boost) |

**`php artisan migrate` a secas siempre falla.** Hay ~397 archivos de migración en disco y solo ~157 registrados en la tabla `migrations`: el esquema se construyó importando un dump, no ejecutando las migraciones. Por eso `migrate` arranca por `0001_01_01_000000_create_users_table` y muere con *«Table 'users' already exists»*. No hay daño (aborta en el primer `CREATE TABLE`), pero se pierde tiempo diagnosticando. Escribir las migraciones nuevas idempotentes (`Schema::hasTable`, `Schema::hasColumn`) y aplicarlas una por una:

```
php artisan migrate --path=database/migrations/2026_08_20_180000_add_pricing_mode_to_plans_table.php
```

Verificar el resultado con una consulta a `information_schema`, no asumiendo que corrió.

**Casi todo el framework está cacheado en `bootstrap/cache/`.** `php artisan about` reporta hoy `Config`, `Events`, `Routes`, `Views`, `Blade Icons` y `Panel Components` en **CACHED**. Consecuencias que cuestan una sesión entera de diagnóstico si no se saben:

| Cache | Qué queda invisible | Cómo refrescar |
| --- | --- | --- |
| `config.php` | cambios en `.env` y `config/*.php`; los `env()` de `phpunit.xml` se ignoran (§0.2) | `php artisan config:clear` |
| `routes-v7.php` | **una ruta nueva en `routes/web.php` no existe** hasta refrescar (404 inexplicable) | `php artisan route:clear` |
| `events.php` | listeners nuevos no se disparan | `php artisan event:clear` |
| `blade-icons.php` | un set de iconos recién instalado no resuelve | `php artisan icons:cache` |
| Panel Components | componentes/temas Filament nuevos | `php artisan filament:cache-components` |

`Views` sí se invalida sola por `mtime`: un cambio en un Blade se ve sin tocar nada.

Antes de concluir «mi código no se ejecuta», comprobar la cache correspondiente. Y si se limpia una, **volver a cachearla al terminar** solo si el usuario lo pide: dejarlas limpias es más seguro en local que dejarlas obsoletas.

`vite.config.js` solo compila cuatro temas Filament: `admin`, `agents`, `general`, `telemedicina`. Un tema nuevo en `resources/css/filament/` **no** se compila hasta agregarlo al array `input`.

Comandos propios en `app/Console/Commands/` (backup, exports de afiliaciones, imports CSV de inventario y prospectos, tarjetas de afiliado, SLA de helpdesk, recordatorios). Antes de escribir un script suelto, revisar si ya existe el comando.

### 2.2 Integración continua

- `.github/workflows/lint.yml`: corre `vendor/bin/pint` (el auto-commit del estilo está comentado, así que **el formateo debe venir ya hecho en el commit**).
- `.github/workflows/tests.yml`: `npm run build` + `./vendor/bin/pest`.
- Ambos workflows disparan solo en `push`/`pull_request` a **`develop`** y **`main`**, y **ninguna de esas dos ramas existe en `origin`** (`origin/HEAD` apunta a `master`; las ramas vivas son `master`, `incidencias`, `negocios`, `telemedicina`, `renovaciones`, `certificados`, `administracion`, `GESTION-MARCA-BLANCA`, `Domiciliaciones`, `domiciliaciones-r4`, `zona-de-descargas`). Es decir: **CI no corre nunca**. Pint y los tests afectados en local son la única red; no esperar que «lo valide el pipeline».
- `.trunk/` trae Trunk CLI configurado (actionlint, markdownlint, prettier, yamllint, trufflehog, osv-scanner, svgo, oxipng…), pero **no incluye ningún linter PHP** y sus acciones `trunk-fmt-pre-commit` / `trunk-check-pre-push` están **deshabilitadas**; CI tampoco lo invoca. No correr `trunk check` como si fuera el gate del proyecto: el gate del código PHP es `vendor/bin/pint --dirty`.
- CI usa **PHP 8.4** y Node 22 mientras el local es PHP 8.3.25 y `composer.json` pide `^8.2`. No usar sintaxis exclusiva de 8.4.

---

## 3. Arquitectura: 11 paneles Filament

Cada panel es un “producto” con login propio, SPA, notificaciones en base de datos, transacciones de formulario y theme Vite. Providers en `app/Providers/Filament/` y registro en `bootstrap/providers.php`.

Acceso: `User::canAccessPanel()` en `app/Models/User.php`. Casi todos los paneles internos exigen email `@tudrencasa.com`, `status = ACTIVO` y un valor en el array `departament`. **Ojo:** varias ramas usan `$this->status = 'ACTIVO'` (asignación). No “corregir” eso de pasada: es comportamiento histórico; cualquier cambio debe ser consciente y testeado.

Middleware extra interno: `App\Http\Middleware\DuplicatedSession` (business, operations, marketing, administration, telemedicina) para evitar sesiones duplicadas.

Logout interno: `POST /` ruta `internal`. Logout de red comercial: `POST /external` ruta `external` (redirige a `config('parameters.REDIRECT_LOGOUT_EXTERNAL_URL')`).

| Panel ID | Path | Quién entra | Color / notas | Discovery |
| --- | --- | --- | --- | --- |
| `admin` | `/admin` | `is_admin` + `@tudrencasa.com`. Panel **default**. | `#052F60`. Grupos TDEC, TDEV, SOLICITUDES, COTIZACIONES, AFILIACIONES, ADMINISTRACIÓN, TELEMEDICINA, MARKETING, CONFIGURACION, SISTEMA, HISTORICOS | `app/Filament/Resources`, `Pages`, `Widgets` |
| `business` | `/business` | Depto `NEGOCIOS` | `#4c566a`. Búsqueda global Ctrl/K, ticker helpdesk, tours, cluster `NuevosNegocios`, menú a Administración/Operaciones/Marketing si SUPERADMIN. Páginas: Agenda Corporativa, Calendarios TDG, generador QR | `app/Filament/Business/*` |
| `operations` | `/operations` | Depto `OPERACIONES` (o proveedor AMD / telemedicina con `supplier_id`) | `#2d89ca`. Widgets de afiliaciones, chat de seguimiento de casos | `app/Filament/Operations/*` |
| `administration` | `/administration` | Depto `ADMINISTRACION` | Amber. Cobranza, comisiones, RRHH, nómina, TDEV | `app/Filament/Administration/*` |
| `marketing` | `/marketing` | Depto `MARKETING` | `#17335e`. Masivos, cumpleaños, eventos, CAPEMIAC, viajes | `app/Filament/Marketing/*` |
| `agents` | `/agents` | `is_agent` | `#00DCCD`. Menú top según `Agent.conf_position_menu`. Perfil = editar su agente | `app/Filament/Agents/*` |
| `master` | `/master` | Agencia `agency_type === MASTER` | `#038C4C`. Jerarquía de red | `app/Filament/Master/*` |
| `general` | `/general` | Agencia `agency_type === GENERAL` | `#063467` | `app/Filament/General/*` |
| `telemedicina` | `/telemedicina` | Depto `TELEMEDICINA` (tiene registration) | `#005ca9` | `app/Filament/Telemedicina/*` |
| `projects` | `/projects` | Depto `PROYECTOS` | Amber. Scrum: epics, sprints, actividades, kanban | `app/Filament/Projects/*` |
| `metrics` | `/metrics` | `SUPERADMIN` **y** `METRICAS` | Teal. KPIs, mapa Venezuela | `app/Filament/Metrics/*` |

Avatares internos: `BoringAvatarsProvider` + foto de `RrhhColaborador` si existe (`User::getFilamentAvatarUrl()`).

Navegación de analistas: `App\Support\Filament\UserNavigationAccess` (módulos + slugs de `Permission` vía `user_permissions`). Superadmin bypassa restricciones de menú.

---

## 4. Dominio funcional (detalle)

### 4.1 Estructura comercial

- **Agency** (`agencies`): RIF, contactos, cuentas bancarias locales y USD, comisiones TDEC/TDEV (venta y renovación), documentos, tipo MASTER/GENERAL, condiciones, auditoría.
- **Agent** (`agents`): datos personales, códigos, owner, comisiones, menú, documentos, bitácora (`AgentNoteBlog`), observaciones.
- **ProspectAgent**: pipeline, tareas, observaciones, import CSV (`ProspectAgentCsvImporter`).
- **AccountManager**, **TravelAgency** / **TravelAgent**, **TdevAgency** / **TdevAgent**.
- **Zone / Region / State / City / Country**: catálogo geográfico (búsquedas, fichas, mapas).
- Jerarquía: páginas `ViewMyHierarchy` en agents/master/general.

Flujos públicos de alta:

- `/at/c` crear agente, `/ay/c` crear agencia
- `/ay/lk/{code}` agente bajo agencia, `/at/lk/{code}` subagente
- `/tdev/{token}`, `/tdev/agencia/{token}`, `/tdev/web/{token}` registro TDEV
- Chat/IA: `PublicChatController` + `App\Services\PublicAiAgent\AgentOrchestrator` (sesión `ChatSession`)

Al registrar, jobs envían WhatsApp/email de bienvenida y notifican analistas (`NotifyAnalystsOfTdevRegistrationJob`, cartas de bienvenida).

### 4.2 Producto: planes, tarifas, coberturas

- **Plan** ligado a unidad de negocio / línea. Relaciones: benefits, coverages, fees, agencies (`agency_plans`).
- **Fee**: tarifas por rango de edad / plan (`fee_plans`, y `plan_id` en fees en el trabajo reciente de catálogo). La forma de la fila depende del modo de precio del plan — ver §4.2.1.
- **Coverage**, **Benefit**, **BenefitCoverage**, **Limit**, **Sublimit**, **ConfigCostoBenefit**.
- **AgeRange**, **BusinessUnit**, **BusinessLine**.
- **PlanGenerator**: matriz de cotización corporativa (celdas, rates, imágenes, PDF). Lógica en `app/Support/PlanGenerators/` y `PlanGeneratorPdfService`.
- **WhiteCompany**: tarifas negociadas, planes asignados (`WhiteCompanyPlan` + `WhiteCompanyPlanAssignment`), documentos de marca, liquidaciones (`WhiteCompanyPaymentSettlement`).
- **Reporte de ventas de empresa aliada**: `WhiteCompanySalesReportService` arma el reporte por rango de fechas desde la **neta congelada en cada afiliación** (`white_company_neta` / `white_company_sale_price`), no desde la matriz de negociación vigente — así un reporte ya emitido sigue cuadrando aunque mañana se renegocien tarifas. Preview y envío por `WhiteCompanySalesReportController` (rutas `administration/white-companies/{whiteCompany}/sales-report/{preview,send}`), entrega en cola (`SendWhiteCompanySalesReportJob`, `SendWhiteCompanySalesReportWhatsAppJob`, `WhiteCompanySalesReportMail`) y verificación pública del documento en `/reporte-aliada/verificar/{key?}` con clave de `WhiteCompanySalesReportKey`.

### 4.2.1 Modos de precio de un plan (`plans.pricing_mode`)

Un plan se arma y se cobra de **dos maneras excluyentes**, según el enum `PlanPricingMode`. Este valor decide a la vez el formulario que ve el analista, la forma de las filas de `fees` y cómo se resuelve la tarifa de un afiliado. Es el eje del módulo de planes; no tocar `fees`, el asistente de plan ni el cálculo de tarifa sin entenderlo.

| Modo | Estructura | Fila de `fees` | Resolución de tarifa |
| --- | --- | --- | --- |
| `COBERTURAS` | El plan tiene coberturas propias; cada beneficio declara un costo límite por cobertura (`benefit_coverages.limit`, NULL = sin límite) | una por **(rango de edad, cobertura)**, con `coverage_id` | por (plan, cobertura, rango de edad) |
| `PAQUETE` | Sin coberturas; los beneficios van como un todo | una por **rango de edad**, con `coverage_id` **nulo** | por (plan, rango de edad) |

- **Escritura:** `App\Support\Plans\PlanStructurePersistence` (asistente de Negocios, `PlanWizardForm` + `CreatePlan`/`EditPlan`). Todo en una transacción: un plan a medio escribir deja cotizaciones y afiliaciones tomando precios inexistentes.
- **Lectura/cálculo:** `App\Support\AffiliationAffiliateFeeCalculator::planHasNoCoverages()`. **Antes esto era el número mágico `plan_id === 1`**; hoy es la columna, con el plan 1 solo como respaldo si `pricing_mode` aún no está poblado. `isInitialPlanWithoutCoverage()` queda `@deprecated` pero viva porque la llaman afiliaciones, renovaciones y tarifas negociadas — no borrarla de pasada.
- **Nada que pueda estar referenciado se borra a ciegas.** Una cobertura que sale del plan se **desvincula** (`plan_id` a NULL), no se elimina. Una tarifa con precio negociado por una empresa aliada **nunca se borra**: se marca `INACTIVO`, porque `white_company_fees.fee_id` la referencia y `affiliations.white_company_fee_id` apunta a esa negociación. Romper esa cadena descuadra reportes ya emitidos.
- Los IDs 1/2/3 (`INITIAL_PLAN_ID`, `IDEAL_PLAN_ID`, `SPECIAL_PLAN_ID`) siguen siendo constantes del calculador con reglas propias: el Ideal valida que la edad caiga en sus rangos y, si no, emite el mensaje de negociación hacia el Plan Especial.
- **`PlanQuotableScope`** (`individual` / `corporate` / `both`) acota dónde puede cotizarse un plan **Dress Tylor**; solo aplica si `plans.is_quotable`, y los planes BÁSICOS lo ignoran.
- Tests de referencia: `PlanPricingModeTest`, `PlanStructurePersistenceTest`, `PlanStructureMatrixTest`, `PlanQuotabilityTest`.

### 4.2.2 Plantillas PDF (DomPDF) — reglas que ya costaron romper el documento

Cuatro restricciones del motor, aprendidas rompiendo la propuesta económica. Partir de `resources/views/livewire/planes-cotizacion-estructura.blade.php`, que ya las aplica y las tiene fijadas en `tests/Unit/QuotePdfStructurePageRenderTest.php`.

1. **Márgenes con `padding` en celdas de tabla, no en `<div>`.** Un `<div>` al 100 % con `padding` desborda a la derecha aunque haya `box-sizing: border-box`: recorta el logo y la última columna. `@page { margin }` tampoco sirve cuando la plantilla se embebe en otro documento que ya declara el suyo.
2. **Un bloque por fila de tabla, no todo en una celda.** DomPDF no parte una celda entre páginas: un plan con muchos beneficios empuja la hoja entera y deja la primera en blanco. Varias filas (`page-cell-first`, `page-cell`, `page-cell-last`) permiten paginar entre bloques.
3. **`table-layout: fixed` con anchos explícitos.** Sin eso un nombre de beneficio largo estira la tabla y expulsa las últimas columnas.
4. **La fuente del glifo `✓` va en línea.** Solo `DejaVu Sans` lo trae; con Arial o Helvetica DomPDF imprime `?`. Declararla en el `<style>` de la plantilla no basta si el documento envolvente declara otra.

**Verificar un PDF por su pipeline real, no renderizando el componente aislado.** Una plantilla no es autónoma: la herencia de estilos del documento envolvente cambia el resultado y ninguna aserción sobre strings del HTML lo revela. Generar con `Pdf::loadHTML(view('documents.propuesta-economica', ...)->render())`, guardar en el scratchpad y **mirar la imagen**: `sips -s format png --out salida.png archivo.pdf` (macOS; `pdftoppm` no está instalado, y `sips` solo convierte la primera página).

Cualquier cambio de precio debe pasar por los updaters existentes (`FeePriceUpdater`, calculadoras de afiliación) para no desincronizar cotizaciones, afiliaciones y renovaciones.

### 4.3 Cotizaciones

**Individuales (`IndividualQuote`)**

1. Agente/agencia cotiza planes (Livewire `PlanesCotizacionIndividual*` + portadas PDF). El cliente abre la cotización interactiva Volt en `/in/{quote}/c` (individual) o `/cor/{quote}/c` (corporativa).
2. Estados de negocio (p. ej. PRE-APROBADA).
3. Follow-up WhatsApp programado días 3, 5, 7, 9 y 12 (`config/individual-quotes.php`, jobs `SendIndividualQuoteDay*FollowUp`).
4. A las 23:00 se anulan cotizaciones vencidas (`AnulateAgentQuotes`) si el centro de notificaciones lo tiene activo.
5. Pre-afiliación pública: `/plk/{id}`.

**Corporativas (`CorporateQuote`, `CorporateQuoteRequest`, `CorporateQuoteData`)**

- Solicitud → cotización → detalle de población → PDF (`CorporateQuotePdfController`, propuestas económicas por job: inicial, ideal, especial, múltiple).
- Importación de padrones (parsers de fecha de nacimiento, log de actividad de import).

**Dress Tylor**: cotizaciones de convenio aparte.

### 4.4 Afiliaciones

**Individual (`Affiliation` + `Affiliate` familia)**

- Código, agente/agencia, plan, pagador, titular, cuestionario médico (cuestion_1…16), firmas, tarifa anual, status, documentos.
- Pre-afiliación Livewire `IndividualPreAffiliation`.
- Documentos de negocio, ficha PDF, tarjeta con QR (`TarjetaAfiliacionController`, `AffiliateCard`, `GdPngQrCodeGenerator`).
- Voucher ILS y días restantes (`UpdateAffiliateIlsRemainingDays`, `AffiliateVaucherIlsRemainingDays`).
- Jobs: notificación de afiliación, tarjeta, reenvíos, push a Viveplus (`PushAffiliationDocumentToViveplusJob`).

**Corporativa (`AffiliationCorporate` + `AffiliateCorporate` + planes `AfilliationCorporatePlan`)**

- Población, documentos, certificados (`GenerateCorporateCertificateJob`, tarjetas en chunks).
- Pre-afiliación `/plk/c/{id}`.
- Asociados de empresa: link `/nb/{token}` (`CompanyAssociateRegistration`) y sincronización con telemedicina.

**Renovaciones**

- Scheduler 6:00: `PrepareAffiliationRenovations` y `PrepareAffiliationCorporateRenovations` (cuando faltan ~30 días a `effective_date + 1 año`, recálculo por edad).
- Aceptación: `AcceptAffiliationRenovationsService` / corporativo equivalente, precios en `RenovationManualAcceptancePricing`.
- Históricos: `AffiliationRenovationHistory`, `AffiliationCorporateRenovationHistory`.

Status y bitácora: `StatusLogAffiliationCorporate`, observaciones, `AffiliationObservation`.

### 4.5 Administración, cobranza y comisiones

Panel `administration` + recursos en admin:

- **Collection** / **AnnualCollection**: cobranza TDEC, días restantes (`UpdateAnnualCollectionRemainingDays` a las 6:00).
- **PaidMembership** / **PaidMembershipCorporate** / **CompanyPaidMembership**.
- **Sale**, **CheckSale**, **Commission**, **CommissionPayroll** (+ details).
- **CreditReconciliation**.
- **TdevReport**: compensación TDEV.
- Recibos y avisos: `ReciboDePagoIndividual`, `ReciboDePagoCorporativo`, `CreateAvisoDeCobro`, `SendAvisoDePago`.
- Exportes agencia/agente: `AdministrationAgencyReportsExportService`.

Cierre de mes: `CierreMesController`. Tasa BCV: `ApiBcvController` + `BcvOfficialRate`.

### 4.6 Operaciones

Red de servicio real (no solo “catálogo”):

- **Affiliate / AffiliateCorporate / CompanyAssociate (Nuevos Negocios)** para atender población.
- **Supplier** (clínicas jurídicas), **DoctorNurse**, **CorporateAlly**.
- **OperationServiceOrder**: órdenes, ítems, cotización a proveedor, PDF, CSV, caducidad a 10 días (`ExpireOperationServiceOrders` 7:30).
- **OperationCoordinationService**: coordinaciones, reversos, documentos de clínica, chat de seguimiento (`case-follow-up-chat-panel`).
- **OperationMedicalAppointment**.
- **Cuentas por pagar / cobrar** (`AccountsPayable`, `AccountsReceivable`).
- **Inventario Diagnomóvil**: productos, categorías, ubicaciones, entradas, salidas, movimientos, stock bajo (`NotifyOperationInventoryProductLowStockJob`, watchers). Deducción de medicamentos en telemedicina (`TelemedicineMedicationInventoryDeductor`).
- Indicadores de desempeño de colaboradores y proveedores.
- Mapas Google (`config/services.php` → `google_maps`, default Caracas).

Exportes CSV autenticados bajo `/operations/export-*`.

### 4.7 Telemedicina

Modelos densos: paciente, doctor, caso, consulta, historia, recetas, laboratorios, imagenología, alergias, medicamentos, informes AMD, documentos, prioridades, representantes, follow-ups, bitácora.

Flujos:

- Asociación afiliado ↔ paciente (`AssociateAffiliateWithTelemedicinePatientService` y variantes corporativa/asociado).
- Casos: asignación (`AssignedCase`), reverso, reasignación TDG, alta (`TelemedicineCaseDischargeGuard`), documentos (corto/largo, especialista, laboratorio, imagen, medicamentos) vía jobs `GeneratePdf*`.
- Recordatorio de medicación, informes, webhook Viveplus.
- Reportes de siniestralidad (preview PDF/CSV).
- Documentación de esquema con link firmado (`/docs/telemedicina/esquema`).

Panel médico: `telemedicina`. Operaciones ve la misma data con más herramientas.

### 4.7.1 Cupos clínicos (`app/Support/ClinicalEntitlements/`)

Subsistema que controla **cuántas veces** un afiliado puede consumir cada beneficio de su plan. Es independiente del tope comercial en USD (`benefit_coverages.limit`): ese es dinero, esto es conteo. Atraviesa planes (Negocios), consulta médica (Telemedicina) y las tarjetas de Operaciones, así que un cambio aquí se siente en tres paneles.

**Piezas**

- `ClinicalQuotaScope` — cómo se cuenta: `PER_AFFILIATION_YEAR` (por año de afiliación), `PER_CONTRACT` (una vez en toda la vigencia), `DISTINCT_CASES` (por casos distintos), `UNLIMITED`.
- `ClinicalServiceChannel` — la puerta por la que se consume; un beneficio se liga a **una sola**: `TYPE_1` (select de telemedicina), `MEDICATION`, `LABORATORY`, `IMAGING`, `SPECIALIST`.
- `PlanBenefitClinicalSetting` / `BenefitClinicalSetting` — la configuración por plan-beneficio. Se edita con `PlanBenefitClinicalFormSchema`; `PlanClinicalCompleteness` dice si un plan quedó bien configurado.
- `AffiliateClinicalEntitlementResolver` → `ClinicalEntitlementSnapshot` — resuelve el derecho vigente de un titular. Entra por paciente de telemedicina, `Affiliate` o `AffiliateCorporate` (el puente al plan es `TelemedicinePatientPlanBridge`) y **cachea en estático por request**; en un job largo que recorra muchos titulares, tenerlo en cuenta.
- `ClinicalEntitlementWindow` — calcula la ventana de conteo por **aniversario de `effective_date`**, no por año calendario.
- `ClinicalUsageLedger` — el libro mayor: `consume()` al guardar la consulta, `reverseForConsultation()` / `reverseForCase()` al revertir. Persiste en `AffiliateClinicalServiceUsage`. `TelemedicineCaseReversalService` depende de esto: revertir un caso **debe** devolver el cupo.

**Dos OTP distintos, no confundirlos**

| Clase | Modelo de reto | Para qué |
| --- | --- | --- |
| `ClinicalServiceOverrideOtp` | `ClinicalServiceOverrideChallenge` | El médico agotó el cupo y necesita autorización para pasarse |
| `ClinicalUsageAccessOtp` | `ClinicalUsageAccessChallenge` | Editar la configuración de cupos de un plan/beneficio (contextos de `ClinicalUsageAccessContext`) |

Ambos emiten (`issue`), reenvían (`resend`), verifican (`verify`) y marcan consumido (`markConsumed`); el segundo notifica a los SUPERADMIN. El campo de la UI es `App\Filament\Forms\Components\OtpBoxesInput`.

**Dónde se aplica el bloqueo:** `ClinicalQuotaFormGuard` corta en el **campo** del formulario de consulta, no al final del asistente — el médico ve el tope antes de recorrer todos los pasos, y la misma regla impide avanzar al pulsar «Siguiente». Al tocar el formulario de consulta, mantener esa propiedad.

Tarjetas de lectura en Operaciones: `OperationsClinicalQuotaCard` y `OperationsAffiliatePlanBenefitsCard`. Tests: `ClinicalEntitlementsTest`, `ClinicalQuotaFormGuardTest`, `ClinicalUsageAccessOtpTest`.

### 4.8 Marketing

- Notificaciones masivas email/WhatsApp/video (`MassNotification*`, throttle y lock en `config/mass-notifications.php`).
- Cumpleaños (`SendNotificationBirthday` 8:00, `WhatsAppBirthdayNotification`).
- Aniversarios de colaboradores.
- Eventos, listas de contacto, InfoFree, DataNotification, CAPEMIAC.
- Helpdesk compartido.

Scheduler: `DispatchScheduledMassNotifications` cada minuto; reconciliación de emails huérfanos cada 5 minutos.

### 4.9 Helpdesk (transversal)

Tickets en business, operations, administration y marketing. SLA (`MarkHelpdeskSlaBreachesJob` cada hora), CSAT, videos tutoriales, flujos de proceso, ticker Livewire `BusinessHelpdeskTicketsTicker`, tours, asignación por WhatsApp/email.

### 4.10 RRHH y nómina

`RrhhColaborador`, cargos, departamentos, asignaciones, deducciones, préstamos (+ detalle), vacaciones, constancias, nómina. Avatares usados en Filament. Notificaciones de aniversario.

### 4.11 Proyectos (Scrum interno)

Panel `projects`: Project, Subproject, Epic, Sprint, Activity, Group, Department. Ceremonias (`SprintCeremony`), notas, documentos, kanban (`app/Support/Filament/ProjectManagement/`). Usuario con traits `HasProjectManagement*`.

### 4.12 Métricas / KPI

Panel `metrics` (solo SUPERADMIN + METRICAS): dashboards, `VenezuelaActivityMapWidget`, clusters. No es un clon de business: es lectura analítica.

### 4.13 Sistema, auditoría y backups

- `SystemAuditTrace`, `UserLog`, `Log`, `Bitacora`, `SecurityAudit`.
- Tracker de sesión: `UserSessionAuditTracker` (login/logout).
- Imports Filament logueados (`LogFilamentImportActivity`).
- `BackupDatabase` job + `config/backup.php` (aviso WhatsApp, límite de adjunto).
- Centro de notificaciones: `SystemNotificationKey` + `SystemNotificationRecipients` (interruptores del scheduler).
- Permisos: modelo `Permission`, tabla `user_permissions`.

### 4.14 Chat público / agente IA

`PublicChatController`, `ChatSession`, `GuiaChatFeedback`, orquestador en `app/Services/PublicAiAgent/`. Registro guiado de agentes/agencias MASTER/GENERAL con WhatsApp de negocio (`config/services.php` `chat_agent_registration`).

---

## 5. Estructura de código (dónde va cada cosa)

```
app/
  Filament/                    # Un namespace por panel (ver tabla §3)
    Actions/                   # Acciones reutilizables (p. ej. ImportAction)
    Concerns/ Shared/          # Traits y componentes usados por varios paneles
    Exports/ Imports/          # Exporters e importers Filament
    Forms/Components/          # Campos de formulario propios (OtpBoxesInput)
    AvatarProviders/
    Widgets/                   # Widgets compartidos (WelcomeUserLiquidGlassWidget)
  Http/Controllers/            # PDF, CSV, webhooks, utilidades, Livewire pages clásicas
  Http/Middleware/             # DuplicatedSession, EnsurePresentationHubAccess
  Http/Requests/               # Form requests (validación HTTP)
  Jobs/                        # ~98 jobs: WhatsApp, PDF, mail, exports, renovaciones
  Livewire/                    # Flujos públicos de cotización/afiliación/registro
  Mail/  Notifications/
  Models/                      # ~268 modelos. ProjectManagement/ anidado
  Observers/
  Listeners/                   # Solo LogFilamentImportActivity
  Policies/                    # Casi vacío a propósito (ver §6): solo TelemedicineCaseMessagePolicy
  Providers/                   # AppServiceProvider + 11 PanelProviders + Volt
  Services/                    # Casos de uso de aplicación (PDF, exports, aceptaciones)
  Support/                     # Lógica de dominio reutilizable (preferir aquí antes de inflar modelos)
    Filament/                  # UI, navegación, permisos, tablas, global search
    Operations/ Telemedicine/ WhiteCompanies/ PlanGenerators/
    Companies/ Exports/ Imports/ GuiaChat/ Viveplus/ QrCode/ Audit/
  Enums/                       # StatusPago, FormaPago, SystemNotificationKey, etc.
  Casts/                       # Casts de TDEV y helpdesk (TdevStatusPagoCast, HelpdeskTeamMembersCast)
  Tables/Columns/              # Columnas Blade legadas de comisiones (CommissionAgent, PaymentInfo, …)
  Console/Commands/            # Backup, exports, imports CSV, tarjetas, SLA, permisos de menú
bootstrap/app.php              # Routing, middleware, excepciones (Laravel 12)
bootstrap/providers.php
config/                        # backup, mass-notifications, individual-quotes, plan-generator,
                               # scheduled-exports, scheduled-notifications, affiliate-card,
                               # supplier-report, parameters, services
database/migrations|factories|seeders
resources/css/filament/        # Temas Vite por panel (admin, agents, …)
resources/views/               # Blade: landings, PDFs, hooks de paneles, Flux/Volt
routes/web.php                 # Muy grande: públicos + PDF + exports + firmados
routes/console.php             # Scheduler
tests/Unit  tests/Feature
```

**Regla de colocación**

- UI Filament de un panel → `app/Filament/{Panel}/...`
- Regla de negocio reusable → `app/Support/...` (clases `final` con métodos estáticos o servicios inyectables, como el resto del repo)
- Orquestación con I/O (PDF, mail, CSV, APIs) → `app/Services/...`
- Trabajo asíncrono → `app/Jobs/...`
- HTTP público o descarga → controller delgado + service
- No meter queries gordas en el modelo salvo que el archivo hermano ya lo haga; extraer a Support.

Filament v4 en este repo:

- Forms/Infolists usan `Filament\Schemas\Components\*`
- Actions heredan `Filament\Actions\Action` (no `Filament\Tables\Actions`)
- Recursos: `Schemas/{Entity}Form.php`, `Tables/{Entity}Table.php`, `Pages/`, `RelationManagers/`
- Iconos: `Filament\Support\Icons\Heroicon` + sets Blade ya instalados

---

## 6. Autenticación, roles y permisos

Modelo `User` (`FilamentUser`):

Flags: `is_admin`, `is_agent`, `is_agency`, `is_doctor`, `is_subagent`, `is_patient`, `is_designer`, `is_business_admin`, `is_superAdmin`, `is_accountManagers`, `is_proveedor_amd`.

Campos de red: `code_agency`, `link_agency`, `link_agent`, `agency_type`, `agent_id`, `doctor_id`, `supplier_id`.

`departament` es **array** (cast). Valores usados: `SUPERADMIN`, `NEGOCIOS`, `OPERACIONES`, `ADMINISTRACION`, `MARKETING`, `TELEMEDICINA`, `PROYECTOS`, `METRICAS`.

Perfiles UI: `UserRoleProfiles`.

**No se usa Spatie Permission.** El sistema es propio:

- Modelo `Permission` (`slug`, `module`, `name`) y pivot `user_permissions`
- Mapa recurso/página → módulo + slugs: `DepartmentNavigationPermissionRegistry`
- Trait `AuthorizesDepartmentNavigation` en resources internos
- Acciones extra de Business: `BusinessFilamentActionPermissionRegistry`
- Helper `UserNavigationAccess` (`isSuperAdmin()`, `canAccessMenuItem()`)
- Policies Eloquent: casi no hay (excepción `TelemedicineCaseMessagePolicy`). La autorización vive en Filament `canAccess` + registries.

Nunca crear un recurso “visible para todos” en un panel interno. Copiar el patrón `hidden()` / `canViewAny()` del recurso hermano.

Logout dual: staff → `POST /` (`internal`); agentes/agencias → `POST /external` (`tudrencasa.com`).

---

## 7. Jobs, colas y scheduler

Definidos en `routes/console.php`. Muchos jobs respetan interruptores del Centro de notificaciones.

| Horario | Job | Función |
| --- | --- | --- |
| Diario 2:00 | `BackupDatabase` (si el centro de notificaciones lo activa) | Respaldo |
| Diario 6:00 | `UpdateAnnualCollectionRemainingDays` | Cobranza anual |
| Diario 6:00 | `PrepareAffiliationRenovations` | Renovaciones individuales |
| Diario 6:00 | `PrepareAffiliationCorporateRenovations` | Renovaciones corporativas |
| Diario 6:00 | `UpdateAffiliateIlsRemainingDays` | Voucher ILS |
| Diario 6:30 | auditoría identidad paciente/caso telemedicina | Consistencia clínica |
| Diario 7:00 | `SendDailyAuditSummary` (si está activo) | Resumen de auditoría |
| Diario 7:30 | `ExpireOperationServiceOrders` | Caduca OS |
| Diario 8:00 | `SendNotificationBirthday` | Cumpleaños |
| Diario 8:00 | `SendCollaboratorAnniversaryNotification` | Aniversario laboral |
| Diario 8:00–8:40 | Follow-ups cotización individual D+3…D+12 | WhatsApp escalonado |
| Diario 23:00 | `AnulateAgentQuotes` | Anula cotizaciones de agente |
| Cada hora | `MarkHelpdeskSlaBreachesJob` | SLA helpdesk |
| Cada minuto | `DispatchScheduledMassNotifications` | Disparo de masivos |
| Cada 5 min | `ReconcileOrphanedMassNotificationEmails` | Emails pending huérfanos |
| Diario 6:00–7:10 (escalonado) | `ExportIndividualAffiliations`, `ExportCorporateAffiliations` y `ExportScheduledEntity` para agents, agencies, natural_providers, juridical_providers, collaborators y doctors | Exports programados (`config/scheduled-exports.php`) |
| Diario 8:00 | `SendOperationInventoryLowStockAlert` | Stock bajo Diagnomóvil |

Todos los `Schedule::job(...)` van a la cola `system`, salvo las renovaciones que usan `renovations`. Otras tareas (backup DB, exports programados, resumen de auditoría, stock bajo) están condicionadas a flags. Leer `routes/console.php` completo antes de tocar horarios.

Colas nombradas usadas: `system`, `renovations`, y default. WhatsApp masivo: lock + throttle; **no** enviar en bucle síncrono.

Todo PDF pesado, Excel, certificado, tarjeta corporativa o mail masivo: **job**, no controller síncrono.

---

## 8. Integraciones externas

| Sistema | Uso | Config / código |
| --- | --- | --- |
| **UltraMsg (WhatsApp)** | Texto, imagen, video, documento: follow-ups, masivos, fichas, backups, helpdesk, cumpleaños | `config/parameters.php` (`CURLOPT_URL*`, `TOKEN`), `NotificationController`, jobs `SendNotificacionWhatsApp*` |
| **Correo** | Propuestas, recibos, bienvenidas, helpdesk (`app/Mail/`) | Laravel Mail + colas |
| **ViVEplus** | Push de certificados/carnets de empresas aliadas | `config/services.php` → `viveplus_documents`, `app/Support/Viveplus/*` |
| **R4 / Mi Banco** | Domiciliación CNTA/CELE, consulta de operación, link débito inmediato (`/ldi/{transaction_id}`) | `config/parameters.php` (`URL_R4_*`), Livewire `LinkDebitoInmediato` |
| **API BCV** | Tasa oficial | `ApiBcvController`, ruta `/tasa-bcv`, `BcvOfficialRate` |
| **integracorp-api** | KPIs del panel `metrics` | `app/Services/IntegracorpApi/*` |
| **Ollama** | LLM opcional del chat público / guía | `app/Services/Ollama/OllamaClient.php` |
| **Google Maps** | Mapas de proveedores y vistas públicas | `GOOGLE_MAPS_API_KEY` + Places + Directions |
| **QR** | Tarjetas, asociar plan, generador en Business | `simplesoftwareio/simple-qrcode` + `GdPngQrCodeGenerator` |
| **DomPDF / FPDI** | Fichas, cotizaciones, informes médicos, OS, avisos, recibos | `app/Services/*PdfService.php` |

Secretos: preferir `.env` → `config/*.php`. No copiar tokens UltraMsg/R4/Viveplus a código nuevo ni a este archivo. El hub WhatsApp histórico está en `NotificationController` (métodos estáticos); código nuevo debe reutilizar Support/Jobs, no duplicar cURL.

---

## 9. Rutas HTTP relevantes (no exhaustivo)

`routes/web.php` es el mapa de descargas y landings (~2100 líneas):

- Públicos: home, pre-afiliaciones `/plk/{id}` y `/plk/c/{id}`, registros agente/agencia/TDEV, asociados `/nb/{token}`, chat `/chat/publico` y `/guia-chat` (API `/api/public-chat/*`), presentaciones (hub con `EnsurePresentationHubAccess`).
- Cotizaciones interactivas **Volt** (no reinventar en Filament): `/in/{quote}/c` individual, `/cor/{quote}/c` corporativa. Vistas en `resources/views/livewire/volt/`.
- Autenticados: decenas de preview/download PDF y CSV (`business/export-*`, `operations/export-*`, `administration/export-*`).
- Firmados: documentación de esquema de telemedicina, RSVP de agenda corporativa, débito inmediato `/ldi/{transaction_id}`.
- Chat interno de flujos: `/api/internal/chat/*` (registro agente/agencia, cotización individual).

**Rutas de prueba/debug (no usar, no invocar, no “arreglar” ejecutándolas):** `/pp`, `/notify`, `/d`, `/truncate` (destructiva sobre `users`), `/r4/*`. No son herramientas de desarrollo.

Al agregar una descarga: middleware `auth` (o `signed`), autorización del panel/recurso, y service dedicado. No duplicar queries en el controller.

Livewire públicos en `app/Livewire/`: cotizadores individuales/corporativos (inicial, ideal, especial), portadas, pre-afiliación, upgrade de beneficio, débito inmediato, tablas de telemedicina, ticker de helpdesk.

Pantallas nuevas de **producto interno** van en Filament del panel correspondiente. Volt se reserva a los flujos públicos/interativos que ya existen (cotización, chat, settings del starter).

---

## 10. Convenciones de implementación en este repo

1. **Copiar al hermano.** Antes de inventar un Resource, abrir uno del mismo panel (p. ej. `AffiliationResource` de Business) y replicar Form/Table/Infolist/Widgets/Policies.
2. **PHP 8.3:** tipos explícitos, `casts()` method, constructor promotion, enums TitleCase, PHPDoc de array shapes, llaves siempre.
3. **Eloquent primero.** Relaciones con return type. Eager load. Evitar `DB::` salvo queries imposibles de expresar (y entonces query builder).
4. **Filament 4:** `Grid`/`Section` no ocupan todas las columnas por defecto: setear `columnSpanFull()` cuando el hermano lo hace. Filtros diferidos por defecto.
5. **SPA + notificaciones DB** ya están on. Polling 10s en business/telemedicina: no bajar a 1s.
6. **Idempotencia** en jobs de envío y webhooks (Viveplus tiene retries y excepciones transientes).
7. **Importaciones:** acciones Filament (`app/Filament/Actions/ImportAction.php`), logging, chunks; `retry_after` de Redis ya está en 900s por `ImportCsv::$timeout`.
8. **UI iOS-like** en business: helpers `FilamentIosButton`, presentaciones de export, liquid glass welcome widget. Respetar ese lenguaje visual en el panel business.
9. **No N+1 en tablas** de afiliados, cotizaciones, órdenes y casos: son las pantallas más usadas del día a día.
10. **Comentarios:** PHPDoc, no narrar lo obvio. Comentarios inline solo si la regla de negocio es realmente oscura (cuestionarios, comisiones, renovaciones).

---

## 11. Testing

- Pest 3. Unit en `tests/Unit` (~857 archivos), Feature en `tests/Feature` (18 archivos). Las cifras de este apartado envejecen: recontar con `ls tests/Unit/*.php | wc -l` antes de citarlas.
- La proporción es deliberada: **el estilo dominante es el test unitario que lee el código fuente** — assertions sobre strings de Resources, Schemas, Tables y registries, sin tocar base de datos ni contenedor. Copiar ese patrón antes de montar un test con DB.
- **`tests/Unit` no arranca la aplicación por defecto.** `tests/Pest.php` hace `pest()->extend(Tests\TestCase::class)` solo `->in('Feature')`. Un test de Unit es PHPUnit puro: `config()`, `app()` y las facades **no resuelven** (fallan con `Target class [config] does not exist`). Para tener contenedor hay que declarar `uses(Tests\TestCase::class);` — lo hacen ~237 de los 857 archivos. Y de esos 237, **solo 10 envuelven en transacción revertida**: el resto escribe en MySQL si el test escribe.
- **En cuanto un test de Unit arranca la aplicación, la conexión es la MySQL de desarrollo** (§0.2). Escribir sin transacción revertida ensucia la base del usuario.
- **`tests/Feature` está roto y no sirve como alternativa.** `tests/Pest.php` le aplica `RefreshDatabase`, `TestCase` lo redirige a sqlite `:memory:` (correcto, protege la base), pero el esquema no se puede construir desde cero: hay tablas sin migración de creación y revienta en `2025_07_14_145024_add_configuration_menu_to_agents.php` con `no such table: agents`. **Todo el suite de Feature falla hoy.** No montar tests nuevos ahí esperando que pasen.
- Helpers globales de `tests/Pest.php`: `ensureSqliteInMemoryDatabaseOrSkip()` e `insertPublicAiAgentTestAgency()` (siembra una agencia mínima para el chat público).
- **Fallos preexistentes**, ajenos a cualquier cambio nuevo — no perseguirlos ni "arreglarlos" borrando aserciones: `QuotePdfCoverageTableTest` (falla por `Target class [config] does not exist`, le falta el `uses`), `PlanCodeGeneratorTest` (2 tests con aserciones sobre strings que nunca existieron) y `PlanGeneratorTest` (exige un `canView` que `PlanGeneratorResource.php` nunca tuvo). Ante una corrida en rojo, confirmar con `git stash` antes de atribuírselo a lo propio.
- Nombrar tests en español está aceptado en el repo (`it('...', ...)`).
- Cubrir: autorización (403), validación, cálculo de tarifas, no regresión de navegación, jobs (fake Mail/Queue/Storage).
- Correr el mínimo: `php artisan test --filter=NombreDelTest`.
- Tras verde en lo afectado, **preguntar** si se corre el suite completo. No lanzar el suite entero por defecto (es grande).
- No borrar tests existentes.
- Al crear modelos nuevos: factory (preguntar seeder). `php artisan make:test --pest`.

---

## 12. Rendimiento y UX — checklist de cada PR

Antes de dar por cerrado un cambio, verificar:

1. ¿El usuario final espera menos de ~200–400 ms en interacciones de tabla/formulario? Si no, cola o query.
2. ¿Hay confirmación y rollback (transacción) en escrituras múltiples?
3. ¿Estados vacíos, errores y éxito están en español y se entienden?
4. ¿Se rompió un filtro, un RelationManager, un PDF o un permiso de otro panel que reutiliza el mismo modelo?
5. ¿Dark mode / logo / anchos `Width::Full` se mantienen?
6. ¿Se añadió índice si se filtra/ordena una columna nueva en tabla grande?
7. ¿Los documentos (PDF/QR/Excel) se generan igual en cola que en preview?
8. ¿Se evitó `get()` de colecciones enormes en widgets del dashboard?
9. ¿Pint + tests del módulo en verde?

---

## 13. Cómo desarrollar aquí (procedimiento)

1. Leer este archivo y el recurso/servicio hermano del módulo.
2. Buscar documentación de paquete con Boost `search-docs` **antes** de improvisar APIs de Filament/Livewire/Laravel.
3. Implementar de forma aditiva, con autorización, validación y UX completa.
4. Escribir o actualizar tests Pest. Ejecutar solo esos tests.
5. `vendor/bin/pint --dirty`.
6. No `migrate:fresh`. Migración nueva: idempotente y aplicada con `--path` (§2.1), con el usuario de acuerdo.
7. Responder siempre en español: qué cambió, por qué, riesgos, cómo probar en UI (panel + URL de Herd).

URLs para el usuario: usar el tool `get-absolute-url` de Boost (esquema/host/puerto correctos de Herd). No inventar `localhost:8000`.

Frontend que “no se ve”: pedir `npm run dev` / `npm run build` / `composer run dev`. No abrir puertos.

---

## 14. Glosario rápido

- **TDG / Tu Dr En Casa / IntegraCorp:** misma organización; IntegraCorp es el software.
- **TDEC / TDEV:** líneas salud vs viajes.
- **Ficha:** PDF resumido de entidad (agencia, agente, afiliación, proveedor).
- **OS:** orden de servicio de operaciones.
- **AMD:** proveedor/atención médica domiciliaria o red asociada (`is_proveedor_amd`).
- **ILS / vaucher:** comprobante de vigencia / días restantes del afiliado.
- **White company:** marca blanca con tarifas y planes propios.
- **Padron / población:** listado de asegurados corporativos.
- **Centro de notificaciones:** interruptores que prenden o apagan jobs del scheduler.
- **Cupo clínico:** número de veces que un afiliado puede consumir un beneficio (§4.7.1). No es el tope en USD.
- **Paquete / Coberturas:** los dos modos de precio de un plan (§4.2.1).
- **Diagnomóvil:** inventario operativo de insumos/medicamentos.
- **R4:** pasarela de domiciliación de Mi Banco (CNTA/CELE).
- **UltraMsg:** API de WhatsApp usada por notificaciones.
- **Guía Chat:** agente conversacional público (`/guia-chat`) para registro y cotización.
- **TDG-100:** código de la casa matriz en la red de agencias.

---

## 15. Archivos de arranque que un agente debe conocer

| Archivo | Por qué |
| --- | --- |
| `app/Models/User.php` | Acceso a paneles y roles |
| `app/Support/Filament/UserNavigationAccess.php` | Menús y helper de permisos |
| `app/Support/Filament/DepartmentNavigationPermissionRegistry.php` | Mapa recurso → slug/módulo |
| `config/parameters.php` | URLs públicas, UltraMsg, R4, logout |
| `bootstrap/providers.php` | Paneles registrados |
| `routes/web.php` / `routes/console.php` | HTTP y cron |
| `app/Providers/AppServiceProvider.php` | Colores Filament, observers, imports, widgets Livewire |
| `config/services.php` | Maps, chat, Viveplus |
| `app/Http/Controllers/NotificationController.php` | Hub histórico WhatsApp |
| `app/Services/PublicAiAgent/AgentOrchestrator.php` | Chat / guía pública |
| `app/Support/AffiliationAffiliateFeeCalculator.php` | Cómo se resuelve la tarifa de un afiliado (§4.2.1) |
| `app/Support/Plans/PlanStructurePersistence.php` | Cómo se escribe un plan y qué nunca se borra |
| `app/Support/ClinicalEntitlements/ClinicalUsageLedger.php` | Consumo y reverso de cupos clínicos (§4.7.1) |
| `tests/TestCase.php` | Guarda dura: fuerza sqlite `:memory:` en tests que recrean el esquema |
| `tests/Pest.php` | Helpers globales (`ensureSqliteInMemoryDatabaseOrSkip`) y `RefreshDatabase` en Feature |
| `phpunit.xml` | Declara sqlite `:memory:`… que la config cacheada ignora (§0.2) |
| `vite.config.js` | Qué temas Filament se compilan |
| `.cursor/rules/agent-no-refresh-database.mdc` | Misma prohibición de wipe a nivel Cursor |
| `.cursor/rules/integracorp-agente.mdc` | Resumen de los mandatos del §0 para Cursor |

**Instrucciones duplicadas:** `.github/copilot-instructions.md`, `.junie/guidelines.md` y `.cursor/rules/laravel-boost.mdc` son copias generadas de las mismas guías de Laravel Boost que van al final de este archivo. Se regeneran con `php artisan boost:install`; no editarlas a mano ni tratarlas como fuente de verdad distinta. Lo específico de IntegraCorp vive solo aquí y en `.cursor/rules/integracorp-agente.mdc`.

---

# Laravel Boost Guidelines

Las secciones siguientes son las guías oficiales de Boost para este stack. **No anulan** los mandatos del §0 (español, no destruir la DB, robustez, UX). En caso de duda, §0 gana.

)
<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to enhance the user's satisfaction building Laravel applications.

## Foundational Context
This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.3.25
- filament/filament (FILAMENT) - v4
- laravel/framework (LARAVEL) - v12
- laravel/prompts (PROMPTS) - v0
- livewire/flux (FLUXUI_FREE) - v2
- livewire/flux-pro (FLUXUI_PRO) - v2
- livewire/livewire (LIVEWIRE) - v3
- livewire/volt (VOLT) - v1
- laravel/pint (PINT) - v1
- laravel/sail (SAIL) - v1
- pestphp/pest (PEST) - v3
- tailwindcss (TAILWINDCSS) - v4


## Conventions
- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts
- Do not create verification scripts or tinker when tests cover that functionality and prove it works. Unit and feature tests are more important.

## Application Structure & Architecture
- Stick to existing directory structure - don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling
- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Replies
- Be concise in your explanations - focus on what's important rather than explaining obvious details.

## Documentation Files
- You must only create documentation files if explicitly requested by the user.


=== boost rules ===

## Laravel Boost
- Laravel Boost is an MCP server that comes with powerful tools designed specifically for this application. Use them.

## Artisan
- Use the `list-artisan-commands` tool when you need to call an Artisan command to double check the available parameters.

## URLs
- Whenever you share a project URL with the user you should use the `get-absolute-url` tool to ensure you're using the correct scheme, domain / IP, and port.

## Tinker / Debugging
- You should use the `tinker` tool when you need to execute PHP to debug code or query Eloquent models directly.
- Use the `database-query` tool when you only need to read from the database.

## Reading Browser Logs With the `browser-logs` Tool
- You can read browser logs, errors, and exceptions using the `browser-logs` tool from Boost.
- Only recent browser logs will be useful - ignore old logs.

## Searching Documentation (Critically Important)
- Boost comes with a powerful `search-docs` tool you should use before any other approaches. This tool automatically passes a list of installed packages and their versions to the remote Boost API, so it returns only version-specific documentation specific for the user's circumstance. You should pass an array of packages to filter on if you know you need docs for particular packages.
- The 'search-docs' tool is perfect for all Laravel related packages, including Laravel, Inertia, Livewire, Filament, Tailwind, Pest, Nova, Nightwatch, etc.
- You must use this tool to search for Laravel-ecosystem documentation before falling back to other approaches.
- Search the documentation before making code changes to ensure we are taking the correct approach.
- Use multiple, broad, simple, topic based queries to start. For example: `['rate limiting', 'routing rate limiting', 'routing']`.
- Do not add package names to queries - package information is already shared. For example, use `test resource table`, not `filament 4 test resource table`.

### Available Search Syntax
- You can and should pass multiple queries at once. The most relevant results will be returned first.

1. Simple Word Searches with auto-stemming - query=authentication - finds 'authenticate' and 'auth'
2. Multiple Words (AND Logic) - query=rate limit - finds knowledge containing both "rate" AND "limit"
3. Quoted Phrases (Exact Position) - query="infinite scroll" - Words must be adjacent and in that order
4. Mixed Queries - query=middleware "rate limit" - "middleware" AND exact phrase "rate limit"
5. Multiple Queries - queries=["authentication", "middleware"] - ANY of these terms


=== php rules ===

## PHP

- Always use curly braces for control structures, even if it has one line.

### Constructors
- Use PHP 8 constructor property promotion in `__construct()`.
    - <code-snippet>public function __construct(public GitHub $github) { }</code-snippet>
- Do not allow empty `__construct()` methods with zero parameters.

### Type Declarations
- Always use explicit return type declarations for methods and functions.
- Use appropriate PHP type hints for method parameters.

<code-snippet name="Explicit Return Types and Method Params" lang="php">
protected function isAccessible(User $user, ?string $path = null): bool
{
    ...
}
</code-snippet>

## Comments
- Prefer PHPDoc blocks over comments. Never use comments within the code itself unless there is something _very_ complex going on.

## PHPDoc Blocks
- Add useful array shape type definitions for arrays when appropriate.

## Enums
- Typically, keys in an Enum should be TitleCase. For example: `FavoritePerson`, `BestLake`, `Monthly`.


=== herd rules ===

## Laravel Herd

- The application is served by Laravel Herd and will be available at: https?://[kebab-case-project-dir].test. Use the `get-absolute-url` tool to generate URLs for the user to ensure valid URLs.
- You must not run any commands to make the site available via HTTP(s). It is _always_ available through Laravel Herd.


=== filament/core rules ===

## Filament
- Filament is used by this application, check how and where to follow existing application conventions.
- Filament is a Server-Driven UI (SDUI) framework for Laravel. It allows developers to define user interfaces in PHP using structured configuration objects. It is built on top of Livewire, Alpine.js, and Tailwind CSS.
- You can use the `search-docs` tool to get information from the official Filament documentation when needed. This is very useful for Artisan command arguments, specific code examples, testing functionality, relationship management, and ensuring you're following idiomatic practices.
- Utilize static `make()` methods for consistent component initialization.

### Artisan
- You must use the Filament specific Artisan commands to create new files or components for Filament. You can find these with the `list-artisan-commands` tool, or with `php artisan` and the `--help` option.
- Inspect the required options, always pass `--no-interaction`, and valid arguments for other options when applicable.

### Filament's Core Features
- Actions: Handle doing something within the application, often with a button or link. Actions encapsulate the UI, the interactive modal window, and the logic that should be executed when the modal window is submitted. They can be used anywhere in the UI and are commonly used to perform one-time actions like deleting a record, sending an email, or updating data in the database based on modal form input.
- Forms: Dynamic forms rendered within other features, such as resources, action modals, table filters, and more.
- Infolists: Read-only lists of data.
- Notifications: Flash notifications displayed to users within the application.
- Panels: The top-level container in Filament that can include all other features like pages, resources, forms, tables, notifications, actions, infolists, and widgets.
- Resources: Static classes that are used to build CRUD interfaces for Eloquent models. Typically live in `app/Filament/Resources`.
- Schemas: Represent components that define the structure and behavior of the UI, such as forms, tables, or lists.
- Tables: Interactive tables with filtering, sorting, pagination, and more.
- Widgets: Small component included within dashboards, often used for displaying data in charts, tables, or as a stat.

### Relationships
- Determine if you can use the `relationship()` method on form components when you need `options` for a select, checkbox, repeater, or when building a `Fieldset`:

<code-snippet name="Relationship example for Form Select" lang="php">
Forms\Components\Select::make('user_id')
    ->label('Author')
    ->relationship('author')
    ->required(),
</code-snippet>


## Testing
- It's important to test Filament functionality for user satisfaction.
- Ensure that you are authenticated to access the application within the test.
- Filament uses Livewire, so start assertions with `livewire()` or `Livewire::test()`.

### Example Tests

<code-snippet name="Filament Table Test" lang="php">
    livewire(ListUsers::class)
        ->assertCanSeeTableRecords($users)
        ->searchTable($users->first()->name)
        ->assertCanSeeTableRecords($users->take(1))
        ->assertCanNotSeeTableRecords($users->skip(1))
        ->searchTable($users->last()->email)
        ->assertCanSeeTableRecords($users->take(-1))
        ->assertCanNotSeeTableRecords($users->take($users->count() - 1));
</code-snippet>

<code-snippet name="Filament Create Resource Test" lang="php">
    livewire(CreateUser::class)
        ->fillForm([
            'name' => 'Howdy',
            'email' => 'howdy@example.com',
        ])
        ->call('create')
        ->assertNotified()
        ->assertRedirect();

    assertDatabaseHas(User::class, [
        'name' => 'Howdy',
        'email' => 'howdy@example.com',
    ]);
</code-snippet>

<code-snippet name="Testing Multiple Panels (setup())" lang="php">
    use Filament\Facades\Filament;

    Filament::setCurrentPanel('app');
</code-snippet>

<code-snippet name="Calling an Action in a Test" lang="php">
    livewire(EditInvoice::class, [
        'invoice' => $invoice,
    ])->callAction('send');

    expect($invoice->refresh())->isSent()->toBeTrue();
</code-snippet>


=== filament/v4 rules ===

## Filament 4

### Important Version 4 Changes
- File visibility is now `private` by default.
- The `deferFilters` method from Filament v3 is now the default behavior in Filament v4, so users must click a button before the filters are applied to the table. To disable this behavior, you can use the `deferFilters(false)` method.
- The `Grid`, `Section`, and `Fieldset` layout components no longer span all columns by default.
- The `all` pagination page method is not available for tables by default.
- All action classes extend `Filament\Actions\Action`. No action classes exist in `Filament\Tables\Actions`.
- The `Form` & `Infolist` layout components have been moved to `Filament\Schemas\Components`, for example `Grid`, `Section`, `Fieldset`, `Tabs`, `Wizard`, etc.
- A new `Repeater` component for Forms has been added.
- Icons now use the `Filament\Support\Icons\Heroicon` Enum by default. Other options are available and documented.

### Organize Component Classes Structure
- Schema components: `Schemas/Components/`
- Table columns: `Tables/Columns/`
- Table filters: `Tables/Filters/`
- Actions: `Actions/`


=== laravel/core rules ===

## Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using the `list-artisan-commands` tool.
- If you're creating a generic PHP class, use `artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Database
- Always use proper Eloquent relationship methods with return type hints. Prefer relationship methods over raw queries or manual joins.
- Use Eloquent models and relationships before suggesting raw database queries
- Avoid `DB::`; prefer `Model::query()`. Generate code that leverages Laravel's ORM capabilities rather than bypassing them.
- Generate code that prevents N+1 query problems by using eager loading.
- Use Laravel's query builder for very complex database operations.

### Model Creation
- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `list-artisan-commands` to check the available options to `php artisan make:model`.

### APIs & Eloquent Resources
- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

### Controllers & Validation
- Always create Form Request classes for validation rather than inline validation in controllers. Include both validation rules and custom error messages.
- Check sibling Form Requests to see if the application uses array or string based validation rules.

### Queues
- Use queued jobs for time-consuming operations with the `ShouldQueue` interface.

### Authentication & Authorization
- Use Laravel's built-in authentication and authorization features (gates, policies, Sanctum, etc.).

### URL Generation
- When generating links to other pages, prefer named routes and the `route()` function.

### Configuration
- Use environment variables only in configuration files - never use the `env()` function directly outside of config files. Always use `config('app.name')`, not `env('APP_NAME')`.

### Testing
- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] <name>` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

### Vite Error
- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.


=== laravel/v12 rules ===

## Laravel 12

- Use the `search-docs` tool to get version specific documentation.
- Since Laravel 11, Laravel has a new streamlined file structure which this project uses.

### Laravel 12 Structure
- No middleware files in `app/Http/Middleware/`.
- `bootstrap/app.php` is the file to register middleware, exceptions, and routing files.
- `bootstrap/providers.php` contains application specific service providers.
- **No app\Console\Kernel.php** - use `bootstrap/app.php` or `routes/console.php` for console configuration.
- **Commands auto-register** - files in `app/Console/Commands/` are automatically available and do not require manual registration.

### Database
- When modifying a column, the migration must include all of the attributes that were previously defined on the column. Otherwise, they will be dropped and lost.
- Laravel 11 allows limiting eagerly loaded records natively, without external packages: `$query->latest()->limit(10);`.

### Models
- Casts can and likely should be set in a `casts()` method on a model rather than the `$casts` property. Follow existing conventions from other models.


=== fluxui-free/core rules ===

## Flux UI Free

- This project is using the free edition of Flux UI. It has full access to the free components and variants, but does not have access to the Pro components.
- Flux UI is a component library for Livewire. Flux is a robust, hand-crafted, UI component library for your Livewire applications. It's built using Tailwind CSS and provides a set of components that are easy to use and customize.
- You should use Flux UI components when available.
- Fallback to standard Blade components if Flux is unavailable.
- If available, use Laravel Boost's `search-docs` tool to get the exact documentation and code snippets available for this project.
- Flux UI components look like this:

<code-snippet name="Flux UI Component Usage Example" lang="blade">
    <flux:button variant="primary"/>
</code-snippet>


### Available Components
This is correct as of Boost installation, but there may be additional components within the codebase.

<available-flux-components>
avatar, badge, brand, breadcrumbs, button, callout, checkbox, dropdown, field, heading, icon, input, modal, navbar, profile, radio, select, separator, switch, text, textarea, tooltip
</available-flux-components>


=== fluxui-pro/core rules ===

## Flux UI Pro

- This project is using the Pro version of Flux UI. It has full access to the free components and variants, as well as full access to the Pro components and variants.
- Flux UI is a component library for Livewire. Flux is a robust, hand-crafted, UI component library for your Livewire applications. It's built using Tailwind CSS and provides a set of components that are easy to use and customize.
- You should use Flux UI components when available.
- Fallback to standard Blade components if Flux is unavailable.
- If available, use Laravel Boost's `search-docs` tool to get the exact documentation and code snippets available for this project.
- Flux UI components look like this:

<code-snippet name="Flux UI component usage example" lang="blade">
    <flux:button variant="primary"/>
</code-snippet>


### Available Components
This is correct as of Boost installation, but there may be additional components within the codebase.

<available-flux-components>
accordion, autocomplete, avatar, badge, brand, breadcrumbs, button, calendar, callout, card, chart, checkbox, command, context, date-picker, dropdown, editor, field, heading, icon, input, modal, navbar, pagination, popover, profile, radio, select, separator, switch, table, tabs, text, textarea, toast, tooltip
</available-flux-components>


=== livewire/core rules ===

## Livewire Core
- Use the `search-docs` tool to find exact version specific documentation for how to write Livewire & Livewire tests.
- Use the `php artisan make:livewire [Posts\CreatePost]` artisan command to create new components
- State should live on the server, with the UI reflecting it.
- All Livewire requests hit the Laravel backend, they're like regular HTTP requests. Always validate form data, and run authorization checks in Livewire actions.

## Livewire Best Practices
- Livewire components require a single root element.
- Use `wire:loading` and `wire:dirty` for delightful loading states.
- Add `wire:key` in loops:

    ```blade
    @foreach ($items as $item)
        <div wire:key="item-{{ $item->id }}">
            {{ $item->name }}
        </div>
    @endforeach
    ```

- Prefer lifecycle hooks like `mount()`, `updatedFoo()`) for initialization and reactive side effects:

<code-snippet name="Lifecycle hook examples" lang="php">
    public function mount(User $user) { $this->user = $user; }
    public function updatedSearch() { $this->resetPage(); }
</code-snippet>


## Testing Livewire

<code-snippet name="Example Livewire component test" lang="php">
    Livewire::test(Counter::class)
        ->assertSet('count', 0)
        ->call('increment')
        ->assertSet('count', 1)
        ->assertSee(1)
        ->assertStatus(200);
</code-snippet>


    <code-snippet name="Testing a Livewire component exists within a page" lang="php">
        $this->get('/posts/create')
        ->assertSeeLivewire(CreatePost::class);
    </code-snippet>


=== livewire/v3 rules ===

## Livewire 3

### Key Changes From Livewire 2
- These things changed in Livewire 2, but may not have been updated in this application. Verify this application's setup to ensure you conform with application conventions.
    - Use `wire:model.live` for real-time updates, `wire:model` is now deferred by default.
    - Components now use the `App\Livewire` namespace (not `App\Http\Livewire`).
    - Use `$this->dispatch()` to dispatch events (not `emit` or `dispatchBrowserEvent`).
    - Use the `components.layouts.app` view as the typical layout path (not `layouts.app`).

### New Directives
- `wire:show`, `wire:transition`, `wire:cloak`, `wire:offline`, `wire:target` are available for use. Use the documentation to find usage examples.

### Alpine
- Alpine is now included with Livewire, don't manually include Alpine.js.
- Plugins included with Alpine: persist, intersect, collapse, and focus.

### Lifecycle Hooks
- You can listen for `livewire:init` to hook into Livewire initialization, and `fail.status === 419` for the page expiring:

<code-snippet name="livewire:load example" lang="js">
document.addEventListener('livewire:init', function () {
    Livewire.hook('request', ({ fail }) => {
        if (fail && fail.status === 419) {
            alert('Your session expired');
        }
    });

    Livewire.hook('message.failed', (message, component) => {
        console.error(message);
    });
});
</code-snippet>


=== volt/core rules ===

## Livewire Volt

- This project uses Livewire Volt for interactivity within its pages. New pages requiring interactivity must also use Livewire Volt. There is documentation available for it.
- Make new Volt components using `php artisan make:volt [name] [--test] [--pest]`
- Volt is a **class-based** and **functional** API for Livewire that supports single-file components, allowing a component's PHP logic and Blade templates to co-exist in the same file
- Livewire Volt allows PHP logic and Blade templates in one file. Components use the `@livewire("volt-anonymous-fragment-eyJuYW1lIjoidm9sdC1hbm9ueW1vdXMtZnJhZ21lbnQtYmQ5YWJiNTE3YWMyMTgwOTA1ZmUxMzAxODk0MGJiZmIiLCJwYXRoIjoic3RvcmFnZVwvZnJhbWV3b3JrXC92aWV3c1wvMTUxYWRjZWRjMzBhMzllOWIxNzQ0ZDRiMWRjY2FjYWIuYmxhZGUucGhwIn0=", Livewire\Volt\Precompilers\ExtractFragments::componentArguments([...get_defined_vars(), ...array (
)]))
</code-snippet>


### Volt Class Based Component Example
To get started, define an anonymous class that extends Livewire\Volt\Component. Within the class, you may utilize all of the features of Livewire using traditional Livewire syntax:


<code-snippet name="Volt Class-based Volt Component Example" lang="php">
use Livewire\Volt\Component;

new class extends Component {
    public $count = 0;

    public function increment()
    {
        $this->count++;
    }
} ?>

<div>
    <h1>{{ $count }}</h1>
    <button wire:click="increment">+</button>
</div>
</code-snippet>


### Testing Volt & Volt Components
- Use the existing directory for tests if it already exists. Otherwise, fallback to `tests/Feature/Volt`.

<code-snippet name="Livewire Test Example" lang="php">
use Livewire\Volt\Volt;

test('counter increments', function () {
    Volt::test('counter')
        ->assertSee('Count: 0')
        ->call('increment')
        ->assertSee('Count: 1');
});
</code-snippet>


<code-snippet name="Volt Component Test Using Pest" lang="php">
declare(strict_types=1);

use App\Models\{User, Product};
use Livewire\Volt\Volt;

test('product form creates product', function () {
    $user = User::factory()->create();

    Volt::test('pages.products.create')
        ->actingAs($user)
        ->set('form.name', 'Test Product')
        ->set('form.description', 'Test Description')
        ->set('form.price', 99.99)
        ->call('create')
        ->assertHasNoErrors();

    expect(Product::where('name', 'Test Product')->exists())->toBeTrue();
});
</code-snippet>


### Common Patterns


<code-snippet name="CRUD With Volt" lang="php">
<?php

use App\Models\Product;
use function Livewire\Volt\{state, computed};

state(['editing' => null, 'search' => '']);

$products = computed(fn() => Product::when($this->search,
    fn($q) => $q->where('name', 'like', "%{$this->search}%")
)->get());

$edit = fn(Product $product) => $this->editing = $product->id;
$delete = fn(Product $product) => $product->delete();

?>

<!-- HTML / UI Here -->
</code-snippet>

<code-snippet name="Real-Time Search With Volt" lang="php">
    <flux:input
        wire:model.live.debounce.300ms="search"
        placeholder="Search..."
    />
</code-snippet>

<code-snippet name="Loading States With Volt" lang="php">
    <flux:button wire:click="save" wire:loading.attr="disabled">
        <span wire:loading.remove>Save</span>
        <span wire:loading>Saving...</span>
    </flux:button>
</code-snippet>


=== pint/core rules ===

## Laravel Pint Code Formatter

- You must run `vendor/bin/pint --dirty` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test`, simply run `vendor/bin/pint` to fix any formatting issues.


=== pest/core rules ===

## Pest

### Testing
- If you need to verify a feature is working, write or update a Unit / Feature test.

### Pest Tests
- All tests must be written using Pest. Use `php artisan make:test --pest <name>`.
- You must not remove any tests or test files from the tests directory without approval. These are not temporary or helper files - these are core to the application.
- Tests should test all of the happy paths, failure paths, and weird paths.
- Tests live in the `tests/Feature` and `tests/Unit` directories.
- Pest tests look and behave like this:
<code-snippet name="Basic Pest Test Example" lang="php">
it('is true', function () {
    expect(true)->toBeTrue();
});
</code-snippet>

### Running Tests
- Run the minimal number of tests using an appropriate filter before finalizing code edits.
- To run all tests: `php artisan test`.
- To run all tests in a file: `php artisan test tests/Feature/ExampleTest.php`.
- To filter on a particular test name: `php artisan test --filter=testName` (recommended after making a change to a related file).
- When the tests relating to your changes are passing, ask the user if they would like to run the entire test suite to ensure everything is still passing.

### Pest Assertions
- When asserting status codes on a response, use the specific method like `assertForbidden` and `assertNotFound` instead of using `assertStatus(403)` or similar, e.g.:
<code-snippet name="Pest Example Asserting postJson Response" lang="php">
it('returns all', function () {
    $response = $this->postJson('/api/docs', []);

    $response->assertSuccessful();
});
</code-snippet>

### Mocking
- Mocking can be very helpful when appropriate.
- When mocking, you can use the `Pest\Laravel\mock` Pest function, but always import it via `use function Pest\Laravel\mock;` before using it. Alternatively, you can use `$this->mock()` if existing tests do.
- You can also create partial mocks using the same import or self method.

### Datasets
- Use datasets in Pest to simplify tests which have a lot of duplicated data. This is often the case when testing validation rules, so consider going with this solution when writing tests for validation rules.

<code-snippet name="Pest Dataset Example" lang="php">
it('has emails', function (string $email) {
    expect($email)->not->toBeEmpty();
})->with([
    'james' => 'james@laravel.com',
    'taylor' => 'taylor@laravel.com',
]);
</code-snippet>


=== tailwindcss/core rules ===

## Tailwind Core

- Use Tailwind CSS classes to style HTML, check and use existing tailwind conventions within the project before writing your own.
- Offer to extract repeated patterns into components that match the project's conventions (i.e. Blade, JSX, Vue, etc..)
- Think through class placement, order, priority, and defaults - remove redundant classes, add classes to parent or child carefully to limit repetition, group elements logically
- You can use the `search-docs` tool to get exact examples from the official documentation when needed.

### Spacing
- When listing items, use gap utilities for spacing, don't use margins.

    <code-snippet name="Valid Flex Gap Spacing Example" lang="html">
        <div class="flex gap-8">
            <div>Superior</div>
            <div>Michigan</div>
            <div>Erie</div>
        </div>
    </code-snippet>


### Dark Mode
- If existing pages and components support dark mode, new pages and components must support dark mode in a similar way, typically using `dark:`.


=== tailwindcss/v4 rules ===

## Tailwind 4

- Always use Tailwind CSS v4 - do not use the deprecated utilities.
- `corePlugins` is not supported in Tailwind v4.
- In Tailwind v4, you import Tailwind using a regular CSS `@import` statement, not using the `@tailwind` directives used in v3:

<code-snippet name="Tailwind v4 Import Tailwind Diff" lang="diff"
   - @tailwind base;
   - @tailwind components;
   - @tailwind utilities;
   + @import "tailwindcss";
</code-snippet>


### Replaced Utilities
- Tailwind v4 removed deprecated utilities. Do not use the deprecated option - use the replacement.
- Opacity values are still numeric.

| Deprecated |	Replacement |
|------------+--------------|
| bg-opacity-* | bg-black/* |
| text-opacity-* | text-black/* |
| border-opacity-* | border-black/* |
| divide-opacity-* | divide-black/* |
| ring-opacity-* | ring-black/* |
| placeholder-opacity-* | placeholder-black/* |
| flex-shrink-* | shrink-* |
| flex-grow-* | grow-* |
| overflow-ellipsis | text-ellipsis |
| decoration-slice | box-decoration-slice |
| decoration-clone | box-decoration-clone |


=== tests rules ===

## Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test` with a specific filename or filter.
</laravel-boost-guidelines>