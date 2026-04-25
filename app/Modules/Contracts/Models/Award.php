<?php

namespace Modules\Contracts\Models;

use Database\Factories\Modules\Contracts\AwardFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Award extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'amount_without_tax' => 'decimal:2',
            'lower_tender_amount' => 'decimal:2',
            'higher_tender_amount' => 'decimal:2',
            'award_date' => 'date',
            'start_date' => 'date',
            'formalization_date' => 'date',
            'sme_awarded' => 'boolean',
        ];
    }

    public function contractLot(): BelongsTo
    {
        return $this->belongsTo(ContractLot::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    protected static function newFactory(): AwardFactory
    {
        return AwardFactory::new();
    }
}
