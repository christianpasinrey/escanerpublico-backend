<?php

namespace Modules\Borme\Services\Support;

use Modules\Borme\Enums\LegalForm;

class LegalFormDetector
{
    /**
     * Map of legal-form suffix tokens (as they appear in BORME company names)
     * to LegalForm enum cases. Order matters: more specific first. Patterns
     * are PCRE fragments — they must match end-of-string after pre-cleaning.
     */
    private const SUFFIX_PATTERNS = [
        ['SUCURSAL\s+EN\s+ESPA(?:Ñ|N)A', LegalForm::Branch],
        ['SOCIEDAD\s+ANONIMA\s+UNIPERSONAL', LegalForm::SAU],
        ['SOCIEDAD\s+LIMITADA\s+UNIPERSONAL', LegalForm::SLU],
        ['SOCIEDAD\s+LIMITADA\s+LABORAL', LegalForm::SLL],
        ['SOCIEDAD\s+LIMITADA\s+PROFESIONAL', LegalForm::SLP],
        ['SOCIEDAD\s+LIMITADA\s+NUEVA\s+EMPRESA', LegalForm::SLNE],
        ['SOCIEDAD\s+ANONIMA\s+PROFESIONAL', LegalForm::SAP],
        ['SOCIEDAD\s+CIVIL\s+PROFESIONAL', LegalForm::SCP],
        ['SOCIEDAD\s+COOPERATIVA', LegalForm::Coop],
        ['SOCIEDAD\s+LIMITADA', LegalForm::SL],
        ['SOCIEDAD\s+ANONIMA', LegalForm::SA],
        ['SLNE', LegalForm::SLNE],
        ['S\.?\s*L\.?\s*N\.?\s*E\.?', LegalForm::SLNE],
        ['SLU', LegalForm::SLU],
        ['S\.?\s*L\.?\s*U\.?', LegalForm::SLU],
        ['SLP', LegalForm::SLP],
        ['S\.?\s*L\.?\s*P\.?', LegalForm::SLP],
        ['SLL', LegalForm::SLL],
        ['S\.?\s*L\.?\s*L\.?', LegalForm::SLL],
        ['SAU', LegalForm::SAU],
        ['S\.?\s*A\.?\s*U\.?', LegalForm::SAU],
        ['SAP', LegalForm::SAP],
        ['S\.?\s*A\.?\s*P\.?', LegalForm::SAP],
        ['SCRL', LegalForm::SCRL],
        ['S\.?\s*C\.?\s*R\.?\s*L\.?', LegalForm::SCRL],
        ['SCP', LegalForm::SCP],
        ['SRL', LegalForm::SL],
        ['S\.?\s*R\.?\s*L\.?', LegalForm::SL],
        ['COOP', LegalForm::Coop],
        ['UTE', LegalForm::UTE],
        ['AIE', LegalForm::AIE],
        ['A\.?\s*I\.?\s*E\.?', LegalForm::AIE],
        ['SL', LegalForm::SL],
        ['S\.\s*L\.?', LegalForm::SL],
        ['SA', LegalForm::SA],
        ['S\.\s*A\.?', LegalForm::SA],
        ['SC', LegalForm::SC],
    ];

    /**
     * Trailing status tags that may follow the legal-form suffix in BORME
     * (e.g. "EN LIQUIDACION", "EN CONCURSO"). Stripped before detection.
     */
    private const STATUS_TAGS = [
        'EN\s+LIQUIDACION',
        'EN\s+CONCURSO',
        'EN\s+DISOLUCION',
        'EN\s+EXTINCION',
        // Sociedad Mercantil Estatal — modifier on top of SA, not a legal form.
        'S\.?\s*M\.?\s*E\.?',
        'SOCIEDAD\s+MERCANTIL\s+ESTATAL',
    ];

    public function detect(string $companyName): ?LegalForm
    {
        $upper = $this->preClean($companyName);

        foreach (self::SUFFIX_PATTERNS as [$pattern, $form]) {
            if (preg_match('/(?<![A-Z])'.$pattern.'\.?\s*$/u', $upper) === 1) {
                return $form;
            }
        }

        return null;
    }

    public function stripSuffix(string $companyName): string
    {
        $upper = $this->preClean($companyName);

        foreach (self::SUFFIX_PATTERNS as [$pattern, $_]) {
            $candidate = preg_replace('/[\s,]*(?<![A-Z])'.$pattern.'\.?\s*$/u', '', $upper);
            if ($candidate !== null && $candidate !== $upper) {
                return trim($candidate);
            }
        }

        return trim($upper);
    }

    /**
     * Upper-case + drop trailing status tags ("EN LIQUIDACION" etc.) and any
     * trailing punctuation. Leaves the legal-form token (with or without dots)
     * as the final token of the string.
     */
    private function preClean(string $companyName): string
    {
        $upper = mb_strtoupper(trim($companyName));
        $upper = rtrim($upper, '.');

        foreach (self::STATUS_TAGS as $tag) {
            $cleaned = preg_replace('/\s+'.$tag.'\s*$/u', '', $upper);
            if ($cleaned !== null && $cleaned !== $upper) {
                $upper = $cleaned;
                break;
            }
        }

        return rtrim($upper, '. ,');
    }
}
