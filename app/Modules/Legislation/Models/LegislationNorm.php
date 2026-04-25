<?php

namespace Modules\Legislation\Models;

use Database\Factories\Modules\Legislation\LegislationNormFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Contracts\Models\Organization;

class LegislationNorm extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'fecha_disposicion' => 'date',
            'fecha_publicacion' => 'date',
            'fecha_vigencia' => 'date',
            'fecha_actualizacion' => 'datetime',
            'vigencia_agotada' => 'boolean',
            'ingested_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    protected static function newFactory(): LegislationNormFactory
    {
        return LegislationNormFactory::new();
    }
}
