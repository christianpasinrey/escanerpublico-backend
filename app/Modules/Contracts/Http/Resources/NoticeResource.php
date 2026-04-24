<?php

namespace Modules\Contracts\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \Modules\Contracts\Models\ContractNotice
 */
class NoticeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'notice_type_code' => $this->notice_type_code,
            'publication_media' => $this->publication_media,
            'issue_date' => $this->issue_date?->toDateString(),
            'document_uri' => $this->document_uri,
            'document_filename' => $this->document_filename,
            'document_type_code' => $this->document_type_code,
            'document_type_name' => $this->document_type_name,
        ];
    }
}
