<?php

namespace Modules\Contracts\Services\Parser\DTOs;

final readonly class NoticeDTO
{
    public function __construct(
        public string $notice_type_code,
        public ?string $publication_media,
        public string $issue_date,
        public ?string $document_uri,
        public ?string $document_filename,
        public ?string $document_type_code,
        public ?string $document_type_name,
    ) {}
}
