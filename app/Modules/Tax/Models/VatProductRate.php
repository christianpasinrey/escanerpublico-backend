<?php

namespace Modules\Tax\Models;

use Illuminate\Database\Eloquent\Model;

class VatProductRate extends Model
{
    protected $table = 'vat_product_rates';

    protected $fillable = [
        'year',
        'activity_code',
        'keyword',
        'rate_type',
        'rate',
        'description',
        'source_url',
    ];

    protected $casts = [
        'year' => 'integer',
        'rate' => 'decimal:2',
    ];
}
