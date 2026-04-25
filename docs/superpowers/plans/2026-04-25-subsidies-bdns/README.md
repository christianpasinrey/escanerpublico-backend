# Subsidies (BDNS) — Implementation Plan (Index)

**Spec:** `docs/superpowers/specs/2026-04-25-subsidies-bdns-design.md`

**Goal:** Módulo `Subsidies` paralelo a `Contracts` que ingiere la API REST pública de BDNS (convocatorias + concesiones), reutilizando `companies` y `organizations` para crear ficha unificada de empresa/organismo (contratos + subvenciones).

**Architecture:** Service-oriented Laravel. `BdnsClient` (HTTP + rate limit), `SubsidyIngestor` (idempotente), `EntityResolver` reutilizado. 5 tablas nuevas. API con `spatie/laravel-query-builder`. Cache CF + Redis. Documentación auto-generada en /docs.

**Tech Stack:** Laravel 12, PHP 8.4, MySQL 8, Redis, Pest, PHPStan L8, Pint.

---

## Phase plans

| # | Plan | Worktree | Depends on |
|---|---|---|---|
| **5.0** | [Foundation](phase-5.0-foundation.md) — migrations + models + factories + module bootstrap | `wt-5.0-foundation` | `main` |
| **5.1** | [Ingestor](phase-5.1-ingestor.md) — BdnsClient + SubsidyIngestor + comando sync | `wt-5.1-ingestor` | 5.0 |
| **5.2** | [API](phase-5.2-api.md) — controllers + resources + filters + sorts | `wt-5.2-api` | 5.0 |
| **5.3** | [Frontend](phase-5.3-frontend.md) — páginas + integración + nav | `wt-5.3-frontend` | 5.2 |
| **5.4** | [Integration](phase-5.4-integration.md) — ingesta MVP último año + verificación | `wt-5.4-integration` | 5.1+5.2+5.3 |

## Merge graph

```
main
 └─ 5.0 (foundation)
     ├─ 5.1 (ingestor)
     │   └─ 5.4 (integration)
     ├─ 5.2 (api) [paralelo a 5.1]
     │   └─ 5.3 (frontend)
     │       └─ 5.4
     └─
```

## Global commit discipline

- TDD: rojo → implementa → verde → commit.
- Mensajes: `feat(subsidies): ...` | `fix(subsidies): ...` | `test(subsidies): ...` | `chore(subsidies): ...` | `docs(subsidies): ...`.
- PR por fase con su gate verde (PHPStan L8 sobre el módulo, Pint sin diffs, tests en verde).

## Global gates

- PHPStan L8 verde sobre `app/Modules/Subsidies/`.
- Coverage ≥ 80% al cerrar cada fase del módulo.
- Sin `TODO`/`FIXME` finales.
- Idempotencia probada: re-ingerir el mismo payload N veces = 0 diff.

## Quickstart phase 5.0

```bash
# Estamos ya en main, sin worktree para esta fase introductoria
php artisan make:migration create_subsidy_calls_table --path=database/migrations/contracts
# ... crear las 5 migrations
php artisan migrate
php artisan test --filter='Subsidies'
```

## Notas

- BDNS API es REST/JSON sin auth → cliente HTTP propio reemplaza al `PlacspStreamParser` de Contracts.
- Idempotencia por `(source='BDNS', external_id)` + `content_hash`.
- 24M concesiones — MVP cubre solo último año (≈2-3M registros, 12-15 GB JSON, manejable).
- BDNS expone `idConvocatoria` que hay que cazar lazy (la convocatoria puede llegar después de la concesión en el orden de ingesta). Por eso `subsidy_grants.external_call_id` y FK lazy resolution.
