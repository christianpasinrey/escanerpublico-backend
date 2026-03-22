<?php

namespace Modules\Contracts\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Award extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2', 'amount_without_tax' => 'decimal:2',
            'sme_awarded' => 'boolean',
            'award_date' => 'date', 'start_date' => 'date', 'formalization_date' => 'date',
        ];
    }

    public function contract(): BelongsTo { return $this->belongsTo(Contract::class); }
    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
}
