<?php

namespace Modules\Borme\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Contracts\Models\Company;

class BormeOfficer extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'effective_date' => 'date',
    ];

    public function entry(): BelongsTo
    {
        return $this->belongsTo(BormeEntry::class, 'borme_entry_id');
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'officer_person_id');
    }

    public function representative(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'representative_person_id');
    }

    public function officerCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'officer_company_id');
    }
}
