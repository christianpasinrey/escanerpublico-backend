<?php

namespace Modules\Contracts\Models;

use App\Models\Address;
use App\Models\Contact;
use Database\Factories\Modules\Contracts\CompanyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Company extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    /**
     * Note: the legacy belongsToMany(Contract::class, 'awards') relation was dropped
     * because awards.contract_id no longer exists (awards now belong to contract_lots).
     * To query "contracts where this company won" use:
     *   Contract::whereHas('lots.awards', fn($q) => $q->where('company_id', $companyId))
     * Phase 1.3 will wire this into an API endpoint.
     */
    public function awards(): HasMany
    {
        return $this->hasMany(Award::class);
    }

    public function addresses(): MorphMany
    {
        return $this->morphMany(Address::class, 'addressable');
    }

    public function contacts(): MorphMany
    {
        return $this->morphMany(Contact::class, 'contactable');
    }

    protected static function newFactory(): CompanyFactory
    {
        return CompanyFactory::new();
    }
}
