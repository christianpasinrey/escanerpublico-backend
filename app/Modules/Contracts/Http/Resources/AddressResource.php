<?php

namespace Modules\Contracts\Http\Resources;

use App\Models\Address;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Address
 */
class AddressResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'line' => $this->line,
            'postal_code' => $this->postal_code,
            'city_name' => $this->city_name,
            'country_code' => $this->country_code,
        ];
    }
}
