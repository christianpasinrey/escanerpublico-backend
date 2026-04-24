<?php

namespace Modules\Contracts\Services\Parser\Extractors;

use Modules\Contracts\Services\Parser\DTOs\NoticeDTO;

class NoticesExtractor
{
    private const NS_CBC = 'urn:dgpe:names:draft:codice:schema:xsd:CommonBasicComponents-2';

    private const NS_CAC = 'urn:dgpe:names:draft:codice:schema:xsd:CommonAggregateComponents-2';

    private const NS_CBC_EXT = 'urn:dgpe:names:draft:codice-place-ext:schema:xsd:CommonBasicComponents-2';

    private const NS_CAC_EXT = 'urn:dgpe:names:draft:codice-place-ext:schema:xsd:CommonAggregateComponents-2';

    /** @return NoticeDTO[] */
    public function extract(\SimpleXMLElement $folder): array
    {
        $notices = [];
        foreach ($folder->children(self::NS_CAC_EXT)->ValidNoticeInfo as $vni) {
            $typeCode = trim((string) $vni->children(self::NS_CBC_EXT)->NoticeTypeCode);
            if ($typeCode === '') {
                continue;
            }

            $pubStatus = $vni->children(self::NS_CAC_EXT)->AdditionalPublicationStatus;
            if (! $pubStatus || ! $pubStatus->count()) {
                continue;
            }

            $mediaName = trim((string) $pubStatus->children(self::NS_CBC_EXT)->PublicationMediaName) ?: null;

            foreach ($pubStatus->children(self::NS_CAC_EXT)->AdditionalPublicationDocumentReference as $docRef) {
                $issueDate = trim((string) $docRef->children(self::NS_CBC)->IssueDate);
                if ($issueDate === '') {
                    continue;
                }

                $documentTypeCode = null;
                $documentTypeName = null;
                $documentUri = null;
                $documentFilename = null;

                $docTypeEl = $docRef->children(self::NS_CBC)->DocumentTypeCode;
                if ($docTypeEl && trim((string) $docTypeEl) !== '') {
                    $documentTypeCode = trim((string) $docTypeEl);
                    $name = (string) ($docTypeEl->attributes()->name ?? '');
                    $documentTypeName = $name !== '' ? $name : null;
                }

                $attachment = $docRef->children(self::NS_CAC)->Attachment;
                if ($attachment && $attachment->count()) {
                    $extRef = $attachment->children(self::NS_CAC)->ExternalReference;
                    if ($extRef && $extRef->count()) {
                        $extCbc = $extRef->children(self::NS_CBC);
                        $documentUri = trim((string) $extCbc->URI) ?: null;
                        $documentFilename = trim((string) $extCbc->FileName) ?: null;
                    }
                }

                $notices[] = new NoticeDTO(
                    notice_type_code: $typeCode,
                    publication_media: $mediaName,
                    issue_date: $issueDate,
                    document_uri: $documentUri,
                    document_filename: $documentFilename,
                    document_type_code: $documentTypeCode,
                    document_type_name: $documentTypeName,
                );
            }
        }

        return $notices;
    }
}
