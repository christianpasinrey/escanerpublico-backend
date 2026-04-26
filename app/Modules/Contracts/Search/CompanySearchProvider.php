<?php

namespace Modules\Contracts\Search;

use Modules\Contracts\Models\Company;
use Modules\Search\Contracts\SearchProvider;
use Modules\Search\DataObjects\SearchBucket;
use Modules\Search\DataObjects\SearchHit;

class CompanySearchProvider implements SearchProvider
{
    public function key(): string
    {
        return 'company';
    }

    public function label(): string
    {
        return 'Empresas';
    }

    public function search(string $query, int $limit): SearchBucket
    {
        $like = '%'.$query.'%';

        $rows = Company::query()
            ->select(['id', 'name', 'nif', 'identifier'])
            ->where(function ($q) use ($like): void {
                $q->where('name', 'LIKE', $like)
                    ->orWhere('nif', 'LIKE', $like)
                    ->orWhere('identifier', 'LIKE', $like);
            })
            ->orderByRaw('CHAR_LENGTH(name) ASC')
            ->limit($limit)
            ->get();

        $hits = $rows->map(fn (Company $c) => new SearchHit(
            type: 'company',
            id: $c->id,
            title: $c->name ?? "Empresa #{$c->id}",
            subtitle: $c->nif ?: $c->identifier,
            url: "/empresas/{$c->id}",
            api_url: "/api/v1/companies/{$c->id}",
            meta: [
                'nif' => $c->nif,
            ],
        ))->all();

        return new SearchBucket(
            key: $this->key(),
            label: $this->label(),
            hits: array_values($hits),
            total: count($hits),
        );
    }
}
