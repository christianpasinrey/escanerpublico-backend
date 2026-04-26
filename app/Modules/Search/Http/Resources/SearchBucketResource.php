<?php

namespace Modules\Search\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Search\DataObjects\SearchBucket;

/**
 * @mixin SearchBucket
 */
class SearchBucketResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'total' => $this->total,
            'hits' => SearchHitResource::collection($this->hits),
        ];
    }
}
