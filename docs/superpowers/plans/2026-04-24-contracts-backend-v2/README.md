# Contracts Backend v2 — Implementation Plan (Index)

> **For agentic workers:** REQUIRED SUB-SKILL: Use `superpowers:subagent-driven-development` (recommended) or `superpowers:executing-plans` to implement each phase plan task-by-task.

**Spec:** `docs/superpowers/specs/2026-04-24-contracts-backend-v2-design.md`

**Goal:** Refactor completo del backend del módulo Contracts — parser decompuesto en 10 extractors, ingestor idempotente con snapshots, multi-lote, API query builder, cache CF+Redis, landing pública + docs auto-generadas, Premium tests.

**Architecture:** Service-oriented Laravel con XMLReader streaming dentro del parser. 12 migraciones, 13 modelos. API con `spatie/laravel-query-builder`. Cache en tres capas. Landing Blade + OpenAPI vía `dedoc/scramble` + UI `scalar/laravel`.

**Tech Stack:** Laravel 12, PHP 8.4, MySQL 8, Redis, Horizon, Cloudflare API, Pest, Infection, PHPStan L8, Pint.

---

## Phase plans (execute in this order, each in its own worktree)

| # | Plan | Worktree | Depends on | Subagents |
|---|---|---|---|---|
| **1.0** | [Foundation](phase-1.0-foundation.md) | `wt-1.0-foundation` | `main` | Backend Architect |
| **1.1** | [Parser](phase-1.1-parser.md) | `wt-1.1-parser` | 1.0 merged | Backend Architect + 5 paralelos + Evidence Collector |
| **1.2** | [Ingestor + Reprocess](phase-1.2-ingestor.md) | `wt-1.2-ingestor` | 1.1 merged | Backend Architect + DevOps Automator |
| **1.3** | [API + QueryBuilder](phase-1.3-api.md) | `wt-1.3-api` | 1.0 merged (puede paralelo a 1.1/1.2) | Backend Architect + API Tester |
| **1.4** | [Landing + Docs](phase-1.4-landing.md) | `wt-1.4-landing` | 1.0 merged | Frontend Developer + Technical Writer |
| **1.5** | [Integration + Reprocess total](phase-1.5-integration.md) | `wt-1.5-integration` | 1.1+1.2+1.3+1.4 merged | Performance Benchmarker + Reality Checker |

## Merge graph

```
main
 └─ 1.0 (foundation)
     ├─ 1.1 (parser)
     │   └─ 1.2 (ingestor)
     ├─ 1.3 (api)          [paralelo]
     └─ 1.4 (landing)      [paralelo]

main (todo merged)
 └─ 1.5 (integration)
```

## Global commit discipline

- TDD: test rojo → implementa → verde → commit.
- Un commit por paso verde (no commits mixtos).
- Commit message: `feat(contracts): ...` | `fix(contracts): ...` | `test(contracts): ...` | `refactor(contracts): ...` | `chore(contracts): ...` | `docs(contracts): ...`.
- Push al final de cada fase (no al final de cada task).
- PR al terminar la fase con su gate verde.

## Global gates (cross-phase)

- PHPStan L8 verde sobre `app/Modules/Contracts/`.
- Laravel Pint sin diffs.
- Coverage ≥ 85% en `app/Modules/Contracts/` al cerrar cada fase.
- Benchmarks Pest bajo threshold.
- Sin `TODO`/`FIXME` en código.

## Before starting

1. Verifica que estás en la rama base correcta (`main` para Phase 1.0; la rama de la fase anterior mergeada para las siguientes).
2. Crea el worktree usando `superpowers:using-git-worktrees` skill: `git worktree add ../escanerpublico-backend-wt-1.0-foundation feature/contracts-v2-foundation`.
3. Verifica MySQL/Redis corriendo: `php artisan tinker --execute='DB::connection()->getPdo(); Cache::store("redis")->get("ping");'`.
4. Copia `.env` al worktree (el worktree nuevo no tiene `.env`): `cp .env ../escanerpublico-backend-wt-1.0-foundation/.env`.
5. `cd` al worktree y arranca.
