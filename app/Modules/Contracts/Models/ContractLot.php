<?php

namespace Modules\Contracts\Models;

use Database\Factories\Modules\Contracts\ContractLotFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContractLot extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'cpv_codes' => 'array',
            'budget_with_tax' => 'decimal:2',
            'budget_without_tax' => 'decimal:2',
            'estimated_value' => 'decimal:2',
            'duration' => 'decimal:2',
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function awards(): HasMany
    {
        return $this->hasMany(Award::class);
    }

    public function criteria(): HasMany
    {
        return $this->hasMany(AwardingCriterion::class)->orderBy('sort_order');
    }

    protected static function newFactory(): ContractLotFactory
    {
        return ContractLotFactory::new();
    }
}
