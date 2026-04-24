<?php

namespace Modules\Contracts\Models;

use App\Models\Address;
use App\Models\Contact;
use Database\Factories\Modules\Contracts\OrganizationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Organization extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['hierarchy' => 'array'];
    }

    public function contracts(): HasMany { return $this->hasMany(Contract::class); }
    public function addresses(): MorphMany { return $this->morphMany(Address::class, 'addressable'); }
    public function contacts(): MorphMany { return $this->morphMany(Contact::class, 'contactable'); }

    protected static function newFactory(): OrganizationFactory
    {
        return OrganizationFactory::new();
    }
}
