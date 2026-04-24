<?php

namespace Modules\Contracts\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Contracts\Models\Award;

/**
 * @mixin Award
 */
class AwardResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'amount' => $this->amount,
            'amount_without_tax' => $this->amount_without_tax,
            'description' => $this->description,
            'award_date' => $this->award_date?->toDateString(),
            'formalization_date' => $this->formalization_date?->toDateString(),
            'contract_number' => $this->contract_number,
            'sme_awarded' => $this->sme_awarded,
            'num_offers' => $this->num_offers,
            'lower_tender_amount' => $this->lower_tender_amount,
            'higher_tender_amount' => $this->higher_tender_amount,
            'result_code' => $this->result_code,

            'company' => CompanyResource::make($this->whenLoaded('company')),
            'contract_lot' => LotResource::make($this->whenLoaded('contractLot')),
        ];
    }
}
