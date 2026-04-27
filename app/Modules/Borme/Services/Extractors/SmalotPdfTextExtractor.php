<?php

namespace Modules\Borme\Services\Extractors;

use Smalot\PdfParser\Parser;

class SmalotPdfTextExtractor
{
    public function __construct(private readonly Parser $parser = new Parser) {}

    public function extract(string $path): string
    {
        return $this->parser->parseFile($path)->getText();
    }
}
