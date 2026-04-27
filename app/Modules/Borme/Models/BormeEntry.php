<?php

namespace Modules\Borme\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Contracts\Models\Company;

class BormeEntry extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'registry_date' => 'date',
        'act_types' => 'array',
        'parsed_at' => 'datetime',
    ];

    public function pdf(): BelongsTo
    {
        return $this->belongsTo(BormePdf::class, 'borme_pdf_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function actItems(): HasMany
    {
        return $this->hasMany(BormeActItem::class);
    }

    public function officers(): HasMany
    {
        return $this->hasMany(BormeOfficer::class);
    }
}
