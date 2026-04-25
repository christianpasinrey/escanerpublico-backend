<?php

namespace Modules\Tax\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class EconomicActivity extends Model
{
    protected $table = 'economic_activities';

    protected $fillable = [
        'system',
        'code',
        'parent_code',
        'level',
        'name',
        'section',
        'year',
        'valid_from',
        'valid_to',
        'editorial_md',
    ];

    protected $casts = [
        'level' => 'integer',
        'year' => 'integer',
        'valid_from' => 'date',
        'valid_to' => 'date',
    ];

    public function regimeMapping(): HasOne
    {
        return $this->hasOne(ActivityRegimeMapping::class, 'activity_id');
    }

    public function parent(): ?self
    {
        if ($this->parent_code === null) {
            return null;
        }

        return self::query()
            ->where('system', $this->system)
            ->where('year', $this->year)
            ->where('code', $this->parent_code)
            ->first();
    }
}
