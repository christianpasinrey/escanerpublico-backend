<?php

namespace Modules\Contracts\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Contracts\Models\Company;

/**
 * @mixin Company
 */
class CompanyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'nif' => $this->nif,
            'legal_form' => $this->legal_form,
            'status' => $this->status,
            'registry' => $this->registry_letter !== null ? [
                'letter' => $this->registry_letter,
                'sheet' => $this->registry_sheet,
                'section' => $this->registry_section,
            ] : null,
            'domicile' => $this->domicile_address !== null ? [
                'address' => $this->domicile_address,
                'city' => $this->domicile_city,
            ] : null,
            'capital' => $this->capital_cents !== null ? [
                'amount_cents' => $this->capital_cents,
                'currency' => $this->capital_currency,
            ] : null,
            'incorporation_date' => $this->incorporation_date?->toDateString(),
            'last_act_date' => $this->last_act_date?->toDateString(),
            'source_modules' => $this->source_modules ?? [],
            'awards_count' => $this->whenCounted('awards'),
            'awards_sum_amount' => $this->awards_sum_amount ?? null,
            'addresses' => AddressResource::collection($this->whenLoaded('addresses')),
            'contacts' => ContactResource::collection($this->whenLoaded('contacts')),
            'awards' => AwardResource::collection($this->whenLoaded('awards')),
        ];
    }
}
