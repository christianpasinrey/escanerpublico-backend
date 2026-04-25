<?php

namespace Modules\Tax\Services\Invoice;

use InvalidArgumentException;

/**
 * Formateador de IBAN para mostrar en facturas.
 *
 * Aplica:
 *  - Validación básica de formato (ISO 13616).
 *  - Validación de checksum mod-97-10 (sin verificar país concreto).
 *  - Agrupación en bloques de 4 caracteres ("ES12 3456 7890 …").
 *  - Enmascarado opcional para versiones públicas (HTML / PDF compartido).
 *
 * No persiste IBAN en BD: siempre se trabaja sobre el string recibido.
 */
class IbanFormatter
{
    /**
     * Devuelve el IBAN normalizado en bloques de 4 caracteres.
     *
     * @throws InvalidArgumentException si el formato es inválido.
     */
    public function format(string $iban): string
    {
        $clean = $this->normalize($iban);

        if (! $this->isValid($clean)) {
            throw new InvalidArgumentException("IBAN inválido: {$iban}");
        }

        return trim(chunk_split($clean, 4, ' '));
    }

    /**
     * Devuelve el IBAN enmascarado dejando visibles solo los 4 últimos
     * dígitos. Útil para enseñar en el frontend público o en URLs HMAC.
     */
    public function mask(string $iban): string
    {
        $clean = $this->normalize($iban);

        if (strlen($clean) < 8) {
            throw new InvalidArgumentException("IBAN demasiado corto: {$iban}");
        }

        $visible = substr($clean, -4);
        $hidden = str_repeat('*', max(0, strlen($clean) - 4));

        return trim(chunk_split($hidden.$visible, 4, ' '));
    }

    public function isValid(string $iban): bool
    {
        $clean = $this->normalize($iban);

        if (! preg_match('/^[A-Z]{2}\d{2}[A-Z0-9]{11,30}$/', $clean)) {
            return false;
        }

        // Mod-97-10: mover los 4 primeros caracteres al final, convertir
        // letras a números (A=10, B=11, …, Z=35) y comprobar que mod 97 = 1.
        $rearranged = substr($clean, 4).substr($clean, 0, 4);
        $numeric = '';
        foreach (str_split($rearranged) as $char) {
            if (ctype_alpha($char)) {
                $numeric .= (string) (ord($char) - 55);
            } else {
                $numeric .= $char;
            }
        }

        return bcmod($numeric, '97') === '1';
    }

    private function normalize(string $iban): string
    {
        return strtoupper(preg_replace('/\s+/', '', $iban) ?? '');
    }
}
