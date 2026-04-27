<?php

namespace Modules\Borme\Services\Parsers;

use Modules\Borme\DTOs\BormeEntryDTO;
use Modules\Borme\Enums\ActType;
use Modules\Borme\Services\Extractors\SmalotPdfTextExtractor;
use Modules\Borme\Services\Support\ActTypeClassifier;
use Modules\Borme\Services\Support\PageSplitter;
use Modules\Borme\Services\Support\TextNormalizer;

class SectionOneParser
{
    public const PARSER_VERSION = 'sectionone@v1';

    public function __construct(
        private readonly SmalotPdfTextExtractor $extractor,
        private readonly TextNormalizer $normalizer,
        private readonly PageSplitter $splitter,
        private readonly ActTypeClassifier $classifier,
        private readonly CompanyHeaderExtractor $headerExtractor,
        private readonly RegistryDataExtractor $registryExtractor,
        private readonly OfficerExtractor $officerExtractor,
        private readonly ActParserRegistry $actParserRegistry,
    ) {}

    /**
     * @return BormeEntryDTO[]
     */
    public function parseFile(string $path): array
    {
        $raw = $this->extractor->extract($path);
        $normalized = $this->normalizer->normalize($raw);
        $blocks = $this->splitter->split($normalized);

        $entries = [];
        foreach ($blocks as $block) {
            $entry = $this->parseBlock($block['raw']);
            if ($entry !== null) {
                $entries[] = $entry;
            }
        }

        return $entries;
    }

    public function parseBlock(string $block): ?BormeEntryDTO
    {
        $header = $this->headerExtractor->extract($block);
        if ($header === null) {
            return null;
        }

        $registry = $this->registryExtractor->extract($block);
        $actTypes = $this->classifier->classify($block);
        $officers = $this->officerExtractor->extract($block);

        $actItems = [];
        foreach ($actTypes as $type) {
            $parser = $this->actParserRegistry->for($type);
            if ($parser === null) {
                continue;
            }
            $item = $parser->parse($block);
            if ($item !== null) {
                $actItems[] = $item;
            }
        }

        return new BormeEntryDTO(
            entryNumber: $header['number'],
            companyNameRaw: $header['name_raw'],
            companyNameNormalized: $header['name_normalized'],
            legalForm: $header['legal_form'],
            registryLetter: $registry['letter'] ?? null,
            registrySheet: $registry['sheet'] ?? null,
            registrySection: $registry['section'] ?? null,
            registryInscription: $registry['inscription'] ?? null,
            registryDate: $registry['date'] ?? null,
            actTypes: $actTypes,
            actItems: $actItems,
            officers: $officers,
            rawText: $block,
        );
    }
}
