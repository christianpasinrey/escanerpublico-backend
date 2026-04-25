# Cargos Públicos — Module Design Spec

**Date:** 2026-04-25
**Status:** Aprobado para implementación
**Spec series:** Spec 8

## Contexto

Los nombramientos y ceses de altos cargos del Estado se publican en el BOE en la **Sección II.A**. Ya los ingestamos a través del módulo Legislation (`boe_items` con `seccion_code='2A'`). Lo que falta es **extraer las personas como entidades de primer nivel** y vincular cada nombramiento/cese a la persona y al cargo.

Sin esta capa, los nombramientos son strings dentro de boe_items.titulo. Con esta capa, podemos:
- Construir la **ficha de un cargo público**: trayectoria de nombramientos, organismos donde sirvió, fechas de toma de posesión y cese.
- Cruzar con futuros módulos (declaraciones de bienes, contratos firmados por su organismo durante su mandato).
- Servir de base al periodismo: "¿qué hizo X en su tiempo en el cargo Y?".

## Naturaleza de los datos BOE Sección II.A

Las entradas siguen patrones reconocibles. Tras analizar 100+ entradas reales:

```
Real Decreto 123/2026, de 12 de marzo, por el que se nombra a Don Juan Pérez García
Director General de Tributos.

Real Decreto 456/2026, de 15 de abril, por el que se dispone el cese de Doña María
López Sánchez como Subsecretaria de Hacienda.

Resolución de 20 de mayo de 2026, de la Subsecretaría, por la que se nombran
funcionarios de carrera del Cuerpo Técnico de Hacienda.  ← descartar (colectivo)
```

### Patrones extractables

| Tipo | Regex |
|---|---|
| Nombramiento singular | `/se nombra a (?:D\.\|Don\|Doña\|D\.ª)\s+([A-Z][\w\s]+?)\s+(?:como\s+)?([A-Z][^,.]+?)[.,]/u` |
| Cese | `/(?:el cese de\|cesa) (?:D\.\|Don\|Doña\|D\.ª)\s+([A-Z][\w\s]+?)\s+(?:como\s+)?([A-Z][^,.]+?)[.,]/u` |
| Toma de posesión | `/toma posesión\s+(?:D\.\|Don\|Doña\|D\.ª)\s+([A-Z][\w\s]+?)\s+(?:como\s+)?([A-Z][^,.]+?)[.,]/u` |

Los plurales/colectivos (`se nombran funcionarios`, `se promueve a`) se descartan en el MVP.

### Tasa de extracción esperada

- **Nombramientos individuales**: ~70-80% extracción correcta.
- **Casos ambiguos**: nombres compuestos largos, abreviaturas raras, cargos con conjunciones.
- **Strategy**: lo que no parsea queda registrado en `cargo_extraction_errors` para mejora iterativa de los regex.

## Decisiones de alcance

| Pregunta | Decisión |
|---|---|
| ¿Modulo separado? | **Sí**: `app/Modules/Officials/` |
| Reusar BOE | **Sí**: el extractor lee de `boe_items` filtrando `seccion_code='2A'`. No ingesta nueva fuente. |
| Identificación | Por `normalized_name` (sin tildes/lowercase). Sin DNI (no público en estas publicaciones). Misma persona mismo nombre = misma fila (suposición razonable; ambigüedades quedan documentadas). |
| Tipos de evento | `appointment`, `cessation`, `posession` (toma de posesión efectiva). |
| Boletines autonómicos | **Spec 9** (futuro). Solo BOE estatal en este MVP. |
| Declaraciones bienes | **Spec 10**. PDFs scrapeables desde Portal Transparencia y CdE. |

## Modelo de datos

```sql
CREATE TABLE public_officials (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  full_name VARCHAR(255) NOT NULL,
  normalized_name VARCHAR(255) NOT NULL,
  honorific VARCHAR(20) NULL,                  -- "Don", "Doña", "D.", "D.ª"
  appointments_count INT DEFAULT 0,            -- denormalizado para ranking
  first_appointment_date DATE NULL,
  last_event_date DATE NULL,
  created_at DATETIME, updated_at DATETIME,
  UNIQUE KEY uk_normalized_name (normalized_name),
  FULLTEXT KEY ft_full_name (full_name)
);

CREATE TABLE appointments (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  public_official_id BIGINT NOT NULL,
  boe_item_id BIGINT NOT NULL,                 -- FK al item BOE original
  organization_id BIGINT NULL,                 -- organismo donde se nombró/cesó
  event_type ENUM('appointment','cessation','posession'),
  cargo VARCHAR(500),                          -- "Director General de Tributos"
  effective_date DATE NULL,                    -- usualmente fecha del propio BOE
  created_at DATETIME, updated_at DATETIME,
  INDEX idx_official (public_official_id),
  INDEX idx_boe_item (boe_item_id),
  INDEX idx_org (organization_id),
  INDEX idx_type_date (event_type, effective_date),
  CONSTRAINT fk_official FOREIGN KEY (public_official_id) REFERENCES public_officials(id) ON DELETE CASCADE,
  CONSTRAINT fk_boe_item FOREIGN KEY (boe_item_id) REFERENCES boe_items(id) ON DELETE CASCADE
);

CREATE TABLE cargo_extraction_errors (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  boe_item_id BIGINT NOT NULL,
  reason VARCHAR(255),
  raw_titulo TEXT,
  created_at DATETIME, updated_at DATETIME
);
```

## Comando

```bash
# Procesa todos los boe_items con seccion_code=2A no extraídos aún
php artisan officials:extract

# Reprocesa todo (idempotente — re-ejecutar produce 0 cambios)
php artisan officials:extract --force
```

El comando recorre `boe_items` (Sección II.A) en chunks de 1000, invoca `CargoExtractor::extract($titulo)`, y persiste resultados.

## API

```
GET /api/v1/officials                          # listing con filtros nombre, cargo
GET /api/v1/officials/{id}                     # ficha con appointments cronológicos
GET /api/v1/officials/{id}/timeline            # appointments + ceses ordenados
```

Filtros: `name` (FULLTEXT), `organization_id`, `event_type`.
Sorts: `appointments_count`, `last_event_date`, `full_name`.

## Frontend

- `/cargos` — listing de altos cargos con foto placeholder, nombre, último cargo conocido, nº nombramientos
- `/cargos/[id]` — ficha cinematográfica con timeline de eventos (similar a ContractTimeline)

## Plan de fases

| # | Fase |
|---|---|
| 8.0 | Foundation: migrations + models + factories + tests |
| 8.1 | CargoExtractor (regex) + ExtractOfficials command + tests |
| 8.2 | API + resources |
| 8.3 | Frontend pages + nav |
| 8.4 | Docs + ejecución MVP |

## Filosofía

- **Lectura derivada**: no ingestamos nada nuevo; transformamos boe_items existentes.
- **Idempotente**: el extractor se puede re-ejecutar N veces sin duplicar.
- **Errores documentados**: cada fallo de extracción queda en cargo_extraction_errors para iterar regex.
- **Sin afirmaciones**: no inferimos partido político, ideología, etc. Solo lo que el BOE publica literalmente.

---

> *"La identidad de quien firma una orden ministerial es información pública por definición."*
