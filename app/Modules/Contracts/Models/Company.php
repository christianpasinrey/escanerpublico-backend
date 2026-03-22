<?php

namespace Modules\Contracts\Models;

use App\Models\Address;
use App\Models\Contact;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Company extends Model
{
    protected $guarded = ['id'];

    public function contracts(): BelongsToMany
    {
        return $this->belongsToMany(Contract::class, 'awards')
            ->withPivot('amount', 'amount_without_tax', 'award_date', 'start_date',
                'formalization_date', 'contract_number', 'sme_awarded', 'num_offers', 'result_code')
            ->withTimestamps();
    }

    public function awards(): HasMany { return $this->hasMany(Award::class); }
    public function addresses(): MorphMany { return $this->morphMany(Address::class, 'addressable'); }
    public function contacts(): MorphMany { return $this->morphMany(Contact::class, 'contactable'); }
}
