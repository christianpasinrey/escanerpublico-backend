<?php

namespace Modules\Contracts\Models;

use Database\Factories\Modules\Contracts\ContractModificationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractModification extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'effective_date' => 'date',
            'new_end_date' => 'date',
            'amount_delta' => 'decimal:2',
        ];
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function relatedNotice(): BelongsTo
    {
        return $this->belongsTo(ContractNotice::class, 'related_notice_id');
    }

    protected static function newFactory(): ContractModificationFactory
    {
        return ContractModificationFactory::new();
    }
}
