# Escáner Público — Backend

API de la plataforma de transparencia gubernamental española. Recopila, procesa y expone datos públicos de instituciones del Estado.

## Stack

- Laravel 12 + MySQL + Redis + Typesense
- Laravel Horizon (colas)
- Laravel Scout + Typesense (búsqueda)
- Spatie Activity Log (auditoría)

## Requisitos

- PHP 8.3+
- Composer
- MySQL 16+
- Redis

## Instalación

```bash
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate
php artisan serve
# → http://localhost:8000
```

## API

Base URL: `http://localhost:8000/api/v1/`

- `GET /api/v1/health` — Estado del servicio
