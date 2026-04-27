<?php

namespace Modules\Contracts\Models;

use App\Models\Address;
use App\Models\Contact;
use Database\Factories\Modules\Contracts\CompanyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * @property int $id
 * @property string|null $name
 * @property string|null $name_normalized
 * @property string|null $identifier
 * @property string|null $nif
 * @property string|null $registry_letter
 * @property int|null $registry_sheet
 * @property string|null $registry_section
 * @property string|null $legal_form
 * @property string|null $domicile_address
 * @property string|null $domicile_city
 * @property int|null $capital_cents
 * @property string|null $capital_currency
 * @property \Illuminate\Support\Carbon|null $incorporation_date
 * @property string|null $status
 * @property \Illuminate\Support\Carbon|null $last_act_date
 * @property array|null $source_modules
 */
class Company extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'source_modules' => 'array',
        'incorporation_date' => 'date',
        'last_act_date' => 'date',
    ];

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
