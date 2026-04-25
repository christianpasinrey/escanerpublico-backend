<?php

namespace Modules\Legislation\Models;

use Database\Factories\Modules\Legislation\BoeItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Contracts\Models\Organization;

class BoeItem extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'fecha_publicacion' => 'date',
        ];
    }

    public function summary(): BelongsTo
    {
        return $this->belongsTo(BoeSummary::class, 'summary_id');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    protected static function newFactory(): BoeItemFactory
    {
        return BoeItemFactory::new();
    }
}
