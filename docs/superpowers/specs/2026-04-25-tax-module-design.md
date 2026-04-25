# Spec — Módulo `Tax` (Escáner Público / GobTracker)

Fecha: 2026-04-25
Autor: design spec consolidado tras research approved
Estado: aprobado para implementación, derivar planes hijos por fase
Research base: [docs/research/2026-04-25-aeat-tax-module.md](../../research/2026-04-25-aeat-tax-module.md)

---

## 1. Objetivo

Construir un nuevo módulo `Tax` en la plataforma con dos caras complementarias:

1. **Catálogo enciclopédico estructurado** del sistema tributario español: regímenes, actividades económicas (CNAE-2025 + IAE), impuestos y tasas (estatales/autonómicos), modelos AEAT, obligaciones por régimen, todo versionado por año y publicado como **open data CC-BY**.
2. **Calculadoras de transparencia**: nómina, factura autónomo, IVA (modelo 303/390), IRPF (modelo 100/130/131). Cada cálculo devuelve un breakdown línea a línea con referencia legal al BOE.

El valor diferencial frente a calculadoras comerciales es **publicar el catálogo como dato abierto**: ninguna agencia, asesoría o desarrollo externo dispone hoy de un dataset estructurado JSON de los regímenes fiscales españoles con sus compatibilidades, modelos y obligaciones.

## 2. Arquitectura

Sigue el patrón modular del proyecto (`app/Modules/{Contracts,Subsidies,Legislation,Officials,Tax}/`). El módulo expone un `TaxServiceProvider` que registra rutas, comandos artisan y repositorios. Idempotencia en ingesta vía `(scope, code, year) + content_hash`. Cache de catálogo y parámetros en Redis con bust manual al reseed; **nunca** se cachean inputs de calculadoras (PII).

```
app/Modules/Tax/
├── TaxServiceProvider.php
├── Routes/
│   └── api.php
├── Http/
│   ├── Controllers/
│   │   ├── Catalog/
│   │   │   ├── RegimeController.php
│   │   │   ├── EconomicActivityController.php
│   │   │   ├── TaxTypeController.php
│   │   │   └── ParameterController.php
│   │   ├── Calculators/
│   │   │   ├── PayrollController.php
│   │   │   ├── InvoiceController.php
│   │   │   ├── VatReturnController.php
│   │   │   └── IncomeTaxController.php
│   │   └── ObligationsController.php
│   ├── Requests/
│   └── Resources/
├── Models/                           # 12 tablas — ver §4
├── Calculators/                      # dispatch por régimen
│   ├── Payroll/
│   ├── Invoice/
│   ├── Vat/
│   └── IncomeTax/
├── Services/
│   ├── TaxParameterRepository.php
│   ├── RegimeResolver.php
│   ├── IrpfScaleResolver.php
│   ├── SocialSecurityResolver.php
│   ├── ObligationsResolver.php
│   ├── CnaeIaeMapper.php
│   └── BoeParameterDetector.php
├── DTOs/
│   ├── BreakdownLine.php
│   ├── PayrollInput.php / PayrollResult.php
│   ├── InvoiceInput.php / InvoiceResult.php
│   ├── VatReturnInput.php / VatReturnResult.php
│   ├── IncomeTaxInput.php / IncomeTaxResult.php
│   └── ObligationCalendar.php
├── Ingestion/
│   ├── CnaeImporter.php
│   ├── IaeImporter.php
│   ├── AutonomoBracketsImporter.php
│   └── TaxTypeCatalogImporter.php
├── Console/
│   ├── ValidateTaxParameters.php
│   ├── SyncCnae.php
│   ├── SyncIae.php
│   └── ReportRegimeCoverage.php
├── database/
│   ├── migrations/
│   └── seeders/
│       ├── catalog/
│       └── parameters/
└── tests/
    ├── Unit/Calculators/
    ├── Feature/Catalog/
    └── fixtures/aeat/
```

### 2.1 Cada `Calculator` es **régimen-aware**

No hay un único `PayrollCalculator`; hay un dispatcher que carga el régimen efectivo del input y delega:

```
PayrollCalculator (dispatcher)
├── RegimenGeneralPayroll
└── RegimenForalPayroll          # backlog

InvoiceCalculator (dispatcher)
├── EstimacionDirectaInvoice
├── EstimacionObjetivaInvoice    # módulos
└── RecargoEquivalenciaInvoice

VatReturnCalculator (dispatcher)
├── RegimenGeneralVat
├── RegimenSimplificadoVat
├── CriterioCajaVat
└── RebuVat                       # backlog

IncomeTaxCalculator (dispatcher)
├── EstimacionDirectaNormal
├── EstimacionDirectaSimplificada # incluye 5 % gastos genéricos
└── EstimacionObjetivaModulos     # modelo 131
```

### 2.2 DTO `BreakdownLine` (clave del valor de transparencia)

Cada calculator devuelve siempre un `Breakdown` con `lines: BreakdownLine[]`:

```
BreakdownLine {
  concept:          string   # "Cuota empleado contingencias comunes"
  legalReference:   string   # URL BOE consolidado
  base:             decimal
  ratePercent:      decimal
  amount:           decimal
  explanation:      string   # markdown corto
  category:         enum     # contribution|tax|reduction|deduction|net
}
```

## 3. Regímenes cubiertos

### 3.1 En MVP (M0-M8)

| Scope | Códigos | Notas |
|---|---|---|
| IRPF | `EDN`, `EDS`, `EO` | Estimación directa normal/simplificada + objetiva top-20 actividades |
| IRPF | `ASALARIADO_GEN` | Régimen general empleado por cuenta ajena |
| IVA | `IVA_GEN`, `IVA_CAJA`, `IVA_SIMPLE`, `IVA_RE` | General, criterio caja, simplificado, recargo equivalencia |
| IS | `IS_GEN`, `IS_ERD`, `IS_MICRO`, `IS_STARTUP` | Sólo en catálogo, sin calculator dedicado en MVP |
| SS | `RG`, `RETA` | Régimen general + autónomos |
| Alcance territorial | Estado + Madrid + Cataluña + Andalucía + C.Valenciana | ~70 % población |

### 3.2 Backlog explícito (fuera de MVP)

| Scope | Códigos | Razón |
|---|---|---|
| Régimen foral | País Vasco, Navarra | Sistema fiscal distinto, no es solo "otra escala" |
| IVA | `REBU`, `REAGP`, `IVA_AAVV`, `IVA_OSS`, `IVA_OIC`, `IVA_ISP`, prorrata, sectores diferenciados | Casuística estrecha, alta complejidad |
| IS | `IS_SOCIMI`, `IS_SICAV`, `IS_COOP`, `IS_CONSOL`, `IS_ESFL` | Calculadora propia futura |
| IRPF especial | Beckham, nómadas digitales | Casuística estrecha |
| IRPF | Rendimientos del capital, ganancias patrimoniales, pluriempleo, rendimientos irregulares | M6 entrega solo rendimientos del trabajo y actividades |
| Territorial | 13 CCAA restantes | M9 incremental |
| Locales | IBI, IVTM, IIVTNU, ICIO, tasas municipales | M10 — requiere ingesta de 8131 ordenanzas |
| Modelos | 720, 721, 232, 184, 347 | Modelos informativos no entran en MVP |

## 4. Modelo de datos (12 tablas)

```sql
tax_regimes
  id, code, scope (irpf|iva|is|ss), name, description, requirements (json),
  model_quarterly, model_annual, valid_from, valid_to,
  legal_reference_url, source_hash, editorial_md (longtext)

tax_regime_compatibility
  regime_a_id, regime_b_id, compatibility (required|exclusive|optional)

tax_regime_obligations
  id, regime_id, model_code, periodicity, deadline_rule, description,
  electronic_required, certificate_required, draft_available

economic_activities
  id, system (cnae|iae), code, parent_code, level, name, section,
  year, valid_from, valid_to, editorial_md

activity_regime_mappings
  id, activity_id, eligible_regimes (json), vat_rate_default,
  irpf_retention_default

tax_types
  id, code, scope (state|regional|local), levy_type (impuesto|tasa|contribucion),
  name, base_law_url, region_code (nullable), municipality_id (nullable),
  editorial_md

tax_rates
  id, tax_type_id, year, region_code, rate, base_min, base_max,
  conditions (json), source_url

vat_product_rates
  id, year, activity_code_or_keyword, rate (21|10|5|4|0|exempt),
  source_url

tax_parameters
  id, year, region_code, key, value (json), source_url,
  valid_from, valid_to

tax_brackets
  id, year, scope (state|regional), region_code, type, from_amount,
  to_amount, rate

social_security_rates
  id, year, regime, contingency, rate_employer, rate_employee,
  base_min, base_max

autonomo_brackets
  id, year, from_yield, to_yield, monthly_quota_min, monthly_quota_max,
  source_url
```

**Índices principales:**
- `tax_parameters (year, region_code, key)`
- `tax_regimes (scope, code, year)`
- `economic_activities (system, code, year)`
- `tax_brackets (year, scope, region_code)`
- `tax_rates (tax_type_id, year, region_code)`

**Foreign keys:** soft (no constraint hard) para permitir reseed por año sin cascadas.

**Versionado:** todas las tablas con datos paramétricos llevan `valid_from` y `valid_to` (DATE). Permite cambios retroactivos (caso STC plusvalía 2021), sembrar parámetros futuros pendientes de Ley PGE, mostrar al usuario "según legislación de fecha X".

**Capa editorial:** las tablas `tax_regimes`, `economic_activities`, `tax_types` llevan columna `editorial_md` (longtext) con markdown explicativo en lenguaje plano. Se redacta manualmente para los ~50 conceptos top en M1-M2.

## 5. API pública (open data CC-BY)

Todos los endpoints siguen el patrón Spatie Query Builder + paginación + filtros del resto del proyecto. Throttle `api` (100/min IP) compartido.

**Catálogo (GET):**
```
GET /api/v1/tax/regimes                    ?filter[scope]=iva&filter[year]=2025
GET /api/v1/tax/regimes/{code}
GET /api/v1/tax/activities                 ?filter[system]=cnae&filter[parent_code]=A
GET /api/v1/tax/activities/{system}/{code}
GET /api/v1/tax/types                      ?filter[scope]=state&filter[levy_type]=tasa
GET /api/v1/tax/types/{code}
GET /api/v1/tax/parameters/{year}          ?filter[region_code]=MAD
GET /api/v1/tax/obligations                ?regime=EDS&year=2025
GET /api/v1/tax/calendar                   ?regime=EDS&year=2025
```

**Calculadoras (POST, idempotente, sin auth):**
```
POST /api/v1/tax/payroll
POST /api/v1/tax/invoice
POST /api/v1/tax/vat-return
POST /api/v1/tax/income-tax
```

**Compartir resultados:** las calculadoras admiten `?share=<hmac_signed_payload>` en query string para reproducir un cálculo desde URL pública. **No** se persisten cálculos en BD.

## 6. Frontend (Nuxt 3)

```
pages/
├── transparencia-fiscal.vue            # índice del módulo
├── regimenes/
│   ├── index.vue                       # explorador con filtro scope
│   └── [code].vue                      # ficha régimen + obligaciones + modelos
├── actividades/
│   ├── index.vue                       # buscador CNAE/IAE
│   └── [system]/[code].vue             # ficha actividad + regímenes elegibles + IVA
├── impuestos/
│   ├── index.vue                       # catálogo con filtro estatal/autonómico
│   └── [code].vue                      # ficha impuesto/tasa
├── calendario-fiscal.vue               # calendario interactivo según régimen
└── calculadoras/
    ├── index.vue
    ├── nomina.vue
    ├── factura.vue
    ├── iva.vue
    ├── irpf.vue
    └── pagos-fraccionados.vue
```

Composables:
- `useTaxCatalog()` — wrapper sobre API catálogo con cache local
- `useTaxCalculator(type)` — postea inputs, recibe `Breakdown`, renderiza tabla colapsable
- `useFiscalCalendar(regime, year)` — obligaciones por régimen

**Tooltip legal:** cada `BreakdownLine` muestra al hover dos enlaces — BOE consolidado + RD original — separados.

## 7. Sinergias internas

- **Módulo Legislation:** `BoeParameterDetector` monitoriza ingesta diaria. Cuando detecta RD del patrón `BOE-A-{year}-\d+ Real Decreto.*Reglamento.*Renta.*Personas Físicas` o equivalentes (Ley PGE, modificaciones IRPF/IVA), crea entrada en BD interna `tax_parameter_alerts (id, source_boe_id, suggested_action, status, created_at)` para revisión admin. **No** abre issue GitHub — flujo interno.
- **Módulo Officials:** ninguna directa.
- **Módulo Subsidies / Contracts:** futuras métricas cruzadas (ej. coste fiscal de subvenciones, recaudación AEAT vs gasto público) quedan fuera de MVP.

## 8. Decisiones aprobadas (resumen)

| # | Decisión | Resolución |
|---|---|---|
| 1 | Empezar por catálogo antes que calculadoras | SÍ — M1+M2 primero |
| 2 | 4 CCAA top vs 17 en MVP | SÍ — 4 (Madrid+Cat+And+CV) |
| 3 | Recorte de regímenes en calculadoras MVP | Aprobado §3.1 |
| 4 | PDFs de modelos rellenados informativos | NO en MVP |
| 5 | Historial "mis cálculos" con auth | NO — compartir por URL HMAC |
| 6 | Detector RD: GitHub issue vs BD interna | BD interna + dashboard admin |
| 7 | Régimen foral PV/Navarra | Fuera de alcance, FAQ transparente |
| 8 | Multi-año desde 2023 | SÍ — desde 2023 |
| 9 | Open data CC-BY del catálogo | SÍ — prioritario |
| 10 | BOE consolidado vs RD original | Ambos separados |
| 11 | CNAE-2009 + CNAE-2025 simultáneos | SÍ durante transición |
| 12 | Importer IAE automático | SÍ + fixture committeado |
| 13 | Calendario fiscal: AEAT + cuotas SS | SÍ ambos |
| 14 | Atributos extra obligaciones (electrónica/cert/borrador) | SÍ |

## 9. Roadmap

| Fase | Alcance | Estimación | Bloquea a |
|---|---|---|---|
| M0 | Cimientos: 12 migraciones, ServiceProvider, repositorios, DTOs | 1 sem | M1, M2, M3 |
| M1 | Catálogo regímenes + CNAE-2025 + IAE + páginas Nuxt + open data | 2 sem | M5 |
| M2 | Catálogo impuestos+tasas estatales+4 CCAA + página Nuxt | 1 sem | — |
| M3 | Parámetros numéricos 2023-2026 + BoeParameterDetector | 3 días | M4 |
| M4 | PayrollCalculator (asalariado régimen general 4 CCAA) | 2 sem | M6 |
| M5 | InvoiceCalculator (autónomo, dispatch IVA+IRPF) | 1 sem | M7 |
| M6 | IncomeTaxCalculator (modelo 100, EDN/EDS/EO) | 3 sem | M8 |
| M7 | VatReturnCalculator (modelo 303/390 general+caja+simplificado) | 2 sem | — |
| M8 | Pagos fraccionados modelo 130 + 131 | 1 sem | — |

**MVP utilizable (M0-M5):** ~7-8 semanas
**MVP completo (M0-M8):** ~13-15 semanas

**Backlog explícito (no entra en MVP):**
- M9: 13 CCAA restantes + tributos cedidos completos (ITP/AJD/ISD/IP por CCAA)
- M10: tributos locales (IBI, IVTM, plusvalía, ICIO, tasas)
- M11: regímenes forales PV/Navarra
- Calculator IS dedicado (SOCIMI, SICAV, cooperativas, consolidación, ESFL)
- Régimen Beckham y nómadas digitales
- Rendimientos del capital, ganancias patrimoniales, pluriempleo, rendimientos irregulares
- Modelos informativos: 720, 721, 232, 184, 347
- Prorrata IVA, sectores diferenciados, REBU, REAGP, OSS, OIC, ISP completo
- PDF rellenable de modelos
- Historial "mis cálculos" con auth

## 10. Riesgos y mitigaciones (resumen del research)

| # | Riesgo | Mitigación |
|---|---|---|
| 1 | Parámetros desactualizados a 1 enero | Cron diciembre + alerta vía Legislation |
| 2 | 17 CCAA con escalas propias | MVP solo 4 CCAA top, FAQ transparente |
| 3 | Régimen foral distinto sistema | Fuera MVP, banner informativo |
| 4 | Tramos autónomos + regularización SS↔AEAT | Tabla `autonomo_brackets` + nota explicativa |
| 5 | Convenios colectivos en nómina | Calculadora "estándar", disclaimer |
| 6 | Casuística IVA (prorrata, ISP, REBU) | Solo régimen general+caja+simplificado+RE en MVP |
| 7 | Errores de céntimo | bcmath/decimal, golden tests AEAT en CI |
| 8 | Cambios retroactivos (STC) | `valid_from/valid_to` en todas las tablas |
| 9 | Responsabilidad legal | Disclaimer prominente en cada calculator |
| 10 | PII en logs | Logs anonimizados o desactivados, cálculo stateless |
| 11 | CNAE-2009 vs 2025 | Ambos sistemas en BD durante transición |
| 12 | Estimación objetiva (cientos de actividades) | MVP top-20, resto incremental |
| 13 | 8131 ordenanzas locales | Fuera MVP completo |
| 14 | Modelos cambian con el tiempo | `tax_regime_obligations` versionado |
| 15 | Combinatoria regímenes compatibles | `RegimeResolver` valida antes de calcular |

## 11. Estructura de specs hijos

Este spec es paraguas. Cada fase del roadmap tendrá su propio spec hijo + plan de implementación bajo `docs/superpowers/specs/2026-04-25-tax-mN-*.md`. Empezamos por:

1. `2026-04-25-tax-m0-foundation-design.md` — cimientos
2. `2026-04-25-tax-m1-catalog-regimes-design.md` — regímenes + CNAE/IAE
3. `2026-04-25-tax-m2-catalog-types-design.md` — impuestos + tasas
4. `2026-04-25-tax-m3-parameters-design.md` — parámetros numéricos
5. `2026-04-25-tax-m4-payroll-design.md` — calculadora nómina
6. `2026-04-25-tax-m5-invoice-design.md` — calculadora factura
7. `2026-04-25-tax-m6-income-tax-design.md` — calculadora IRPF
8. `2026-04-25-tax-m7-vat-design.md` — calculadora IVA
9. `2026-04-25-tax-m8-fractional-payments-design.md` — modelos 130/131

## 12. Próximo paso

Implementar **M0 (cimientos)**. Es la fase de menor riesgo y desbloquea todo el resto. Abrir spec hijo `2026-04-25-tax-m0-foundation-design.md` con migraciones detalladas + plan de tareas TDD.
