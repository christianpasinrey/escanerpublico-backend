<?php

namespace Modules\Contracts\Models;

use Database\Factories\Modules\Contracts\AwardingCriterionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AwardingCriterion extends Model
{
    use HasFactory;

    protected $table = 'awarding_criteria';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'weight_numeric' => 'decimal:2',
            'sort_order' => 'integer',
        ];
    }

    public function contractLot(): BelongsTo
    {
        return $this->belongsTo(ContractLot::class);
    }

    protected static function newFactory(): AwardingCriterionFactory
    {
        return AwardingCriterionFactory::new();
    }
}
