<?php

namespace Modules\Tax\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Tax\Models\EconomicActivity;

/**
 * @mixin EconomicActivity
 */
class EconomicActivityResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'system' => $this->system,
            'code' => $this->code,
            'parent_code' => $this->parent_code,
            'level' => $this->level,
            'name' => $this->name,
            'section' => $this->section,
            'year' => $this->year,
            'valid_from' => $this->valid_from?->toDateString(),
            'valid_to' => $this->valid_to?->toDateString(),
            'editorial_md' => $this->editorial_md,
            'regime_mapping' => $this->whenLoaded('regimeMapping', fn () => $this->regimeMapping ? [
                'eligible_regimes' => $this->regimeMapping->eligible_regimes,
                'vat_rate_default' => $this->regimeMapping->vat_rate_default,
                'irpf_retention_default' => $this->regimeMapping->irpf_retention_default !== null
                    ? (float) $this->regimeMapping->irpf_retention_default
                    : null,
                'notes' => $this->regimeMapping->notes,
            ] : null),
        ];
    }
}
