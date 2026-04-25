# Legislation (BOE) — Module Design Spec

**Date:** 2026-04-25
**Status:** Aprobado para implementación
**Spec series:** Spec 7

## Contexto

Tras Contracts (Spec 1-3) y Subsidies (Spec 5), el siguiente vector de transparencia es la **producción legislativa**: leyes, decretos y disposiciones publicadas en el [Boletín Oficial del Estado (BOE)](https://www.boe.es/datosabiertos/).

El BOE expone una API REST pública JSON sin autenticación, igual de limpia que la de BDNS. Endpoints validados empíricamente:

| Endpoint | Devuelve | Notas |
|---|---|---|
| `GET /datosabiertos/api/boe/sumario/{YYYYMMDD}` | Sumario diario completo: secciones → departamentos → epígrafes → items | Estructura jerárquica, URLs PDF/HTML/XML por item |
| `GET /datosabiertos/api/legislacion-consolidada` | Lista paginada de normas consolidadas (legislación vigente) | `offset` + `limit` (default 50, -1 = todo) |
| `GET /datosabiertos/api/legislacion-consolidada/id/{id}` | Norma consolidada completa con bloques de texto | Response grande |
| `GET /datosabiertos/api/borme/sumario/{YYYYMMDD}` | Sumario BORME (Registro Mercantil) | Para futuro módulo Empresas extendido |

### Schema sumario diario (sample real BOE-S-2026-100)

```json
{
  "diario": [{
    "numero": "100",
    "sumario_diario": { "identificador": "BOE-S-2026-100", "url_pdf": {...} },
    "seccion": [{
      "codigo": "1", "nombre": "I. Disposiciones generales",
      "departamento": [{
        "codigo": "6110", "nombre": "MINISTERIO DE DEFENSA",
        "epigrafe": [{
          "nombre": "Formación militar",
          "item": [{
            "identificador": "BOE-A-2026-8955",
            "control": "2026/6670",
            "titulo": "Orden DEF/372/2026...",
            "url_pdf": { "texto": "https://www.boe.es/.../BOE-A-2026-8955.pdf", "szBytes": "510708" },
            "url_html": "https://www.boe.es/diario_boe/txt.php?id=BOE-A-2026-8955",
            "url_xml": "https://www.boe.es/diario_boe/xml.php?id=BOE-A-2026-8955"
          }]
        }]
      }]
    }]
  }]
}
```

### Schema legislación consolidada (sample real)

```json
{
  "fecha_actualizacion": "20260424T114500Z",
  "identificador": "BOE-A-2020-4859",
  "ambito": { "codigo": "1", "texto": "Estatal" },
  "departamento": { "codigo": "9574", "texto": "Ministerio de la Presidencia, Relaciones con las Cortes y Memoria Democrática" },
  "rango": { "codigo": "1310", "texto": "Real Decreto Legislativo" },
  "fecha_disposicion": "20200505",
  "numero_oficial": "1/2020",
  "titulo": "Real Decreto Legislativo 1/2020, de 5 de mayo, por el que se aprueba el texto refundido de la Ley Concursal.",
  "fecha_publicacion": "20200507",
  "fecha_vigencia": "20200901",
  "vigencia_agotada": "N",
  "estado_consolidacion": { "codigo": "3", "texto": "Finalizado" },
  "url_eli": "https://www.boe.es/eli/es/rdlg/2020/05/05/1",
  "url_html_consolidada": "https://www.boe.es/buscar/act.php?id=BOE-A-2020-4859"
}
```

## Decisiones de alcance

| Pregunta | Decisión |
|---|---|
| ¿Modulo separado? | **Sí**: `app/Modules/Legislation/` |
| ¿Reusar `organizations`? | **Sí** (departamentos ministeriales). Crea organization con `identifier` = código BOE si no existe. |
| Granularidad MVP | (a) **Sumarios diarios** desde 2024-01-01 (~700 días, ~140k items) (b) **Legislación consolidada** completa (~80k normas) |
| Scope diferido a Spec 8 | BORME, texto completo de normas con bloques (`/legislacion-consolidada/id/{id}/texto`), búsqueda full-text |
| Idempotencia | Por `(source='BOE', external_id)` con `content_hash` (igual que Subsidies) |
| API pública | `spatie/laravel-query-builder`. Whitelist explícita |
| Cache | CF + Redis tagged. TTL bajo durante ingesta inicial |
| Datos personales | Nombramientos contienen nombres completos. Fuente accesible al público (Ley 19/2013). Mismo tratamiento que Contracts/Subsidies. |

## Modelo de datos

```sql
-- Normas consolidadas (legislación vigente)
CREATE TABLE legislation_norms (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  source VARCHAR(20) DEFAULT 'BOE',
  external_id VARCHAR(50) NOT NULL,             -- BOE-A-YYYY-NNNN
  ambito_code VARCHAR(10),
  ambito_text VARCHAR(100),                     -- "Estatal", "CCAA", etc.
  organization_id BIGINT NULL,                  -- departamento resuelto
  departamento_code VARCHAR(10),
  departamento_text VARCHAR(255),
  rango_code VARCHAR(10),                       -- 1300=Ley, 1310=RDL, 1320=RD, etc.
  rango_text VARCHAR(100),
  numero_oficial VARCHAR(50),                   -- "1/2020"
  titulo TEXT,
  fecha_disposicion DATE,
  fecha_publicacion DATE,
  fecha_vigencia DATE,
  fecha_actualizacion DATETIME,
  vigencia_agotada BOOLEAN DEFAULT FALSE,
  estado_consolidacion_code VARCHAR(10),
  estado_consolidacion_text VARCHAR(100),
  url_eli VARCHAR(500),                         -- ELI permalink (European Legislation Identifier)
  url_html_consolidada VARCHAR(500),
  content_hash CHAR(64),
  ingested_at DATETIME,
  created_at DATETIME, updated_at DATETIME,
  UNIQUE KEY uk_source_external (source, external_id),
  INDEX idx_org (organization_id),
  INDEX idx_rango (rango_code),
  INDEX idx_ambito (ambito_code),
  INDEX idx_fecha_pub (fecha_publicacion),
  FULLTEXT KEY ft_titulo (titulo)
);

-- Sumarios diarios BOE
CREATE TABLE boe_summaries (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  source VARCHAR(20) DEFAULT 'BOE',
  identificador VARCHAR(50) NOT NULL,           -- BOE-S-YYYY-NNN
  fecha_publicacion DATE NOT NULL,
  numero VARCHAR(10),                           -- número del diario
  url_pdf VARCHAR(500),
  pdf_size_bytes BIGINT NULL,
  raw_payload JSON,                             -- snapshot completo
  content_hash CHAR(64),
  ingested_at DATETIME,
  created_at DATETIME, updated_at DATETIME,
  UNIQUE KEY uk_source_identificador (source, identificador),
  INDEX idx_fecha (fecha_publicacion)
);

-- Items individuales (cada disposición/anuncio dentro de un sumario)
CREATE TABLE boe_items (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  summary_id BIGINT NOT NULL,
  source VARCHAR(20) DEFAULT 'BOE',
  external_id VARCHAR(50) NOT NULL,             -- BOE-A-YYYY-NNNN
  control VARCHAR(50),                          -- "2026/6670"
  seccion_code VARCHAR(10),
  seccion_nombre VARCHAR(255),
  organization_id BIGINT NULL,                  -- departamento resuelto
  departamento_code VARCHAR(10),
  departamento_nombre VARCHAR(500),
  epigrafe VARCHAR(500),
  titulo TEXT,
  url_pdf VARCHAR(500),
  pdf_size_bytes BIGINT NULL,
  pagina_inicial VARCHAR(20),
  pagina_final VARCHAR(20),
  url_html VARCHAR(500),
  url_xml VARCHAR(500),
  fecha_publicacion DATE,                       -- denormalizado del summary para sort/filter
  content_hash CHAR(64),
  created_at DATETIME, updated_at DATETIME,
  UNIQUE KEY uk_source_external (source, external_id),
  INDEX idx_summary (summary_id),
  INDEX idx_org (organization_id),
  INDEX idx_seccion (seccion_code),
  INDEX idx_fecha (fecha_publicacion),
  FULLTEXT KEY ft_titulo (titulo),
  CONSTRAINT fk_summary FOREIGN KEY (summary_id) REFERENCES boe_summaries(id) ON DELETE CASCADE
);

-- Tracking de runs igual que Subsidies
CREATE TABLE legislation_ingest_runs (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  type ENUM('summaries','consolidated'),
  cursor_offset INT DEFAULT 0,
  cursor_date DATE NULL,                        -- para summaries: fecha actual
  from_date DATE NULL,
  to_date DATE NULL,
  total_pages INT NULL,
  total_elements INT NULL,
  processed_records INT DEFAULT 0,
  failed_records INT DEFAULT 0,
  status ENUM('pending','running','completed','failed','paused') DEFAULT 'pending',
  started_at DATETIME, finished_at DATETIME NULL,
  error_message TEXT NULL,
  created_at DATETIME, updated_at DATETIME
);
```

## Endpoints API públicos

```
GET /api/v1/legislation/norms                           # consolidated laws listing
GET /api/v1/legislation/norms/{id}                      # show

GET /api/v1/legislation/summaries                       # daily summaries listing
GET /api/v1/legislation/summaries/{id}                  # show with items

GET /api/v1/legislation/items                           # individual items (disposiciones, anuncios)
GET /api/v1/legislation/items/{id}
```

### Filtros

- norms: `ambito_code`, `rango_code`, `organization_id`, `vigencia_agotada`, `fecha_pub_from/to`, `search`
- items: `seccion_code`, `organization_id`, `fecha_from/to`, `search`
- summaries: `fecha_from/to`

## Comando de ingesta

```bash
# Sumarios desde fecha hasta hoy
php artisan legislation:sync --type=summaries --from=2024-01-01

# Legislación consolidada completa
php artisan legislation:sync --type=consolidated

# Reanudar
php artisan legislation:sync --resume
```

`BoeClient` con mismo patrón que BdnsClient: timeout 120s, retry 5x, cooldown 250ms, User-Agent identificable.

## Plan de fases

| # | Fase | Estimación |
|---|---|---|
| 7.0 | Foundation: migrations + models + factories | 1 sesión |
| 7.1 | BoeClient + LegislationIngestor + comando sync + tests | 1 sesión |
| 7.2 | API: controllers + resources + filters | 1 sesión |
| 7.3 | Frontend: /legislacion listing + ficha + nav | 1 sesión |
| 7.4 | Integration: ingesta MVP + docs | 1 sesión |

## Filosofía

- **Reproducción literal**: el BOE es la fuente, no inventamos texto.
- **Permalinks**: cada norma expone su `url_eli` (European Legislation Identifier) como ancla canónica.
- **Conexión cross-módulo**: `organizations` se enriquecen con FK desde `boe_items.organization_id` → un ministerio aparece tanto contratando como publicando legislación.
- **Sin OCR de PDFs**: solo metadatos estructurados + enlaces a los PDFs originales del BOE.

---

> *"La ley no debería ser un PDF que tienes que descargar, sino una API que cualquier desarrollador puede consultar."*
