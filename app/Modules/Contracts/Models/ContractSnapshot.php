<?php

namespace Modules\Contracts\Models;

use Database\Factories\Modules\Contracts\ContractSnapshotFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractSnapshot extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'entry_updated_at' => 'datetime',
            'ingested_at' => 'datetime',
            'payload' => 'array',
        ];
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    protected static function newFactory(): ContractSnapshotFactory
    {
        return ContractSnapshotFactory::new();
    }
}
