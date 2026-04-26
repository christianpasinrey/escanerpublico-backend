<?php

namespace Modules\Contracts\Search;

use Modules\Contracts\Models\Organization;
use Modules\Search\Contracts\SearchProvider;
use Modules\Search\DataObjects\SearchBucket;
use Modules\Search\DataObjects\SearchHit;

class OrganizationSearchProvider implements SearchProvider
{
    public function key(): string
    {
        return 'organization';
    }

    public function label(): string
    {
        return 'Organismos';
    }

    public function search(string $query, int $limit): SearchBucket
    {
        $like = '%'.$query.'%';

        $rows = Organization::query()
            ->select(['id', 'name', 'parent_name', 'identifier', 'nif'])
            ->where(function ($q) use ($like): void {
                $q->where('name', 'LIKE', $like)
                    ->orWhere('parent_name', 'LIKE', $like);
            })
            ->orderByRaw('CHAR_LENGTH(name) ASC')
            ->limit($limit)
            ->get();

        $hits = $rows->map(fn (Organization $o) => new SearchHit(
            type: 'organization',
            id: $o->id,
            title: $o->name ?? "Organismo #{$o->id}",
            subtitle: $o->parent_name ?: $o->identifier,
            url: "/organismos/{$o->id}",
            api_url: "/api/v1/organizations/{$o->id}",
            meta: [
                'identifier' => $o->identifier,
                'nif' => $o->nif,
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
