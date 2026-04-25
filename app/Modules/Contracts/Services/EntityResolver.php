<?php

namespace Modules\Contracts\Services;

use Illuminate\Support\Facades\Cache;
use Modules\Contracts\Models\Company;
use Modules\Contracts\Models\Organization;

class EntityResolver
{
    private const CACHE_TAG = 'placsp_import';

    private const CACHE_TTL = 7200;

    /** @var array<string, int> */
    private array $orgsCache = [];

    /** @var array<string, int> */
    private array $companiesCache = [];

    public function preload(): void
    {
        $cached = Cache::tags([self::CACHE_TAG])->get('orgs_resolver');
        if (is_array($cached)) {
            $this->orgsCache = $cached;
        } else {
            Organization::select('id', 'identifier', 'nif', 'name')->chunk(10000, function ($orgs): void {
                foreach ($orgs as $o) {
                    if ($o->identifier) {
                        $this->orgsCache['dir3:'.$o->identifier] = (int) $o->id;
                    }
                    if ($o->nif) {
                        $this->orgsCache['nif:'.$o->nif] = (int) $o->id;
                    }
                    if ($o->name) {
                        $this->orgsCache['name:'.$this->normalizeName((string) $o->name)] = (int) $o->id;
                    }
                }
            });
            Cache::tags([self::CACHE_TAG])->put('orgs_resolver', $this->orgsCache, self::CACHE_TTL);
        }

        $cached = Cache::tags([self::CACHE_TAG])->get('companies_resolver');
        if (is_array($cached)) {
            $this->companiesCache = $cached;
        } else {
            Company::select('id', 'nif', 'name')->chunk(10000, function ($cs): void {
                foreach ($cs as $c) {
                    if ($c->nif) {
                        $this->companiesCache['nif:'.$c->nif] = (int) $c->id;
                    }
                    if ($c->name) {
                        $this->companiesCache['name:'.$this->normalizeName((string) $c->name)] = (int) $c->id;
                    }
                }
            });
            Cache::tags([self::CACHE_TAG])->put('companies_resolver', $this->companiesCache, self::CACHE_TTL);
        }
    }

    public function resolveOrganizationId(?string $dir3, ?string $nif, ?string $name): ?int
    {
        if ($dir3 !== null && $dir3 !== '' && isset($this->orgsCache['dir3:'.$dir3])) {
            return $this->orgsCache['dir3:'.$dir3];
        }
        if ($nif !== null && $nif !== '' && isset($this->orgsCache['nif:'.$nif])) {
            return $this->orgsCache['nif:'.$nif];
        }
        if ($name !== null && $name !== '') {
            $k = 'name:'.$this->normalizeName($name);
            if (isset($this->orgsCache[$k])) {
                return $this->orgsCache[$k];
            }
        }

        return null;
    }

    public function resolveCompanyId(?string $nif, ?string $name): ?int
    {
        if ($nif !== null && $nif !== '' && isset($this->companiesCache['nif:'.$nif])) {
            return $this->companiesCache['nif:'.$nif];
        }
        if ($name !== null && $name !== '') {
            $k = 'name:'.$this->normalizeName($name);
            if (isset($this->companiesCache[$k])) {
                return $this->companiesCache[$k];
            }
        }

        return null;
    }

    public function registerOrganization(Organization $o): void
    {
        if ($o->identifier) {
            $this->orgsCache['dir3:'.$o->identifier] = (int) $o->id;
        }
        if ($o->nif) {
            $this->orgsCache['nif:'.$o->nif] = (int) $o->id;
        }
        if ($o->name) {
            $this->orgsCache['name:'.$this->normalizeName((string) $o->name)] = (int) $o->id;
        }
    }

    public function registerCompany(Company $c): void
    {
        if ($c->nif) {
            $this->companiesCache['nif:'.$c->nif] = (int) $c->id;
        }
        if ($c->name) {
            $this->companiesCache['name:'.$this->normalizeName((string) $c->name)] = (int) $c->id;
        }
    }

    public function persistCaches(): void
    {
        Cache::tags([self::CACHE_TAG])->put('orgs_resolver', $this->orgsCache, self::CACHE_TTL);
        Cache::tags([self::CACHE_TAG])->put('companies_resolver', $this->companiesCache, self::CACHE_TTL);
    }

    private function normalizeName(string $name): string
    {
        $t = mb_strtolower($name, 'UTF-8');
        $transliterated = @iconv('UTF-8', 'ASCII//TRANSLIT', $t);
        if ($transliterated !== false) {
            $t = $transliterated;
        }
        // Strip punctuation (keep letters, digits, whitespace). S.L. → SL.
        $replaced = preg_replace('/[^a-z0-9\s]+/', '', $t);
        $t = $replaced ?? $t;
        $collapsed = preg_replace('/\s+/', ' ', $t);

        return trim($collapsed ?? $t);
    }
}
