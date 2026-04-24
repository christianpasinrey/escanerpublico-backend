<?php

namespace Modules\Contracts\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractNotice extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['issue_date' => 'date'];
    }

    public const NOTICE_TYPE_LABELS = [
        'DOC_CN' => 'Anuncio de licitación',
        'DOC_CD' => 'Carátula del expediente',
        'DOC_CAN_ADJ' => 'Anuncio de adjudicación',
        'DOC_FORM' => 'Anuncio de formalización',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }
}
