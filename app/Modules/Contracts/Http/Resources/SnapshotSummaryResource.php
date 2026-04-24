<?php

namespace Modules\Contracts\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \Modules\Contracts\Models\ContractSnapshot
 */
class SnapshotSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'entry_updated_at' => $this->entry_updated_at?->toIso8601String(),
            'status_code' => $this->status_code,
            'content_hash' => $this->content_hash,
            'source_atom' => $this->source_atom,
        ];
    }
}
