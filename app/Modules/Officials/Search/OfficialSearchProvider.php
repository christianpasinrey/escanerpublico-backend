<?php

namespace Modules\Officials\Search;

use Illuminate\Database\Eloquent\Builder;
use Modules\Officials\Models\PublicOfficial;
use Modules\Search\Contracts\SearchProvider;
use Modules\Search\DataObjects\SearchBucket;
use Modules\Search\DataObjects\SearchHit;

class OfficialSearchProvider implements SearchProvider
{
    public function key(): string
    {
        return 'official';
    }

    public function label(): string
    {
        return 'Cargos públicos';
    }

    public function search(string $query, int $limit): SearchBucket
    {
        $rows = $this->buildQuery($query)
            ->select(['id', 'full_name', 'honorific', 'appointments_count', 'last_event_date'])
            ->orderByDesc('appointments_count')
            ->limit($limit)
            ->get();

        $hits = $rows->map(fn (PublicOfficial $o) => new SearchHit(
            type: 'official',
            id: $o->id,
            title: $o->honorific ? "{$o->honorific} {$o->full_name}" : $o->full_name,
            subtitle: $this->buildSubtitle($o),
            url: "/cargos/{$o->id}",
            api_url: "/api/v1/officials/{$o->id}",
            meta: [
                'appointments_count' => (int) $o->appointments_count,
            ],
        ))->all();

        return new SearchBucket(
            key: $this->key(),
            label: $this->label(),
            hits: array_values($hits),
            total: count($hits),
        );
    }

    /**
     * @return Builder<PublicOfficial>
     */
    private function buildQuery(string $term): Builder
    {
        $query = PublicOfficial::query();

        if (mb_strlen($term) < 4) {
            $like = '%'.$term.'%';
            $query->where('full_name', 'LIKE', $like);

            return $query;
        }

        $booleanTerm = '+'.str_replace(['+', '-', '(', ')', '<', '>', '~', '*', '"'], ' ', $term).'*';
        $query->whereRaw('MATCH(full_name) AGAINST (? IN BOOLEAN MODE)', [$booleanTerm]);

        return $query;
    }

    private function buildSubtitle(PublicOfficial $o): ?string
    {
        $parts = [];

        if ($o->appointments_count > 0) {
            $count = (int) $o->appointments_count;
            $parts[] = $count === 1 ? '1 nombramiento' : "{$count} nombramientos";
        }

        if ($o->last_event_date) {
            $parts[] = 'último '.$o->last_event_date->format('d/m/Y');
        }

        return $parts ? implode(' · ', $parts) : null;
    }
}
