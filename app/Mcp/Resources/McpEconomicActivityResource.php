<?php

namespace App\Mcp\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Tax\Models\EconomicActivity;

/**
 * Actividad económica del catálogo (CNAE 2025 / IAE).
 *
 * @mixin EconomicActivity
 */
class McpEconomicActivityResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'system' => $this->system,
            'code' => $this->code,
            'parent_code' => $this->parent_code,
            'level' => (int) $this->level,
            'name' => $this->name,
            'section' => $this->section,
            'year' => $this->year !== null ? (int) $this->year : null,
            'valid_from' => $this->valid_from?->toDateString(),
            'valid_to' => $this->valid_to?->toDateString(),
        ];
    }
}
