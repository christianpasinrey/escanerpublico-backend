<?php

namespace Modules\Officials\Models;

use Database\Factories\Modules\Officials\PublicOfficialFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PublicOfficial extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'first_appointment_date' => 'date',
            'last_event_date' => 'date',
        ];
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    protected static function newFactory(): PublicOfficialFactory
    {
        return PublicOfficialFactory::new();
    }
}
