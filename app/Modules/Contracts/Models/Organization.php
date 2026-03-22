<?php

namespace Modules\Contracts\Models;

use App\Models\Address;
use App\Models\Contact;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Organization extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['hierarchy' => 'array'];
    }

    public function contracts(): HasMany { return $this->hasMany(Contract::class); }
    public function addresses(): MorphMany { return $this->morphMany(Address::class, 'addressable'); }
    public function contacts(): MorphMany { return $this->morphMany(Contact::class, 'contactable'); }
}
