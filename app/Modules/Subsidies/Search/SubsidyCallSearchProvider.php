<?php

namespace Modules\Subsidies\Search;

use Illuminate\Database\Eloquent\Builder;
use Modules\Search\Contracts\SearchProvider;
use Modules\Search\DataObjects\SearchBucket;
use Modules\Search\DataObjects\SearchHit;
use Modules\Subsidies\Models\SubsidyCall;

class SubsidyCallSearchProvider implements SearchProvider
{
    public function key(): string
    {
        return 'subsidy-call';
    }

    public function label(): string
    {
        return 'Convocatorias';
    }

    public function search(string $query, int $limit): SearchBucket
    {
        $rows = $this->buildQuery($query)
            ->select(['id', 'external_id', 'numero_convocatoria', 'description', 'reception_date', 'nivel1', 'is_mrr'])
            ->orderByDesc('reception_date')
            ->limit($limit)
            ->get();

        $hits = $rows->map(fn (SubsidyCall $c) => new SearchHit(
            type: 'subsidy-call',
            id: $c->external_id,
            title: $this->shorten($c->description, 110) ?: "Convocatoria {$c->numero_convocatoria}",
            subtitle: $this->buildSubtitle($c),
            url: "/convocatorias/{$c->external_id}",
            api_url: "/api/v1/subsidies/calls/{$c->external_id}",
            meta: [
                'nivel1' => $c->nivel1,
                'is_mrr' => (bool) $c->is_mrr,
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
     * @return Builder<SubsidyCall>
     */
    private function buildQuery(string $term): Builder
    {
        $query = SubsidyCall::query();

        if (mb_strlen($term) < 4) {
            $like = '%'.$term.'%';
            $query->where(function (Builder $q) use ($like): void {
                $q->where('description', 'LIKE', $like)
                    ->orWhere('numero_convocatoria', 'LIKE', $like);
            });

            return $query;
        }

        $booleanTerm = '+'.str_replace(['+', '-', '(', ')', '<', '>', '~', '*', '"'], ' ', $term).'*';
        $query->whereRaw('MATCH(description) AGAINST (? IN BOOLEAN MODE)', [$booleanTerm]);

        return $query;
    }

    private function buildSubtitle(SubsidyCall $c): ?string
    {
        $parts = [];

        if ($c->numero_convocatoria) {
            $parts[] = "Nº {$c->numero_convocatoria}";
        }

        if ($c->reception_date) {
            $parts[] = $c->reception_date->format('d/m/Y');
        }

        if ($c->is_mrr) {
            $parts[] = 'MRR';
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
