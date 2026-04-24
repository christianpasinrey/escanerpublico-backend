<?php

namespace Modules\Contracts\Services\Parser\Extractors;

use Modules\Contracts\Services\Parser\DTOs\DocumentDTO;

class DocumentsExtractor
{
    private const NS_CBC = 'urn:dgpe:names:draft:codice:schema:xsd:CommonBasicComponents-2';

    private const NS_CAC = 'urn:dgpe:names:draft:codice:schema:xsd:CommonAggregateComponents-2';

    private const NS_CAC_EXT = 'urn:dgpe:names:draft:codice-place-ext:schema:xsd:CommonAggregateComponents-2';

    /** @return DocumentDTO[] */
    public function extract(\SimpleXMLElement $folder): array
    {
        $docs = [];
        $cac = $folder->children(self::NS_CAC);

        $mappings = [
            'LegalDocumentReference' => 'legal',
            'TechnicalDocumentReference' => 'technical',
            'AdditionalDocumentReference' => 'additional',
        ];

        foreach ($mappings as $tag => $type) {
            foreach ($cac->{$tag} as $ref) {
                $dto = $this->parseRef($ref, $type);
                if ($dto !== null) {
                    $docs[] = $dto;
                }
            }
        }

        foreach ($folder->children(self::NS_CAC_EXT)->GeneralDocument as $gd) {
            $ref = $gd->children(self::NS_CAC_EXT)->GeneralDocumentDocumentReference;
            if (! $ref || ! $ref->count()) {
                $ref = $gd->children(self::NS_CAC)->DocumentReference;
            }
            if ($ref && $ref->count()) {
                $dto = $this->parseRef($ref, 'general');
                if ($dto !== null) {
                    $docs[] = $dto;
                }
            }
        }

        return $docs;
    }

    private function parseRef(\SimpleXMLElement $ref, string $type): ?DocumentDTO
    {
        $name = trim((string) $ref->children(self::NS_CBC)->ID);
        if ($name === '') {
            return null;
        }

        $uri = null;
        $hash = null;
        $attachment = $ref->children(self::NS_CAC)->Attachment;
        if ($attachment && $attachment->count()) {
            $extRef = $attachment->children(self::NS_CAC)->ExternalReference;
            if ($extRef && $extRef->count()) {
                $extCbc = $extRef->children(self::NS_CBC);
                $uri = trim((string) $extCbc->URI) ?: null;
                $hash = trim((string) $extCbc->DocumentHash) ?: null;

                $fileName = trim((string) $extCbc->FileName);
                if ($fileName !== '') {
                    // prefer FileName over UUID-ID for general docs
                    $name = $fileName;
                }
            }
        }

        return new DocumentDTO($type, $name, $uri, $hash);
    }
}
