<?php

namespace Tests\Feature\Contracts\Support;

use Illuminate\Support\Facades\DB;

class DatabaseSnapshot
{
    /** @var string[] */
    private const TABLES = [
        'contracts',
        'contract_lots',
        'awards',
        'awarding_criteria',
        'contract_notices',
        'contract_documents',
        'contract_modifications',
        'organizations',
        'companies',
        'addresses',
        'contacts',
    ];

    public function __construct(public string $signature) {}

    public static function capture(): self
    {
        $hashes = [];
        foreach (self::TABLES as $t) {
            $rows = DB::table($t)->orderBy('id')->get();
            // Normalize rows: strip volatile timestamp fields so re-ingest
            // producing identical semantic state yields identical hash.
            $normalized = $rows->map(function ($r): array {
                $arr = (array) $r;
                unset($arr['created_at'], $arr['updated_at'], $arr['ingested_at'], $arr['synced_at']);

                return $arr;
            });
            $hashes[$t] = sha1((string) json_encode($normalized));
        }

        return new self(sha1((string) json_encode($hashes)));
    }

    public function hash(): string
    {
        return $this->signature;
    }
}
