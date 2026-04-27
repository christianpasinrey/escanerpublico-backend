<?php

namespace Modules\Borme\Services\Support;

class ProvinceDetector
{
    /**
     * Maps RM letter (used in `Datos registrales. ... H {letter}{sheet}`) to
     * province INE code and canonical name. BORME-A covers the 50 Spanish
     * provinces + Ceuta (CE) + Melilla (ML). Source: BOE open-data BORME spec.
     */
    private const LETTER_TO_INE = [
        'A' => ['03', 'ALICANTE'],
        'AB' => ['02', 'ALBACETE'],
        'AL' => ['04', 'ALMERIA'],
        'AV' => ['05', 'AVILA'],
        'B' => ['08', 'BARCELONA'],
        'BA' => ['06', 'BADAJOZ'],
        'BI' => ['48', 'BIZKAIA'],
        'BU' => ['09', 'BURGOS'],
        'C' => ['15', 'A CORUÑA'],
        'CA' => ['11', 'CADIZ'],
        'CC' => ['10', 'CACERES'],
        'CE' => ['51', 'CEUTA'],
        'CO' => ['14', 'CORDOBA'],
        'CR' => ['13', 'CIUDAD REAL'],
        'CS' => ['12', 'CASTELLON'],
        'CU' => ['16', 'CUENCA'],
        'GC' => ['35', 'LAS PALMAS'],
        'GI' => ['17', 'GIRONA'],
        'GR' => ['18', 'GRANADA'],
        'GU' => ['19', 'GUADALAJARA'],
        'H' => ['21', 'HUELVA'],
        'HU' => ['22', 'HUESCA'],
        'IB' => ['07', 'BALEARS'],
        'J' => ['23', 'JAEN'],
        'L' => ['25', 'LLEIDA'],
        'LE' => ['24', 'LEON'],
        'LO' => ['26', 'LA RIOJA'],
        'LU' => ['27', 'LUGO'],
        'M' => ['28', 'MADRID'],
        'MA' => ['29', 'MALAGA'],
        'ML' => ['52', 'MELILLA'],
        'MU' => ['30', 'MURCIA'],
        'NA' => ['31', 'NAVARRA'],
        'O' => ['33', 'ASTURIAS'],
        'OR' => ['32', 'OURENSE'],
        'P' => ['34', 'PALENCIA'],
        'PM' => ['07', 'BALEARS'],
        'PO' => ['36', 'PONTEVEDRA'],
        'S' => ['39', 'CANTABRIA'],
        'SA' => ['37', 'SALAMANCA'],
        'SE' => ['41', 'SEVILLA'],
        'SG' => ['40', 'SEGOVIA'],
        'SO' => ['42', 'SORIA'],
        'SS' => ['20', 'GIPUZKOA'],
        'T' => ['43', 'TARRAGONA'],
        'TE' => ['44', 'TERUEL'],
        'TF' => ['38', 'SANTA CRUZ DE TENERIFE'],
        'TO' => ['45', 'TOLEDO'],
        'V' => ['46', 'VALENCIA'],
        'VA' => ['47', 'VALLADOLID'],
        'VI' => ['01', 'ARABA/ALAVA'],
        'Z' => ['50', 'ZARAGOZA'],
        'ZA' => ['49', 'ZAMORA'],
    ];

    public function ineFromLetter(string $letter): ?string
    {
        return self::LETTER_TO_INE[strtoupper($letter)][0] ?? null;
    }

    public function nameFromLetter(string $letter): ?string
    {
        return self::LETTER_TO_INE[strtoupper($letter)][1] ?? null;
    }
}
