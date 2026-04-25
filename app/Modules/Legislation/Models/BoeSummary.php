<?php

namespace Modules\Legislation\Models;

use Database\Factories\Modules\Legislation\BoeSummaryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BoeSummary extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'fecha_publicacion' => 'date',
            'raw_payload' => 'array',
            'ingested_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(BoeItem::class, 'summary_id');
    }

    protected static function newFactory(): BoeSummaryFactory
    {
        return BoeSummaryFactory::new();
    }
}
