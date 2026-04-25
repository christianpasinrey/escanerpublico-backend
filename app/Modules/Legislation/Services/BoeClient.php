<?php

namespace Modules\Legislation\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Cliente HTTP para la API de datos abiertos del BOE (boe.es/datosabiertos).
 *
 * - Sin autenticación
 * - Sumarios diarios y legislación consolidada en JSON
 * - Reintentos con backoff en 5xx, 429 y errores de red
 * - Timeout amplio para queries pesadas de la API
 */
class BoeClient
{
    public const BASE_URL = 'https://www.boe.es/datosabiertos/api/';

    public const USER_AGENT = 'GobTracker/1.0 (escaner-publico-backend; +https://gobtracker.tailor-bytes.com)';

    public const TIMEOUT_SECONDS = 120;

    public const CONNECT_TIMEOUT_SECONDS = 15;

    public const RETRY_TIMES = 5;

    public const RETRY_SLEEP_MS = 2000;

    public const SLEEP_BETWEEN_MS = 250;

    /**
     * Sumario diario completo del BOE para una fecha (YYYYMMDD).
     *
     * @return array<string, mixed>|null  null si la fecha no tiene sumario (404)
     */
    public function getDailySummary(string $yyyymmdd): ?array
    {
        try {
            $body = $this->fetchJson("boe/sumario/{$yyyymmdd}");
        } catch (RequestException $e) {
            if ($e->response->status() === 404) {
                return null;
            }
            throw $e;
        }
        $this->cooldown();

        return $body['data']['sumario'] ?? null;
    }

    /**
     * Lista paginada de legislación consolidada.
     *
     * @return array<string, mixed>  body completo con `data` y posible `links`/paginación
     */
    public function searchConsolidatedLegislation(int $offset = 0, int $limit = 50): array
    {
        $body = $this->fetchJson('legislacion-consolidada', [
            'offset' => $offset,
            'limit' => $limit,
        ]);
        $this->cooldown();

        return $body;
    }

    /**
     * @param  array<string, scalar>  $query
     * @return array<string, mixed>
     */
    private function fetchJson(string $path, array $query = []): array
    {
        $response = $this->request()
            ->retry(self::RETRY_TIMES, self::RETRY_SLEEP_MS, function (\Throwable $exception) {
                if ($exception instanceof RequestException) {
                    $status = $exception->response->status();

                    return in_array($status, [429, 500, 502, 503, 504], true);
                }

                return true;
            }, throw: true)
            ->get(self::BASE_URL.$path, $query);

        return $this->parseResponse($response);
    }

    /**
     * @return array<string, mixed>
     */
    private function parseResponse(Response $response): array
    {
        if (! $response->successful()) {
            $response->throw();
        }
        $body = $response->json();
        if (! is_array($body)) {
            throw new \RuntimeException('BOE response is not JSON');
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

    protected function cooldown(): void
    {
        if (self::SLEEP_BETWEEN_MS > 0 && ! app()->runningUnitTests()) {
            usleep(self::SLEEP_BETWEEN_MS * 1000);
        }
    }
}
