# Subsidies (BDNS) — Module Design Spec

**Date:** 2026-04-25
**Author:** Brainstorming session con Christian
**Status:** Aprobado para implementación
**Spec series:** Spec 5 (continuación de Spec 1–4 del módulo Contracts)

## Contexto

El módulo `Contracts` cubre la contratación pública (PLACSP). Pero el otro gran flujo de dinero público — **las subvenciones** — vive en otra base de datos del Estado: la **Base de Datos Nacional de Subvenciones (BDNS)**, gestionada por la IGAE (Intervención General de la Administración del Estado), publicada en [infosubvenciones.es](https://www.infosubvenciones.es/bdnstrans/).

Este spec añade un módulo `Subsidies` paralelo al de `Contracts`, reutilizando las tablas `companies` y `organizations` para crear una vista unificada del dinero recibido por cualquier empresa del sector público español: **contratos + subvenciones** en la misma ficha.

## Naturaleza de BDNS

A diferencia de PLACSP (atom XML mensual) BDNS expone una **API REST pública JSON sin autenticación**. Endpoints validados empíricamente:

| Endpoint | Método | Devuelve | Volumen total |
|---|---|---|---|
| `https://www.infosubvenciones.es/bdnstrans/api/convocatorias/busqueda` | GET | Convocatorias paginadas | **625.066** |
| `https://www.infosubvenciones.es/bdnstrans/api/concesiones/busqueda` | GET | Concesiones paginadas | **23.686.969** |

Formato respuesta: estructura **Spring Page** (Java) con `content[]`, `pageable`, `totalElements`, `totalPages`, `last`, `first`. Cada respuesta incluye un campo `advertencia` con el aviso legal de reutilización.

### Schema convocatorias (validated sample)
```json
{
  "id": 1103144,
  "mrr": false,
  "numeroConvocatoria": "901583",
  "descripcion": "BASES EN REGIMEN ABREVIADO...",
  "descripcionLeng": "BASES EN RÉXIME ABREVIADO...",
  "fechaRecepcion": "2026-04-24",
  "nivel1": "LOCAL",
  "nivel2": "DIPUTACIÓN PROV. DE LUGO",
  "nivel3": "DIPUTACIÓN PROVINCIAL DE LUGO",
  "codigoInvente": null
}
```

### Schema concesiones (validated sample)
```json
{
  "id": 150295544,
  "codConcesion": "SB150295544",
  "fechaConcesion": "2026-04-24",
  "beneficiario": "E21790647 MELAGRUM T.C.E.A",
  "instrumento": "SUBVENCIÓN y ENTREGA DINERARIA SIN CONTRAPRESTACIÓN ",
  "importe": 18375,
  "ayudaEquivalente": 18375,
  "urlBR": "https://docm.jccm.es/.../2023_2713.pdf",
  "tieneProyecto": false,
  "numeroConvocatoria": "686367",
  "idConvocatoria": 887927,
  "convocatoria": "Resolución de 04/04/2023, de la Dirección General...",
  "descripcionCooficial": null,
  "nivel1": "AUTONOMICA",
  "nivel2": "CASTILLA-LA MANCHA",
  "nivel3": "VICECONSEJERÍA DE LA POLÍTICA AGRARIA COMÚN Y POLÍTICA AGROAMBIENTAL",
  "codigoInvente": null,
  "idPersona": 20694215,
  "fechaAlta": "2026-04-24"
}
```

### Restricciones declaradas por la IGAE
- **Sin auth, sin API key**.
- Pueden aplicar restricciones ante "manifiesto abuso del servicio". Mitigación: rate-limit **≤ 5 req/s** sostenido y **≤ 1 req/s** prolongado.
- Datos dinámicos: pueden corregirse retrospectivamente. Idempotencia es crítica.
- Reutilización sujeta a [aviso legal SNPSAP](https://www.infosubvenciones.es/bdnstrans/GE/es/avisolegal).

## Decisiones de alcance

| Pregunta | Decisión |
|---|---|
| ¿Modulo separado o extender Contracts? | **Separado** (`app/Modules/Subsidies/`). Tablas propias, controladores propios. |
| ¿Reusar `companies` y `organizations`? | **Sí**, vía FK. El campo `beneficiario` de BDNS viene como `"NIF Razón social"` → resolvemos a `companies.id` por NIF. `nivel2/nivel3` resuelven a `organizations` por nombre + jerarquía. |
| ¿Histórico completo o ventana? | **Por fases**: (a) últimos 2 años para MVP demostrativo, (b) histórico completo en operación batch posterior. |
| Idempotencia | Por `(source='BDNS', external_id)` con `content_hash` y `fechaAlta` como timestamp del snapshot. |
| Cliente HTTP | Servicio propio `BdnsClient` con backoff exponencial + rate limit por token bucket. Sin libs externas. |
| Forma de la API pública | Mismo patrón que Contracts: `spatie/laravel-query-builder` con `?include=`, `?filter[]=`, `?sort=`, `?fields[]=`. Whitelist por endpoint. |
| Cache | Cloudflare edge SWR + Redis tagged. Invalidación al re-ingestar. |
| Calidad / tests | Premium: Pest unit + feature, fixtures de respuestas reales, idempotencia (re-ingerir 100x = 0 diff), MSI ≥ 70%, coverage ≥ 80%. |
| Frontend | Listing `/subvenciones` + ficha `/subvenciones/[id]`, **integración en `/empresas/[id]` (tab "Subvenciones recibidas") y `/organismos/[id]` (tab "Subvenciones concedidas")**. |
| Scope MVP | Convocatorias + Concesiones del último año natural. **Sin proyectos** (`tieneProyecto`=false en MVP), sin Grandes Beneficiarios (es vista derivada). |
| Datos personales | El campo `beneficiario` puede ser autónomo (NIF=DNI). Mismo tratamiento que en Contracts: fuente accesible al público (Ley 19/2013 + Ley 38/2003 LGS Art. 18). |

## Arquitectura

```
CLI (subsidies:sync --from=YYYY-MM-DD --type=calls|grants)
    ↓ stream paginado
BdnsClient (HTTP + rate limit + retry/backoff)
    ↓ JSON pages
SubsidyIngestor (transaccional, idempotente)
    ↓ resolve company.id, organization.id
MySQL (5 tablas nuevas)
    ↑ read
SubsidiesController + QueryBuilder + Resources
    ↓ Cache-Control + Redis tag
Cache (CF edge SWR → Redis tagged → DB)
```

## Modelo de datos

### Tablas nuevas

```sql
-- Convocatorias (calls for grants)
CREATE TABLE subsidy_calls (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  external_id BIGINT NOT NULL,                    -- BDNS id
  numero_convocatoria VARCHAR(50),                -- BDNS numeroConvocatoria
  organization_id BIGINT NULL,                    -- FK organizations (resolved)
  description TEXT,
  description_cooficial TEXT,
  reception_date DATE,
  nivel1 VARCHAR(50),                             -- LOCAL/AUTONOMICA/ESTATAL
  nivel2 VARCHAR(255),
  nivel3 VARCHAR(255),
  codigo_invente VARCHAR(50),
  is_mrr TINYINT(1),
  source VARCHAR(20) DEFAULT 'BDNS',
  content_hash CHAR(64),
  ingested_at DATETIME,
  source_updated_at DATETIME NULL,
  created_at DATETIME, updated_at DATETIME,
  UNIQUE KEY uk_source_external (source, external_id),
  INDEX idx_org (organization_id),
  INDEX idx_date (reception_date),
  FULLTEXT KEY ft_description (description)
);

-- Concesiones (granted subsidies)
CREATE TABLE subsidy_grants (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  external_id BIGINT NOT NULL,                    -- BDNS concesion id
  cod_concesion VARCHAR(50),                      -- BDNS codConcesion (e.g. SB150295544)
  call_id BIGINT NULL,                            -- FK subsidy_calls
  external_call_id BIGINT NULL,                   -- BDNS idConvocatoria (para FK lazy)
  organization_id BIGINT NULL,                    -- FK organizations (resuelto desde nivel*)
  company_id BIGINT NULL,                         -- FK companies (resuelto desde beneficiario NIF)
  beneficiario_raw VARCHAR(500),                  -- texto original sin parsear
  beneficiario_nif VARCHAR(20),                   -- extraído del raw
  beneficiario_name VARCHAR(500),                 -- extraído del raw
  grant_date DATE,
  amount DECIMAL(15,2),
  ayuda_equivalente DECIMAL(15,2),
  instrumento TEXT,
  url_br VARCHAR(500),                            -- BDNS urlBR (PDF del boletín)
  tiene_proyecto TINYINT(1) DEFAULT 0,
  id_persona BIGINT,                              -- BDNS idPersona (link interno BDNS)
  fecha_alta DATE,
  source VARCHAR(20) DEFAULT 'BDNS',
  content_hash CHAR(64),
  ingested_at DATETIME,
  created_at DATETIME, updated_at DATETIME,
  UNIQUE KEY uk_source_external (source, external_id),
  INDEX idx_call (call_id),
  INDEX idx_external_call (external_call_id),
  INDEX idx_org (organization_id),
  INDEX idx_company (company_id),
  INDEX idx_company_date (company_id, grant_date),
  INDEX idx_org_date (organization_id, grant_date),
  INDEX idx_grant_date (grant_date),
  INDEX idx_amount (amount),
  INDEX idx_beneficiario_nif (beneficiario_nif)
);

-- Snapshots históricos (paralelo a contract_snapshots)
CREATE TABLE subsidy_snapshots (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  subsidy_grant_id BIGINT NOT NULL,
  raw_payload JSON,
  content_hash CHAR(64),
  fetched_at DATETIME,
  source_updated_at DATETIME NULL,
  INDEX idx_grant_fetched (subsidy_grant_id, fetched_at)
);

-- Errores de parseo (paralelo a parse_errors)
CREATE TABLE subsidy_parse_errors (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  external_id BIGINT NULL,
  field VARCHAR(100),
  message VARCHAR(500),
  raw_value TEXT,
  created_at DATETIME
);

-- Tracking de ingestas resumibles
CREATE TABLE subsidy_ingest_runs (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  type ENUM('calls','grants'),
  cursor_page INT,
  from_date DATE NULL,
  to_date DATE NULL,
  total_pages INT NULL,
  status ENUM('pending','running','completed','failed','paused'),
  started_at DATETIME, finished_at DATETIME NULL,
  error_message TEXT NULL
);
```

### Sin tablas nuevas (reutiliza)
- `companies` — beneficiario empresa adjudicataria
- `organizations` — nivel2/nivel3 resuelven aquí

## Endpoints API públicos

```
GET /api/v1/subsidies/calls
GET /api/v1/subsidies/calls/{id}
GET /api/v1/subsidies/calls/{id}/stats           — counts grants, total amount, top beneficiaries

GET /api/v1/subsidies/grants
GET /api/v1/subsidies/grants/{id}                 — show con call + organization + company

# Stats agregadas a nivel sistema
GET /api/v1/subsidies/stats                       — totales por nivel1, top organismos, top beneficiarios

# Extensiones a stats existentes
GET /api/v1/companies/{id}/stats                  — añade subsidies_total_count, subsidies_total_amount, subsidies_by_year, top_subsidies_organizations
GET /api/v1/organizations/{id}/stats              — añade subsidies_granted_count, subsidies_granted_amount
```

### Whitelist Spatie (extracto)

```php
// SubsidyGrantsController
->allowedFilters('company_id', 'organization_id', 'call_id', 'beneficiario_nif',
    AllowedFilter::exact('grant_date'),
    AllowedFilter::scope('between_dates'),
    AllowedFilter::scope('amount_above'),
    AllowedFilter::callback('search', /* FULLTEXT */)
)
->allowedIncludes('call', 'company', 'organization')
->allowedSorts('grant_date', 'amount', 'fecha_alta',
    AllowedSort::field('beneficiario', 'beneficiario_name'))
->defaultSort('-grant_date')
```

## Estrategia de ingesta

### Cliente HTTP (`BdnsClient`)
- Base URL: `https://www.infosubvenciones.es/bdnstrans/api/`
- Headers: `User-Agent: GobTracker/1.0 (escaner-publico)`
- Rate limit: token bucket de 5 tokens, refill 1 token/s. Sostener < 5 req/s.
- Reintentos: 3 con backoff exponencial (1s, 4s, 16s) en 5xx y 429.
- Timeout: 30s por request.
- Logging: cada request a `bdns.log` con request_id, status, ms.

### Comando `subsidies:sync`
```bash
# Sincronizar concesiones del último año
php artisan subsidies:sync --type=grants --from=2025-04-25 --to=2026-04-25

# Sincronizar convocatorias completas (no son tantas, 625k)
php artisan subsidies:sync --type=calls

# Reanudar tras error
php artisan subsidies:sync --resume

# Forzar refresh de un rango (re-fetch + idempotencia)
php artisan subsidies:sync --type=grants --from=2024-01-01 --to=2024-01-31 --force
```

Cada page consumida actualiza `subsidy_ingest_runs.cursor_page`. Si el job se cae a mitad, `--resume` retoma donde quedó.

### Resolución de entidades

- **Beneficiario → company**: parsear `beneficiario_raw` con regex `^([A-Z0-9]{8,9})\s+(.+)$`. Si NIF coincide con `companies.nif`, FK directa. Si no, crear nuevo `Company` con `source='BDNS'` (campo a añadir a `companies`). Si beneficiario no parsea (e.g. nombres concatenados sin NIF), guardar raw y marcar `parse_error`.
- **nivel2/nivel3 → organization**: usar `EntityResolver` existente con jerarquía. Si no existe, crear con `identifier=NULL` (no hay DIR3 en BDNS). Hash por nombre normalizado + jerarquía.

### Idempotencia
1. Calcular `content_hash = sha256(json_encode(payload, JSON_SORT_KEYS))`.
2. Buscar `subsidy_grants WHERE source='BDNS' AND external_id=?`.
3. Si no existe → INSERT + snapshot.
4. Si existe y hash coincide → SKIP (no UPDATE).
5. Si existe y hash difiere → UPDATE + nuevo snapshot. Tracking del cambio.

## Frontend

### Páginas nuevas
- `/subvenciones` — listing con cards, filtro por nivel administrativo + beneficiario + fecha
- `/subvenciones/[id]` — ficha de concesión con: KPIs, beneficiario (link a `/empresas/[id]`), organismo concedente (link a `/organismos/[id]`), convocatoria origen, enlace a boletín oficial (PDF)
- `/convocatorias/[id]` — ficha de convocatoria con stats: nº concesiones, importe total concedido, top beneficiarios

### Integraciones en páginas existentes
- **`/empresas/[id]`** — sección nueva "Subvenciones recibidas":
  - KPI: total recibido en subvenciones (separado de contratos)
  - Chart por año (combinado contratos + subvenciones, dos series)
  - Lista paginada de concesiones recientes
  - Top organismos concedentes
- **`/organismos/[id]`** — sección nueva "Subvenciones concedidas":
  - KPI total concedido + nº concesiones
  - Top beneficiarios

### Nav y home
- Añadir "Subvenciones" al menú principal (entre Empresas y "Próximamente")
- ModulePreview home: subir Subvenciones a tarjeta funcional, dejar "Próximamente" residual con Legislación/Cargos

## Plan de fases

| # | Fase | Worktree | Depende | Estimación |
|---|---|---|---|---|
| 5.0 | Foundation: migrations + models + factories + Subsidies module bootstrap | `wt-5.0-foundation` | main | 1 sesión |
| 5.1 | BdnsClient + SubsidyIngestor + comando sync con tests de idempotencia | `wt-5.1-ingestor` | 5.0 | 1 sesión |
| 5.2 | API: controllers + resources + filters + sorts + tests | `wt-5.2-api` | 5.0 | 1 sesión |
| 5.3 | Frontend: páginas + integración empresas/organismos + nav | `wt-5.3-frontend` | 5.2 | 1 sesión |
| 5.4 | Integration: ingesta MVP (último año) + verificación + actualizar OpenAPI | `wt-5.4-integration` | 5.1+5.2+5.3 | 1 sesión |

## Decisiones diferidas a Spec 6

- Análisis de cruces: empresa que recibe contratos + subvenciones del mismo organismo en el mismo año (potencial conflicto)
- Histórico completo de concesiones (24M registros) — fuera de MVP
- Detección de "Grandes Beneficiarios" (vista derivada Top-N por año)
- Webhook hacia terceros para alertas de subvenciones nuevas

## Filosofía

- **El parser nunca pierde datos.** Si un campo no se mapea, se guarda en `subsidy_parse_errors` con su raw.
- **Idempotencia primero.** `subsidies:sync` se puede relanzar 100 veces sin duplicar.
- **Atribución a la fuente.** Cada concesión expone `urlBR` enlazando al PDF original del boletín.
- **Saneamiento heredado.** Mismo umbral `SUSPECT_AMOUNT_THRESHOLD = 1B€` para subvenciones (ya hay erratas conocidas en BDNS).
- **Transparencia es ortogonal a la fuente.** Mismo patrón visual y técnico que Contracts → la plataforma cuenta con dos pilares simétricos.

---

> *"Si tu dinero la financia, tu pantalla debería poder consultarla — venga de PLACSP o de BDNS, da igual la base de datos."*
