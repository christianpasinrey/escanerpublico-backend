<?php

namespace Modules\Legislation\Search;

use Illuminate\Database\Eloquent\Builder;
use Modules\Legislation\Models\LegislationNorm;
use Modules\Search\Contracts\SearchProvider;
use Modules\Search\DataObjects\SearchBucket;
use Modules\Search\DataObjects\SearchHit;

class LegislationSearchProvider implements SearchProvider
{
    public function key(): string
    {
        return 'legislation';
    }

    public function label(): string
    {
        return 'Legislación';
    }

    public function search(string $query, int $limit): SearchBucket
    {
        $rows = $this->buildQuery($query)
            ->select(['id', 'external_id', 'rango_text', 'titulo', 'departamento_text', 'fecha_publicacion'])
            ->orderByDesc('fecha_publicacion')
            ->limit($limit)
            ->get();

        $hits = $rows->map(fn (LegislationNorm $n) => new SearchHit(
            type: 'legislation',
            id: $n->external_id,
            title: $this->shorten($n->titulo, 110) ?: ($n->external_id ?? "Norma #{$n->id}"),
            subtitle: $this->buildSubtitle($n),
            url: "/legislacion/{$n->external_id}",
            api_url: "/api/v1/legislation/{$n->id}",
            meta: [
                'rango_text' => $n->rango_text,
                'departamento_text' => $n->departamento_text,
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
     * @return Builder<LegislationNorm>
     */
    private function buildQuery(string $term): Builder
    {
        $query = LegislationNorm::query();

        if (mb_strlen($term) < 4) {
            $like = '%'.$term.'%';
            $query->where(function (Builder $q) use ($like): void {
                $q->where('titulo', 'LIKE', $like)
                    ->orWhere('external_id', 'LIKE', $like);
            });

            return $query;
        }

        $like = '%'.$term.'%';
        $booleanTerm = '+'.str_replace(['+', '-', '(', ')', '<', '>', '~', '*', '"'], ' ', $term).'*';

        $query->where(function (Builder $q) use ($like, $booleanTerm): void {
            $q->whereRaw('MATCH(titulo) AGAINST (? IN BOOLEAN MODE)', [$booleanTerm])
                ->orWhere('external_id', 'LIKE', $like);
        });

        return $query;
    }

    private function buildSubtitle(LegislationNorm $n): ?string
    {
        $parts = [];

        if ($n->rango_text) {
            $parts[] = $n->rango_text;
        }

        if ($n->fecha_publicacion) {
            $parts[] = $n->fecha_publicacion->format('d/m/Y');
        }

        if ($n->external_id) {
            $parts[] = $n->external_id;
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
