<?php

namespace App\Mcp\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Contracts\Models\Organization;

/**
 * Vista expandida de un organismo: incluye direcciones y contactos
 * cuando se han cargado vía `->load('addresses', 'contacts')`.
 *
 * @mixin Organization
 */
class McpOrganizationDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'identifier' => $this->identifier,
            'nif' => $this->nif,
            'type_code' => $this->type_code,
            'activity_code' => $this->activity_code,
            'platform_id' => $this->platform_id,
            'buyer_profile_uri' => $this->buyer_profile_uri,
            'parent_name' => $this->parent_name,
            'hierarchy' => $this->hierarchy,
            'addresses' => $this->whenLoaded('addresses', fn () => $this->addresses->map(fn ($a) => [
                'street' => $a->street ?? null,
                'postal_code' => $a->postal_code ?? null,
                'city_name' => $a->city_name ?? null,
                'state_name' => $a->state_name ?? null,
                'country_code' => $a->country_code ?? null,
            ])->all()),
            'contacts' => $this->whenLoaded('contacts', fn () => $this->contacts->map(fn ($c) => [
                'name' => $c->name ?? null,
                'email' => $c->email ?? null,
                'phone' => $c->phone ?? null,
                'fax' => $c->fax ?? null,
                'website' => $c->website ?? null,
            ])->all()),
            'detail_url' => "https://app.gobtracker.tailor-bytes.com/api/v1/organizations/{$this->id}",
            'public_url' => "https://gobtracker.tailor-bytes.com/organismos/{$this->id}",
        ];
    }
}
