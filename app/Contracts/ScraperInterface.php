<?php

namespace App\Contracts;

interface ScraperInterface
{
    public function source(): string;

    public function scrape(array $params = []): array;

    public function parse(string $content): array;

    public function isAvailable(): bool;
}
