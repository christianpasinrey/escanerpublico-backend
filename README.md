# Escáner Público — Backend

> **API pública y abierta de transparencia del sector público español.**
> Contratos, subvenciones, legislación y cargos. Datos oficiales parseados, normalizados y consultables vía REST.

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![PHP 8.4+](https://img.shields.io/badge/PHP-8.4+-777BB4.svg)](https://www.php.net/)
[![Laravel 12](https://img.shields.io/badge/Laravel-12-FF2D20.svg)](https://laravel.com/)

---

## Qué es

**Escáner Público** ingesta cuatro fuentes oficiales del Estado español, las normaliza en una BD relacional rica y las expone vía una API REST pública con query builder. Una sola plataforma para cruzar contratos, subvenciones, leyes y cargos.

🔗 **Demo en vivo:** [app.gobtracker.tailor-bytes.com](https://app.gobtracker.tailor-bytes.com)
📚 **Docs interactivas:** [`/docs`](https://app.gobtracker.tailor-bytes.com/docs)
📄 **OpenAPI 3.1:** [`/openapi.json`](https://app.gobtracker.tailor-bytes.com/openapi.json)

Pensado para periodistas, investigadores, tecnólogos cívicos y asociaciones de transparencia que quieran **construir sobre datos públicos sin pelear con XMLs de 9 MB ni feeds JSON heterogéneos**.

## Módulos activos

| Módulo | Fuente | Volumen | Endpoints |
|---|---|---|---|
| **Contracts** | [PLACSP](https://contrataciondelestado.es) atom XML | ~10M contratos desde 2018 | `/api/v1/contracts`, `/organizations`, `/companies`, `/lots` |
| **Subsidies** | [BDNS](https://www.infosubvenciones.es/) JSON REST | 625k convocatorias + 24M concesiones | `/api/v1/subsidies/calls`, `/subsidies/grants` |
| **Legislation** | [BOE Datos Abiertos](https://www.boe.es/datosabiertos/) JSON REST | 80k normas consolidadas + sumarios diarios | `/api/v1/legislation/norms`, `/summaries`, `/items` |
| **Officials** | Derivado del BOE Sección II.A | Altos cargos extraídos por regex pattern matching | `/api/v1/officials` |

Endpoint global: `/api/v1/timelines` — series temporales agregadas (cacheado 1h, rate-limitado).

## Lo que hace bien

- 📥 **Ingesta resiliente** — XMLReader streaming + idempotencia por `content_hash`. Re-procesar el mismo dataset 100 veces produce 0 filas duplicadas en ningún módulo.
- 📚 **Histórico arqueológico** — captura cada snapshot publicado (PLACSP + BDNS), sentando la base para detección de cambios silenciosos.
- 🧬 **Modelos ricos** — multi-lote real, criterios OBJ/SUBJ, modificaciones, prórrogas, anulaciones, evento de cargo (nombramiento/cese/posesión).
- 🔎 **API query builder** — `?include=`, `?fields[]=`, `?filter[]=`, `?sort=` estilo JSON:API vía `spatie/laravel-query-builder`. Whitelist explícita por endpoint.
- ⚡ **Cache 3-capas** — Cloudflare edge SWR + Redis tagged + DB. Sub-300 ms en endpoints calientes con FastPaginator (count aproximado vía `information_schema`).
- 🛡️ **API hardening** — rate limit por IP real, security headers, CORS público GET-only, no fugas de campos internos. [Ver detalles](#seguridad).
- 📊 **Operación low-disk** — comandos `--cleanup` borran los `.atom`/raw tras ingestar (footprint <500 MB en disco aunque el histórico pese 30+ GB).

## Stack

- **Laravel 12** + PHP 8.4 + MySQL 8 (FULLTEXT) + Redis
- **Spatie Query Builder** para la API
- **Dedoc Scramble** + **Scalar** para docs OpenAPI 3.1 auto-generadas
- **Pest 4** para tests
- **PHPStan L8** + **Laravel Pint**
- **Cloudflare** delante como WAF / edge cache (asumido en producción)

## Requisitos

- PHP 8.4+ con extensión `xml`, `intl`, `mbstring`, `pdo_mysql`, `redis`
- Composer 2.x
- MySQL 8.x
- Redis 6+
- HTTPS forzado en el host (HSTS lo refuerza desde la app)

## Instalación local

```bash
git clone https://github.com/christianpasinrey/escanerpublico-backend.git
cd escanerpublico-backend
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate

# Sincroniza un mes de PLACSP para tener datos
php artisan contracts:sync --month=$(date +%Y%m) --sync --cleanup

php artisan serve
# → http://localhost:8000/api/v1/contracts
```

## Comandos clave

```bash
# Contracts (PLACSP)
php artisan contracts:sync --all --sync --cleanup            # descarga + procesa + limpia mes a mes
php artisan contracts:reprocess --from=201801 --to=$(date +%Y%m) --sync
php artisan contracts:reprocess --resume

# Subsidies (BDNS)
php artisan subsidies:sync --type=calls                      # convocatorias completas (625k)
php artisan subsidies:sync --type=grants --from=01/01/2025 --to=31/12/2025
php artisan subsidies:sync --resume

# Legislation (BOE)
php artisan legislation:sync --type=summaries --from=2024-01-01    # sumarios diarios
php artisan legislation:sync --type=consolidated                    # legislación consolidada
php artisan legislation:sync --resume

# Officials (extracción derivada del BOE Sección II.A)
php artisan officials:extract                                       # solo items no procesados
php artisan officials:extract --force                               # reprocesa todo

# Tests + lint
php artisan test
./vendor/bin/phpstan analyse app/Modules --level=8
./vendor/bin/pint
```

## API en 30 segundos

```bash
# Últimos contratos adjudicados con su organismo
curl "https://app.gobtracker.tailor-bytes.com/api/v1/contracts?filter[status_code]=ADJ&include=organization&sort=-snapshot_updated_at"

# Ficha completa de un contrato
curl "https://app.gobtracker.tailor-bytes.com/api/v1/contracts/19066873?include=lots.awards.company,notices,modifications,documents"

# Estadísticas de un órgano
curl "https://app.gobtracker.tailor-bytes.com/api/v1/organizations/1/stats"

# Búsqueda full-text en contratos
curl "https://app.gobtracker.tailor-bytes.com/api/v1/contracts?filter[search]=puente+autopista"

# Subvenciones de 2025 superiores a 100.000 €
curl "https://app.gobtracker.tailor-bytes.com/api/v1/subsidies/grants?filter[grant_date_from]=2025-01-01&filter[amount_above]=100000"

# Disposiciones BOE de la Sección II.A (nombramientos)
curl "https://app.gobtracker.tailor-bytes.com/api/v1/legislation/items?filter[seccion_code]=2A"

# Trayectoria de un cargo público
curl "https://app.gobtracker.tailor-bytes.com/api/v1/officials/1?include=appointments.organization,appointments.boeItem"

# Series temporales agregadas (todos los módulos)
curl "https://app.gobtracker.tailor-bytes.com/api/v1/timelines"
curl "https://app.gobtracker.tailor-bytes.com/api/v1/timelines?module=contracts"
```

## Arquitectura

```
app/
├── Http/
│   ├── Middleware/SecurityHeaders.php   # Cabeceras de seguridad globales
│   └── Pagination/FastPaginator.php      # Count aproximado para tablas masivas
├── Modules/
│   ├── Contracts/                        # PLACSP — Spec 1
│   │   ├── Console/                      # SyncContracts, ReprocessContracts
│   │   ├── Http/Controllers/             # Contract, Organization, Company, Lot, Timelines
│   │   ├── Http/Resources/               # API resources con whenLoaded
│   │   ├── Http/Filters/                 # SearchFilter (FULLTEXT), AmountBetweenFilter
│   │   ├── Http/Middleware/              # LimitNestedIncludes
│   │   ├── Services/Parser/              # XMLReader streaming + 10 extractors + DTOs
│   │   ├── Services/ContractIngestor/    # Idempotencia + snapshots + cache invalidation
│   │   ├── Services/EntityResolver/      # 3-level key strategy (DIR3 → NIF → name_hash)
│   │   ├── Services/Cache/               # Redis tagged invalidator + Cloudflare purger
│   │   ├── Jobs/                         # ProcessPlacspFile, PurgeContractUrls
│   │   └── Models/                       # 13 modelos Eloquent
│   ├── Subsidies/                        # BDNS — Spec 5
│   │   ├── Services/BdnsClient/          # HTTP client retry/backoff/rate limit
│   │   └── Services/SubsidyIngestor/     # Idempotencia + snapshots
│   ├── Legislation/                      # BOE — Spec 7
│   │   ├── Services/BoeClient/
│   │   └── Services/LegislationIngestor/
│   └── Officials/                        # Derivado BOE II.A — Spec 8
│       ├── Services/CargoExtractor/      # Regex multi-pattern + cargo keyword anchor
│       └── Services/OfficialIngestor/    # Resuelve persona + crea appointment
└── Providers/AppServiceProvider.php      # Rate limiters api / api-heavy
```

📖 Specs y plans completos: `docs/superpowers/specs/`, `docs/superpowers/plans/`.

## Seguridad

API 100% pública sin autenticación. Auditoría completa pasada — [PR #38](https://github.com/christianpasinrey/escanerpublico-backend/pull/38).

### Lo que cubre el código

| Capa | Implementación |
|---|---|
| **Rate limit por IP real** | 100 req/min general, 30 req/min en endpoints pesados (`/timelines`). `trustProxies: '*'` para que Laravel use la IP del cliente, no la de Cloudflare. |
| **CORS público** | `allowed_origins: ['*']`, GET + OPTIONS only, `supports_credentials: false`. |
| **Security headers** | `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`, `Referrer-Policy: strict-origin-when-cross-origin`, `Permissions-Policy: interest-cohort=()`, `Strict-Transport-Security: 1y` (sobre HTTPS), `X-XSS-Protection: 0`. |
| **SQL injection** | Imposible vía Spatie Query Builder + filters/sorts whitelist explícita + custom filters con bindings parametrizados y casting numérico estricto. |
| **Includes recursivos profundos** | Middleware `limit.includes` capa la profundidad para evitar over-fetching. |
| **Field exposure** | Resources usan `whenLoaded()` y nunca exponen `raw_payload`, `content_hash` ni IDs internos sensibles. |
| **Saneamiento de datos atípicos** | Awards con importe ≥ 1B€ (errores PLACSP) excluidos de agregaciones; bandera "datos atípicos" en la ficha. |

### Lo que tiene que cubrir el operador en producción

| Responsabilidad | Cómo |
|---|---|
| `APP_DEBUG=false` | En `.env` de prod. Sin esto, los stack traces se filtran. Tras editar: `php artisan config:cache`. |
| HTTPS forzado | En el panel del host (Plesk/nginx). HSTS lo refuerza pero el redirect inicial debe estar en el server. |
| Cloudflare WAF / edge | Activar reglas anti-bot, rate limit a nivel red (capa 3-4) y modo "Under Attack" si hace falta. La app solo protege capa 7. |
| `.git` no accesible | Verifica que `https://app.gobtracker.tailor-bytes.com/.git/` devuelve 403/404. |
| Workers PHP-FPM dimensionados | Limitar workers en Plesk a un número que MySQL aguante. Sin esto, ráfagas dentro del rate limit pueden saturar la BD. |
| Backups BD encriptados | Backup MySQL diario fuera del server, encriptado en reposo. |

## Cómo contribuir

🤝 **Toda contribución es bienvenida** — desde reportar un bug hasta arquitecturar el siguiente módulo.

### Ideas para empezar

- 🐛 **Reportar bugs** — abre un [issue](https://github.com/christianpasinrey/escanerpublico-backend/issues) con caso reproducible.
- ✨ **Nuevos módulos** — [BORME](https://www.boe.es/diario_borme/), Presupuestos PGE, [BDNS Grandes Beneficiarios](https://www.infosubvenciones.es/), boletines autonómicos…
- 📊 **Detección de anomalías** — queries sobre `contract_snapshots` para detectar cambios silenciosos.
- 🌍 **Exportadores** — dumps periódicos en CSV / Parquet / JSON-LD.
- 🔬 **Investigación cívica** — usa la API en una pieza periodística o académica y compártela en Discussions.

### Workflow

1. Fork + branch (`feat/...`, `fix/...`, `security/...`)
2. TDD: test rojo → implementación → verde
3. PHPStan L8 verde sobre tu módulo, Pint sin diffs
4. PR con descripción del *por qué* (más que del *qué* — el diff ya muestra eso)

### Checklist de seguridad para nuevos módulos

Si tu módulo va a entrar en producción, **debe pasar todos los siguientes puntos antes del merge**:

#### Routing y rate limit

- [ ] Las rutas se cargan con `Route::prefix('api/v1/...')->middleware(['throttle:api', 'limit.includes'])`. La presencia de `throttle:api` es **obligatoria** — sin ella el endpoint queda sin rate limit (la API top-level `routes/api.php` no propaga su middleware a `loadRoutesFrom`).
- [ ] Endpoints con queries pesadas (`GROUP BY`, `FULLTEXT`, agregaciones cross-tabla) usan `throttle:api-heavy` (30/min en lugar de 100/min).
- [ ] Endpoints solo aceptan `GET` (la API es read-only por diseño).

#### Validación de input

- [ ] Filters y sorts pasan por **whitelist explícita** vía `Spatie\QueryBuilder` (`allowedFilters`, `allowedSorts`). NO usar `allowedFilters('*')` ni equivalentes.
- [ ] Custom filters (`AllowedFilter::custom`, `AllowedFilter::callback`) **nunca concatenan input directo en SQL**. Usar bindings (`$query->where('col', '=', $value)`) o casting (`(float) $v`, `(int) $v`).
- [ ] Búsquedas FULLTEXT usan `whereFullText()` (laravel binds), no `WHERE MATCH(...) AGAINST('...')` con concatenación.
- [ ] Parámetros numéricos (`per_page`, `page`, `limit`) tienen `min`/`max` cap (ver `min(100, max(1, ...))` en controllers existentes).

#### Resources

- [ ] No expones campos internos: `raw_payload`, `content_hash`, `parse_errors`, `ingested_at` interno, IDs incrementales sensibles para enumeración.
- [ ] Relaciones se cargan con `$this->whenLoaded('relation')` para no filtrar relaciones no pedidas.
- [ ] Si tu modelo tiene PII (DNI/NIF de personas físicas), confirma que es **fuente accesible al público** según Ley 19/2013 / Ley 38/2003 / Ley 9/2017. Documenta el fundamento legal en el spec del módulo.

#### Cache

- [ ] Endpoints listing con `Cache-Control: public, s-maxage=300, stale-while-revalidate=900` (o más alto para datasets estáticos).
- [ ] Endpoints show con `Cache-Control: public, s-maxage=3600, stale-while-revalidate=86400`.
- [ ] Si el módulo escribe datos durante ingesta, invalida cache vía Redis tags (ver `Modules\Contracts\Services\Cache\TaggedCacheInvalidator`).

#### Rendimiento

- [ ] Tablas con > 1M filas usan `FastPaginator::paginate(...)` en lugar de `paginate()` directo (evita `COUNT(*)` caro).
- [ ] Queries con `ORDER BY` tienen índice en la columna ordenada.
- [ ] Filtros frecuentes tienen índices compuestos en migration (`(status_code, snapshot_updated_at)` p.ej.).
- [ ] Sin queries N+1: usar `with()` o `withCount()` consciente.

#### Idempotencia y resiliencia

- [ ] Ingestor calcula `content_hash` determinista (sort recursivo + sha256). Re-procesar = 0 cambios si nada cambió.
- [ ] Comando de sync acepta `--resume` para retomar tras fallos.
- [ ] Comando aplica skip-and-continue ante fallos esporádicos (timeout, 5xx) y solo aborta tras N fallos consecutivos.
- [ ] Cliente HTTP propio tiene retry con backoff exponencial y timeout sano (≥ 60s).

#### Tests

- [ ] Pest feature tests cubren `index`, `show`, filters principales, sorts, includes válidos e inválidos (→ 400).
- [ ] Tests de idempotencia: re-ingerir el mismo payload N veces produce 0 cambios.
- [ ] Tests de cache headers (`assertHeader('Cache-Control', ...)`).
- [ ] Sin auth: tests no requieren token. Si tu módulo SÍ requiere auth para algo, marca el endpoint claramente y separa rate-limit (`throttle:api-auth` p.ej., a definir).

#### Documentación

- [ ] Spec en `docs/superpowers/specs/YYYY-MM-DD-modulo-design.md` con: contexto, decisiones de scope, modelo de datos, endpoints, plan de fases.
- [ ] Plan en `docs/superpowers/plans/YYYY-MM-DD-modulo/` con README + phases.
- [ ] Comandos del módulo añadidos a esta sección "Comandos clave" en el README.
- [ ] Tabla "Módulos activos" arriba en el README actualizada.
- [ ] Ejemplo curl en "API en 30 segundos".

## Filosofía

- **Los datos del Estado son nuestros.** La transparencia no debería requerir saber leer XML, JSON propietario o PDFs maquetados a mano.
- **El parser nunca pierde datos.** Si un campo no se mapea, se queda en `parse_errors` con su raw fragment.
- **Idempotencia primero.** Cualquier comando se puede volver a lanzar sin miedo.
- **Reproducción literal con disclaimers honestos.** Si la fuente publica un dato erróneo (ver caso TALLERES RIESTRA SAN LAZARO SL: 251.5B€ de adjudicación por errata en PLACSP), lo marcamos como dato atípico pero NO lo modificamos. La fuente vinculante es la oficial.
- Si una decisión técnica reduce la fricción para que un periodista descubra algo que no estaba antes, es la correcta.

## Roadmap

- [x] Spec 1 — Backend v2 Contracts (parser modular + ingestor idempotente + API + landing + docs)
- [x] Spec 2 — Frontend ficha de contrato disruptiva
- [x] Spec 3 — Ficha de empresa pública (contratos)
- [x] **Spec 5 — Módulo Subvenciones (BDNS)**
- [x] **Spec 7 — Módulo Legislación (BOE)**
- [x] **Spec 8 — Módulo Cargos Públicos** (extracción derivada BOE II.A)
- [x] **Hardening de seguridad de la API pública** (rate limit, CORS, headers, trust proxy)
- [ ] Spec 4 — Snapshot history exploitation: queries de diff + panel de auditoría
- [ ] Spec 6 — Cruces contratos↔subvenciones↔legislación por organismo/empresa
- [ ] Módulo Cargos autonómicos (boletines BOPA, DOGV, DOGC, etc.)
- [ ] Módulo Presupuestos (PGE + autonómicos)
- [ ] Módulo BORME (Registro Mercantil)
- [ ] Webhooks para terceros que sigan registros concretos
- [ ] Exportadores periódicos (CSV / Parquet / JSON-LD)

## Datos y atribución

- **Fuentes originales**: [PLACSP](https://contrataciondelestado.es), [BDNS](https://www.infosubvenciones.es/), [BOE](https://www.boe.es/datosabiertos/) — datos publicados bajo aviso legal de cada organismo emisor.
- **Esta plataforma**: capa de presentación. No somos PLACSP, BDNS ni BOE. Solo reestructuramos datos abiertos.
- **Si encuentras un dato incorrecto**: probablemente esté así en la fuente original. Reporta el `external_id` y miramos juntos.

## Licencia

MIT. Haz lo que quieras con el código.

## Contacto

- GitHub Issues para bugs y features
- GitHub Discussions para conversaciones abiertas
- Pull Requests siempre bienvenidos

---

> *"La transparencia no es opcional. Si tu dinero la financia, tu pantalla debería poder consultarla."*
