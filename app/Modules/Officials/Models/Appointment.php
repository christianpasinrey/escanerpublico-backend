<?php

namespace Modules\Officials\Models;

use Database\Factories\Modules\Officials\AppointmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Contracts\Models\Organization;
use Modules\Legislation\Models\BoeItem;

class Appointment extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'effective_date' => 'date',
        ];
    }

    public function publicOfficial(): BelongsTo
    {
        return $this->belongsTo(PublicOfficial::class);
    }

    public function boeItem(): BelongsTo
    {
        return $this->belongsTo(BoeItem::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    protected static function newFactory(): AppointmentFactory
    {
        return AppointmentFactory::new();
    }
}
