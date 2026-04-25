<?php

namespace Modules\Contracts\Services\Parser;

use Modules\Contracts\Services\Parser\DTOs\EntryDTO;
use Modules\Contracts\Services\Parser\Extractors\CriteriaExtractor;
use Modules\Contracts\Services\Parser\Extractors\DocumentsExtractor;
use Modules\Contracts\Services\Parser\Extractors\LotsExtractor;
use Modules\Contracts\Services\Parser\Extractors\NoticesExtractor;
use Modules\Contracts\Services\Parser\Extractors\OrganizationExtractor;
use Modules\Contracts\Services\Parser\Extractors\ProcessExtractor;
use Modules\Contracts\Services\Parser\Extractors\ProjectExtractor;
use Modules\Contracts\Services\Parser\Extractors\ResultsExtractor;
use Modules\Contracts\Services\Parser\Extractors\TermsExtractor;

class PlacspEntryParser
{
    private const NS_CBC = 'urn:dgpe:names:draft:codice:schema:xsd:CommonBasicComponents-2';

    private const NS_CAC = 'urn:dgpe:names:draft:codice:schema:xsd:CommonAggregateComponents-2';

    private const NS_CBC_EXT = 'urn:dgpe:names:draft:codice-place-ext:schema:xsd:CommonBasicComponents-2';

    private const NS_CAC_EXT = 'urn:dgpe:names:draft:codice-place-ext:schema:xsd:CommonAggregateComponents-2';

    public function __construct(
        private OrganizationExtractor $orgExtractor,
        private ProjectExtractor $projectExtractor,
        private LotsExtractor $lotsExtractor,
        private ProcessExtractor $processExtractor,
        private ResultsExtractor $resultsExtractor,
        private TermsExtractor $termsExtractor,
        private CriteriaExtractor $criteriaExtractor,
        private NoticesExtractor $noticesExtractor,
        private DocumentsExtractor $documentsExtractor,
    ) {}

    public function parse(\SimpleXMLElement $entry): EntryDTO
    {
        $externalId = trim((string) $entry->id);
        $link = null;
        foreach ($entry->link as $l) {
            $href = (string) ($l->attributes()->href ?? '');
            if ($href !== '') {
                $link = $href;
                break;
            }
        }
        $updated = trim((string) $entry->updated);
        $updatedAt = new \DateTimeImmutable($updated !== '' ? $updated : 'now');

        $folder = $entry->children(self::NS_CAC_EXT)->ContractFolderStatus;
        $expediente = trim((string) $folder->children(self::NS_CBC)->ContractFolderID) ?: trim((string) $entry->title);
        $statusCode = trim((string) $folder->children(self::NS_CBC_EXT)->ContractFolderStatusCode) ?: 'PUB';

        $lp = $folder->children(self::NS_CAC_EXT)->LocatedContractingParty;
        $organization = $this->orgExtractor->extract($lp);

        $project = $folder->children(self::NS_CAC)->ProcurementProject;
        $lots = $this->lotsExtractor->extract($project);
        if ($lots === []) {
            $lots = [$this->projectExtractor->extract($project)];
        }

        $process = null;
        $processNode = $folder->children(self::NS_CAC)->TenderingProcess;
        if ($processNode && $processNode->count()) {
            $process = $this->processExtractor->extract($processNode);
        }

        $results = $this->resultsExtractor->extract($folder);

        $terms = null;
        $criteriaByLot = [];
        $termsNode = $folder->children(self::NS_CAC)->TenderingTerms;
        if ($termsNode && $termsNode->count()) {
            $terms = $this->termsExtractor->extract($termsNode);
            $criteriaByLot = $this->criteriaExtractor->extract($termsNode, defaultLotNumber: 1);
        }

        $notices = $this->noticesExtractor->extract($folder);
        $documents = $this->documentsExtractor->extract($folder);

        return new EntryDTO(
            external_id: $externalId,
            link: $link,
            expediente: mb_substr($expediente, 0, 490),
            status_code: $statusCode,
            entry_updated_at: $updatedAt,
            organization: $organization,
            lots: $lots,
            process: $process,
            results: $results,
            terms: $terms,
            criteria_by_lot: $criteriaByLot,
            notices: $notices,
            documents: $documents,
        );
    }
}
