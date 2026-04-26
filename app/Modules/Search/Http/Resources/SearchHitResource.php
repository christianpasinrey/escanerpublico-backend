<?php

namespace Modules\Search\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Search\DataObjects\SearchHit;

/**
 * @mixin SearchHit
 */
class SearchHitResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'type' => $this->type,
            'id' => $this->id,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'url' => $this->url,
            'api_url' => $this->api_url,
            'meta' => (object) $this->meta,
        ];
    }
}
