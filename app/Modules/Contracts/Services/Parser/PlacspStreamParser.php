<?php

namespace Modules\Contracts\Services\Parser;

use Modules\Contracts\Services\Parser\DTOs\EntryDTO;
use Modules\Contracts\Services\Parser\DTOs\TombstoneDTO;
use Modules\Contracts\Services\Parser\Extractors\TombstoneExtractor;

class PlacspStreamParser
{
    private const NS_ATOM = 'http://www.w3.org/2005/Atom';

    private const NS_AT = 'http://purl.org/atompub/tombstones/1.0';

    public function __construct(
        private PlacspEntryParser $entryParser,
        private TombstoneExtractor $tombstoneExtractor,
    ) {}

    /** @return \Generator<EntryDTO|TombstoneDTO> */
    public function stream(string $atomPath): \Generator
    {
        $reader = new \XMLReader();
        if (! $reader->open($atomPath)) {
            throw new \RuntimeException("Cannot open atom: {$atomPath}");
        }

        while ($reader->read()) {
            if ($reader->nodeType !== \XMLReader::ELEMENT) {
                continue;
            }

            if ($reader->localName === 'entry' && $reader->namespaceURI === self::NS_ATOM) {
                $xml = $this->expandNode($reader);
                if ($xml !== null) {
                    yield $this->entryParser->parse($xml);
                }
            } elseif ($reader->localName === 'deleted-entry' && $reader->namespaceURI === self::NS_AT) {
                $xml = $this->expandNode($reader);
                if ($xml !== null) {
                    yield $this->tombstoneExtractor->extract($xml);
                }
            }
        }

        $reader->close();
    }

    private function expandNode(\XMLReader $reader): ?\SimpleXMLElement
    {
        $doc = new \DOMDocument('1.0', 'UTF-8');
        $node = $reader->expand($doc);
        if ($node === false) {
            return null;
        }
        $doc->appendChild($node);
        $xml = simplexml_import_dom($doc->documentElement);

        return $xml instanceof \SimpleXMLElement ? $xml : null;
    }
}
