<?php

namespace Modules\Contracts\Search;

use Illuminate\Database\Eloquent\Builder;
use Modules\Contracts\Models\Contract;
use Modules\Search\Contracts\SearchProvider;
use Modules\Search\DataObjects\SearchBucket;
use Modules\Search\DataObjects\SearchHit;

class ContractSearchProvider implements SearchProvider
{
    public function key(): string
    {
        return 'contract';
    }

    public function label(): string
    {
        return 'Contratos';
    }

    public function search(string $query, int $limit): SearchBucket
    {
        $rows = $this->buildQuery($query)
            ->select(['id', 'external_id', 'expediente', 'objeto', 'status_code', 'importe_con_iva', 'snapshot_updated_at'])
            ->orderByDesc('snapshot_updated_at')
            ->limit($limit + 1)
            ->get();

        $truncated = $rows->count() > $limit;
        $items = $rows->take($limit);

        $hits = $items->map(fn (Contract $c) => new SearchHit(
            type: 'contract',
            id: $c->external_id ?? $c->id,
            title: $this->shorten($c->objeto, 110) ?: ('Expediente '.$c->expediente),
            subtitle: $this->buildSubtitle($c),
            url: "/contratos/{$c->external_id}",
            api_url: "/api/v1/contracts/{$c->external_id}",
            meta: [
                'status_code' => $c->status_code,
                'importe_con_iva' => $c->importe_con_iva !== null ? (float) $c->importe_con_iva : null,
                'expediente' => $c->expediente,
            ],
        ))->all();

        return new SearchBucket(
            key: $this->key(),
            label: $this->label(),
            hits: array_values($hits),
            total: $truncated ? -1 : count($hits),
        );
    }

    /**
     * @return Builder<Contract>
     */
    private function buildQuery(string $term): Builder
    {
        $query = Contract::query();

        if (mb_strlen($term) < 4) {
            $like = '%'.$term.'%';
            $query->where(function (Builder $q) use ($like): void {
                $q->where('objeto', 'LIKE', $like)
                    ->orWhere('expediente', 'LIKE', $like);
            });

            return $query;
        }

        $booleanTerm = '+'.str_replace(['+', '-', '(', ')', '<', '>', '~', '*', '"'], ' ', $term).'*';
        $query->whereRaw('MATCH(objeto, expediente) AGAINST (? IN BOOLEAN MODE)', [$booleanTerm]);

        return $query;
    }

    private function buildSubtitle(Contract $c): ?string
    {
        $parts = [];

        if ($c->expediente) {
            $parts[] = "Exp. {$c->expediente}";
        }

        if ($c->status_code) {
            $parts[] = $c->status_code;
        }

        if ($c->importe_con_iva !== null) {
            $parts[] = number_format((float) $c->importe_con_iva, 0, ',', '.').' €';
        }

        return $parts ? implode(' · ', $parts) : null;
    }

    private function shorten(?string $text, int $length): ?string
    {
        if ($text === null) {
            return null;
        }

        $text = trim($text);

        if ($text === '') {
            return null;
        }

        return mb_strlen($text) > $length ? mb_substr($text, 0, $length - 1).'…' : $text;
    }
}
