<?php

namespace Modules\Subsidies\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cliente HTTP para la API REST pública de BDNS (infosubvenciones.es).
 *
 * Características:
 * - Sin autenticación
 * - Rate limit: ≤ 1 req/s sostenido (sleep entre requests para no agredir al server)
 * - Reintentos: 3 con backoff exponencial (1s, 4s, 16s) en 5xx, 429 y errores de red
 * - Timeout: 30s
 * - User-Agent identificable (cortesía con la IGAE)
 */
class BdnsClient
{
    public const BASE_URL = 'https://www.infosubvenciones.es/bdnstrans/api/';

    public const USER_AGENT = 'GobTracker/1.0 (escaner-publico-backend; +https://gobtracker.tailor-bytes.com)';

    // BDNS responde lento bajo carga (sobre todo concesiones con filtros de fecha en
    // queries que afectan a millones de filas). Margen amplio para evitar timeouts.
    public const TIMEOUT_SECONDS = 120;

    public const CONNECT_TIMEOUT_SECONDS = 15;

    public const RETRY_TIMES = 5;

    public const RETRY_SLEEP_MS = 2000;

    public const SLEEP_BETWEEN_MS = 250;

    /**
     * Página de convocatorias (calls for grants).
     *
     * @param  array<string, scalar>  $filters  fechaDesde=dd/MM/yyyy, fechaHasta=dd/MM/yyyy, vpd, etc. (BDNS-specific)
     * @return array<string, mixed>  Spring Page: content[], pageable, totalElements, totalPages, last, first
     */
    public function searchCalls(int $page = 0, int $pageSize = 100, array $filters = []): array
    {
        return $this->fetchPage('convocatorias/busqueda', $page, $pageSize, $filters);
    }

    /**
     * Página de concesiones (granted subsidies).
     *
     * @param  array<string, scalar>  $filters  fechaDesde, fechaHasta, etc.
     * @return array<string, mixed>  Spring Page
     */
    public function searchGrants(int $page = 0, int $pageSize = 100, array $filters = []): array
    {
        return $this->fetchPage('concesiones/busqueda', $page, $pageSize, $filters);
    }

    /**
     * @param  array<string, scalar>  $filters
     * @return array<string, mixed>
     */
    private function fetchPage(string $path, int $page, int $pageSize, array $filters): array
    {
        $query = array_merge(['page' => $page, 'pageSize' => $pageSize], $filters);

        $response = $this->request()
            ->retry(self::RETRY_TIMES, self::RETRY_SLEEP_MS, function (\Throwable $exception, PendingRequest $request) {
                if ($exception instanceof RequestException) {
                    $status = $exception->response->status();
                    // Reintenta sólo en 5xx y 429. 4xx restantes son errores de cliente.
                    return in_array($status, [429, 500, 502, 503, 504], true);
                }

                return true; // network errors → retry
            }, throw: true)
            ->get(self::BASE_URL.$path, $query);

        $this->cooldown();

        return $this->parseResponse($response);
    }

    /**
     * @return array<string, mixed>
     */
    private function parseResponse(Response $response): array
    {
        if (! $response->successful()) {
            Log::warning('BDNS request failed', [
                'status' => $response->status(),
                'url' => $response->effectiveUri()?->__toString(),
            ]);
            $response->throw();
        }

        $body = $response->json();
        if (! is_array($body) || ! array_key_exists('content', $body)) {
            throw new \RuntimeException('BDNS response missing "content" key');
        }

        /** @var array<string, mixed> $body */
        return $body;
    }

    private function request(): PendingRequest
    {
        return Http::withHeaders([
            'User-Agent' => self::USER_AGENT,
            'Accept' => 'application/json',
        ])
            ->timeout(self::TIMEOUT_SECONDS)
            ->connectTimeout(self::CONNECT_TIMEOUT_SECONDS);
    }

    /**
     * Pausa breve entre requests para no agotar el rate limit del servidor BDNS.
     * Configurable vía SLEEP_BETWEEN_MS. En tests se puede mockear esta clase.
     */
    protected function cooldown(): void
    {
        if (self::SLEEP_BETWEEN_MS > 0 && ! app()->runningUnitTests()) {
            usleep(self::SLEEP_BETWEEN_MS * 1000);
        }
    }
}
