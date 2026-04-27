<?php

namespace Modules\Borme\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

/**
 * HTTP client for the BOE open-data BORME endpoints. Mirrors the shape and retry
 * policy used by Modules\Legislation\Services\BoeClient so the two coexist
 * without surprises.
 */
class BormeApiClient
{
    public const BASE_URL = 'https://www.boe.es/datosabiertos/api/';

    public const USER_AGENT = 'GobTracker/1.0 (escaner-publico-backend; +https://gobtracker.tailor-bytes.com)';

    public const TIMEOUT_SECONDS = 90;

    public const CONNECT_TIMEOUT_SECONDS = 15;

    public const RETRY_TIMES = 5;

    public const RETRY_SLEEP_MS = 2000;

    /**
     * Daily BORME summary as JSON. Returns null when the requested date has no
     * publication (404) — typical for weekends and Madrid holidays.
     *
     * @return array<string, mixed>|null
     */
    public function getDailySummary(string $yyyymmdd): ?array
    {
        try {
            $body = $this->fetchJson("borme/sumario/{$yyyymmdd}");
        } catch (RequestException $e) {
            if ($e->response->status() === 404) {
                return null;
            }
            throw $e;
        }

        return $body['data']['sumario'] ?? null;
    }

    /**
     * Download a BORME PDF by URL, returning binary contents. Caller owns the
     * filesystem (write to temp, parse, unlink) — keeping that policy outside
     * this class avoids hidden state.
     */
    public function downloadPdf(string $url): string
    {
        return $this->request()
            ->retry(self::RETRY_TIMES, self::RETRY_SLEEP_MS)
            ->withHeaders(['Accept' => 'application/pdf'])
            ->get($url)
            ->throw()
            ->body();
    }

    /**
     * @param  array<string, scalar>  $query
     * @return array<string, mixed>
     */
    private function fetchJson(string $path, array $query = []): array
    {
        return $this->request()
            ->retry(self::RETRY_TIMES, self::RETRY_SLEEP_MS)
            ->get(self::BASE_URL.$path, $query)
            ->throw()
            ->json();
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
}
