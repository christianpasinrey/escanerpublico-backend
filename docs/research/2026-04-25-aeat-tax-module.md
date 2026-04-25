# Investigación módulo Tax — Escáner Público / GobTracker

Fecha: 2026-04-25
Autor: research spike (Claude)
Estado: propuesta, no implementación

---

## 1. Resumen ejecutivo

El módulo `Tax` tiene **dos caras complementarias**, no solo calculadoras:

- **Cara enciclopédica (catálogo)**: estructurar en BD el universo tributario español que hoy está disperso entre BOE, AEAT, INE, Hacienda autonómica y ordenanzas municipales — regímenes tributarios, actividades económicas (CNAE 2025 + IAE), catálogo de impuestos y tasas (estatales/autonómicas/locales), obligaciones por régimen, modelos asociados. Cada entidad versionada por año fiscal con `valid_from/valid_to` y `legalReference` al BOE / boletín autonómico / ordenanza municipal.
- **Cara calculadora**: PayrollCalculator, InvoiceCalculator, VatReturnCalculator, IncomeTaxCalculator que **consumen** el catálogo + `tax_parameters` y producen breakdowns explicados.

No existe en España un dataset oficial AEAT/SS publicado en JSON/CSV con escalas IRPF, bases de cotización y tipos IVA listos para consumir; los parámetros viven en PDFs del BOE (Reales Decretos anuales) y en páginas estáticas. Igual sucede con el catálogo de tasas y obligaciones: público pero **disperso en HTML, PDFs y ordenanzas locales**. La estrategia recomendada es **mantener todo en BD versionado por año, populado por seeders revisados cada diciembre**, con detección semi-automática de cambios vía el módulo `Legislation` existente. El valor diferencial frente a competidores comerciales (APImpuestos, calculadoras de Cinco Días) es que **publicaremos el catálogo como open data** (CC-BY) — encaja exactamente con el ADN del proyecto: transparencia.

Como referencia técnica, `FacturaScripts/modelo303` (LGPL-3.0, PHP) es la única implementación open source seria de modelo 303 en PHP; para IRPF/nómina no hay package PHP reutilizable. **Build, no buy.** Empezar por **catálogo (M0-M1)** + **Nómina (M2)** + **Factura autónomo (M3)** porque comparten el 80% de la lógica IRPF/SS; modelos 303/130/100 derivan de ellos en M4-M5.

---

## 2. Fuentes de datos paramétricos

| Fuente | Cubre | Formato | Actualización | Score |
|---|---|---|---|---|
| [AEAT — Catálogo datos abiertos](https://sede.agenciatributaria.gob.es/Sede/gobierno-abierto/reutilizacion-informacion/datos-disponibles-catalogo-datos-abiertos-tributaria.html) | Estadísticas agregadas (recaudación, declarantes IRPF) | XLS, CSV, web app "Anuario Estadístico" | Anual, retrasada | 1/5 — útil para visualizaciones macro, **no para parámetros vigentes** |
| [datos.gob.es — IRPF declarantes](https://datos.gob.es/en/catalogo/ea0028512-estadistica-de-los-declarantes-del-impuesto-sobre-la-renta-de-las-personas-fisicas-irpf) | Declarantes IRPF histórico | XLS | Anual | 1/5 — solo histórico estadístico |
| [datos.gob.es — Cotizaciones SS](https://datos.gob.es/en/catalogo/a03002951-cotizacion-ss-espana-v3) | Bases de cotización SS desde 2000 | CSV | Anual | 3/5 — útil como histórico, pero no incluye los **tipos** de cotización por contingencia |
| [BOE — Real Decreto anual IRPF](https://www.boe.es/buscar/doc.php?id=BOE-A-2007-6820) (RD 439/2007 + modificaciones anuales: RD 142/2024, RD 1008/2023, RD 899/2021…) | Tablas retención IRPF, escalas | PDF + HTML semi-estructurado | Anual (Dic/Ene) | 3/5 — **fuente oficial**, pero requiere parsing manual o regex sobre HTML del BOE |
| [Sede SS — Bases y tipos cotización](https://www.seg-social.es/wps/portal/wss/internet/Trabajadores/CotizacionRecaudacionTrabajadores/36537) | Bases mín/máx, tipos por contingencia, MEI, cuotas autónomos por tramos | HTML (tabla) | Anual | 4/5 — la más completa para SS pero **no descargable estructurada** |
| [Ley 35/2006 IRPF](https://www.boe.es/buscar/act.php?id=BOE-A-2006-20764) (texto consolidado) | Escala estatal, mínimos personales/familiares, deducciones | HTML BOE | Cuando hay reforma | 4/5 — **fuente legal canónica** |
| Boletines autonómicos (Madrid, Cataluña, etc.) | Escala autonómica IRPF | HTML/PDF | Anual, descentralizada | 2/5 — 17 fuentes distintas, alta fricción |
| [AEAT — Calculadora retenciones online](https://sede.agenciatributaria.gob.es/Sede/irpf/gestiones-irpf.html) | Retención IRPF nómina | HTML form (no API) | Anual | 2/5 — **referencia para validar nuestros cálculos**, no consumible |

**Veredicto:** no hay "API AEAT de parámetros". Hay que mantener un seeder versionado (`TaxParameter2024Seeder`, `TaxParameter2025Seeder`…) revisado contra BOE en diciembre. Sinergia con módulo `Legislation` existente: cuando `LegislationIngestor` detecte un Real Decreto que matchee patrones tipo `BOE-A-{year}-\d+ Real Decreto.*Reglamento.*Renta.*Personas Físicas`, generar alerta para revisar parámetros del año siguiente.

---

## 2.bis Fuentes para el catálogo enciclopédico

El catálogo no son escalas/tipos numéricos — es la **estructura conceptual** del sistema tributario español. Mapeo de fuentes:

| Dimensión | Fuente | Formato | Notas |
|---|---|---|---|
| **CNAE 2025** (clasificación nacional de actividades económicas) | [INE — CNAE-2025](https://www.ine.es/dyngs/INEbase/es/operacion.htm?c=Estadistica_C&cid=1254736177033) — Real Decreto 10/2025 | XLS/PDF + ficha por código | 5 niveles (sección/división/grupo/clase/subclase). Sustituye CNAE-2009 para fines estadísticos pero IAE sigue ligado a estructura antigua |
| **IAE** (Impuesto Actividades Económicas — epígrafes) | [AEAT — Tarifas e Instrucción del IAE](https://sede.agenciatributaria.gob.es/Sede/iae.html), [RDLeg 1175/1990](https://www.boe.es/buscar/act.php?id=BOE-A-1990-23930) | HTML semi-estructurado + PDF | 3 secciones: Empresariales, Profesionales, Artísticas. Tabla de equivalencias IAE↔CNAE existe pero parcial |
| **Regímenes IRPF** (estimación directa normal/simplificada, módulos, atribución de rentas) | [Ley 35/2006 IRPF](https://www.boe.es/buscar/act.php?id=BOE-A-2006-20764) + [RD 439/2007 Reglamento](https://www.boe.es/buscar/act.php?id=BOE-A-2007-6820) | HTML BOE | Cada régimen con requisitos numéricos (umbrales facturación, sectores excluidos) |
| **Regímenes IVA** (general, simplificado, REAGP, recargo equivalencia, REBU, ISP) | [Ley 37/1992 IVA](https://www.boe.es/buscar/act.php?id=BOE-A-1992-28740) | HTML BOE | Cada régimen con compatibilidades, exclusiones, modelos asociados |
| **Regímenes especiales SS** (general, autónomos RETA, agrario, mar, hogar, minería) | [TGSS — Regímenes](https://www.seg-social.es/wps/portal/wss/internet/Trabajadores) | HTML | Tipos cotización propios por régimen |
| **Obligaciones censales** | [Modelo 036/037 — instrucciones](https://sede.agenciatributaria.gob.es/Sede/procedimientoini/G322.shtml) | PDF + HTML | Mapping regimen→modelos a presentar→periodicidad |
| **Catálogo de impuestos estatales** | [AEAT — Impuestos](https://sede.agenciatributaria.gob.es/Sede/impuestos-tasas.html) | HTML | IRPF, IS, IVA, ITP/AJD, ISD, IIEE (alcohol/hidrocarburos/tabaco/electricidad), IGIC (Canarias), IPSI (Ceuta/Melilla), Impuestos Medioambientales |
| **Catálogo de tasas estatales** | [PortalCiudadano — Tasas y precios públicos](https://administracion.gob.es/), BOE leyes ad-hoc por tasa | HTML + BOE | Tasa judicial, tasa DNI, tasa pasaportes, tasa expedición títulos universitarios, tasas portuarias/aeroportuarias, tasa CNMV, tasa Boletín Oficial… cientos |
| **Tributos cedidos a CCAA** | [Ley 22/2009 financiación autonómica](https://www.boe.es/buscar/act.php?id=BOE-A-2009-20375) + boletines autonómicos | HTML BOE + 17 boletines | ITP, AJD, ISD, IP, tributos sobre juego, hidrocarburos minorista, tasa retirada vehículos… |
| **Tributos locales** | [RDLeg 2/2004 TR Ley Haciendas Locales](https://www.boe.es/buscar/act.php?id=BOE-A-2004-4214) + ordenanzas municipales | HTML BOE + 8131 ordenanzas | IBI, IVTM, ICIO, IIVTNU (plusvalía municipal), IAE local, tasas (basura, agua, terraza, vado…), contribuciones especiales |
| **Tipos IVA por producto/servicio** | [AEAT — Tipos IVA aplicables](https://sede.agenciatributaria.gob.es/Sede/iva.html) | HTML largo | Reglas por sector (productos básicos 4%, hostelería 10%, etc.) |
| **Tasas notariales y registrales** | [RD 1426/1989 aranceles notariales](https://www.boe.es/buscar/act.php?id=BOE-A-1989-29723) | BOE | Públicas pero opacas; tabla escalonada por base |
| **Coeficientes IIVTNU plusvalía municipal** | [Ley 7/1985 LRBRL + RDL 26/2021 post-STC](https://www.boe.es/buscar/act.php?id=BOE-A-2021-19511) | BOE + ordenanzas | Cambian anualmente vía Ley PGE; cada ayto. publica los suyos |
| **Tarifas cotización autónomos por tramos (RD-ley 13/2022)** | [BOE-A-2022-12482](https://www.boe.es/buscar/act.php?id=BOE-A-2022-12482) | BOE | 15 tramos por rendimiento neto + cuota mensual + transitoria 2023-2025 |

**Veredicto:** la información existe, está pública, pero **vivir sin un catálogo estructurado es exactamente el problema que el proyecto resuelve**. Mantener todo en BD versionado por año + `valid_from/valid_to` + `source_url` al BOE. Patrón idéntico al de `Legislation`: ingestor con idempotencia `(year, scope, code)` + `content_hash`. El catálogo se nutre 80% por seeders revisados manualmente y 20% por scrapers focalizados (CNAE-INE, tarifa IAE, tramos autónomos publicados).

---

## 2.ter Matriz completa de regímenes y criterios fiscales

Cada calculator debe ser **régimen-aware**: el mismo input bruto da resultados radicalmente distintos según el régimen elegido por el contribuyente. Esta es la matriz que el catálogo debe cubrir.

### IRPF — Rendimientos de actividades económicas

| Régimen | Código | Quién puede | Criterio temporal | Reglas | Modelo trimestral | Modelo anual |
|---|---|---|---|---|---|---|
| **Estimación directa normal** | `EDN` | Cifra negocio > 600.000 € o renuncia a simplificada | Devengo (factura emitida) | Gastos reales íntegramente deducibles, libros mercantiles | 130 (20 % rendimiento neto) | 100 |
| **Estimación directa simplificada** | `EDS` | Cifra negocio ≤ 600.000 €, no excluido, no renuncia | Devengo | Amortización tabla simplificada, **5 % gastos genéricos** (tope 2000 €), libros simplificados | 130 (20 %) | 100 |
| **Estimación objetiva (módulos)** | `EO` | Actividades del Anexo de la Orden HFP anual + límites: rdto íntegro ≤ 250 k (general) / ≤ 150 k (agrario), compras ≤ 250 k | Anual por módulos (no devengo ni caja) | Rendimiento por **signos/índices** (m², kW, personal, vehículos…), no se deducen gastos reales | 131 (no 130) | 100 |
| **Atribución de rentas** | `AR` | Comunidades de bienes, sociedades civiles sin objeto mercantil | Régimen del comunero | Imputación a socios según pacto | (cada comunero su 130/131) | 100 + 184 informativo |

### IVA — Regímenes y criterios

| Régimen | Código | Quién | Criterio temporal | Reglas | Modelo |
|---|---|---|---|---|---|
| **Régimen general** | `IVA_GEN` | Defecto | Devengo (factura emitida) | IVA devengado − IVA soportado deducible | 303 trim / 390 anual + 349 si UE |
| **Criterio de caja** | `IVA_CAJA` | Volumen op. ≤ 2 M €, opcional, irrenunciable 3 años | **Caja** (cobro/pago real) hasta 31-dic año posterior | IVA se devenga al cobrar, se deduce al pagar; nº cuenta bancario obligatorio en factura | 303 + 322 (libro registro caja) |
| **Régimen simplificado** | `IVA_SIMPLE` | Compatible con EO IRPF, mismas actividades + límites | Trimestral por módulos | Cuota a ingresar por módulos − IVA soportado deducible | 303 trim simplificado + 390 |
| **Recargo de equivalencia** | `IVA_RE` | Comerciantes minoristas persona física (no SL) | Devengo | **Sin obligación de declarar IVA**: el proveedor le recarga 5,2 % / 1,4 % / 0,5 % según tipo. No deduce nada | (ninguno propio; lo paga el proveedor) |
| **REAGP — Régimen agricultura, ganadería y pesca** | `IVA_REAGP` | Titulares explotaciones agrarias | Devengo | Compensación a tanto alzado 12 % / 10,5 % en lugar de declarar | (no 303; receptor paga compensación) |
| **REBU — Bienes usados** | `IVA_REBU` | Revendedores bienes usados, antigüedades, objetos arte | Devengo | Base = margen de beneficio (precio venta − precio compra), no precio total | 303 + libro REBU |
| **Régimen especial agencias de viaje** | `IVA_AAVV` | Agencias de viajes minoristas | Devengo | Base = margen agencia | 303 |
| **Inversión sujeto pasivo (ISP)** | `IVA_ISP` | Operaciones específicas (chatarra, oro inversión, móviles/tablets entre empresas, construcción/rehabilitación, arrendamiento inmuebles afectos…) | Según operación | El cliente autoliquida el IVA, el emisor factura sin IVA | 303 con casillas ISP |
| **Operaciones intracomunitarias** | `IVA_OIC` | Sujetos con NIF-IVA inscritos en ROI | Devengo | Adquisiciones intracomunitarias se autoliquidan en 303 + 349 informativo | 303 + 349 |
| **OSS / IOSS** | `IVA_OSS` | Ventas a distancia B2C en UE > 10 k € umbral | Devengo | Una sola declaración trimestral por todos los Estados de consumo | 369 (OSS) / 369 (IOSS) |
| **Exento sin derecho a deducción** | `IVA_EXENTO` | Sanidad, educación, financiero, seguros, alquiler vivienda… (art. 20 Ley IVA) | n/a | No factura IVA, no deduce IVA soportado | (ninguno o 390 informativo) |

### Sociedades

| Régimen | Código | Tipo gravamen 2026 | Notas |
|---|---|---|---|
| **General** | `IS_GEN` | 25 % | Empresas en general |
| **Tipo reducido entidades de reducida dimensión** | `IS_ERD` | 25 % | Cifra negocio < 10 M € (ya unificado al general desde 2023) |
| **Microempresas (cifra negocio < 1 M €)** | `IS_MICRO` | 21 % progresivo a 23 % | Reforma Ley 7/2024 |
| **Empresas emergentes (Ley 28/2022)** | `IS_STARTUP` | 15 % primeros 4 ejercicios con BI positiva | Certificación ENISA |
| **Cooperativas protegidas** | `IS_COOP` | 20 % resultado cooperativo + 25 % extracoop. | |
| **SOCIMI** | `IS_SOCIMI` | 0 % en sociedad + 19 % retención dividendo socio | |
| **SICAV** | `IS_SICAV` | 1 % | Tras reforma 2022, requisitos estrictos socios |
| **Régimen consolidación fiscal** | `IS_CONSOL` | Tipo del grupo | Modelo 220 |
| **Régimen entidades sin fines lucrativos (Ley 49/2002)** | `IS_ESFL` | 10 % rentas no exentas | Fundaciones, asociaciones DUP |

### Otros criterios transversales

| Criterio | Aplica a | Efecto |
|---|---|---|
| **Devengo vs caja** | IVA, IRPF actividades | Cuándo se imputa el ingreso/gasto |
| **Prorrata general / especial** | IVA con actividades exentas+gravadas | Limita IVA soportado deducible |
| **Sectores diferenciados** | IVA con actividades muy distintas | Prorrata por sector |
| **Operaciones vinculadas** | IS, IRPF | Valoración a mercado obligatoria, modelo 232 |
| **Reducción rentas irregulares (>2 años)** | IRPF rendimientos del trabajo | 30 % reducción tope 300 k |
| **Régimen impatriados (Beckham)** | IRPF | Tributación 24 % hasta 600 k durante 6 años |
| **Régimen nómadas digitales (Startup Law)** | IRPF | Variante Beckham para teletrabajadores extranjeros |

**Modelado en BD:** tabla `tax_regimes` con `(code, scope=irpf|iva|is, name, description, requirements json, model_quarterly, model_annual, valid_from, valid_to, legal_reference_url)`. Tabla `tax_regime_compatibility` para representar compatibilidades (ej. `EO IRPF` ↔ `IVA_SIMPLE` ↔ `IVA_RE`). Cada calculator carga el régimen activo del input y aplica la fórmula correspondiente — no hay un solo "PayrollCalculator", hay dispatch por régimen.

---

## 3. Librerías open source candidatas

| Nombre | Lenguaje | Stars | Última act. | Cubre | Licencia |
|---|---|---|---|---|---|
| [FacturaScripts/modelo303](https://github.com/FacturaScripts/modelo303) | PHP | 2 | activo | Modelo 303 IVA (genera PDF/XLS desde libro IVA) | LGPL-3.0 |
| [NeoRazorX/facturascripts](https://github.com/NeoRazorX/facturascripts) | PHP | ERP completo | activo | ERP fiscal ES, modelos 303/347/390 vía plugins | LGPL-3.0 |
| [paumrch/larenta](https://github.com/paumrch/larenta) | Astro/JS | 92 | Abr 2026 | Guía IRPF 2025 + asistente deducciones (frontend) | revisar |
| [GeiserX/DeclaRenta](https://github.com/GeiserX/DeclaRenta) | TypeScript | 6 | Abr 2026 | Genera modelos 100/720/721/D-6 desde brokers extranjeros | revisar |
| [luxeon/spanish-tax-calculators](https://github.com/luxeon/spanish-tax-calculators) | JavaScript | 3 | Mar 2026 | Calculadoras autónomo IRPF + RETA + ITP en JS puro | revisar |
| [akrck02/salary](https://github.com/akrck02/salary) | TypeScript | 6 | Ene 2026 | Calculadora nómina País Vasco (régimen foral) | revisar |
| [jongonzlz/Calculadora-de-Salarios-y-Progresividad-en-Frio](https://github.com/jongonzlz/Calculadora-de-Salarios-y-Progresividad-en-Fr-o) | Python | — | reciente | Auditoría salario neto 2012-2026 + IRPF + SS + MEI + inflación | revisar |
| [valeriansaliou/node-sales-tax](https://github.com/valeriansaliou/node-sales-tax) | Node.js | popular | activo | Tipos IVA internacionales offline + validación VIES | MIT |

**Conclusión:** ningún package PHP "calculadora IRPF España" digno de dependerse. `FacturaScripts/modelo303` es referencia para entender el formato del modelo (no se reutiliza directamente porque depende del ORM de FacturaScripts). Los proyectos JS/TS sirven como **fixtures de test** (comparar nuestros números contra los suyos) pero no como dependencia. **Recomendación: build, no buy.** Ningún `holaluz/iva-calculator` real encontrado — era una hipótesis del usuario.

---

## 4. Arquitectura propuesta

```
app/Modules/Tax/
├── TaxServiceProvider.php
├── Routes/
│   └── api.php                       (catálogo público GET + cálculos POST)
├── Http/
│   ├── Controllers/
│   │   ├── Catalog/
│   │   │   ├── RegimeController.php          GET /api/v1/tax/regimes[/{code}]
│   │   │   ├── EconomicActivityController.php GET /api/v1/tax/activities (CNAE+IAE)
│   │   │   ├── TaxTypeController.php         GET /api/v1/tax/types (impuestos+tasas)
│   │   │   └── ParameterController.php       GET /api/v1/tax/parameters/{year} (open data)
│   │   ├── Calculators/
│   │   │   ├── PayrollController.php         POST /api/v1/tax/payroll
│   │   │   ├── InvoiceController.php         POST /api/v1/tax/invoice
│   │   │   ├── VatReturnController.php       POST /api/v1/tax/vat-return
│   │   │   └── IncomeTaxController.php       POST /api/v1/tax/income-tax
│   │   └── ObligationsController.php         GET /api/v1/tax/obligations?regime=...&year=...
│   ├── Requests/                     (FormRequest por calculator + validación régimen)
│   └── Resources/                    (Resources del catálogo)
├── Models/
│   │  # —— Catálogo enciclopédico (versionado por año) ——
│   ├── TaxRegime.php                 (code, scope=irpf|iva|is|ss, name, requirements, valid_from/to)
│   ├── TaxRegimeCompatibility.php    (regime_a_id, regime_b_id, compatibility=required|exclusive|optional)
│   ├── TaxRegimeObligation.php       (regime_id, obligation_type, model, periodicity, deadline_rule)
│   ├── EconomicActivity.php          (system=cnae|iae, code, parent_code, level, name, year)
│   ├── ActivityRegimeMapping.php     (activity_id, eligible_regimes json) — qué regímenes puede elegir
│   ├── TaxType.php                   (code, scope=state|regional|local, name, levy_type=impuesto|tasa|contribucion, base_law_url)
│   ├── TaxRate.php                   (tax_type_id, year, region_code, rate, base_min, base_max, conditions json)
│   ├── VatProductRate.php            (year, cnae_or_keyword, rate=21|10|5|4|0|exempt, source_url)
│   │  # —— Parámetros numéricos ——
│   ├── TaxParameter.php              (year, region_code, key, value json, source_url, valid_from/to)
│   ├── TaxBracket.php                (year, scope=state|regional, region_code, type, from_amount, to_amount, rate)
│   ├── SocialSecurityRate.php        (year, regime, contingency, rate_employer, rate_employee, base_min, base_max)
│   ├── AutonomoBracket.php           (year, from_yield, to_yield, monthly_quota_min, monthly_quota_max)
│   │  # —— Calculadoras (opcional, para "compartir cálculo") ——
│   └── TaxCalculationShare.php       (uuid, calculator_type, inputs_signed_json, created_at, expires_at)
├── Calculators/
│   ├── Payroll/
│   │   ├── PayrollCalculator.php             (dispatch principal)
│   │   ├── RegimenGeneralPayroll.php
│   │   └── RegimenForalPayroll.php           (futuro)
│   ├── Invoice/
│   │   ├── InvoiceCalculator.php             (dispatch IVA régimen + IRPF)
│   │   ├── EstimacionDirectaInvoice.php
│   │   ├── EstimacionObjetivaInvoice.php
│   │   └── RecargoEquivalenciaInvoice.php
│   ├── Vat/
│   │   ├── VatReturnCalculator.php           (dispatch por régimen IVA)
│   │   ├── RegimenGeneralVat.php             (modelo 303)
│   │   ├── RegimenSimplificadoVat.php
│   │   ├── CriterioCajaVat.php
│   │   └── RebuVat.php
│   └── IncomeTax/
│       ├── IncomeTaxCalculator.php           (dispatch por régimen IRPF)
│       ├── EstimacionDirectaNormal.php
│       ├── EstimacionDirectaSimplificada.php (incluye 5 % gastos genéricos)
│       └── EstimacionObjetivaModulos.php     (modelo 131)
├── Services/
│   ├── TaxParameterRepository.php            (cache estática por año)
│   ├── RegimeResolver.php                    (input usuario → régimen efectivo + compatibilidades)
│   ├── IrpfScaleResolver.php                 (estatal + autonómica → escala efectiva)
│   ├── SocialSecurityResolver.php            (régimen general / autónomos por tramos)
│   ├── ObligationsResolver.php               (régimen + año → calendario fiscal)
│   ├── CnaeIaeMapper.php                     (CNAE 2025 ↔ IAE)
│   └── BoeParameterDetector.php              (sinergia con módulo Legislation)
├── DTOs/
│   ├── PayrollInput.php / PayrollResult.php
│   ├── InvoiceInput.php / InvoiceResult.php
│   ├── VatReturnInput.php / VatReturnResult.php
│   ├── IncomeTaxInput.php / IncomeTaxResult.php
│   ├── BreakdownLine.php                     (concept, legalReference, base, rate, amount, explanation)
│   └── ObligationCalendar.php                (lista de fechas con modelo+plazo)
├── Ingestion/
│   ├── CnaeImporter.php                      (importa CNAE 2025 desde XLS INE)
│   ├── IaeImporter.php                       (importa epígrafes IAE de AEAT)
│   ├── AutonomoBracketsImporter.php          (BOE-A-2022-12482 + actualizaciones)
│   └── TaxTypeCatalogImporter.php            (catálogo de impuestos+tasas)
├── Console/
│   ├── ValidateTaxParameters.php             (artisan tax:validate {year})
│   ├── SyncCnae.php                          (artisan tax:sync-cnae)
│   ├── SyncIae.php                           (artisan tax:sync-iae)
│   └── ReportRegimeCoverage.php              (qué regímenes están implementados, qué no)
├── database/
│   ├── migrations/
│   └── seeders/
│       ├── catalog/
│       │   ├── TaxRegimesSeeder.php          (catálogo de regímenes IRPF/IVA/IS/SS)
│       │   ├── TaxTypesSeeder.php            (catálogo impuestos+tasas estatales/autonómicos/locales)
│       │   ├── VatProductRatesSeeder.php     (mapeo CNAE→tipo IVA aplicable)
│       │   └── ActivityRegimeMappingSeeder.php (qué regímenes admite cada CNAE/IAE)
│       └── parameters/
│           ├── TaxParameters2023Seeder.php
│           ├── TaxParameters2024Seeder.php
│           ├── TaxParameters2025Seeder.php
│           └── TaxParameters2026Seeder.php
└── tests/
    ├── Unit/Calculators/                     (golden tests por régimen)
    ├── Feature/Catalog/                      (consultas API catálogo)
    └── fixtures/aeat/                        (capturas calculadora oficial AEAT)
```

| Calculator | Responsabilidad | Inputs principales | Output |
|---|---|---|---|
| `PayrollCalculator` | Bruto → neto mensual/anual | bruto anual, nº pagas, situación familiar (hijos, ascendientes, discapacidad), CCAA, tipo contrato, edad | breakdown: bruto, base SS, cuota SS empleado (CC+desempleo+FP+MEI), base IRPF, retención IRPF, neto + cuota empresa informativa |
| `InvoiceCalculator` | Generador factura autónomo | base, tipo IVA, retención IRPF aplicable (7%/15%) | breakdown: base, IVA repercutido, retención IRPF, total a cobrar |
| `VatReturnCalculator` | Modelo 303 (trimestral) y 390 (anual) | listado facturas emitidas + recibidas con tipos | breakdown: IVA devengado por tipo, IVA soportado, resultado a ingresar/compensar |
| `IncomeTaxCalculator` | Modelo 100 (anual) y 130 (trimestral autónomos) | rendimientos trabajo/actividades + retenciones soportadas + mínimos + deducciones + CCAA | breakdown: base general, base ahorro, cuota íntegra estatal+autonómica, deducciones, cuota líquida, resultado |

Cada calculator devuelve **siempre** un `Breakdown` con `lines: BreakdownLine[]`, donde cada `BreakdownLine` contiene `concept`, `legalReference` (URL al BOE), `base`, `ratePercent`, `amount`, `explanation`. Esto es lo que aporta valor de transparencia: el ciudadano ve **por qué** paga cada euro.

**BD ampliada (≈12 tablas):**

```
tax_regimes                  (id, code, scope, name, description, requirements json,
                              model_quarterly, model_annual, valid_from, valid_to,
                              legal_reference_url, source_hash)
tax_regime_compatibility     (regime_a_id, regime_b_id, compatibility=required|exclusive|optional)
tax_regime_obligations       (id, regime_id, model_code, periodicity, deadline_rule, description)
economic_activities          (id, system=cnae|iae, code, parent_code, level, name,
                              section, year, valid_from, valid_to)
activity_regime_mappings     (id, activity_id, eligible_regimes json, vat_rate_default)
tax_types                    (id, code, scope=state|regional|local, levy_type, name,
                              base_law_url, region_code nullable, municipality_id nullable)
tax_rates                    (id, tax_type_id, year, region_code, rate, base_min,
                              base_max, conditions json, source_url)
vat_product_rates            (id, year, activity_code_or_keyword, rate, source_url)
tax_parameters               (id, year, region_code, key, value json, source_url,
                              valid_from, valid_to)
tax_brackets                 (id, year, scope, region_code, type, from_amount,
                              to_amount, rate)
social_security_rates        (id, year, regime, contingency, rate_employer,
                              rate_employee, base_min, base_max)
autonomo_brackets            (id, year, from_yield, to_yield, monthly_quota_min,
                              monthly_quota_max, source_url)
```

Índices: `(year, region_code, key)` en parameters, `(scope, code, year)` en regimes, `(system, code, year)` en activities, `(year, scope, region_code)` en brackets. Foreign keys soft (no constraint hard) para permitir reseed por año sin cascadas.

**Cache:** los **parámetros** y **catálogo** sí se cachean (Redis, TTL infinito invalidado al reseed via `php artisan tax:cache-bust {year}`). Los **resultados de cálculo** NO — son inputs personales no autenticados, además son baratos (<5ms).

**Frontend Nuxt 3:**
- `pages/calculadoras/{nomina,factura,iva,irpf}.vue` — calculadoras con composable `useTaxCalculator()` que postea al endpoint y renderiza el breakdown como tabla colapsable con tooltips legales.
- `pages/regimenes/{index,[code]}.vue` — explorador de regímenes con filtro por scope (IRPF/IVA/IS/SS) y comparador.
- `pages/actividades/{index,[code]}.vue` — buscador CNAE/IAE con qué regímenes admite cada uno y qué tipos IVA aplica.
- `pages/impuestos/{index,[code]}.vue` — catálogo de impuestos y tasas con filtro estatal/autonómico/local + buscador por concepto.
- `pages/calendario-fiscal.vue` — calendario interactivo de obligaciones según régimen elegido.

**API pública (open data CC-BY):** todos los catálogos expuestos en GET con paginación y filtros (mismo patrón Spatie Query Builder que el resto del proyecto). Esto multiplica el valor del proyecto: una agencia/desarrollo externo puede consumir nuestro catálogo de regímenes/CNAE/tasas estructurado en JSON sin tener que parsear BOE.

---

## 5. Roadmap MVP

1. **M0 — Cimientos del módulo + scaffolding catálogo (1 semana):**
   - Migraciones de las 12 tablas, índices.
   - `TaxServiceProvider`, `Routes/api.php`, `TaxParameterRepository` con cache Redis.
   - Contratos DTOs (`BreakdownLine`, `*Input`, `*Result`).
   - Página `/transparencia-fiscal` (índice del módulo).
2. **M1 — Catálogo de regímenes + CNAE/IAE (2 semanas):**
   - Seeders manuales: catálogo de regímenes IRPF/IVA/IS/SS con compatibilidades, obligaciones, modelos asociados.
   - Importer CNAE-2025 desde XLS del INE.
   - Importer IAE desde HTML AEAT.
   - Mapping inicial CNAE↔IAE↔regímenes elegibles para top-100 actividades más comunes.
   - Páginas Nuxt: `/regimenes`, `/actividades`, `/calendario-fiscal`.
   - **Hito:** primer entregable con valor por sí solo (catálogo navegable + open data).
3. **M2 — Catálogo de impuestos y tasas (1 semana):**
   - Seeder estatales: IRPF, IS, IVA, ITP/AJD, ISD, IIEE, IGIC, IPSI.
   - Seeder cedidos a CCAA (4 CCAA top: Madrid, Cataluña, Andalucía, C.Valenciana).
   - Tasas estatales más comunes (judicial, DNI, pasaporte, expedición títulos, CNMV).
   - Página `/impuestos` con filtros por scope/levy_type.
4. **M3 — Parámetros numéricos 2025+2026 (3 días):**
   - Seeder `TaxParameters2025/2026Seeder`: escalas IRPF estatal + 4 CCAA, mínimos personales/familiares, bases SS, tipos cotización, tramos autónomos, tipos IVA por sector.
   - `BoeParameterDetector` (sinergia con módulo Legislation).
5. **M4 — PayrollCalculator (2 semanas):** caso "asalariado régimen general, escala estatal + 4 CCAA". Bruto → neto con SS empleado/empresa + IRPF + reducciones por situación familiar. Tests dorados contra calculadora oficial AEAT y Cinco Días.
6. **M5 — InvoiceCalculator (1 semana):** dispatch por régimen IVA (general / criterio caja / recargo equivalencia / REBU) + retención IRPF según actividad (7 % nuevos / 15 % general / 1 % o 2 % módulos / 19 % capital). Genera PDF opcional informativo.
7. **M6 — IncomeTaxCalculator (3 semanas):** modelo 100 con dispatch por régimen IRPF (EDN / EDS / EO). Sin rendimientos del capital ni ganancias patrimoniales en MVP — banner "próximamente".
8. **M7 — VatReturnCalculator (2 semanas):** modelo 303 régimen general + criterio de caja + simplificado. Modelo 390 = agregación anual. Referenciar `FacturaScripts/modelo303` para casillas exactas.
9. **M8 — Modelo 130 + Modelo 131 (1 semana):** pagos fraccionados IRPF para autónomos (130 EDN/EDS, 131 EO).
10. **M9 — Cobertura completa CCAA + tributos autonómicos (futuro):** 17 escalas autonómicas + ITP/AJD/ISD/IP por CCAA con sus particularidades.
11. **M10 — Tributos locales (futuro):** IBI, IVTM, plusvalía municipal, ICIO. Requiere ingesta de ordenanzas — fuera de MVP.
12. **M11 — Régimenes forales y especiales (futuro):** País Vasco/Navarra fuera de MVP. Bandera "no soportado" honesta. SOCIMI/SICAV/Beckham también futuros.

**Por qué este orden:**
- M1+M2 son **catálogo puro**: dan valor inmediato sin necesitar calculadoras (es ya transparencia per se).
- M3 es el cimiento numérico — sin esto los calculadores no pueden existir.
- Nómina (M4) y Factura (M5) comparten lógica IRPF/SS y validan el modelo de breakdown.
- IRPF anual (M6) reusa la escala. IVA (M7) es relativamente independiente.
- Los modelos de autoliquidación se construyen agregando cálculos previos.

**Estimación total MVP utilizable (M0-M5):** ~8 semanas de trabajo focalizado.
**MVP completo (M0-M8):** ~13-15 semanas.

---

## 6. Riesgos y casos límite (priorizados)

1. **Parámetros desactualizados a 1 de enero.** El BOE publica RDs en Dic/Ene. Mitigación: cron Diciembre que monitoriza módulo Legislation + alerta + checklist manual.
2. **Escala autonómica IRPF (17 CCAA).** Cada una con tramos propios. Mitigación: seeders por CCAA, MVP solo Madrid+Cataluña+Andalucía+C.Valenciana (cubren ~70% población), resto "próximamente".
3. **Régimen foral (País Vasco, Navarra).** Sistema fiscal **distinto**, no es solo "otra escala". Mitigación: explícitamente fuera de MVP, banner informativo.
4. **Cuota autónomos por tramos de ingresos reales (RD-ley 13/2022, prórroga 16/2025).** 15 tramos + regularización anual SS↔AEAT + MEI 0,9% en 2026. Mitigación: tabla `autonomo_brackets (year, from_yield, to_yield, monthly_quota)` + nota sobre regularización posterior.
5. **Convenios colectivos en nómina.** Antigüedad, complementos, horas extra. Mitigación: calculadora de nómina "estándar" sin convenio; disclaimer claro "no sustituye a tu nómina real".
6. **IVA: prorrata, sectores diferenciados, ISP, REBU.** Mitigación: MVP cubre régimen general + criterio caja + simplificado + recargo equivalencia (los 4 mayoritarios). Prorrata y sectores diferenciados → fuera. ISP detectado por flag pero no calculado en MVP.
7. **Validación numérica.** Errores de céntimo erosionan credibilidad. Mitigación: golden tests contra calculadora oficial AEAT en CI; usar `bcmath` o casts decimales, nunca floats.
8. **Cambios retroactivos (ej. STC anula RDL como pasó con plusvalía municipal en 2021).** Mitigación: `valid_from/valid_to` permite versionar y mostrar "según legislación de fecha X".
9. **Responsabilidad legal.** Mitigación: disclaimer prominente "calculadora informativa, no asesoramiento fiscal" en cada calculator. Términos legales revisados.
10. **PII en logs.** Si se loguean inputs (sueldo, hijos, CCAA) puede ser PII. Mitigación: logs anonimizados o desactivados; el cálculo es stateless.
11. **Catálogo CNAE: CNAE-2009 vs CNAE-2025.** Coexisten durante años (estadística usa 2025, IAE sigue ligado a 2009 y a su tabla de equivalencias). Mitigación: tabla `economic_activities` con campo `system` para distinguir + `valid_from/valid_to`. Mantener ambas + tabla de equivalencias.
12. **Estimación objetiva (módulos): Anexo II Orden HFP anual con cientos de actividades, signos e índices.** Cada actividad con sus propios módulos (m², kW, personal asalariado/no asalariado, vehículos…). Mitigación: estructurar `eo_modules (year, activity_iae_code, sign, index_value, unit)` + en MVP cubrir top-20 actividades EO (taxi, bar, peluquería, etc.) y dejar resto para iteraciones.
13. **Tasas locales y ordenanzas municipales (8131 ayuntamientos).** Mitigación: completamente fuera de MVP. Si se aborda en futuro, importar las 50 ciudades grandes manualmente y dejar el resto como "consultar ordenanza local".
14. **Modelos cambian de número y casillas con el tiempo** (modelo 720 sustituido por 721 para criptos; modelo 232 nuevo para vinculadas; etc.). Mitigación: `tax_regime_obligations` versionado con `valid_from/valid_to`.
15. **Compatibilidad de regímenes — combinatoria.** Ej. EO IRPF obliga a IVA simplificado o recargo equivalencia, REAGP sólo agrarios, criterio de caja excluye prorrata especial, etc. Mitigación: tabla `tax_regime_compatibility` + servicio `RegimeResolver` que detecta inconsistencias antes de calcular.
16. **Rendimientos irregulares, mínimos exentos, indemnizaciones, pluriempleo.** Mitigación: MVP cubre nómina simple monoempleo. Pluriempleo y rendimientos irregulares fuera.
17. **Cláusula de actualización: cada Ley de PGE (Diciembre) puede cambiar todo.** Mitigación: `valid_from` permite tener parámetros futuros sembrados pendientes de Ley.

---

## 7. Decisiones abiertas

1. **¿Empezamos por catálogo (M1+M2) o por calculadoras (M4)?** Recomendado catálogo primero — es entregable independiente y da valor sin necesitar parámetros numéricos. También es lo que más diferencia el proyecto frente a competencia comercial (calculadoras hay decenas; catálogo estructurado open data, ninguno).
2. **¿MVP cubre solo régimen estatal+Madrid, o las 4 CCAA top desde el día 1?** Recomendado: 4 CCAA top en M4 (Madrid+Cataluña+Andalucía+C.Valenciana = ~70 % población), resto incremental.
3. **¿Cobertura de regímenes en MVP de calculadoras?** Recomendado:
   - **Sí MVP**: General asalariado, EDN, EDS, EO (top-20 actividades), IVA general, criterio caja, simplificado, recargo equivalencia.
   - **Fuera MVP**: SOCIMI, SICAV, Beckham, REBU, REAGP, OSS, agencias de viaje, ISP completo, prorrata, sectores diferenciados, foral PV/Navarra.
4. **¿Generar PDFs de modelos rellenados (no presentables, solo informativos)?** Coste ~3-5 días extra por modelo; aporta sensación de "rigor" pero puede inducir a confusión legal. Recomiendo NO en MVP, evaluar tras feedback.
5. **¿Persistimos cálculos del usuario logueado (historial "mis cálculos")?** Implica auth + PII. Recomiendo NO en MVP; sí compartir mediante URL con inputs firmados (HMAC) en query string.
6. **Sinergia Legislation: ¿el detector de RD nuevos abre issue en GitHub o crea entrada en BD para revisión interna?** Recomiendo BD interna + dashboard admin; mantenerlo dentro del propio sistema.
7. **¿Régimen foral PV/Navarra entra en roadmap o se marca explícitamente "fuera de alcance" en el FAQ?** Recomiendo lo segundo, transparente.
8. **¿Multi-año desde el inicio (2023-2026) o solo año vigente?** Multi-año es valor diferencial real ("¿cuánto habría pagado en 2019?"); coste ~2 días extra por año en seeder. Recomendado multi-año desde 2023.
9. **¿Open data propio del catálogo?** Exponer `tax_parameters`, `tax_regimes`, `economic_activities`, `tax_types` como dataset CC-BY público en `/api/v1/tax/...`. Encaja exactamente con el ADN del proyecto y nos posiciona como **fuente de referencia estructurada** del sistema fiscal español. **Recomendado: SÍ, prioritario.**
10. **¿Tooltip legal links al BOE consolidado o al RD original?** Consolidado es más útil para el ciudadano; original es más auditable. Sugerencia: ambos, separados (`source_url_original`, `source_url_consolidated`).
11. **¿CNAE-2009 + CNAE-2025 ambos, o solo el más reciente?** Recomendado ambos durante 2025-2027 (transición + IAE sigue ligado a CNAE-2009).
12. **¿Importer automático de tarifa IAE o seeder manual?** El HTML de AEAT es estable pero feo. Recomiendo importer + seeder fixture committeado, regenerable cada vez que AEAT publique cambios.
13. **¿Calendario fiscal incluye cuotas mensuales SS de autónomos o sólo modelos AEAT?** Recomendado AMBOS — el ciudadano ve calendario integral.
14. **¿Estructura de "obligaciones por régimen" expone también `presentación electrónica obligatoria`, `requiere certificado digital`, `borrador disponible`?** Recomendado SÍ, son atributos del catálogo de obligaciones.

---

## 8. Bloque de catálogo enciclopédico — qué publicaremos como contenido editorial (más allá de las tablas)

Para que el catálogo no sea solo "filas en BD" sino contenido navegable de transparencia, cada entidad lleva markdown editorial:

- **Régimen** → ficha con: descripción plain language, requisitos, obligaciones, modelos, ventajas/inconvenientes, "¿quién debería elegirlo?", referencias BOE.
- **Actividad CNAE/IAE** → ficha con: descripción, regímenes elegibles, tipo IVA aplicable, retención IRPF aplicable, ejemplos de actividades incluidas/excluidas.
- **Impuesto/Tasa** → ficha con: descripción, hecho imponible, sujetos pasivos, base, tipo, exenciones principales, modelos asociados, ámbito (estatal/autonómico/local).
- **Modelo (303, 130, 100…)** → ficha con: para qué sirve, quién lo presenta, plazo, casillas principales explicadas, link a sede AEAT para presentar.

Este contenido va en columna `editorial_md` longtext en cada tabla principal. Generación inicial: yo redacto markdown plano para los ~50 conceptos top (regímenes + impuestos estatales + 10 modelos clave). Mantenimiento manual.

---

## 9. Próximo paso recomendado

1. Usuario revisa este documento y responde a las 14 decisiones abiertas (un mensaje "OK al recomendado salvo X, Y, Z" basta).
2. Yo escribo el spec definitivo en `docs/superpowers/specs/2026-04-25-tax-module-design.md` con las decisiones consolidadas.
3. De ese spec se derivan ~5-6 specs hijos (uno por fase M1, M2, M3+M4, M5, M6, M7) para no concentrar todo en un solo plan inabarcable.
4. Empezar implementación por **M0 (cimientos)** + **M1 (regímenes + CNAE/IAE)** que son entregables independientes y de bajo riesgo.
