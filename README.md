# Escáner Público — Backend

> **API pública y abierta de contratación del sector público español.**
> Datos oficiales de PLACSP, parseados, normalizados y consultables vía REST.

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![PHP 8.4+](https://img.shields.io/badge/PHP-8.4+-777BB4.svg)](https://www.php.net/)
[![Laravel 12](https://img.shields.io/badge/Laravel-12-FF2D20.svg)](https://laravel.com/)

---

## Qué es

**Escáner Público** ingesta el feed [PLACSP (Plataforma de Contratación del Sector Público)](https://contrataciondelestado.es) — los atom XML mensuales que publica el Estado con todos los contratos públicos españoles desde 2018 — los procesa, los normaliza en una BD relacional rica, y los expone vía una API REST pública con query builder.

Pensado para periodistas, investigadores, tecnólogos cívicos, asociaciones de transparencia, y cualquiera que quiera **construir sobre datos públicos sin pelear con XMLs de 9 MB**.

🔗 **Demo en vivo:** [app.gobtracker.tailor-bytes.com](https://app.gobtracker.tailor-bytes.com)
📚 **Docs interactivas:** [`/docs`](https://app.gobtracker.tailor-bytes.com/docs)
📄 **OpenAPI 3.1:** [`/openapi.json`](https://app.gobtracker.tailor-bytes.com/openapi.json)

## Lo que hace bien

- 📥 **Ingesta resiliente** — XMLReader streaming + idempotencia por `content_hash` y `entry.updated_at`. Re-procesar el mismo atom 100 veces produce 0 filas duplicadas.
- 📚 **Histórico arqueológico** — captura cada snapshot publicado por PLACSP en la tabla `contract_snapshots`, sentando la base para detección de cambios silenciosos (modificaciones de importe sin trazabilidad pública).
- 🧬 **Modelo rico** — multi-lote real (un contrato → N lotes → M adjudicatarios), criterios OBJ/SUBJ con fórmulas, modificaciones, prórrogas, anulaciones (tombstones).
- 🔎 **API query builder** — `?include=`, `?fields[]=`, `?filter[]=`, `?sort=` estilo JSON:API vía `spatie/laravel-query-builder`. Whitelist explícita por endpoint.
- ⚡ **Cache Cloudflare + Redis** — `Cache-Control: public, s-maxage=3600, stale-while-revalidate=86400`, invalidación por tag al re-ingest. Sub-300 ms en endpoints calientes.
- 📊 **Operación low-disk** — comando `contracts:sync --cleanup` procesa mes a mes y borra los `.atom` tras ingestar (footprint <500 MB en disco aunque el histórico pese 30+ GB).

## Stack

- **Laravel 12** + PHP 8.4 + MySQL 8 (FULLTEXT) + Redis
- **Spatie Query Builder** para la API
- **Dedoc Scramble** + **Scalar** para docs OpenAPI 3.1 auto-generadas
- **Pest 4** para tests + **Infection** para mutation testing
- **PHPStan L8** + **Laravel Pint**

## Requisitos

- PHP 8.4+
- Composer 2.x
- MySQL 8.x
- Redis 6+

## Instalación local

```bash
git clone https://github.com/christianpasinrey/escanerpublico-backend.git
cd escanerpublico-backend
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate

# Sincroniza un mes para tener datos
php artisan contracts:sync --month=$(date +%Y%m) --sync --cleanup

php artisan serve
# → http://localhost:8000/api/v1/contracts
```

## Comandos clave

```bash
# Sincronizar PLACSP (descarga + procesa + limpia, mes a mes)
php artisan contracts:sync --all --sync --cleanup

# Reprocesar atoms ya extraídos (sin descarga)
php artisan contracts:reprocess --from=201801 --to=$(date +%Y%m) --sync

# Reanudar un reproceso fallido
php artisan contracts:reprocess --resume

# Ejecutar tests + lint
php artisan test
./vendor/bin/phpstan analyse app/Modules/Contracts --level=8
./vendor/bin/pint
```

## API en 30 segundos

```bash
# Listar últimos contratos adjudicados con su organización
curl "https://app.gobtracker.tailor-bytes.com/api/v1/contracts?filter[status_code]=ADJ&include=organization&sort=-snapshot_updated_at"

# Ficha completa de un contrato
curl "https://app.gobtracker.tailor-bytes.com/api/v1/contracts/19066873?include=lots.awards.company,notices,modifications,documents"

# Estadísticas de un órgano
curl "https://app.gobtracker.tailor-bytes.com/api/v1/organizations/1/stats"

# Búsqueda full-text
curl "https://app.gobtracker.tailor-bytes.com/api/v1/contracts?filter[search]=puente+autopista"
```

## Arquitectura

```
app/Modules/Contracts/
├── Console/                # SyncContracts, ReprocessContracts
├── Http/
│   ├── Controllers/        # Contract, Organization, Company, Lot, Landing, Health
│   ├── Resources/          # API resources con whenLoaded
│   ├── Filters/            # SearchFilter (FULLTEXT), AmountBetweenFilter
│   └── Sorts/              # RelevanceSort (FULLTEXT)
├── Services/
│   ├── Parser/             # XMLReader streaming + 10 extractors + DTOs
│   ├── ContractIngestor    # Idempotencia + snapshots + cache invalidation
│   ├── EntityResolver      # 3-level key strategy (DIR3 → NIF → name_hash)
│   └── Cache/              # Redis tagged invalidator + Cloudflare purger
├── Jobs/                   # ProcessPlacspFile, PurgeContractUrls
└── Models/                 # 13 modelos Eloquent con relations + factories
```

📖 Spec completa: `docs/superpowers/specs/2026-04-24-contracts-backend-v2-design.md`
📋 Plan de implementación: `docs/superpowers/plans/2026-04-24-contracts-backend-v2/`

## Cómo contribuir

🤝 **Toda contribución es bienvenida** — desde reportar un bug hasta arquitecturar el siguiente módulo.

### Ideas para empezar

- 🐛 **Reportar bugs** — abre un [issue](https://github.com/christianpasinrey/escanerpublico-backend/issues) con caso reproducible.
- ✨ **Nuevos parsers** — ¿otra fuente abierta de datos públicos? Plantillea un módulo similar al de Contracts para BOE, presupuestos, subvenciones, cargos, etc.
- 📊 **Detección de anomalías** — queries sobre `contract_snapshots` para detectar cambios silenciosos (importes mutados sin notice asociado, empresas rotando entre lotes, organismos cancelando y reconvocando idéntico).
- 🌍 **Exportadores** — dumps periódicos en CSV / Parquet / JSON-LD para consumo offline.
- 🔬 **Investigación cívica** — usa la API en una pieza periodística o académica y compártela en Discussions.

### Workflow

1. Fork + branch (`feat/...`, `fix/...`)
2. TDD: test rojo → implementación → verde
3. PHPStan L8 verde sobre tu módulo, Pint sin diffs
4. PR con descripción del *por qué* (más que del *qué* — el diff ya muestra eso)

### Filosofía

- **Los datos del Estado son nuestros.** La transparencia no debería requerir saber leer XML.
- **El parser nunca pierde datos.** Si un campo no se mapea, se queda en `parse_errors` con su raw fragment.
- **Idempotencia primero.** Cualquier comando se puede volver a lanzar sin miedo.
- Si una decisión técnica reduce la fricción para que un periodista descubra algo que no estaba antes, es la correcta.

## Roadmap

- [x] Spec 1 — Backend v2 (parser modular + ingestor idempotente + API + landing + docs)
- [x] Spec 2 — Frontend ficha de contrato disruptiva
- [ ] Spec 3 — Ficha de empresa pública con grafo de relaciones
- [ ] Spec 4 — Snapshot history exploitation: queries de diff + panel de auditoría
- [ ] Módulo Cargos públicos (BOE + boletines autonómicos)
- [ ] Módulo Presupuestos
- [ ] Módulo Subvenciones (BDNS)
- [ ] API keys + rate limit por cuenta
- [ ] Webhooks para terceros que sigan contratos concretos

## Datos y atribución

- **Fuente original**: [Plataforma de Contratación del Sector Público](https://contrataciondelestado.es) — datos publicados bajo Aviso Legal de la JCCPE.
- **Esta plataforma**: capa de presentación. No somos PLACSP. Solo reestructuramos datos abiertos.
- **Si encuentras un dato incorrecto**: probablemente esté así en el atom XML original. Reporta el `external_id` y miramos juntos.

## Licencia

MIT. Haz lo que quieras con el código.

## Contacto

- GitHub Issues para bugs y features
- GitHub Discussions para conversaciones abiertas
- Pull Requests siempre bienvenidos

---

> *"La transparencia no es opcional. Si tu dinero la financia, tu pantalla debería poder consultarla."*
