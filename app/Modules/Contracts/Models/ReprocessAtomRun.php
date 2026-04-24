<?php

namespace Modules\Contracts\Models;

use Database\Factories\Modules\Contracts\ReprocessAtomRunFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReprocessAtomRun extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function reprocessRun(): BelongsTo
    {
        return $this->belongsTo(ReprocessRun::class);
    }

    protected static function newFactory(): ReprocessAtomRunFactory
    {
        return ReprocessAtomRunFactory::new();
    }
}
