<?php

namespace Modules\Tax\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaxType extends Model
{
    protected $table = 'tax_types';

    protected $fillable = [
        'code',
        'scope',
        'levy_type',
        'name',
        'base_law_url',
        'region_code',
        'municipality_id',
        'editorial_md',
    ];

    public function rates(): HasMany
    {
        return $this->hasMany(TaxRate::class, 'tax_type_id');
    }
}
