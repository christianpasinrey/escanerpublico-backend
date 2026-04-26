<?php

namespace Modules\Subsidies\Search;

use Modules\Search\Contracts\SearchProvider;
use Modules\Search\DataObjects\SearchBucket;
use Modules\Search\DataObjects\SearchHit;
use Modules\Subsidies\Models\SubsidyGrant;

class SubsidyGrantSearchProvider implements SearchProvider
{
    public function key(): string
    {
        return 'subsidy-grant';
    }

    public function label(): string
    {
        return 'Subvenciones';
    }

    public function search(string $query, int $limit): SearchBucket
    {
        $like = '%'.$query.'%';

        $rows = SubsidyGrant::query()
            ->select(['id', 'cod_concesion', 'beneficiario_name', 'beneficiario_nif', 'amount', 'grant_date'])
            ->where(function ($q) use ($like): void {
                $q->where('beneficiario_name', 'LIKE', $like)
                    ->orWhere('beneficiario_nif', 'LIKE', $like)
                    ->orWhere('cod_concesion', 'LIKE', $like);
            })
            ->orderByDesc('grant_date')
            ->limit($limit)
            ->get();

        $hits = $rows->map(fn (SubsidyGrant $g) => new SearchHit(
            type: 'subsidy-grant',
            id: $g->id,
            title: $g->beneficiario_name ?: ($g->cod_concesion ?: "Concesión #{$g->id}"),
            subtitle: $this->buildSubtitle($g),
            url: "/subvenciones/{$g->id}",
            api_url: "/api/v1/subsidies/grants/{$g->id}",
            meta: [
                'beneficiario_nif' => $g->beneficiario_nif,
                'amount' => $g->amount !== null ? (float) $g->amount : null,
            ],
        ))->all();

        return new SearchBucket(
            key: $this->key(),
            label: $this->label(),
            hits: array_values($hits),
            total: count($hits),
        );
    }

    private function buildSubtitle(SubsidyGrant $g): ?string
    {
        $parts = [];

        if ($g->beneficiario_nif) {
            $parts[] = $g->beneficiario_nif;
        }

        if ($g->grant_date) {
            $parts[] = $g->grant_date->format('d/m/Y');
        }

        if ($g->amount !== null) {
            $parts[] = number_format((float) $g->amount, 0, ',', '.').' €';
        }

        return $parts ? implode(' · ', $parts) : null;
    }
}
