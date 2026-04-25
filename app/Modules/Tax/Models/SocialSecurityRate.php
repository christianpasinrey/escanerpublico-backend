<?php

namespace Modules\Tax\Models;

use Illuminate\Database\Eloquent\Model;

class SocialSecurityRate extends Model
{
    protected $table = 'social_security_rates';

    protected $fillable = [
        'year',
        'regime',
        'contingency',
        'rate_employer',
        'rate_employee',
        'base_min',
        'base_max',
        'valid_from',
        'valid_to',
        'source_url',
        'notes',
    ];

    protected $casts = [
        'year' => 'integer',
        'rate_employer' => 'decimal:4',
        'rate_employee' => 'decimal:4',
        'base_min' => 'decimal:2',
        'base_max' => 'decimal:2',
        'valid_from' => 'date',
        'valid_to' => 'date',
    ];
}
