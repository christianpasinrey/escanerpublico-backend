<?php

namespace Modules\Subsidies\Services;

/**
 * Parsea el campo `beneficiario` de BDNS, que viene como string concatenado
 * "NIF Razón social". Formatos observados:
 *
 *   "B12345678 ACME CONSTRUCCIONES SL"
 *   "12345678A JOSE LOPEZ GARCIA"
 *   "Q1234567A AYUNTAMIENTO DE LUGO"
 *   "***1234** PERSONA FISICA"        <- nombre redactado por privacidad
 *   "AYUNTAMIENTO X (sin NIF)"        <- a veces faltan dígitos
 *
 * Devuelve [nif, nombre]. Si el patrón no encaja, devuelve [null, raw] para
 * que el ingestor pueda guardar `beneficiario_raw` y registrar parse_error.
 */
class BeneficiarioParser
{
    /**
     * Patrón NIF/CIF/NIE español:
     *  - Letra inicial + 7 dígitos + letra/dígito (CIF: A-Z0-9)
     *  - 8 dígitos + letra (DNI)
     *  - X/Y/Z + 7 dígitos + letra (NIE)
     *
     * Aceptamos formatos con asteriscos de redacción (privacidad).
     */
    private const NIF_PATTERN = '/^([\*A-Z0-9]{8,9})\s+(.+)$/u';

    /**
     * @return array{0: string|null, 1: string|null}
     */
    public function parse(?string $raw): array
    {
        if ($raw === null) {
            return [null, null];
        }

        $trimmed = trim($raw);
        if ($trimmed === '') {
            return [null, null];
        }

        if (preg_match(self::NIF_PATTERN, $trimmed, $matches) === 1) {
            $nif = strtoupper(trim($matches[1]));
            $name = trim($matches[2]);

            // Si el "NIF" está totalmente redactado con asteriscos, es persona física
            // anonimizada — no podemos resolver a Company por NIF. Pero conservamos el nombre.
            if ($this->isFullyRedacted($nif)) {
                return [null, $name];
            }

            return [$nif, $name];
        }

        // No match: nombre completo sin NIF reconocible.
        return [null, $trimmed];
    }

    private function isFullyRedacted(string $nif): bool
    {
        // "***1234**" — todos los caracteres alfabéticos sustituidos por asteriscos
        // y los dígitos parcialmente. Si hay 4+ asteriscos, lo consideramos no parseable.
        return substr_count($nif, '*') >= 4;
    }
}
