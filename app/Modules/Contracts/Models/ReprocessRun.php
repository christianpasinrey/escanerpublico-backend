<?php

namespace Modules\Contracts\Models;

use Database\Factories\Modules\Contracts\ReprocessRunFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReprocessRun extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'config' => 'array',
        ];
    }

    public function atomRuns(): HasMany
    {
        return $this->hasMany(ReprocessAtomRun::class);
    }

    protected static function newFactory(): ReprocessRunFactory
    {
        return ReprocessRunFactory::new();
    }
}
